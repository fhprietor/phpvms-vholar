<?php

namespace Modules\VmsOpenFileManager\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class StorageService
{
    protected $thumbnailWidth;
    protected $thumbnailHeight;

    public function __construct()
    {
        $this->thumbnailWidth = config('vmsopenfilemanager.thumbnail_size.width', 200);
        $this->thumbnailHeight = config('vmsopenfilemanager.thumbnail_size.height', 200);
    }

    public function generateThumbnail(string $path, ?int $width = null, ?int $height = null): ?string
    {
        $width = $width ?? $this->thumbnailWidth;
        $height = $height ?? $this->thumbnailHeight;
    
        $fullPath = Storage::disk('public')->path($path);
    
        if (!file_exists($fullPath)) {
            \Log::error('Thumbnail: File not found at ' . $fullPath);
            return null;
        }
    
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return null;
        }
    
        // Generar nombre del thumbnail
        $pathInfo = pathinfo($path);
        $thumbnailPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $extension;
    
        try {
            $image = Image::make($fullPath);
            $image->fit($width, $height, function ($constraint) {
                $constraint->upsize();
            });
            $image->save(Storage::disk('public')->path($thumbnailPath));
    
            \Log::info('Thumbnail created: ' . $thumbnailPath);
            return $thumbnailPath;
        } catch (\Exception $e) {
            \Log::error('Thumbnail error: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteFile(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }
        return false;
    }

    public function fileExists(string $path): bool
    {
        return Storage::disk('public')->exists($path);
    }
}