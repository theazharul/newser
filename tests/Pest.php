<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case binding (root tests/ — see docs/development/testing.md)
|--------------------------------------------------------------------------
|
| acceptance: behavioral tests hitting real entry points (/api/v1/...)
| feature:    Laravel feature tests
| unit:       unit tests (bind explicitly in-file when a fixture is needed)
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__ . '/Helpers.php';

uses(TestCase::class)->in('acceptance', 'unit', 'feature');

uses(RefreshDatabase::class)->in('acceptance', 'feature');

/*
| Custom expectations
| Pest ships toBeEmpty() but not its inverse. AC-11 asserts that each field's
| validation messages form a non-empty array, so provide the inverse matcher.
*/
expect()->extend('toBeNotEmpty', function (): \Pest\Expectation {
    return $this->not->toBeEmpty();
});
