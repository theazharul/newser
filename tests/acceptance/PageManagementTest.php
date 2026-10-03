<?php

declare(strict_types=1);

/*
| AC-7: Pages — admin CRUD (phases.md Build list includes "Pages"; api.md
|       specifies only the public GET /api/v1/pages/{slug}, so admin CRUD
|       endpoints are a documented inference) + public published-only read.
*/

use App\Models\Page;

test('admin can create a page', function () {
    // Given: an admin
    $admin = adminUser();

    // When: they create a page
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/pages', [
        'title' => 'About Us',
        'content' => '<p>Who we are.</p>',
        'seo_title' => 'About Us — News',
        'seo_description' => 'About the newsroom.',
    ]);

    // Then: 201 and the row exists as a draft
    $response->assertStatus(201);
    $this->assertDatabaseHas('pages', [
        'title' => 'About Us',
        'content' => '<p>Who we are.</p>',
        'status' => 'draft',
        'seo_title' => 'About Us — News',
    ]);
});

test('admin can edit a page', function () {
    // Given: an admin and an existing page
    $admin = adminUser();
    $page = Page::factory()->create();

    // When: they update the content
    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/pages/{$page->id}", [
        'title' => $page->title,
        'content' => '<p>Updated content.</p>',
        'status' => $page->status,
    ]);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('pages', ['id' => $page->id, 'content' => '<p>Updated content.</p>']);
});

test('admin can delete a page', function () {
    // Given: an admin and an existing page
    $admin = adminUser();
    $page = Page::factory()->create();

    // When: they delete it
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/pages/{$page->id}");

    // Then: the row is gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
});

test('admin page list includes drafts', function () {
    // Given: an admin and a draft page
    $admin = adminUser();
    Page::factory()->create(['status' => 'draft']);

    // When: they list pages
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/pages');

    // Then: the draft appears
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

test('a published page is reachable by slug on the public API', function () {
    // Given: a published page
    Page::factory()->create([
        'slug' => 'about',
        'title' => 'About Us',
        'status' => 'published',
        'seo_title' => 'About Us SEO',
    ]);

    // When: the slug is requested publicly
    $response = $this->getJson('/api/v1/pages/about');

    // Then: content and SEO fields come back
    $response->assertOk()
        ->assertJsonPath('data.title', 'About Us')
        ->assertJsonPath('data.seo_title', 'About Us SEO');
});

test('a draft page is not reachable by slug on the public API', function () {
    // Given: a draft page
    Page::factory()->create(['slug' => 'secret-page', 'status' => 'draft']);

    // When: the slug is requested publicly
    $response = $this->getJson('/api/v1/pages/secret-page');

    // Then: it 404s with the error envelope
    $response->assertStatus(404)
        ->assertJsonStructure(['error' => ['code', 'message']]);
});
