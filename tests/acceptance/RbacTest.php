<?php

declare(strict_types=1);

/*
| AC-9: Roles and permissions enforced (phases.md exit criterion).
|       Matrix inferred — docs name roles/permissions but not the mapping
|       (see tests/Helpers.php):
|         admin:  all permissions
|         editor: all content permissions
|         author: posts.create/edit/publish, media.upload/edit
|         viewer: none (GETs are authenticated but need no permission)
*/

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;

test('viewer can read the admin post list without any permission', function () {
    // Given: a viewer (authenticated, no permissions)
    $viewer = viewerUser();
    Post::factory()->create();

    // When: they list posts
    $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/admin/posts');

    // Then: reads are allowed for any authenticated user
    $response->assertOk()->assertJsonCount(1, 'data');
});

test('viewer cannot create a post', function () {
    // Given: a viewer
    $viewer = viewerUser();

    // When: they attempt a mutation requiring posts.create
    $response = $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Forbidden Draft',
        'body' => 'Body.',
        'author_id' => Author::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    // Then: 403 with the error envelope
    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN')
        ->assertJsonStructure(['error' => ['code', 'message']]);
    $this->assertDatabaseMissing('posts', ['title' => 'Forbidden Draft']);
});

test('viewer cannot publish or delete a post', function () {
    // Given: a viewer and a draft post
    $viewer = viewerUser();
    $post = Post::factory()->create(['status' => 'draft', 'published_at' => null]);

    // When: they attempt publish and delete
    $publish = $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/admin/posts/{$post->id}/publish");
    $delete = $this->actingAs($viewer, 'sanctum')->deleteJson("/api/v1/admin/posts/{$post->id}");

    // Then: both are forbidden
    $publish->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    $delete->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'draft']);
});

test('author can create and publish posts but cannot manage categories', function () {
    // Given: an author
    $author = authorUser();

    // When: they create a post (posts.create)
    $create = $this->actingAs($author, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Author Story',
        'body' => 'Body.',
        'author_id' => Author::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);
    $create->assertStatus(201);

    // And: they publish it (posts.publish)
    $publish = $this->actingAs($author, 'sanctum')
        ->postJson("/api/v1/admin/posts/{$create->json('data.id')}/publish");
    $publish->assertOk();

    // And: they cannot create a category (no categories.create)
    $category = $this->actingAs($author, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'Nope', 'slug' => 'nope',
    ]);
    $category->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
});

test('author cannot delete a post', function () {
    // Given: an author and a post
    $author = authorUser();
    $post = Post::factory()->create();

    // When: they attempt a delete (no posts.delete)
    $response = $this->actingAs($author, 'sanctum')->deleteJson("/api/v1/admin/posts/{$post->id}");

    // Then: forbidden
    $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    $this->assertDatabaseHas('posts', ['id' => $post->id]);
});

test('editor can manage content across posts and categories', function () {
    // Given: an editor
    $editor = editorUser();

    // When: they create a category (categories.create)
    $category = $this->actingAs($editor, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'Editor Desk', 'slug' => 'editor-desk',
    ]);
    $category->assertStatus(201);

    // And: they create a post in it (posts.create)
    $post = $this->actingAs($editor, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Editor Story',
        'body' => 'Body.',
        'author_id' => Author::factory()->create()->id,
        'category_id' => Category::first()->id,
    ]);
    $post->assertStatus(201);

    // And: they delete the category they own (categories.delete)
    $this->actingAs($editor, 'sanctum')
        ->deleteJson('/api/v1/admin/categories/' . Category::firstWhere('slug', 'editor-desk')->id)
        ->assertStatus(204);
});

test('mutations without a token are rejected before permissions are considered', function () {
    // Given/When: an admin mutation is attempted with no token
    $response = $this->postJson('/api/v1/admin/posts', [
        'title' => 'Anonymous',
        'body' => 'Body.',
    ]);

    // Then: 401, not 403 — authentication precedes authorization
    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});
