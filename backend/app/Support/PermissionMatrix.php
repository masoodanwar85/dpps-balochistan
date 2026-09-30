<?php

namespace App\Support;

class PermissionMatrix
{
    /**
     * @return list<array{module: string, view: ?string, create: ?string, update: ?string, delete: ?string, other: list<array{key: string, label: string}>}>
     */
    public static function modules(): array
    {
        return [
            self::row('Dashboard', view: 'dashboard.view'),
            self::row('Companies', view: 'companies.view', create: 'companies.create', update: 'companies.update', delete: 'companies.delete'),
            self::row('Dealers', view: 'dealers.view', create: 'dealers.create', update: 'dealers.update', delete: 'dealers.delete'),
            self::row('Persons', view: 'persons.view', other: ['persons.update' => 'update']),
            self::row('Staff', other: ['staff.manage' => 'manage', 'staff.verify' => 'verify']),
            self::row('Products', other: ['products.manage' => 'manage', 'products.verify' => 'verify']),
            self::row('Products Master', other: ['products_master.manage' => 'manage']),
            self::row('Documents', other: ['documents.upload' => 'upload', 'documents.verify' => 'verify', 'documents.download' => 'download']),
            self::row('Applications', view: 'applications.view', create: 'applications.create', other: ['applications.process' => 'process', 'applications.reject' => 'reject']),
            self::row('Penalties', other: ['penalties.enter' => 'enter', 'penalties.waive' => 'waive']),
            self::row('Challans', other: ['challans.verify' => 'verify']),
            self::row('Licenses', other: ['licenses.issue' => 'issue', 'licenses.suspend' => 'suspend', 'licenses.cancel' => 'cancel', 'licenses.restore' => 'restore']),
            self::row('Checklists', other: ['checklists.manage' => 'manage']),
            self::row('Workflow', other: ['workflow.manage' => 'manage']),
            self::row('Settings', other: ['settings.manage' => 'manage']),
            self::row('Lookups', other: ['lookups.manage' => 'manage']),
            self::row('Users', other: ['users.manage' => 'manage']),
            self::row('Roles', other: ['roles.manage' => 'manage']),
            self::row('Activity Logs', view: 'activity_logs.view'),
            self::row('Imports', other: ['imports.run' => 'run', 'imports.resolve' => 'resolve']),
            self::row('Exports', other: ['exports.run' => 'run']),
            self::row('Portal', other: ['portal.access' => 'access', 'portal.users.manage' => 'users', 'portal.renewal.submit' => 'renewal']),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::modules() as $module) {
            foreach (['view', 'create', 'update', 'delete'] as $column) {
                if (is_string($module[$column])) {
                    $keys[] = $module[$column];
                }
            }

            foreach ($module['other'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, string>  $other
     * @return array{module: string, view: ?string, create: ?string, update: ?string, delete: ?string, other: list<array{key: string, label: string}>}
     */
    private static function row(
        string $module,
        ?string $view = null,
        ?string $create = null,
        ?string $update = null,
        ?string $delete = null,
        array $other = [],
    ): array {
        $items = [];

        foreach ($other as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label];
        }

        return [
            'module' => $module,
            'view' => $view,
            'create' => $create,
            'update' => $update,
            'delete' => $delete,
            'other' => $items,
        ];
    }
}
