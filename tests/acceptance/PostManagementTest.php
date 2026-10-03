<?php

declare(strict_types=1);

/*
| AC-2: Posts via Admin API — create/edit/publish (phases.md exit criterion).
|       api.md: CRUD + publish/archive under /api/v1/admin/posts.
*/

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;

test('admin can create a post', function () {
    // Given: an admin and required relations
    $admin = adminUser();
    $author = Author::factory()->create();
    $category = Category::factory()->create();

    // When: they create a post
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Laravel Ships News Platform',
        'body' => 'The full story body.',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'excerpt' => 'Short summary.',
        'seo_title' => 'Laravel Ships — News',
        'seo_description' => 'Announcement post.',
    ]);

    // Then: 201 and the row exists as a draft
    $response->assertStatus(201);
    $this->assertDatabaseHas('posts', [
        'title' => 'Laravel Ships News Platform',
        'status' => 'draft',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'seo_title' => 'Laravel Ships — News',
        'seo_description' => 'Announcement post.',
    ]);
});

test('admin can edit a post', function () {
    // Given: an admin and an existing post
    $admin = adminUser();
    $post = Post::factory()->create();

    // When: they update the title
    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/posts/{$post->id}", [
        'title' => 'Updated Title',
        'body' => $post->body,
        'author_id' => $post->author_id,
        'category_id' => $post->category_id,
    ]);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Updated Title']);
});

test('admin can publish a post', function () {
    // Given: an admin and a draft post
    $admin = adminUser();
    $post = Post::factory()->create(['status' => 'draft', 'published_at' => null]);

    // When: they publish it
    $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/posts/{$post->id}/publish");

    // Then: status flips and published_at is set
    $response->assertOk()->assertJsonPath('data.status', 'published');
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'published']);
    expect(Post::query()->find($post->id)->published_at)->not->toBeNull();
});

test('admin can archive a post', function () {
    // Given: an admin and a published post
    $admin = adminUser();
    $post = Post::factory()->published()->create();

    // When: they archive it
    $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/posts/{$post->id}/archive");

    // Then: status is archived
    $response->assertOk()->assertJsonPath('data.status', 'archived');
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'archived']);
});

test('admin can delete a post', function () {
    // Given: an admin and an existing post
    $admin = adminUser();
    $post = Post::factory()->create();

    // When: they delete it
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/posts/{$post->id}");

    // Then: the row is gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

test('admin post list includes drafts', function () {
    // Given: an admin and a draft post
    $admin = adminUser();
    Post::factory()->create(['status' => 'draft']);

    // When: they list posts
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/posts');

    // Then: the draft appears
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

test('post creation rejects a duplicate slug', function () {
    // Given: an admin and a post with a known slug
    $admin = adminUser();
    $existing = Post::factory()->create(['slug' => 'already-taken']);

    // When: another post reuses that slug
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Dup',
        'slug' => 'already-taken',
        'body' => 'Body.',
        'author_id' => Author::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    // Then: validation rejects it
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['slug']]]);
    unset($existing);
});

test('admin can attach tags to a post', function () {
    // Given: an admin, relations, and two tags
    $admin = adminUser();
    $tags = Tag::factory()->count(2)->create();

    // When: they create a post referencing the tags
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/posts', [
        'title' => 'Tagged Story',
        'body' => 'Body.',
        'author_id' => Author::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    // Then: the pivot rows exist
    $response->assertStatus(201);
    $postId = $response->json('data.id') ?? $response->json('id');
    expect($tags->every(fn ($tag) => $tag->posts()->where('post_id', $postId)->exists()))->toBeTrue();
});
