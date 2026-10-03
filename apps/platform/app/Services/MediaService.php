<?php

namespace App\Services;

use App\Jobs\ExtractVideoPoster;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Image as SpatieImage;

/**
 * Media storage + variant generation (docs/backend/media-system.md, ADR-0010).
 *
 * Images: variants are generated SYNCHRONOUSLY on every tier — never queued.
 * Videos: poster extraction is dispatched as a job only when the deployment
 * has a queue; on tier 1 it is attempted inline, best-effort.
 */
class MediaService
{
    /**
     * Variant name => max width in pixels. All variants are WebP.
     *
     * @var array<string, int>
     */
    private const VARIANT_WIDTHS = [
        'thumbnail' => 150,
        'small' => 300,
        'medium' => 640,
        'large' => 1280,
    ];

    public function store(UploadedFile $file, array $metadata): Media
    {
        $mime = $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');

        $directory = 'media/'.now()->format('Y/m');
        $storedPath = $file->storeAs($directory, $this->uniqueFileName($file), 'public');

        $dimensions = $isImage ? getimagesize($file->getPathname()) : [null, null];

        $media = Media::query()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => '/'.$storedPath,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'credit' => $metadata['credit'] ?? null,
        ]);

        if ($isImage) {
            $this->generateVariants($media);
        } elseif ($mime === 'video/mp4') {
            $this->handleVideoPoster($media);
        }

        return $media;
    }

    public function delete(Media $media): void
    {
        $disk = Storage::disk('public');

        $disk->delete(ltrim($media->file_path, '/'));

        foreach ($media->variants as $variant) {
            $disk->delete(ltrim($variant->file_path, '/'));
        }

        $media->delete();
    }

    public function regenerateVariants(Media $media): void
    {
        $disk = Storage::disk('public');

        foreach ($media->variants as $variant) {
            $disk->delete(ltrim($variant->file_path, '/'));
        }

        $media->variants()->delete();

        if (str_starts_with($media->mime_type, 'image/')) {
            $this->generateVariants($media);
        }
    }

    private function uniqueFileName(UploadedFile $file): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->guessExtension();

        return $base.'-'.Str::random(8).'.'.$extension;
    }

    private function generateVariants(Media $media): void
    {
        $disk = Storage::disk('public');
        $originalPath = $disk->path(ltrim($media->file_path, '/'));
        $directory = 'media/'.now()->format('Y/m');
        $baseName = Str::slug(pathinfo($media->file_name, PATHINFO_FILENAME));

        foreach (self::VARIANT_WIDTHS as $name => $width) {
            $variantPath = $directory.'/'.$baseName.'-'.$name.'.webp';
            $absolutePath = $disk->path($variantPath);

            SpatieImage::load($originalPath)
                ->width($width)
                ->format('webp')
                ->save($absolutePath);

            [$variantWidth, $variantHeight] = getimagesize($absolutePath);

            MediaVariant::query()->create([
                'media_id' => $media->id,
                'variant_name' => $name,
                'file_path' => '/'.$variantPath,
                'width' => $variantWidth,
                'height' => $variantHeight,
                'size' => filesize($absolutePath),
            ]);
        }
    }

    private function handleVideoPoster(Media $media): void
    {
        if (config('capabilities.has_queue')) {
            ExtractVideoPoster::dispatch($media->id);

            return;
        }

        // Tier 1: no queue — attempt inline, best-effort (ADR-0010).
        try {
            (new ExtractVideoPoster($media->id))->handle();
        } catch (\Throwable $e) {
            logger()->warning('Video poster extraction failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
