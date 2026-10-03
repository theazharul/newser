<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The content cache store, selected by capability — never by tier string
 * (ADR-0012). Redis when the deployment provides it, the configured default
 * store otherwise: one call site, two backends, same code path.
 */
final class ContentCache
{
    public static function store(): Repository
    {
        if (config('capabilities.has_redis')) {
            return Cache::store('redis');
        }

        return Cache::store();
    }
}
