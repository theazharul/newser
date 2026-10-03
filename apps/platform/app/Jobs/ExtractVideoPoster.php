<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Extract a poster frame from an uploaded video (ADR-0010).
 * Dispatched only when the deployment has a queue; on tier 1 it runs inline.
 */
class ExtractVideoPoster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $mediaId)
    {
    }

    public function handle(): void
    {
        try {
            $media = Media::query()->findOrFail($this->mediaId);

            if ($media->mime_type !== 'video/mp4') {
                return;
            }

            $disk = Storage::disk('public');
            $source = $disk->path(ltrim($media->file_path, '/'));
            $posterPath = 'media/'.now()->format('Y/m').'/poster-'.$media->id.'.jpg';
            $absolutePosterPath = $disk->path($posterPath);

            $result = Process::run([
                'ffmpeg', '-y', '-i', $source, '-frames:v', '1', '-q:v', '2', $absolutePosterPath,
            ]);

            if (! $result->successful() || ! file_exists($absolutePosterPath)) {
                return;
            }

            MediaVariant::query()->create([
                'media_id' => $media->id,
                'variant_name' => 'poster',
                'file_path' => '/'.$posterPath,
                'width' => null,
                'height' => null,
                'size' => filesize($absolutePosterPath),
            ]);
        } catch (\Throwable $e) {
            logger()->warning('Video poster extraction failed', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
