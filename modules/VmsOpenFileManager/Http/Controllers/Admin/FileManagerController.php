<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\VmsOpenFileManager\Models\FileFolder;
use Modules\VmsOpenFileManager\Models\FileItem;
use Modules\VmsOpenFileManager\Services\FileManagerService;

class FileManagerController extends Controller
{
    protected $fileManagerService;

    public function __construct(FileManagerService $fileManagerService)
    {
        $this->fileManagerService = $fileManagerService;
    }

    public function index(Request $request)
    {
        $folderId = $request->get('folder');
        $currentFolder = null;
        $folders = collect();
        $files = collect();

        if ($folderId) {
            $currentFolder = FileFolder::with(['parent', 'children', 'files'])->findOrFail($folderId);
            $folders = $currentFolder->children()->orderBy('order')->get();
            $files = $currentFolder->files()->where('is_active', true)->orderBy('created_at', 'desc')->get();
        } else {
            $folders = FileFolder::whereNull('parent_id')->orderBy('order')->get();
        }

        return view('vmsopenfilemanager::admin.filemanager.index', compact('currentFolder', 'folders', 'files'));
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:vmsopen_folders,id',
            'is_public' => 'boolean',
        ]);

        try {
            $folder = $this->fileManagerService->createFolder($request->all());
            return response()->json(['success' => true, 'message' => 'Folder created', 'folder' => $folder]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400',
            'folder_id' => 'required|exists:vmsopen_folders,id',
            'description' => 'nullable|string|max:30',
        ]);
    
        try {
            $file = $this->fileManagerService->uploadFile(
                $request->file('file'),
                $request->folder_id,
                $request->description
            );
            return response()->json(['success' => true, 'message' => 'File uploaded', 'file' => $file]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteFile($id)
    {
        try {
            $file = FileItem::findOrFail($id);
            $this->fileManagerService->deleteFile($file);
            return response()->json(['success' => true, 'message' => 'File deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteFolder($id)
    {
        try {
            $folder = FileFolder::findOrFail($id);
            $this->fileManagerService->deleteFolder($folder);
            return response()->json(['success' => true, 'message' => 'Folder deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}