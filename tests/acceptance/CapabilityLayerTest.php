<?php

declare(strict_types=1);

/*
| AC-10: Capability layer exists and is consumed by queue/cache/media paths —
|        no `deployment_type === 'shared'` string checks anywhere
|        (phases.md exit criterion, ADR-0012, GAP-010 sequencing rule).
|
|        Tests assert on capabilities, never on tier strings (testing.md).
*/

use App\Jobs\ExtractVideoPoster;
use App\Support\Capabilities;
use App\Support\ContentCache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('no application code branches on deployment_type', function () {
    // Given/When: every PHP file under app/ and routes/ is scanned
    $violations = [];
    foreach (['app', 'routes'] as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/deployment_type/', $source) === 1) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }
    }

    // Then: app code asks capabilities, never the tier string (ADR-0012)
    expect($violations)->toBe([]);
});

test('config/capabilities.php derives the shared capability set from the environment', function () {
    // Given: the test environment declares DEPLOYMENT_TYPE=shared (tier1 fixture)
    // When: the capability config is read
    $capabilities = config('capabilities');

    // Then: exactly the seven documented keys, correct for shared (platform-lock.md)
    expect($capabilities)->toBe([
        'has_ssh_tooling' => false,
        'has_queue' => false,
        'has_redis' => false,
        'has_node' => false,
        'has_workers' => false,
        'has_web_installer' => true,
        'has_web_migrations' => true,
    ]);
});

test('image upload generates variants synchronously when has_queue is false', function () {
    // Given: no queue capability (shared tier) and a faked queue
    expect(config('capabilities.has_queue'))->toBeFalse();
    Queue::fake();

    // When: an admin uploads a real JPEG
    $data = $this->actingAs(adminUser(), 'sanctum')
        ->post('/api/v1/admin/media', ['file' => makeImageUpload()])
        ->assertStatus(201)
        ->json('data');

    // Then: variants exist on disk NOW (sync path) and nothing was queued
    expect($data['variants'])->toHaveCount(4);
    foreach ($data['variants'] as $path) {
        expect(Storage::disk('public')->exists(ltrim($path, '/')))->toBeTrue();
    }
    Queue::assertNothingPushed();
});

test('video poster extraction is dispatched as a job only when has_queue is true', function () {
    // Given: the shared capability set (has_queue=false) and a faked queue
    Queue::fake();

    // When: a video is uploaded on tier 1
    $this->actingAs(adminUser(), 'sanctum')
        ->post('/api/v1/admin/media', ['file' => makeMp4Upload()])
        ->assertStatus(201);

    // Then: no poster job — video processing is sync/inline on tier 1 (ADR-0010)
    Queue::assertNotPushed(ExtractVideoPoster::class);

    // And: with has_queue=true the SAME job class is dispatched (ADR-0010 rule 3)
    config(['capabilities.has_queue' => true]);
    $this->actingAs(adminUser(), 'sanctum')
        ->post('/api/v1/admin/media', ['file' => makeMp4Upload('second.mp4')])
        ->assertStatus(201);
    Queue::assertPushed(ExtractVideoPoster::class);
});

test('content cache resolves to redis when has_redis is true and to default otherwise', function () {
    // Given/When: capability flipped on
    config(['capabilities.has_redis' => true]);
    $withRedis = ContentCache::store();

    // And: capability flipped off
    config(['capabilities.has_redis' => false]);
    $withoutRedis = ContentCache::store();

    // Then: same codepath, different store — never a tier string
    expect($withRedis->getStore())->toBeInstanceOf(Illuminate\Cache\RedisStore::class)
        ->and($withoutRedis->getStore())->not->toBeInstanceOf(Illuminate\Cache\RedisStore::class);
});

test('Capabilities::derive maps each tier to its documented matrix', function () {
    // Given/When: each tier is derived (platform-lock.md default derivation table)
    $shared = Capabilities::derive('shared');
    $vps = Capabilities::derive('vps');
    $enterprise = Capabilities::derive('enterprise');

    // Then: shared has no queue/ssh/node/workers, web paths on
    expect($shared['has_queue'])->toBeFalse()
        ->and($shared['has_ssh_tooling'])->toBeFalse()
        ->and($shared['has_workers'])->toBeFalse()
        ->and($shared['has_web_installer'])->toBeTrue()
        ->and($shared['has_web_migrations'])->toBeTrue();

    // vps: full set except web-installer/web-migrations are optional-true
    expect($vps['has_queue'])->toBeTrue()
        ->and($vps['has_redis'])->toBeTrue()
        ->and($vps['has_ssh_tooling'])->toBeTrue()
        ->and($vps['has_node'])->toBeTrue()
        ->and($vps['has_workers'])->toBeTrue();

    // enterprise: no node at runtime (CI-only), no web installer/migrations (K8s Job)
    expect($enterprise['has_queue'])->toBeTrue()
        ->and($enterprise['has_node'])->toBeFalse()
        ->and($enterprise['has_web_installer'])->toBeFalse()
        ->and($enterprise['has_web_migrations'])->toBeFalse();
});

test('an unknown or missing tier fails safe to the shared capability set', function () {
    // Given/When: derivation is asked for nonsense
    $unknown = Capabilities::derive('bare-metal');

    // Then: it degrades to the most constrained tier (Tier 1)
    expect($unknown)->toBe(Capabilities::derive('shared'));
});
