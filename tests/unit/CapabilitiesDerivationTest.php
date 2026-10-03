<?php

declare(strict_types=1);

/*
| Unit: capability derivation (ADR-0012 / docs/generator/platform-lock.md).
| Pure matrix mapping — no HTTP, no DB. The consumption side is covered by
| tests/acceptance/CapabilityLayerTest.php.
*/

use App\Support\Capabilities;

beforeEach(function () {
    $this->expectedKeys = [
        'has_ssh_tooling',
        'has_queue',
        'has_redis',
        'has_node',
        'has_workers',
        'has_web_installer',
        'has_web_migrations',
    ];
});

test('every tier derives exactly the seven documented capability keys', function (string $tier) {
    expect(array_keys(Capabilities::derive($tier)))->toBe($this->expectedKeys);
})->with(['shared', 'vps', 'enterprise']);

test('shared is the fully constrained tier: no queue, no ssh, no workers, web paths on', function () {
    $caps = Capabilities::derive('shared');

    expect($caps)->toBe([
        'has_ssh_tooling' => false,
        'has_queue' => false,
        'has_redis' => false,
        'has_node' => false,
        'has_workers' => false,
        'has_web_installer' => true,
        'has_web_migrations' => true,
    ]);
});

test('vps is the fully capable tier except orchestrator/web-installer nuances', function () {
    $caps = Capabilities::derive('vps');

    expect($caps)->toBe([
        'has_ssh_tooling' => true,
        'has_queue' => true,
        'has_redis' => true,
        'has_node' => true,
        'has_workers' => true,
        'has_web_installer' => true,
        'has_web_migrations' => true,
    ]);
});

test('enterprise runs node only in CI and uses K8s Jobs instead of web migrations', function () {
    $caps = Capabilities::derive('enterprise');

    expect($caps)->toBe([
        'has_ssh_tooling' => true,
        'has_queue' => true,
        'has_redis' => true,
        'has_node' => false,
        'has_workers' => true,
        'has_web_installer' => false,
        'has_web_migrations' => false,
    ]);
});

test('case is normalised so SHARED equals shared', function () {
    expect(Capabilities::derive('SHARED'))->toBe(Capabilities::derive('shared'));
});

test('an unknown tier falls back to the shared capability set', function (?string $tier) {
    expect(Capabilities::derive($tier))->toBe(Capabilities::derive('shared'));
})->with(['', 'bare-metal', 'on-prem', null]);

test('derive never exposes a tier string — only booleans', function () {
    foreach (['shared', 'vps', 'enterprise', 'bogus'] as $tier) {
        foreach (Capabilities::derive($tier) as $key => $value) {
            expect($key)->toBeString()->and($value)->toBeBool();
        }
    }
});
