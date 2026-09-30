<?php

namespace Modules\VmsOpenFileManager\Services;

use Modules\VmsOpenFileManager\Models\FileFolder;
use Modules\VmsOpenFileManager\Models\FileItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class FileManagerService
{
    protected $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function ensureDirectoryExists($path)
    {
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path, 0755, true);
            return true;
        }
        return false;
    }

    public function createFolder(array $data): FileFolder
    {
        $slug = Str::slug($data['name'] . '-' . time());

        return FileFolder::create([
            'name' => $data['name'],
            'slug' => $slug,
            'parent_id' => $data['parent_id'] ?? null,
            'is_public' => $data['is_public'] ?? false,
            'description' => $data['description'] ?? null,
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function uploadFile(UploadedFile $file, int $folderId, ?string $description = null): FileItem
    {
        $folder = FileFolder::findOrFail($folderId);
        
        // Asegurar que el directorio existe
        $directory = "filemanager/{$folder->slug}";
        $this->ensureDirectoryExists($directory);
    
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug($originalName . '-' . time()) . '.' . $extension;
    
        $path = $file->storeAs($directory, $filename, 'public');
    
        $thumbnailPath = null;
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $thumbnailPath = $this->storageService->generateThumbnail($path);
        }
    
        return FileItem::create([
            'name' => $originalName,
            'filename' => $filename,
            'extension' => $extension,
            'size' => $this->formatBytes($file->getSize()),
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'folder_id' => $folderId,
            'user_id' => auth()->id(),
            'description' => $description,
        ]);
    }

    public function deleteFile(FileItem $file): void
    {
        if (Storage::disk('public')->exists($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        if ($file->thumbnail_path && Storage::disk('public')->exists($file->thumbnail_path)) {
            Storage::disk('public')->delete($file->thumbnail_path);
        }

        $file->delete();
    }

    public function deleteFolder(FileFolder $folder): void
    {
        foreach ($folder->files as $file) {
            $this->deleteFile($file);
        }

        foreach ($folder->children as $child) {
            $this->deleteFolder($child);
        }

        $folder->delete();
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}