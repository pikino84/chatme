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

            // Deals
            'deals.view',
            'deals.view-all',
            'deals.create',
            'deals.update',
            'deals.delete',
            'deals.assign',
            'deals.manage-commissions',

            // Pipelines
            'pipelines.view',
            'pipelines.create',
            'pipelines.update',
            'pipelines.delete',

            // Knowledge Base
            'kb.view',
            'kb.create',
            'kb.update',
            'kb.delete',
            'kb.publish',

            // Contacts
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'contacts.import',

            // Campaigns
            'campaigns.view',
            'campaigns.create',
            'campaigns.send',
            'campaigns.delete',

            // Drip Sequences
            'drip.view',
            'drip.create',
            'drip.update',
            'drip.delete',

            // Automations
            'automations.view',
            'automations.create',
            'automations.update',
            'automations.delete',

            // Brands
            'brands.view',
            'brands.create',
            'brands.update',
            'brands.delete',

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
            'deals.view', 'deals.view-all', 'deals.create', 'deals.update', 'deals.delete',
            'deals.assign', 'deals.manage-commissions',
            'pipelines.view', 'pipelines.create', 'pipelines.update', 'pipelines.delete',
            'contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete', 'contacts.import',
            'campaigns.view', 'campaigns.create', 'campaigns.send', 'campaigns.delete',
            'drip.view', 'drip.create', 'drip.update', 'drip.delete',
            'automations.view', 'automations.create', 'automations.update', 'automations.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'kb.view', 'kb.create', 'kb.update', 'kb.delete', 'kb.publish',
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
            'deals.view', 'deals.view-all', 'deals.create', 'deals.update', 'deals.assign',
            'pipelines.view',
            'contacts.view', 'contacts.create', 'contacts.update',
            'campaigns.view',
            'drip.view',
            'automations.view',
            'brands.view',
            'kb.view', 'kb.create', 'kb.update',
            'reports.view',
        ]);

        // Agent - handles assigned conversations
        $agent = Role::firstOrCreate(['name' => 'agent']);
        $agent->syncPermissions([
            'organization.view',
            'conversations.view',
            'brands.view',
            'conversations.close',
            'messages.view', 'messages.send', 'messages.internal-note',
            'contacts.view', 'contacts.create',
            'deals.view', 'deals.create', 'deals.update',
            'pipelines.view',
            'kb.view',
        ]);
    }
}
