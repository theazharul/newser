<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $posts = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->with(['author', 'category', 'featuredImage'])
            ->when($request->input('category'), function ($query, string $slug): void {
                $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
            })
            ->orderByDesc('published_at')
            ->paginate($perPage);

        // api.md shape: {data: [...], meta: {...}} — `data` is the item array,
        // not the paginator object (which would nest its own data/meta and
        // break the documented contract).
        $items = $posts->through(fn (Post $post): array => [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'featured_image_url' => $post->featuredImage?->file_path,
            'published_at' => $post->published_at?->toISOString(),
            'author_name' => $post->author?->name,
            'category_name' => $post->category?->name,
        ])->items();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->with(['author', 'category', 'featuredImage', 'tags'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'featured_image_url' => $post->featuredImage?->file_path,
                'published_at' => $post->published_at?->toISOString(),
                'author_name' => $post->author?->name,
                'category_name' => $post->category?->name,
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
            ],
        ]);
    }
}
