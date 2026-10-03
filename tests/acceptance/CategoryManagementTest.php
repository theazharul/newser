<?php

declare(strict_types=1);

/*
| AC-4: Categories — admin CRUD (api.md §Admin > Categories), unique slug,
|       and the public read list (api.md §Public > Categories).
*/

use App\Models\Category;

test('admin can create a category', function () {
    // Given: an admin
    $admin = adminUser();

    // When: they create a category
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'World',
        'slug' => 'world',
        'description' => 'World news',
    ]);

    // Then: 201 and the row exists
    $response->assertStatus(201);
    $this->assertDatabaseHas('categories', [
        'name' => 'World',
        'slug' => 'world',
        'description' => 'World news',
    ]);
});

test('admin can edit a category', function () {
    // Given: an admin and an existing category
    $admin = adminUser();
    $category = Category::factory()->create();

    // When: they update it
    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/categories/{$category->id}", [
        'name' => 'Renamed World',
        'slug' => $category->slug,
        'description' => 'Renamed description.',
    ]);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Renamed World',
        'description' => 'Renamed description.',
    ]);
});

test('admin can delete a category', function () {
    // Given: an admin and an unused category
    $admin = adminUser();
    $category = Category::factory()->create();

    // When: they delete it
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/categories/{$category->id}");

    // Then: the row is gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('admin category list returns every category', function () {
    // Given: an admin and two categories
    $admin = adminUser();
    Category::factory()->count(2)->create();

    // When: they list categories
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/categories');

    // Then: both are returned in api.md's shape
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('category creation rejects a duplicate slug', function () {
    // Given: an admin and a category with a known slug
    $admin = adminUser();
    Category::factory()->create(['slug' => 'already-taken']);

    // When: another category reuses that slug
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'Dup',
        'slug' => 'already-taken',
    ]);

    // Then: validation rejects it
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['slug']]]);
});

test('public category list is readable without authentication', function () {
    // Given: two categories created by an admin
    $admin = adminUser();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'Sports', 'slug' => 'sports',
    ])->assertStatus(201);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'Tech', 'slug' => 'tech',
    ])->assertStatus(201);

    // When: the public list is read with no token
    $response = $this->getJson('/api/v1/categories');

    // Then: both come back
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});
