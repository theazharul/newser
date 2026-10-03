<?php

use App\Http\Controllers\Api\V1\Admin\AuthorController as AdminAuthorController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\V1\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\V1\Admin\TagController as AdminTagController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Public\AuthorController as PublicAuthorController;
use App\Http\Controllers\Api\V1\Public\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\V1\Public\PostController as PublicPostController;
use App\Http\Controllers\Api\V1\Public\TagController as PublicTagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — prefix: api/v1 (docs/backend/api.md)
|--------------------------------------------------------------------------
|
| Reads (GET) require a valid token but no permission — any authenticated
| role may read. Mutations require the matching permission via the
| 'permission' middleware (AC-9). Public reads need no token at all.
|
*/

// --- Authentication ---
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// --- Public reads (no token) ---
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/posts', [PublicPostController::class, 'index']);
    Route::get('/posts/{slug}', [PublicPostController::class, 'show']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
    Route::get('/tags', [PublicTagController::class, 'index']);
    Route::get('/authors', [PublicAuthorController::class, 'index']);
    Route::get('/pages/{slug}', [PublicPageController::class, 'show']);
});

// --- Admin (token required; mutations additionally require a permission) ---
Route::middleware(['auth:sanctum', 'throttle:120,1'])
    ->prefix('admin')
    ->group(function () {
        // Posts
        Route::get('/posts', [AdminPostController::class, 'index']);
        Route::post('/posts', [AdminPostController::class, 'store'])
            ->middleware('permission:posts.create');
        Route::put('/posts/{post}', [AdminPostController::class, 'update'])
            ->middleware('permission:posts.edit');
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])
            ->middleware('permission:posts.delete');
        Route::post('/posts/{post}/publish', [AdminPostController::class, 'publish'])
            ->middleware('permission:posts.publish');
        Route::post('/posts/{post}/archive', [AdminPostController::class, 'archive'])
            ->middleware('permission:posts.publish');

        // Categories
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store'])
            ->middleware('permission:categories.create');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])
            ->middleware('permission:categories.edit');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
            ->middleware('permission:categories.delete');

        // Tags
        Route::get('/tags', [AdminTagController::class, 'index']);
        Route::post('/tags', [AdminTagController::class, 'store'])
            ->middleware('permission:tags.create');
        Route::put('/tags/{tag}', [AdminTagController::class, 'update'])
            ->middleware('permission:tags.edit');
        Route::delete('/tags/{tag}', [AdminTagController::class, 'destroy'])
            ->middleware('permission:tags.delete');

        // Authors
        Route::get('/authors', [AdminAuthorController::class, 'index']);
        Route::post('/authors', [AdminAuthorController::class, 'store'])
            ->middleware('permission:authors.create');
        Route::put('/authors/{author}', [AdminAuthorController::class, 'update'])
            ->middleware('permission:authors.edit');
        Route::delete('/authors/{author}', [AdminAuthorController::class, 'destroy'])
            ->middleware('permission:authors.delete');

        // Pages
        Route::get('/pages', [AdminPageController::class, 'index']);
        Route::post('/pages', [AdminPageController::class, 'store'])
            ->middleware('permission:pages.create');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])
            ->middleware('permission:pages.edit');
        Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])
            ->middleware('permission:pages.delete');

        // Media
        Route::get('/media', [MediaController::class, 'index']);
        Route::post('/media', [MediaController::class, 'store'])
            ->middleware('permission:media.upload');
        Route::put('/media/{media}', [MediaController::class, 'update'])
            ->middleware('permission:media.edit');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])
            ->middleware('permission:media.delete');
        Route::post('/media/{media}/variants', [MediaController::class, 'regenerateVariants'])
            ->middleware('permission:media.edit');
    });
