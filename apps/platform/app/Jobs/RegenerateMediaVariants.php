<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerate all image variants for a media item (ADR-0010).
 * Always dispatched — on tier 1 it is executed by cron `queue:work --once`.
 */
class RegenerateMediaVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $mediaId)
    {
    }

    public function handle(MediaService $mediaService): void
    {
        $media = Media::query()->findOrFail($this->mediaId);

        $mediaService->regenerateVariants($media);
    }
}
