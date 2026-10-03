<?php

namespace App\Support;

/**
 * The ONLY place a deployment tier string is mapped to capability booleans
 * (ADR-0012 / docs/generator/platform-lock.md derivation table).
 *
 * Everywhere else in application code branches on config('capabilities.*')
 * booleans — never on a tier string. Unknown or missing tiers fail safe to
 * the most constrained tier (shared), so a misconfigured deployment loses
 * features instead of gaining unexpected access.
 */
final class Capabilities
{
    /**
     * Documented tier → capability matrix. Key order is part of the contract:
     * has_ssh_tooling, has_queue, has_redis, has_node, has_workers,
     * has_web_installer, has_web_migrations.
     *
     * @var array<string, array<string, bool>>
     */
    private const MATRIX = [
        'shared' => [
            'has_ssh_tooling' => false,
            'has_queue' => false,
            'has_redis' => false,
            'has_node' => false,
            'has_workers' => false,
            'has_web_installer' => true,
            'has_web_migrations' => true,
        ],
        'vps' => [
            'has_ssh_tooling' => true,
            'has_queue' => true,
            'has_redis' => true,
            'has_node' => true,
            'has_workers' => true,
            'has_web_installer' => true,
            'has_web_migrations' => true,
        ],
        'enterprise' => [
            'has_ssh_tooling' => true,
            'has_queue' => true,
            'has_redis' => true,
            'has_node' => false,
            'has_workers' => true,
            'has_web_installer' => false,
            'has_web_migrations' => false,
        ],
    ];

    /**
     * Derive the capability set for a tier (case-insensitive, fail-safe).
     *
     * @return array<string, bool> the seven documented capability keys, in order
     */
    public static function derive(?string $tier): array
    {
        $normalized = strtolower(trim((string) $tier));

        return self::MATRIX[$normalized] ?? self::MATRIX['shared'];
    }
}
