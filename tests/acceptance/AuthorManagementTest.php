<?php

declare(strict_types=1);

/*
| AC-6: Authors — admin CRUD (api.md §Admin > Authors) and the public
|       read list (api.md §Public > Authors).
*/

use App\Models\Author;

test('admin can create an author', function () {
    // Given: an admin
    $admin = adminUser();

    // When: they create an author
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/authors', [
        'name' => 'Jane Reporter',
        'bio' => 'Covers world affairs.',
        'designation' => 'Senior Correspondent',
    ]);

    // Then: 201 and the row exists
    $response->assertStatus(201);
    $this->assertDatabaseHas('authors', [
        'name' => 'Jane Reporter',
        'bio' => 'Covers world affairs.',
        'designation' => 'Senior Correspondent',
    ]);
});

test('admin can edit an author', function () {
    // Given: an admin and an existing author
    $admin = adminUser();
    $author = Author::factory()->create();

    // When: they update the bio
    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/authors/{$author->id}", [
        'name' => $author->name,
        'bio' => 'Updated biography.',
    ]);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('authors', ['id' => $author->id, 'bio' => 'Updated biography.']);
});

test('admin can delete an author with no posts', function () {
    // Given: an admin and an author who never wrote anything
    $admin = adminUser();
    $author = Author::factory()->create();

    // When: they delete it
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/authors/{$author->id}");

    // Then: the row is gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('authors', ['id' => $author->id]);
});

test('admin author list returns every author', function () {
    // Given: an admin and two authors
    $admin = adminUser();
    Author::factory()->count(2)->create();

    // When: they list authors
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/authors');

    // Then: both are returned
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name']]]);
});

test('public author list is readable without authentication', function () {
    // Given: an author created by an admin
    $admin = adminUser();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/authors', [
        'name' => 'Public Author', 'bio' => 'Bio.',
    ])->assertStatus(201);

    // When: the public list is read with no token
    $response = $this->getJson('/api/v1/authors');

    // Then: the author comes back
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'name']]])
        ->assertJsonPath('data.0.name', 'Public Author');
});
