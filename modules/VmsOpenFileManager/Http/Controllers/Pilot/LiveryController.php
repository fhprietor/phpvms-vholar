<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Pilot;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\VmsOpenFileManager\Models\Livery;
use Modules\VmsOpenFileManager\Models\Simulator;
use App\Models\Subfleet;

class LiveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display list of simulators
     */
public function index()
{
    $simulators = Simulator::where('is_active', true)
        ->whereHas('liveries', function ($query) {
            $query->where('is_active', true);
        })
        ->withCount(['liveries' => function ($query) {
            $query->where('is_active', true);
        }])
        ->orderBy('order')
        ->get();

    return view('vmsopenfilemanager::pilot.liveries.index', compact('simulators'));
}

    /**
     * Display subfleets for a specific simulator
     */
    public function bySimulator($simulatorSlug)
    {
        $simulator = Simulator::where('slug', $simulatorSlug)
            ->where('is_active', true)
            ->firstOrFail();

        // Get subfleets that have active liveries for this simulator
        $subfleetIds = Livery::where('simulator_id', $simulator->id)
            ->where('is_active', true)
            ->pluck('subfleet_id')
            ->unique();

        $subfleets = Subfleet::with('aircraft')
            ->whereIn('id', $subfleetIds)
            ->orderBy('name')
            ->get();

        return view('vmsopenfilemanager::pilot.liveries.subfleets', compact('simulator', 'subfleets'));
    }

    /**
     * Display liveries for a specific subfleet and simulator
     */
    public function bySubfleet($simulatorSlug, $subfleetId)
    {
        $simulator = Simulator::where('slug', $simulatorSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $subfleet = Subfleet::with('aircraft')->findOrFail($subfleetId);

        $liveries = Livery::with(['manufacturer', 'aircraft'])
            ->where('simulator_id', $simulator->id)
            ->where('subfleet_id', $subfleetId)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return view('vmsopenfilemanager::pilot.liveries.liveries', compact('simulator', 'subfleet', 'liveries'));
    }

    /**
     * Download a livery
     */
    public function download($id)
    {
        $livery = Livery::with(['subfleet', 'manufacturer'])
            ->findOrFail($id);

        // Increment download counter
        $livery->incrementDownloads();

        // Check if it's an external URL
        if ($livery->file_type === 'external') {
            return redirect()->away($livery->file_path);
        }

        // Local file download
        if (Storage::disk('public')->exists($livery->file_path)) {
            // Generate filename: liveryname_manufacturer_subfleet.zip
            $subfleetName = $livery->subfleet ? $livery->subfleet->name : 'unknown';
            $manufacturerName = $livery->manufacturer ? $livery->manufacturer->name : 'unknown';
            $filename = $livery->name . '_' . $manufacturerName . '_' . $subfleetName . '.zip';
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
            
            return Storage::disk('public')->download($livery->file_path, $filename);
        }

        abort(404, 'File not found');
    }

    /**
     * Search liveries
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        $liveries = Livery::with(['simulator', 'subfleet', 'manufacturer'])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->orderBy('name')
            ->paginate(20);

        if ($request->expectsJson()) {
            return response()->json($liveries);
        }

        return view('vmsopenfilemanager::pilot.liveries.search', compact('liveries', 'query'));
    }

    /**
     * Get livery details for API
     */
    public function info($id)
    {
        $livery = Livery::with(['simulator', 'subfleet', 'manufacturer', 'aircraft'])
            ->findOrFail($id);

        return response()->json([
            'id' => $livery->id,
            'name' => $livery->name,
            'description' => $livery->description,
            'simulator' => $livery->simulator ? $livery->simulator->name : null,
            'subfleet' => $livery->subfleet ? $livery->subfleet->name : null,
            'manufacturer' => $livery->manufacturer ? $livery->manufacturer->name : null,
            'aircraft' => $livery->aircraft ? $livery->aircraft->registration : null,
            'thumbnail' => $livery->thumbnail_url,
            'download_url' => route('vmsopenfilemanager.liveries.download', $livery->id),
            'downloads' => $livery->downloads,
            'is_external' => $livery->file_type === 'external',
            'file_size' => $livery->file_size,
        ]);
    }

    /**
     * Get random featured liveries
     */
    public function featured($limit = 6)
    {
        $liveries = Livery::with(['simulator', 'subfleet', 'manufacturer'])
            ->where('is_active', true)
            ->whereNotNull('thumbnail_path')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return response()->json($liveries);
    }
}