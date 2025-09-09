<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function generateKey()
    {
        $key = Str::random(32); // Generate random key
        ApiKey::create(['key' => $key]);
        return response()->json(['key' => $key]);
    }

    public function index()
    {
        return ApiKey::all();
    }

    public function delete($id)
    {
        $key = ApiKey::findOrFail($id);
        $key->delete();
        return response()->json(['message' => 'API key deleted successfully']);
    }

// Tambahan fungsi untuk menonaktifkan API key
    public function deactivate($id)
    {
        $key = ApiKey::findOrFail($id);
        $key->update(['active' => false]);
        return response()->json(['message' => 'API key deactivated successfully']);
    }
}
