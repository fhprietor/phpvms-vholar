<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $config = config('vmsopenfilemanager');
        return view('vmsopenfilemanager::admin.settings.index', compact('config'));
    }

    public function update(Request $request)
    {
        // TODO: Implement settings update
        return redirect()->back()->with('success', 'Settings updated');
    }
}