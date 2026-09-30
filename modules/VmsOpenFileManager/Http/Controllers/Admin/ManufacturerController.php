<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\VmsOpenFileManager\Models\Manufacturer;

class ManufacturerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index()
    {
        $manufacturers = Manufacturer::withCount('liveries')->orderBy('order')->get();
        return view('vmsopenfilemanager::admin.manufacturers.index', compact('manufacturers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255|unique:vmsopen_manufacturers,name',
            'order' => 'nullable|integer|min:0',
        ]);

        Manufacturer::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'order'     => $request->order ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.manufacturers.index')
            ->with('success', "Manufacturer '{$request->name}' created.");
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $request->validate([
            'name'  => 'required|string|max:255|unique:vmsopen_manufacturers,name,' . $manufacturer->id,
            'order' => 'nullable|integer|min:0',
        ]);

        $manufacturer->update([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'order'     => $request->order ?? 0,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()->route('admin.manufacturers.index')
            ->with('success', "Manufacturer '{$manufacturer->name}' updated.");
    }

    public function destroy(Manufacturer $manufacturer)
    {
        if ($manufacturer->liveries()->count() > 0) {
            return redirect()->route('admin.manufacturers.index')
                ->with('error', "Cannot delete '{$manufacturer->name}': it has {$manufacturer->liveries()->count()} liveries assigned.");
        }

        $manufacturer->delete();

        return redirect()->route('admin.manufacturers.index')
            ->with('success', 'Manufacturer deleted.');
    }
}
