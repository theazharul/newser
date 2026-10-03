<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves uploaded media files from storage (outside the web root).
 * The /media/{year}/{month}/{file} route lives in routes/web.php.
 */
class MediaFileController extends Controller
{
    public function show(string $year, string $month, string $file): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = "media/{$year}/{$month}/{$file}";
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException();
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $disk->mimeType($path),
        ]);
    }
}
