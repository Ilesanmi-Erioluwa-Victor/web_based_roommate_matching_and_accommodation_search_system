<?php

namespace RoomieMatch\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Transformation;
use RoomieMatch\Config\Env;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => Env::get('CLOUDINARY_CLOUD_NAME'),
                'api_key' => Env::get('CLOUDINARY_API_KEY'),
                'api_secret' => Env::get('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    public function uploadProfilePhoto(string $filePath, string $userId): array
    {
        $folder = "roomiematch/profiles/{$userId}";
        $result = $this->cloudinary->uploadApi()->upload($filePath, [
            'folder' => $folder,
            'public_id' => "profile_{$userId}_" . time(),
            'transformation' => [
                'width' => 1200, 'height' => 1200, 'crop' => 'limit',
                'quality' => 'auto', 'fetch_format' => 'auto',
            ],
            'allowed_formats' => ['jpg', 'png', 'webp'],
        ]);

        return [
            'url' => $result['secure_url'],
            'publicId' => $result['public_id'],
        ];
    }

    public function uploadListingPhoto(string $filePath, string $listingId, int $index): array
    {
        $folder = "roomiematch/listings/{$listingId}";
        $result = $this->cloudinary->uploadApi()->upload($filePath, [
            'folder' => $folder,
            'public_id' => "photo_{$listingId}_{$index}_" . time(),
            'transformation' => [
                'width' => 1200, 'height' => 1200, 'crop' => 'limit',
                'quality' => 'auto', 'fetch_format' => 'auto',
            ],
            'allowed_formats' => ['jpg', 'png', 'webp'],
        ]);

        return [
            'url' => $result['secure_url'],
            'publicId' => $result['public_id'],
        ];
    }

    public function deleteImage(string $publicId): bool
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getThumbnailUrl(string $publicId, int $width = 300, int $height = 300): string
    {
        return $this->cloudinary->image($publicId)->resize("c_fill,w_{$width},h_{$height}")->quality('auto')->format('auto')->toUrl();
    }

    public function validateUploadedFile(array $file): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 5 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
        }

        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large. Maximum size is 5MB.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            return ['valid' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid MIME type.'];
        }

        return ['valid' => true, 'error' => null];
    }
}
