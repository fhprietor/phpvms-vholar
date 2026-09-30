<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Pilot;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\VmsOpenFileManager\Models\FileFolder;
use Modules\VmsOpenFileManager\Models\FileItem;

class FileDownloadController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $folders = FileFolder::whereNull('parent_id')
            ->where(function ($query) use ($user) {
                $query->where('is_public', true);
            })
            ->orderBy('order')
            ->get();

        return view('vmsopenfilemanager::pilot.downloads.index', compact('folders'));
    }

    public function folder($folderId)
    {
        $folder = FileFolder::with(['parent', 'children', 'files'])->findOrFail($folderId);

        $subfolders = $folder->children()->get();
        $files = $folder->files()->where('is_active', true)->orderBy('created_at', 'desc')->get();

        return view('vmsopenfilemanager::pilot.downloads.folder', compact('folder', 'subfolders', 'files'));
    }

    public function download($fileId)
    {
        $file = FileItem::with('folder')->findOrFail($fileId);
    
        $file->incrementDownloads();
    
        if (Storage::disk('public')->exists($file->path)) {
            // Usar el nombre original con extensión
            $originalName = $file->name . '.' . $file->extension;
            
            return Storage::disk('public')->download($file->path, $originalName);
        }
    
        abort(404);
    }
}