<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Tag::query()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')],
        ]);

        $tag = Tag::query()->create($data);

        return response()->json(['data' => $tag], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($tag->id)],
        ]);

        $tag->update($data);

        return response()->json(['data' => $tag]);
    }

    public function destroy(Tag $tag): \Illuminate\Http\Response
    {
        $tag->delete();

        return response()->noContent();
    }
}
