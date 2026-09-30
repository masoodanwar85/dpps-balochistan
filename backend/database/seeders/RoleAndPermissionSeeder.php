<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $keys = [
            'dashboard.view',
            'companies.view', 'companies.create', 'companies.update', 'companies.delete',
            'dealers.view', 'dealers.create', 'dealers.update', 'dealers.delete',
            'persons.view', 'persons.update',
            'staff.manage', 'staff.verify',
            'products.manage', 'products.verify', 'products_master.manage',
            'documents.upload', 'documents.verify', 'documents.download',
            'applications.view', 'applications.create', 'applications.process', 'applications.reject',
            'penalties.enter', 'penalties.waive',
            'challans.verify',
            'licenses.issue', 'licenses.suspend', 'licenses.cancel', 'licenses.restore',
            'checklists.manage', 'workflow.manage', 'settings.manage', 'lookups.manage',
            'users.manage', 'roles.manage',
            'activity_logs.view',
            'imports.run', 'imports.resolve',
            'exports.run',
            'portal.access', 'portal.users.manage', 'portal.renewal.submit',
        ];

        foreach ($keys as $key) {
            Permission::findOrCreate($key, 'web');
        }

        $staffRecords = [
            'companies.view', 'companies.create', 'companies.update',
            'dealers.view', 'dealers.create', 'dealers.update',
            'persons.view', 'persons.update',
            'staff.manage', 'staff.verify',
            'products.manage', 'products.verify',
            'documents.upload', 'documents.verify', 'documents.download',
            'applications.view', 'applications.create', 'applications.process', 'applications.reject',
            'penalties.enter',
            'challans.verify',
            'exports.run',
        ];

        $roles = [
            'Super Admin' => array_values(array_diff($keys, [
                'portal.access',
                'portal.users.manage',
                'portal.renewal.submit',
            ])),
            'Director' => array_merge($staffRecords, [
                'products_master.manage',
                'penalties.waive',
                'licenses.issue', 'licenses.suspend', 'licenses.cancel', 'licenses.restore',
                'checklists.manage', 'workflow.manage', 'settings.manage', 'lookups.manage',
                'activity_logs.view',
                'dashboard.view',
            ]),
            'Registration Officer' => array_merge($staffRecords, [
                'dashboard.view',
            ]),
            'District Officer' => [
                'dashboard.view',
                'companies.view',
                'dealers.view', 'dealers.create', 'dealers.update',
                'documents.upload', 'documents.verify', 'documents.download',
                'products.verify',
                'applications.view', 'applications.process',
                'penalties.enter',
                'exports.run',
            ],
            'Data Entry Operator' => [
                'dashboard.view',
                'companies.view', 'companies.create', 'companies.update',
                'dealers.view', 'dealers.create', 'dealers.update',
                'persons.view', 'persons.update',
                'staff.manage',
                'products.manage',
                'documents.upload', 'documents.download',
                'applications.view', 'applications.create',
            ],
            'Auditor' => [
                'dashboard.view',
                'companies.view',
                'dealers.view',
                'persons.view',
                'documents.download',
                'activity_logs.view',
                'exports.run',
            ],
            'Company Admin' => [
                'dashboard.view',
                'companies.view',
                'staff.manage',
                'products.manage',
                'documents.upload', 'documents.download',
                'portal.access',
                'portal.users.manage',
                'portal.renewal.submit',
            ],
            'Company Staff' => [
                'dashboard.view',
                'companies.view',
                'staff.manage',
                'products.manage',
                'documents.upload', 'documents.download',
                'portal.access',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
