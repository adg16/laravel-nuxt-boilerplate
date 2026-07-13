<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sort_by' => ['string', 'in:name,users_count'],
            'sort_dir' => ['string', 'in:asc,desc'],
            'name' => ['string', 'max:255'],
            'permissions' => ['string', 'max:1000'],
        ]);

        // Count assigned users via the pivot directly. spatie's `users()`
        // relation resolves the model from the *default* guard, which under
        // `auth:sanctum` is `sanctum` (provider null) — so `withCount('users')`
        // blows up. The pivot subquery avoids guard resolution altogether.
        $query = Role::query()
            ->with('permissions')
            ->select('roles.*')
            ->addSelect(['users_count' => DB::table($this->pivotTable())
                ->selectRaw('count(*)')
                ->whereColumn($this->rolePivotKey(), 'roles.id')]);

        // The super-admin role is only visible to a super-admin. Since every role
        // dropdown in the app sources this endpoint, hiding it here also keeps it
        // out of those selects for everyone else.
        if (! $request->user()->hasRole('Super Admin')) {
            $query->where('name', '!=', 'Super Admin');
        }

        if (($name = trim((string) ($validated['name'] ?? ''))) !== '') {
            $query->where('name', 'like', '%'.$this->escapeLike($name).'%');
        }
        // `permissions` is a comma-separated list — match roles granting ANY of them.
        $permissions = array_filter(array_map('trim', explode(',', $validated['permissions'] ?? '')));
        if ($permissions) {
            $query->whereHas('permissions', fn (Builder $q) => $q->whereIn('name', $permissions));
        }

        $query->orderBy($validated['sort_by'] ?? 'name', $validated['sort_dir'] ?? 'asc');

        $paginator = $query->paginate(
            perPage: $validated['per_page'] ?? 25,
            page: $validated['page'] ?? 1,
        );

        return response()->json([
            'data' => RoleResource::collection($paginator->getCollection())->resolve($request),
            'total' => $paginator->total(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        // Pin the guard to `web` (the SPA's session guard — see config/sanctum).
        // Under `auth:sanctum` the *default* guard is switched to `sanctum` for
        // the request, so a bare Role::create would stamp guard_name=sanctum and
        // then fail to find our web-guard permissions.
        $role = Role::create([
            'name' => $request->string('name'),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($request->input('permissions', []));

        return RoleResource::make($role->load('permissions'))->response()->setStatusCode(201);
    }

    public function show(Request $request, Role $role): RoleResource
    {
        // A non-super-admin can't view the super-admin role even by guessing its
        // id — 404 (not 403) so we don't confirm it exists.
        if ($role->name === 'Super Admin' && ! $request->user()->hasRole('Super Admin')) {
            abort(404);
        }

        $role->load('permissions');
        $role->users_count = DB::table($this->pivotTable())
            ->where($this->rolePivotKey(), $role->id)
            ->count();

        return RoleResource::make($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->guardSuperAdmin($role);

        $role->update(['name' => $request->string('name')]);
        $role->syncPermissions($request->input('permissions', []));

        return RoleResource::make($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->guardSuperAdmin($role);

        $inUse = DB::table($this->pivotTable())
            ->where($this->rolePivotKey(), $role->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'role' => [__('management.cannot_delete_role_in_use')],
            ]);
        }

        $role->delete();

        return response()->json(['message' => __('management.role_deleted')]);
    }

    /**
     * The super-admin role is the Gate::before bypass — its permissions are
     * meaningless and it must never be renamed or deleted out from under itself.
     */
    private function guardSuperAdmin(Role $role): void
    {
        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages([
                'role' => [__('management.cannot_modify_super_admin_role')],
            ]);
        }
    }

    private function pivotTable(): string
    {
        return config('permission.table_names.model_has_roles', 'model_has_roles');
    }

    private function rolePivotKey(): string
    {
        return config('permission.column_names.role_pivot_key') ?: 'role_id';
    }
}
