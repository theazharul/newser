<?php

declare(strict_types=1);

/*
| AC-8: Media — upload + manage media (phases.md exit criterion).
|       media-system.md: finfo MIME check on content, 10MB image cap,
|       8000x8000 max, sync variant generation, serve via /media/ controller
|       route (storage outside web root), regenerate variants endpoint.
*/

use App\Jobs\RegenerateMediaVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function uploadImage(object $test, array $overrides = []): array
{
    $admin = adminUser();

    return $test->actingAs($admin, 'sanctum')
        ->post('/api/v1/admin/media', array_merge([
            'file' => makeImageUpload(),
            'alt_text' => 'News photo',
            'caption' => 'A caption',
            'credit' => 'A photographer',
        ], $overrides))
        ->assertStatus(201)
        ->json('data');
}

test('admin can upload an image and gets synchronous webp variants', function () {
    // Given/When: an admin uploads a real JPEG
    $data = uploadImage($this);

    // Then: the response documents the original plus four webp variants
    expect($data['mime_type'])->toBe('image/jpeg')
        ->and($data['alt_text'])->toBe('News photo')
        ->and($data['variants'])->toHaveKeys(['thumbnail', 'small', 'medium', 'large']);
    foreach ($data['variants'] as $path) {
        expect($path)->toStartWith('/media/')->and($path)->toEndWith('.webp');
    }

    // And: every file physically exists under storage/app/public/media/YYYY/MM/
    $disk = Storage::disk('public');
    expect($disk->exists(ltrim($data['file_path'], '/')))->toBeTrue();
    foreach ($data['variants'] as $path) {
        expect($disk->exists(ltrim($path, '/')))->toBeTrue();
    }

    // And: the row is persisted with dimensions
    $this->assertDatabaseHas('media', [
        'id' => $data['id'],
        'mime_type' => 'image/jpeg',
        'alt_text' => 'News photo',
    ]);
});

test('media upload rejects content that is not really an image', function () {
    // Given/When: an admin uploads text bytes named .jpg (finfo reports text/plain)
    $response = $this->actingAs(adminUser(), 'sanctum')->post('/api/v1/admin/media', [
        'file' => makeFakeJpegUpload(),
    ]);

    // Then: validation rejects it — MIME is checked on content, not extension
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['file']]]);
});

test('media upload rejects images larger than 10MB', function () {
    // Given: a real JPEG padded with junk to exceed the 10MB image cap
    $jpeg = makeImageUpload('huge.jpg');
    $bytes = file_get_contents($jpeg->getPathname()) . str_repeat('A', 11 * 1024 * 1024);
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $bytes);
    $huge = new UploadedFile($path, 'huge.jpg', 'image/jpeg', null, true);

    // When/Then: the upload is rejected
    $this->actingAs(adminUser(), 'sanctum')->post('/api/v1/admin/media', ['file' => $huge])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('media upload rejects images wider than 8000px', function () {
    // Given: a real JPEG wider than the documented 8000px ceiling
    $wide = makeImageUpload('wide.jpg', 8001, 100);

    // When/Then: the upload is rejected
    $this->actingAs(adminUser(), 'sanctum')->post('/api/v1/admin/media', ['file' => $wide])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('uploaded media is served through the /media controller route', function () {
    // Given: an uploaded image (original + medium variant)
    $data = uploadImage($this);

    // When: both URLs from the payload are requested with no token
    $original = $this->get($data['file_path']);
    $variant = $this->get($data['variants']['medium']);

    // Then: the controller serves them from storage (outside the web root)
    $original->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $variant->assertOk()->assertHeader('Content-Type', 'image/webp');
});

test('admin can update media metadata', function () {
    // Given: an uploaded image
    $data = uploadImage($this);

    // When: the alt text is corrected
    $response = $this->actingAs(adminUser(), 'sanctum')
        ->putJson("/api/v1/admin/media/{$data['id']}", ['alt_text' => 'Corrected alt']);

    // Then: the change is persisted
    $response->assertOk();
    $this->assertDatabaseHas('media', ['id' => $data['id'], 'alt_text' => 'Corrected alt']);
});

test('admin can delete media and its files are removed', function () {
    // Given: an uploaded image
    $data = uploadImage($this);
    $disk = Storage::disk('public');

    // When: they delete it
    $response = $this->actingAs(adminUser(), 'sanctum')
        ->deleteJson("/api/v1/admin/media/{$data['id']}");

    // Then: the row and every file are gone
    $response->assertStatus(204);
    $this->assertDatabaseMissing('media', ['id' => $data['id']]);
    expect($disk->exists(ltrim($data['file_path'], '/')))->toBeFalse();
    foreach ($data['variants'] as $path) {
        expect($disk->exists(ltrim($path, '/')))->toBeFalse();
    }
});

test('admin media list returns uploaded files', function () {
    // Given: two uploads
    uploadImage($this, ['file' => makeImageUpload('one.jpg')]);
    uploadImage($this, ['file' => makeImageUpload('two.jpg')]);

    // When: an admin lists media
    $response = $this->actingAs(adminUser(), 'sanctum')->getJson('/api/v1/admin/media');

    // Then: both appear
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'file_name', 'file_path', 'mime_type', 'size', 'alt_text']]]);
});

test('regenerating variants dispatches a job (cron queue:work --once on tier 1)', function () {
    // Given: an uploaded image and a faked queue
    $data = uploadImage($this);
    Queue::fake();

    // When: an admin requests variant regeneration
    $response = $this->actingAs(adminUser(), 'sanctum')
        ->postJson("/api/v1/admin/media/{$data['id']}/variants");

    // Then: the bulk job is dispatched — executed by cron queue:work --once (ADR-0010)
    $response->assertOk();
    Queue::assertPushed(RegenerateMediaVariants::class, fn ($job) => $job->mediaId === $data['id']);
});
