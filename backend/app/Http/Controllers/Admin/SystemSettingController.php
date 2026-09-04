<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function __construct(private SystemSettingsService $settings) {}

    public function index()
    {
        return response()->json(['categories' => $this->settings->categories()]);
    }

    public function update(Request $request, string $key)
    {
        $request->validate(['value' => 'required', 'updated_by' => 'prohibited', 'type' => 'prohibited', 'category' => 'prohibited', 'default' => 'prohibited']);

        return response()->json(['data' => $this->settings->update($key, $request->input('value'), $request->user())]);
    }

    public function reset(string $key)
    {
        return response()->json(['data' => $this->settings->reset($key)]);
    }
}
