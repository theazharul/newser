<?php

declare(strict_types=1);

/*
| Unit: the role/permission matrix the seeder must install.
| Mapping is inferred — docs name roles and permission strings but not the
| pairing (see tests/Helpers.php header). Enforced end-to-end in
| tests/acceptance/RbacTest.php.
*/

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CONTENT_PERMISSIONS = [
    'posts.create', 'posts.edit', 'posts.delete', 'posts.publish',
    'categories.create', 'categories.edit', 'categories.delete',
    'tags.create', 'tags.edit', 'tags.delete',
    'authors.create', 'authors.edit', 'authors.delete',
    'media.upload', 'media.edit', 'media.delete',
    'pages.create', 'pages.edit', 'pages.delete',
];

test('the seeder installs exactly four roles', function () {
    seedRolesAndPermissions();

    expect(Role::pluck('name')->all())->toEqualCanonicalizing(['admin', 'editor', 'author', 'viewer']);
});

test('the seeder installs the documented permission names', function () {
    seedRolesAndPermissions();

    expect(Permission::pluck('name')->all())->toEqualCanonicalizing(CONTENT_PERMISSIONS);
});

test('admin holds every permission', function () {
    seedRolesAndPermissions();

    $admin = Role::query()->where('name', 'admin')->firstOrFail();

    expect($admin->permissions->pluck('name')->all())->toEqualCanonicalizing(CONTENT_PERMISSIONS);
});

test('editor holds every content permission', function () {
    seedRolesAndPermissions();

    $editor = Role::query()->where('name', 'editor')->firstOrFail();

    expect($editor->permissions->pluck('name')->all())->toEqualCanonicalizing(CONTENT_PERMISSIONS);
});

test('author can create edit and publish posts plus upload and edit media only', function () {
    seedRolesAndPermissions();

    $author = Role::query()->where('name', 'author')->firstOrFail();

    expect($author->permissions->pluck('name')->all())->toEqualCanonicalizing([
        'posts.create', 'posts.edit', 'posts.publish',
        'media.upload', 'media.edit',
    ]);
});

test('viewer holds no permissions at all', function () {
    seedRolesAndPermissions();

    $viewer = Role::query()->where('name', 'viewer')->firstOrFail();

    expect($viewer->permissions)->toBeEmpty();
});

test('hasPermissionTo answers per user through their role', function () {
    seedRolesAndPermissions();

    $author = userWithRole('author');
    $admin = userWithRole('admin');

    expect($author->hasPermissionTo('posts.create'))->toBeTrue()
        ->and($author->hasPermissionTo('posts.delete'))->toBeFalse()
        ->and($admin->hasPermissionTo('posts.delete'))->toBeTrue();
});

test('a user with no role has no permissions rather than erroring', function () {
    seedRolesAndPermissions();

    $roleless = User::factory()->create(['role_id' => null]);

    expect($roleless->hasPermissionTo('posts.create'))->toBeFalse();
});

test('permission names follow the entity.action convention', function () {
    seedRolesAndPermissions();

    foreach (Permission::pluck('name') as $name) {
        expect($name)->toMatch('/^[a-z]+\.[a-z]+$/');
    }
});
