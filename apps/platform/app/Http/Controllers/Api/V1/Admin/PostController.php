<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Post::query()
            ->with(['author', 'category'])
            ->latest()
            ->get();

        return response()->json(['data' => $posts]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'author_id' => ['required', 'exists:authors,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'featured_image_id' => ['nullable', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ]);

        $post = Post::query()->create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'author_id' => $data['author_id'],
            'category_id' => $data['category_id'],
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'status' => 'draft',
        ]);

        if (! empty($data['tag_ids'])) {
            $post->tags()->sync($data['tag_ids']);
        }

        return response()->json(['data' => $post], 201);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post->id)],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'author_id' => ['required', 'exists:authors,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'featured_image_id' => ['nullable', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ]);

        $post->update([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? $post->slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'author_id' => $data['author_id'],
            'category_id' => $data['category_id'],
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);

        if (array_key_exists('tag_ids', $data)) {
            $post->tags()->sync($data['tag_ids'] ?? []);
        }

        return response()->json(['data' => $post]);
    }

    public function destroy(Post $post): \Illuminate\Http\Response
    {
        $post->delete();

        return response()->noContent();
    }

    public function publish(Post $post): JsonResponse
    {
        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json(['data' => $post]);
    }

    public function archive(Post $post): JsonResponse
    {
        $post->update(['status' => 'archived']);

        return response()->json(['data' => $post]);
    }
}
