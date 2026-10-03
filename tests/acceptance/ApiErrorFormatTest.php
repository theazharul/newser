<?php

declare(strict_types=1);

/*
| AC-11: Error envelope — api.md §Error Format: every non-2xx response is
|        {"error": {code, message, details?}}. Codes: VALIDATION_ERROR is
|        spec'd; UNAUTHENTICATED/FORBIDDEN/NOT_FOUND follow the same envelope
|        (documented inference).
*/

use App\Models\Author;
use App\Models\Category;

test('validation failures return the documented envelope with field details', function () {
    // Given: an admin posts an empty body (title/body/relations all missing)
    $admin = adminUser();

    // When: validation fails
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/posts', []);

    // Then: exactly the api.md shape — error.code, error.message, error.details
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.message', 'The given data was invalid.')
        ->assertJsonStructure(['error' => [
            'code',
            'message',
            'details' => ['title', 'body'],
        ]])
        ->assertJsonPath('error.details.title.0', 'The title field is required.');
});

test('unauthenticated requests return the envelope with UNAUTHENTICATED', function () {
    // Given/When: a protected endpoint is called with no token
    $response = $this->getJson('/api/v1/admin/posts');

    // Then: 401 in the envelope
    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('forbidden requests return the envelope with FORBIDDEN', function () {
    // Given: a viewer attempting a mutation
    $viewer = viewerUser();

    // When: authorization fails
    $response = $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/admin/categories', [
        'name' => 'No', 'slug' => 'no',
    ]);

    // Then: 403 in the envelope
    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('missing resources return the envelope with NOT_FOUND', function () {
    // Given/When: an unknown public slug is requested
    $response = $this->getJson('/api/v1/posts/does-not-exist');

    // Then: 404 in the envelope
    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('login failures return the envelope rather than a redirect', function () {
    // Given: an admin user
    $user = adminUser();

    // When: bad credentials arrive as JSON
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ]);

    // Then: 422 envelope (AuthTest covers the shape; here: never a redirect)
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertHeader('Content-Type', 'application/json');
});

test('public API rate limit returns 429 in the envelope', function () {
    // Given/When: 61 public requests arrive within the minute (60/min per api.md)
    foreach (range(1, 61) as $ignored) {
        $response = $this->getJson('/api/v1/posts');
        if ($response->status() === 429) {
            break;
        }
    }

    // Then: the 429 uses the same envelope
    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('field-level details are always an object of field => messages', function () {
    // Given: an admin creating a category with two invalid fields
    $admin = adminUser();

    // When: both name and slug are missing
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', []);

    // Then: details keys are the field names, values are message arrays
    $response->assertStatus(422);
    $details = $response->json('error.details');
    expect($details)->toBeArray();
    foreach ($details as $field => $messages) {
        expect($field)->toBeString()->and($messages)->toBeArray()->toBeNotEmpty();
    }
    expect($details)->toHaveKeys(['name', 'slug']);
});
