<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Read-only catalog of the code-defined permissions (with the roles that
     * use each). Permissions are owned by the Permission enum and synced via
     * `permission:sync`, so there is deliberately no write endpoint.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return PermissionResource::collection(
            Permission::with('roles')->orderBy('name')->get()
        );
    }
}
