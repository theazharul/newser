<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Acceptance/unit test helpers (auto-loaded by Pest)
|--------------------------------------------------------------------------
|
| Role matrix (inferred — docs define role/permission NAMES, not the mapping):
|   admin:   all permissions
|   editor:  all content permissions (posts/categories/tags/authors/media/pages)
|   author:  posts.create, posts.edit, posts.publish, media.upload, media.edit
|   viewer:  none (read-only — GETs are authenticated but need no permission)
|
*/

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function seedRolesAndPermissions(): void
{
    (new \Database\Seeders\RolesAndPermissionsSeeder())->run();
}

function userWithRole(string $role): User
{
    seedRolesAndPermissions();

    return User::factory()->create([
        'role_id' => Role::query()->where('name', $role)->value('id'),
    ]);
}

function adminUser(): User
{
    return userWithRole('admin');
}

function editorUser(): User
{
    return userWithRole('editor');
}

function authorUser(): User
{
    return userWithRole('author');
}

function viewerUser(): User
{
    return userWithRole('viewer');
}

/**
 * A real JPEG (GD) so finfo() reports image/jpeg — MIME is checked on content.
 */
function makeImageUpload(string $name = 'news-photo.jpg', int $width = 640, int $height = 480): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 180));
    ob_start();
    imagejpeg($image, null, 85);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

/**
 * Text bytes masquerading as a JPEG — finfo() must report text/plain, not image/*.
 */
function makeFakeJpegUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, 'definitely not an image, just some text.');

    return new UploadedFile($path, 'trick.jpg', 'image/jpeg', null, true);
}

/**
 * A minimal file with a valid ISO-BMFF ftyp box so finfo() reports video/mp4.
 */
function makeMp4Upload(string $name = 'clip.mp4'): UploadedFile
{
    $bytes = pack('N', 24) . 'ftypmp42' . str_repeat("\x00", 64) . random_bytes(128);

    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'video/mp4', null, true);
}
