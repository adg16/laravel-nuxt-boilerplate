<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserCreationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sort_by' => ['string', 'in:name,email,created_at'],
            'sort_dir' => ['string', 'in:asc,desc'],
            'name' => ['string', 'max:255'],
            'email' => ['string', 'max:255'],
            'roles' => ['string', 'max:255'],
            'status' => ['string', 'max:255'],
        ]);

        // Eager-load what UserResource reads (roles + the permissions each role
        // grants) so getAllPermissions() doesn't lazy-load per row (N+1).
        $query = User::with('roles.permissions', 'permissions');

        // Protected accounts — the super-admin and the System service user — are
        // only visible to a super-admin. Everyone else, even with users.view,
        // sees ordinary users only.
        if (! $request->user()->hasRole('super-admin')) {
            $query->where('email', '!=', config('app.system_user_email'))
                ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', 'super-admin'));
        }

        // Filters (all optional). Text fields are substring matches with LIKE
        // wildcards escaped so a stray % / _ in the query can't widen it.
        if (($name = trim((string) ($validated['name'] ?? ''))) !== '') {
            $query->where('name', 'like', '%'.$this->escapeLike($name).'%');
        }
        if (($email = trim((string) ($validated['email'] ?? ''))) !== '') {
            $query->where('email', 'like', '%'.$this->escapeLike($email).'%');
        }
        // `roles` is a comma-separated list of role names — match users holding
        // ANY of them.
        $roles = array_filter(array_map('trim', explode(',', $validated['roles'] ?? '')));
        if ($roles) {
            $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $roles));
        }
        // `status` is a comma-separated set — match users satisfying ANY of the
        // selected states (OR), grouped so it doesn't bleed into the other AND
        // filters above.
        $statuses = array_filter(array_map('trim', explode(',', $validated['status'] ?? '')));
        if ($statuses) {
            $query->where(function (Builder $q) use ($statuses) {
                foreach ($statuses as $status) {
                    match ($status) {
                        'active' => $q->orWhereNull('deactivated_at'),
                        'inactive' => $q->orWhereNotNull('deactivated_at'),
                        'verified' => $q->orWhereNotNull('email_verified_at'),
                        'unverified' => $q->orWhereNull('email_verified_at'),
                        default => null,
                    };
                }
            });
        }

        // Sortable columns are whitelisted by the validation above; default to
        // newest first (the old ->latest()).
        $query->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_dir'] ?? 'desc');

        $paginator = $query->paginate(
            perPage: $validated['per_page'] ?? 25,
            page: $validated['page'] ?? 1,
        );

        // Explicit envelope: JsonResource::withoutWrapping() is on globally, so
        // return the resolved rows plus the total the data table needs.
        return response()->json([
            'data' => UserResource::collection($paginator->getCollection())->resolve($request),
            'total' => $paginator->total(),
        ]);
    }

    public function store(StoreUserRequest $request, InvitationService $invitations): JsonResponse
    {
        $setPassword = $request->creationMethod() === UserCreationMode::SetPassword;

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            // Set-password: use the admin-provided password. Invite: an unusable
            // random one until the invitee sets their own.
            'password' => Hash::make($setPassword ? $request->string('password') : Str::random(40)),
        ]);

        $user->syncRoles($request->input('roles', []));

        if ($setPassword) {
            // The admin set the credentials — the user is active immediately, no
            // e-mail round-trip needed.
            $user->markVerified();
        } else {
            // Queued, so a mail outage can't fail this request or orphan the user.
            $invitations->send($user);
        }

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): UserResource
    {
        // A non-super-admin can't view a protected account even by guessing its
        // id — 404 (not 403) so we don't confirm the account exists.
        if ($user->isProtected() && ! $request->user()->hasRole('super-admin')) {
            abort(404);
        }

        return UserResource::make($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->guardProtected($user);

        $user->update([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
        ]);

        $user->syncRoles($request->input('roles', []));

        return UserResource::make($user->load('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => [__('management.cannot_delete_self')],
            ]);
        }

        $this->guardProtected($user);

        $user->delete();

        return response()->json(['message' => __('management.user_deleted')]);
    }

    /**
     * Clear a user's two-factor setup — the lockout recovery for a user who lost
     * their authenticator (and their recovery codes). Uses Fortify's *base*
     * disable action to bypass our required-mode self-disable guard: an admin
     * reset is legitimate. When two_factor_mode is Required, the user simply
     * re-enrolls on their next sign-in (the forced-setup gate takes over).
     */
    public function resetTwoFactor(User $user): JsonResponse
    {
        $this->guardProtected($user);

        // Base disable action clears secret/recovery/confirmed for either method;
        // clearTwoFactorMethod nulls our extra column too.
        (new FortifyDisableTwoFactorAuthentication)($user);
        $user->clearTwoFactorMethod();

        return response()->json(['message' => __('management.two_factor_reset')]);
    }

    /**
     * Deactivate a user — they can no longer sign in and their live session is
     * cut off on its next request (App\Http\Middleware\EnsureActive). You can't
     * deactivate yourself (a lockout footgun) or a protected account.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => [__('management.cannot_deactivate_self')],
            ]);
        }

        $this->guardProtected($user);

        $user->deactivate();

        return response()->json(['message' => __('management.user_deactivated')]);
    }

    /** Reactivate a previously deactivated user. */
    public function activate(User $user): JsonResponse
    {
        $this->guardProtected($user);

        $user->activate();

        return response()->json(['message' => __('management.user_activated')]);
    }

    public function resendInvite(User $user, InvitationService $invitations): JsonResponse
    {
        // Once the invite is accepted (the user is verified) there's nothing to
        // resend — they'd use "forgot password" instead.
        if ($user->isVerified()) {
            throw ValidationException::withMessages([
                'user' => [__('management.invite_already_accepted')],
            ]);
        }

        $invitations->send($user);

        return response()->json(['message' => __('management.invite_sent')]);
    }

    /**
     * Protected accounts — the super-admin (Gate::before bypass) and the System
     * service account — can't be edited or deleted through the API.
     */
    private function guardProtected(User $user): void
    {
        if ($user->isProtected()) {
            throw ValidationException::withMessages([
                'user' => [__('management.cannot_modify_protected_user')],
            ]);
        }
    }
}
