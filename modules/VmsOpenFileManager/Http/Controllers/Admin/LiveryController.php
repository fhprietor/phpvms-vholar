<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\VmsOpenFileManager\Models\Livery;
use Modules\VmsOpenFileManager\Models\Simulator;
use Modules\VmsOpenFileManager\Models\Manufacturer;
use App\Models\Subfleet;
use App\Models\Aircraft;

class LiveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    /**
     * Display list of all liveries
     */
    public function index()
    {
        $liveries = Livery::with(['subfleet', 'simulator', 'manufacturer', 'aircraft'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $subfleets = Subfleet::with('aircraft')->orderBy('name')->get();
        $simulators = Simulator::where('is_active', true)->orderBy('order')->get();
        $manufacturers = Manufacturer::where('is_active', true)->orderBy('order')->get();
        $aircrafts = Aircraft::orderBy('registration')->get();

        return view('vmsopenfilemanager::admin.liveries.index', compact(
            'liveries', 'subfleets', 'simulators', 'manufacturers', 'aircrafts'
        ));
    }

    /**
     * Upload a new livery
     */
    public function upload(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subfleet_id' => 'required|exists:subfleets,id',
            'simulator_id' => 'required|exists:vmsopen_simulators,id',
            'manufacturer_id' => 'required|exists:vmsopen_manufacturers,id',
            'aircraft_id' => 'nullable|exists:aircraft,id',
            'file_type' => 'required|in:local,external',
            'file' => 'required_if:file_type,local|file|max:204800|mimes:zip',
            'external_url' => 'required_if:file_type,external|url',
            'thumbnail' => 'nullable|image|max:5120',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        try {
            // Generate slug
            $slug = Str::slug($request->name . '-' . time());

            // Handle thumbnail
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('liveries/thumbnails', 'public');
                $this->resizeThumbnail($thumbnailPath);
            }

            // Handle file
            $filePath = null;
            $fileSize = null;
            if ($request->file_type === 'local') {
                $file = $request->file('file');
                $filename = $slug . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('liveries/files', $filename, 'public');
                $fileSize = $this->formatBytes($file->getSize());
            } else {
                $filePath = $request->external_url;
            }
            // Create livery
            $livery = Livery::create([
                'name' => $request->name,
                'slug' => $slug,
                'subfleet_id' => $request->subfleet_id,
                'simulator_id' => $request->simulator_id,
                'manufacturer_id' => $request->manufacturer_id,
                'aircraft_id' => $request->aircraft_id,
                'thumbnail_path' => $thumbnailPath,
                'file_type' => $request->file_type,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);
           return response()->json([
                'success' => true,
                'message' => "Livery '{$livery->name}' uploaded successfully."
            ]); 
/**
            return redirect()->route('admin.liveries.index')
                ->with('success', "Livery '{$livery->name}' uploaded successfully.");
*/
        } catch (\Exception $e) {
            /*
            return redirect()->back()
                ->with('error', 'Error uploading livery: ' . $e->getMessage())
                ->withInput();
                */
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a livery
     */
    public function delete($id)
    {
        $livery = Livery::findOrFail($id);

        try {
            // Delete thumbnail if exists
            if ($livery->thumbnail_path && Storage::disk('public')->exists($livery->thumbnail_path)) {
                Storage::disk('public')->delete($livery->thumbnail_path);
            }

            // Delete local file if exists
            if ($livery->file_type === 'local' && Storage::disk('public')->exists($livery->file_path)) {
                Storage::disk('public')->delete($livery->file_path);
            }

            $livery->delete();

            return response()->json([
                'success' => true,
                'message' => 'Livery deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle livery active status
     */
    public function toggleActive($id)
    {
        $livery = Livery::findOrFail($id);
        $livery->is_active = !$livery->is_active;
        $livery->save();

        return response()->json([
            'success' => true,
            'is_active' => $livery->is_active,
            'message' => $livery->is_active ? 'Livery activated' : 'Livery deactivated'
        ]);
    }

    /**
     * Update livery order
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:vmsopen_liveries,id',
            'orders.*.order' => 'required|integer',
        ]);

        foreach ($request->orders as $item) {
            Livery::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get livery details for edit
     */
    public function edit($id)
    {
        $livery = Livery::with(['subfleet', 'simulator', 'manufacturer', 'aircraft'])->findOrFail($id);
        
        $subfleets = Subfleet::with('aircraft')->orderBy('name')->get();
        $simulators = Simulator::where('is_active', true)->orderBy('order')->get();
        $manufacturers = Manufacturer::where('is_active', true)->orderBy('order')->get();
        $aircrafts = Aircraft::orderBy('registration')->get();

        return view('vmsopenfilemanager::admin.liveries.edit', compact(
            'livery', 'subfleets', 'simulators', 'manufacturers', 'aircrafts'
        ));
    }

    /**
     * Update livery
     */
    public function update(Request $request, $id)
    {
        $livery = Livery::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'subfleet_id' => 'required|exists:subfleets,id',
            'simulator_id' => 'required|exists:vmsopen_simulators,id',
            'manufacturer_id' => 'required|exists:vmsopen_manufacturers,id',
            'aircraft_id' => 'nullable|exists:aircraft,id',
            'file_type' => 'required|in:local,external',
            'file' => 'nullable|file|max:204800|mimes:zip',
            'external_url' => 'nullable|url',
            'thumbnail' => 'nullable|image|max:5120',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        try {
            // Handle new thumbnail if uploaded
            if ($request->hasFile('thumbnail')) {
                if ($livery->thumbnail_path && Storage::disk('public')->exists($livery->thumbnail_path)) {
                    Storage::disk('public')->delete($livery->thumbnail_path);
                }
                $thumbnailPath = $request->file('thumbnail')->store('liveries/thumbnails', 'public');
                $this->resizeThumbnail($thumbnailPath);
                $livery->thumbnail_path = $thumbnailPath;
            }

            // Handle file based on type
            if ($request->file_type === 'local') {
                if ($request->hasFile('file')) {
                    // Delete old file if exists
                    if ($livery->file_type === 'local' && $livery->file_path && Storage::disk('public')->exists($livery->file_path)) {
                        Storage::disk('public')->delete($livery->file_path);
                    }
                    $file = $request->file('file');
                    $filename = $livery->slug . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('liveries/files', $filename, 'public');
                    $livery->file_path = $filePath;
                    $livery->file_size = $this->formatBytes($file->getSize());
                }
                $livery->file_type = 'local';
            } else {
                // External URL
                if ($request->external_url) {
                    // Delete old local file if switching from local to external
                    if ($livery->file_type === 'local' && $livery->file_path && Storage::disk('public')->exists($livery->file_path)) {
                        Storage::disk('public')->delete($livery->file_path);
                    }
                    $livery->file_path = $request->external_url;
                    $livery->file_size = null;
                }
                $livery->file_type = 'external';
            }

            $livery->name = $request->name;
            $livery->subfleet_id = $request->subfleet_id;
            $livery->simulator_id = $request->simulator_id;
            $livery->manufacturer_id = $request->manufacturer_id;
            $livery->aircraft_id = $request->aircraft_id;
            $livery->description = $request->description;
            $livery->is_active = $request->is_active ?? false;
            $livery->save();

            return redirect()->route('admin.liveries.index')
                ->with('success', "Livery '{$livery->name}' updated successfully.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error updating livery: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Resize thumbnail to 300px width
     */
    private function resizeThumbnail($path)
    {
        $fullPath = Storage::disk('public')->path($path);
        
        if (file_exists($fullPath) && extension_loaded('gd')) {
            try {
                $image = \Intervention\Image\Facades\Image::make($fullPath);
                $image->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $image->save($fullPath);
            } catch (\Exception $e) {
                \Log::error('Thumbnail resize failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Format bytes to human readable
     */
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