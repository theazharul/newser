<?php

use App\Http\Controllers\Api\V1\MediaFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Serve uploaded media from storage (outside the web root) — docs/backend/media-system.md
Route::get('/media/{year}/{month}/{file}', [MediaFileController::class, 'show'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}']);
