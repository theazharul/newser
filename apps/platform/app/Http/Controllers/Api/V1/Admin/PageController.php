<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Page::query()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,published'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
        ]);

        $page = Page::query()->create([
            ...$data,
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'status' => $data['status'] ?? 'draft',
        ]);

        return response()->json(['data' => $page], 201);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page->id)],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,published'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
        ]);

        $page->update($data);

        return response()->json(['data' => $page]);
    }

    public function destroy(Page $page): \Illuminate\Http\Response
    {
        $page->delete();

        return response()->noContent();
    }
}
