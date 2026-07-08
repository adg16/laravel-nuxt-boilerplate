<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use Illuminate\Console\Command;

/**
 * Emits the frontend permission constants generated from the Permission enum,
 * to stdout. The php container can't write into the frontend tree, so the
 * caller redirects this into the file (see `make sync-permissions`) — that keeps
 * `app/constants/permissions.ts` a projection of the enum instead of a
 * hand-maintained twin that can drift.
 */
class ExportPermissionsTypeScript extends Command
{
    protected $signature = 'permission:export-ts';

    protected $description = 'Generate the frontend TypeScript permission constants from the Permission enum';

    public function handle(): int
    {
        // No trailing comma on the last entry — the frontend ESLint config sets
        // commaDangle: 'never'.
        $entries = collect(Permission::cases())
            ->map(fn (Permission $case) => "  {$case->name}: '{$case->value}'")
            ->implode(",\n");

        $this->output->write(<<<TS
        // AUTO-GENERATED from App\\Enums\\Permission — do not edit by hand.
        // Run `make sync-permissions` after changing the Permission enum.
        export const PERMISSIONS = {
        $entries
        } as const

        export type PermissionName = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]

        TS);

        return self::SUCCESS;
    }
}
