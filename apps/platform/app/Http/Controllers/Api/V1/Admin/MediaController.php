<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RegenerateMediaVariants;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Media::query()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'credit' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $mime = $file->getMimeType();

        $this->assertUploadAllowed($file, $mime);

        $media = $this->mediaService->store($file, $request->only(['alt_text', 'caption', 'credit']));

        return response()->json(['data' => $this->resource($media)], 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'credit' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($data);

        return response()->json(['data' => $this->resource($media)]);
    }

    public function destroy(Media $media): \Illuminate\Http\Response
    {
        $this->mediaService->delete($media);

        return response()->noContent();
    }

    public function regenerateVariants(Media $media): JsonResponse
    {
        RegenerateMediaVariants::dispatch($media->id);

        return response()->json(['message' => 'Variant regeneration queued.']);
    }

    /**
     * MIME is checked on content (finfo), not extension; images are capped at
     * 10MB and 8000x8000 (docs/backend/media-system.md).
     */
    private function assertUploadAllowed(UploadedFile $file, string $mime): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'video/mp4'];

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['The file must be an image (JPEG, PNG, WebP, GIF, AVIF) or MP4 video.'],
            ]);
        }

        if (str_starts_with($mime, 'image/')) {
            [$width, $height] = getimagesize($file->getPathname());

            if ($width > 8000 || $height > 8000) {
                throw ValidationException::withMessages([
                    'file' => ['The file dimensions must not exceed 8000x8000 pixels.'],
                ]);
            }
        }
    }

    private function resource(Media $media): array
    {
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'file_path' => $media->file_path,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'credit' => $media->credit,
            'variants' => $media->variants->mapWithKeys(
                fn (MediaVariant $variant): array => [$variant->variant_name => $variant->file_path]
            )->all(),
        ];
    }
}
