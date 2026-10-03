<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Author::query()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'designation' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'user_id' => ['nullable', 'exists:users,id'],
            'avatar_id' => ['nullable', 'exists:media,id'],
        ]);

        $author = Author::query()->create($data);

        return response()->json(['data' => $author], 201);
    }

    public function update(Request $request, Author $author): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'designation' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'user_id' => ['nullable', 'exists:users,id'],
            'avatar_id' => ['nullable', 'exists:media,id'],
        ]);

        $author->update($data);

        return response()->json(['data' => $author]);
    }

    public function destroy(Author $author): \Illuminate\Http\Response
    {
        $author->delete();

        return response()->noContent();
    }
}
