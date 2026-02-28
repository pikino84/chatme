<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Organization
            'organization.view',
            'organization.update',
            'organization.manage-billing',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Branches
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',

            // Conversations
            'conversations.view',
            'conversations.view-all',
            'conversations.create',
            'conversations.assign',
            'conversations.transfer',
            'conversations.close',
            'conversations.reopen',

            // Messages
            'messages.view',
            'messages.send',
            'messages.delete',
            'messages.internal-note',

            // Channels
            'channels.view',
            'channels.manage',

            // SLA
            'sla.view',
            'sla.manage',

            // Reports
            'reports.view',
            'reports.export',

            // Settings
            'settings.view',
            'settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // SaaS Admin - global backoffice access (no organization_id)
        Role::firstOrCreate(['name' => 'saas_admin']);

        // Super Admin - bypasses all checks via Gate::before
        Role::firstOrCreate(['name' => 'super_admin']);

        // Org Admin - full organizational access
        $orgAdmin = Role::firstOrCreate(['name' => 'org_admin']);
        $orgAdmin->syncPermissions([
            'organization.view', 'organization.update', 'organization.manage-billing',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'conversations.view', 'conversations.view-all', 'conversations.create',
            'conversations.assign', 'conversations.transfer', 'conversations.close', 'conversations.reopen',
            'messages.view', 'messages.send', 'messages.delete', 'messages.internal-note',
            'channels.view', 'channels.manage',
            'sla.view', 'sla.manage',
            'reports.view', 'reports.export',
            'settings.view', 'settings.update',
        ]);

        // Supervisor - manages agents, conversations and SLA monitoring
        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $supervisor->syncPermissions([
            'organization.view',
            'users.view',
            'branches.view',
            'conversations.view', 'conversations.view-all', 'conversations.create',
            'conversations.assign', 'conversations.transfer', 'conversations.close', 'conversations.reopen',
            'messages.view', 'messages.send', 'messages.internal-note',
            'channels.view',
            'sla.view',
            'reports.view',
        ]);

        // Agent - handles assigned conversations
        $agent = Role::firstOrCreate(['name' => 'agent']);
        $agent->syncPermissions([
            'organization.view',
            'conversations.view',
            'conversations.close',
            'messages.view', 'messages.send', 'messages.internal-note',
        ]);
    }
}
