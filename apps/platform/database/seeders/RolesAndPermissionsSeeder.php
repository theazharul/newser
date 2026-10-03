<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Installs the four documented roles and the 19 content permissions.
 * Idempotent (firstOrCreate) — test helpers invoke it repeatedly per test.
 *
 * Matrix (inferred — docs name roles/permissions but not the pairing):
 *   admin:   all permissions
 *   editor:  all content permissions
 *   author:  posts.create/edit/publish + media.upload/edit
 *   viewer:  none
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The content permissions the platform ships with (entity.action convention).
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'posts.create', 'posts.edit', 'posts.delete', 'posts.publish',
        'categories.create', 'categories.edit', 'categories.delete',
        'tags.create', 'tags.edit', 'tags.delete',
        'authors.create', 'authors.edit', 'authors.delete',
        'media.upload', 'media.edit', 'media.delete',
        'pages.create', 'pages.edit', 'pages.delete',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate(['name' => $name]);
        }

        $matrix = [
            'admin' => self::PERMISSIONS,
            'editor' => self::PERMISSIONS,
            'author' => [
                'posts.create', 'posts.edit', 'posts.publish',
                'media.upload', 'media.edit',
            ],
            'viewer' => [],
        ];

        foreach ($matrix as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate(['name' => $roleName]);

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
