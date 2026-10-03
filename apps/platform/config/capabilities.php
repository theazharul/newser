<?php

use App\Support\Capabilities;

/*
|--------------------------------------------------------------------------
| Deployment capabilities (ADR-0012)
|--------------------------------------------------------------------------
|
| The single place the environment's tier is resolved into capability
| booleans. Application code reads config('capabilities.*') and NEVER
| compares tier strings — see App\Support\Capabilities and
| docs/generator/platform-lock.md for the documented derivation table.
|
*/

return Capabilities::derive(env('DEPLOYMENT_TYPE'));
