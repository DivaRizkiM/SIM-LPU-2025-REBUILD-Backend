<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Mendapatkan API key dari header 'api_key'
        $apiKey = $request->header('api-key');

        // Jika API key tidak tersedia, maka kembalikan response error
        if (!$apiKey) {
            return response()->json(['message' => 'API key tidak ditemukan'], 401);
        }

        // Cek apakah API key terdapat dalam database dan aktif
        $apiKeyRecord = ApiKey::where('key', $apiKey)->where('active', true)->first();

        // Jika API key tidak ditemukan atau tidak aktif, kembalikan response error
        if (!$apiKeyRecord) {
            return response()->json(['message' => 'API key tidak valid'], 401);
        }

        // Jika API key ditemukan dan aktif, lanjutkan permintaan
        return $next($request);
    }
}
