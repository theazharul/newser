<?php

declare(strict_types=1);

/*
| AC-1: Authentication — POST /api/v1/auth/login issues a bearer token;
|       GET /api/v1/auth/me resolves the acting user; logout revokes;
|       invalid credentials are rejected; login is throttled (api.md: 5/min/IP).
*/

use App\Models\User;

test('login returns a bearer token and the user', function () {
    // Given: an admin user
    $user = adminUser();

    // When: they post valid credentials
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Then: a token and the user come back
    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', $user->email);
    expect($response->json('token'))->toBeString()->not->toBe('');
});

test('login rejects invalid credentials', function () {
    // Given: an admin user
    $user = adminUser();

    // When: they post a wrong password
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    // Then: the request is rejected with the error envelope
    $response->assertStatus(422)
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('me resolves the authenticated user from the bearer token', function () {
    // Given: a logged-in admin with a real token
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => ($user = adminUser())->email,
        'password' => 'password',
    ]);
    $token = $login->json('token');

    // When: they call /auth/me with that token
    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    // Then: their identity is returned
    $response->assertOk()->assertJsonPath('email', $user->email);
});

test('logout revokes the token', function () {
    // Given: a logged-in admin with a real token
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => adminUser()->email,
        'password' => 'password',
    ])->json('token');

    // When: they log out, then reuse the token
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    // Then: the token no longer authenticates
    $response->assertStatus(401);
});

test('unauthenticated admin requests are rejected', function () {
    // Given/When: an admin endpoint is called without a token
    $response = $this->getJson('/api/v1/admin/posts');

    // Then: 401 with the error envelope
    $response->assertStatus(401)
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

test('login is rate limited to 5 attempts per minute', function () {
    // Given: an admin user
    $user = adminUser();

    // When: six login attempts arrive within the window
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    // Then: the sixth is throttled
    $response->assertStatus(429);
});
