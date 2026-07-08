<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Count assigned users via the pivot directly. spatie's `users()`
        // relation resolves the model from the *default* guard, which under
        // `auth:sanctum` is `sanctum` (provider null) — so `withCount('users')`
        // blows up. The pivot subquery avoids guard resolution altogether.
        return RoleResource::collection(
            Role::query()
                ->with('permissions')
                ->select('roles.*')
                ->addSelect(['users_count' => DB::table($this->pivotTable())
                    ->selectRaw('count(*)')
                    ->whereColumn($this->rolePivotKey(), 'roles.id')])
                ->orderBy('name')
                ->get()
        );
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

    public function show(Role $role): RoleResource
    {
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
        if ($role->name === 'super-admin') {
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
