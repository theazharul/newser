<?php

declare(strict_types=1);

/*
| AC-3: Publishing controls public visibility — only published posts are
|       exposed on the public API (api.md: GET /api/v1/posts, GET .../{slug}).
*/

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;

test('public post list shows only published posts', function () {
    // Given: one published and one draft post
    Post::factory()->published()->create(['title' => 'Visible Story']);
    Post::factory()->create(['title' => 'Hidden Draft', 'status' => 'draft']);

    // When: the public list is requested
    $response = $this->getJson('/api/v1/posts');

    // Then: only the published post appears, in api.md's shape
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Visible Story')
        ->assertJsonStructure([
            'data' => [[
                'id', 'title', 'slug', 'excerpt',
                'featured_image_url', 'published_at', 'author_name', 'category_name',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('a draft post is not reachable by slug on the public API', function () {
    // Given: a draft post
    Post::factory()->create(['slug' => 'secret-draft', 'status' => 'draft']);

    // When: the slug is requested publicly
    $response = $this->getJson('/api/v1/posts/secret-draft');

    // Then: it 404s with the error envelope
    $response->assertStatus(404)
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('a published post is reachable by slug on the public API', function () {
    // Given: a published post
    Post::factory()->published()->create([
        'title' => 'Public Story',
        'slug' => 'public-story',
        'seo_title' => 'Public Story SEO',
    ]);

    // When: the slug is requested publicly
    $response = $this->getJson('/api/v1/posts/public-story');

    // Then: the post body and SEO fields come back
    $response->assertOk()
        ->assertJsonPath('data.title', 'Public Story')
        ->assertJsonPath('data.seo_title', 'Public Story SEO');
});

test('publishing a draft makes it visible on the public API', function () {
    // Given: a draft post
    $post = Post::factory()->create(['status' => 'draft', 'published_at' => null]);

    // When: an admin publishes it, then the public list is read
    $this->actingAs(adminUser(), 'sanctum')
        ->postJson("/api/v1/admin/posts/{$post->id}/publish")
        ->assertOk();
    $response = $this->getJson('/api/v1/posts');

    // Then: it is now listed
    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $post->id);
});

test('public post list can filter by category slug', function () {
    // Given: two posts in different categories
    $sports = Category::factory()->create(['slug' => 'sports']);
    $tech = Category::factory()->create(['slug' => 'tech']);
    Post::factory()->published()->create(['category_id' => $sports->id, 'title' => 'Match Report']);
    Post::factory()->published()->create(['category_id' => $tech->id, 'title' => 'Gadget Launch']);

    // When: the list is filtered to sports
    $response = $this->getJson('/api/v1/posts?category=sports');

    // Then: only the sports post appears
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Match Report');
});

test('public post list paginates with the documented meta shape', function () {
    // Given: three published posts
    Post::factory()->published()->count(3)->create();

    // When: the list is read with per_page=2
    $response = $this->getJson('/api/v1/posts?per_page=2');

    // Then: meta reports two pages
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);
});

test('author and category names are resolved on the public post list', function () {
    // Given: a published post with a known author and category
    $author = \App\Models\Author::factory()->create(['name' => 'Jane Reporter']);
    $category = Category::factory()->create(['name' => 'World']);
    Post::factory()->published()->create([
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);

    // When: the public list is read
    $response = $this->getJson('/api/v1/posts');

    // Then: author_name and category_name are denormalised into the payload
    $response->assertOk()
        ->assertJsonPath('data.0.author_name', 'Jane Reporter')
        ->assertJsonPath('data.0.category_name', 'World');
});
