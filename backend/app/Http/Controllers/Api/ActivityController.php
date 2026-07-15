<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * The audit trail — read-only, gated by `activity.view`. Same shape as the
     * user/role indexes (validate → filter → named-arg paginate → {data, total}
     * envelope). Also drives the per-record history panels via the
     * subject_type/subject_id filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sort_by' => ['string', 'in:created_at,log_name'],
            'sort_dir' => ['string', 'in:asc,desc'],
            'log_name' => ['string', 'max:255'],
            'event' => ['string', 'max:255'],
            'subject_type' => ['string', 'in:user,role'],
            'subject_id' => ['integer', 'min:1'],
            'causer_id' => ['integer', 'min:1'],
            'actor' => ['string', 'max:255'],
            'search' => ['string', 'max:255'],
            'date_from' => ['date'],
            'date_to' => ['date'],
            // The SPA's IANA timezone, so a picked local day maps to the right
            // instant range (the `timezone` rule rejects anything invalid).
            'tz' => ['timezone'],
        ]);

        // Eager-load the polymorphic subject + causer so the resource doesn't
        // lazy-load one query per row.
        $query = Activity::with('subject', 'causer');

        $this->applyVisibility($request, $query);

        // `log_name` / `event` are comma-separated sets matched with ANY.
        $logNames = array_filter(array_map('trim', explode(',', $validated['log_name'] ?? '')));
        if ($logNames) {
            $query->whereIn('log_name', $logNames);
        }
        $events = array_filter(array_map('trim', explode(',', $validated['event'] ?? '')));
        if ($events) {
            $query->whereIn('event', $events);
        }

        // Subject filter (drives the per-record history panel). The frontend
        // sends the morph alias; map it back to the stored class name.
        if (! empty($validated['subject_type'])) {
            $query->where('subject_type', $this->morphClass($validated['subject_type']));
            if (! empty($validated['subject_id'])) {
                $query->where('subject_id', $validated['subject_id']);
            }
        }

        if (! empty($validated['causer_id'])) {
            $query->where('causer_type', User::class)->where('causer_id', $validated['causer_id']);
        }

        // Actor is its own axis: match the causer (always a user) by name.
        if (($actor = trim((string) ($validated['actor'] ?? ''))) !== '') {
            $query->where('causer_type', User::class)
                ->whereIn('causer_id', User::where('name', 'like', '%'.$this->escapeLike($actor).'%')->select('id'));
        }

        if (($search = trim((string) ($validated['search'] ?? ''))) !== '') {
            // When scoped to a single record (the per-record history), the subject
            // is fixed and not shown, so search only the changed values — not the
            // subject name.
            $this->applySearch($query, $search, includeSubject: empty($validated['subject_id']));
        }

        // Inclusive date-range filter on the day the activity was recorded.
        // Resolve the day boundaries in the *user's* timezone (falling back to the
        // app's), then normalize to the storage timezone — so a local day maps to
        // the correct instant range. Compares the raw column (not whereDate(),
        // whose DATE(created_at) wrapper is non-sargable and can't use the index).
        $tz = $validated['tz'] ?? config('app.timezone');
        if (! empty($validated['date_from'])) {
            $from = Carbon::parse($validated['date_from'], $tz)->startOfDay()->setTimezone(config('app.timezone'));
            $query->where('created_at', '>=', $from);
        }
        if (! empty($validated['date_to'])) {
            $to = Carbon::parse($validated['date_to'], $tz)->endOfDay()->setTimezone(config('app.timezone'));
            $query->where('created_at', '<=', $to);
        }

        // `created_at` is only second-precision, so several rows written in one
        // request (e.g. a user's `created` + `role_assignment`) share a timestamp.
        // Tie-break on the monotonic `id` in the same direction so the order is
        // deterministic and matches insertion order (newest-first shows the last
        // write on top).
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($validated['sort_by'] ?? 'created_at', $sortDir)
            ->orderBy('id', $sortDir);

        $paginator = $query->paginate(
            perPage: $validated['per_page'] ?? 25,
            page: $validated['page'] ?? 1,
        );

        return response()->json([
            'data' => ActivityResource::collection($paginator->getCollection())->resolve($request),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Hide activity about restricted subjects from non-super-admins — mirroring
     * the users/roles list visibility. Activity whose *subject* is a super-admin
     * or the System account, or the Super Admin role, is dropped. Causer names
     * are shown as-is (like created_by/updated_by), so no causer filtering here.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyVisibility(Request $request, Builder $query): void
    {
        if ($request->user()->hasRole('Super Admin')) {
            return;
        }

        $restrictedUserIds = User::query()
            ->where('email', config('app.system_user_email'))
            ->orWhereHas('roles', fn (Builder $q) => $q->where('name', 'Super Admin'))
            ->pluck('id')
            ->all();

        $superAdminRoleId = Role::where('name', 'Super Admin')->value('id');

        // Drop rows whose subject is a restricted user OR the super-admin role.
        // Rows with no subject (e.g. settings) match neither branch and stay.
        $query->whereNot(function (Builder $q) use ($restrictedUserIds, $superAdminRoleId) {
            $q->where(function (Builder $inner) use ($restrictedUserIds) {
                $inner->where('subject_type', User::class)->whereIn('subject_id', $restrictedUserIds);
            })->orWhere(function (Builder $inner) use ($superAdminRoleId) {
                $inner->where('subject_type', Role::class)->where('subject_id', $superAdminRoleId);
            });
        });
    }

    /**
     * Full-text-ish search over what the timeline shows, minus the actor (that
     * has its own filter): the change itself (the properties diff — old/new
     * values like a renamed user, a changed email, or the role/permission names
     * in an assignment) and, unless scoped to a single record, the subject's
     * name. Matching the raw `properties` JSON is a coarse substring test, but
     * it's what makes "search for a value you can see" work.
     *
     * @param  Builder<Activity>  $query
     */
    private function applySearch(Builder $query, string $search, bool $includeSubject): void
    {
        $like = '%'.$this->escapeLike($search).'%';

        $query->where(function (Builder $q) use ($like, $includeSubject) {
            // The change payload (covers renamed values, emails, role/permission
            // names, setting values). v5 stores the auto-logged model diff in
            // `attribute_changes` and manual-log data in `properties`, so match both.
            $q->where('attribute_changes', 'like', $like)
                ->orWhere('properties', 'like', $like);

            // The subject (a user or a role), matched by its current name — only
            // when the list isn't already narrowed to one subject.
            if ($includeSubject) {
                $q->orWhere(fn (Builder $s) => $s->where('subject_type', User::class)
                    ->whereIn('subject_id', User::where('name', 'like', $like)->select('id')))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', Role::class)
                        ->whereIn('subject_id', Role::where('name', 'like', $like)->select('id')));
            }
        });
    }

    private function morphClass(string $alias): string
    {
        return match ($alias) {
            'user' => User::class,
            'role' => Role::class,
            default => $alias,
        };
    }
}
