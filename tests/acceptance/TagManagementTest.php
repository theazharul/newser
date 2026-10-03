<?php

declare(strict_types=1);

/*
| AC-5: Tags — admin CRUD (api.md §Admin > Tags), unique slug,
|       and the public read list (api.md §Public > Tags).
*/

use App\Models\Tag;

test('admin can create a tag', function () {
    // Given: an admin
    $admin = adminUser();

    // When: they create a tag
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/tags', [
        'name' => 'Breaking',
        'slug' => 'breaking',
    ]);

    // Then: 201 and the row exists
    $response->assertStatus(201);
    $this->assertDatabaseHas('tags', ['name' => 'Breaking', 'slug' => 'breaking']);
});

test('admin can edit a tag', function () {
    // Given: an admin and an existing tag
    $admin = adminUser();
    $tag = Tag::factory()->create();

    // When: they rename it
    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/tags/{$tag->id}", [
        'name' => 'Renamed Tag',
        'slug' => $tag->slug,
    ]);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Renamed Tag']);
});

test('admin can delete a tag', function () {
    // Given: an admin and an unused tag
    $admin = adminUser();
    $tag = Tag::factory()->create();

    // When: they delete it
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/tags/{$tag->id}");

    // Then: the row is gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

test('admin tag list returns every tag', function () {
    // Given: an admin and two tags
    $admin = adminUser();
    Tag::factory()->count(2)->create();

    // When: they list tags
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/tags');

    // Then: both are returned
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('tag creation rejects a duplicate slug', function () {
    // Given: an admin and a tag with a known slug
    $admin = adminUser();
    Tag::factory()->create(['slug' => 'already-taken']);

    // When: another tag reuses that slug
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/tags', [
        'name' => 'Dup',
        'slug' => 'already-taken',
    ]);

    // Then: validation rejects it
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['slug']]]);
});

test('public tag list is readable without authentication', function () {
    // Given: two tags created by an admin
    $admin = adminUser();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/tags', [
        'name' => 'Election', 'slug' => 'election',
    ])->assertStatus(201);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/tags', [
        'name' => 'Climate', 'slug' => 'climate',
    ])->assertStatus(201);

    // When: the public list is read with no token
    $response = $this->getJson('/api/v1/tags');

    // Then: both come back
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});
