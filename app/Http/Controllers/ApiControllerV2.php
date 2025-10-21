<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Kpc;
use Illuminate\Http\Request;
use App\Models\KategoriPendapatan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ApiControllerV2 extends Controller
{
    private const BASE_URL = 'https://api.posindonesia.co.id:8245';
    private const CLIENT_ID = '4ssTjIkuI9fNa5emFcHQ8fSnCQwa';
    private const CLIENT_SECRET = 'v6viEvfPmcSIvggXcjhzc8Kkc6ka';
    private const API_KEY = 'a29taW5mbw==dEpUaDhDRXg3dw==';
    private const SECRET_KEY = 'a29taW5mbw==94d47c6213b485df4d50b66526b3a366fed1c0b331ad5664786c5a1bb794f268';

    public function getProfileRegional(Request $request)
    {
        $endpoint = "profil_kpc?nopend=78555B1";

        // Untuk contoh, ambil endpoint pertama
        $request->merge(['end_point' => $endpoint]);

        return $this->makeRequest($request);
    }


    public function makeRequest(Request $request)
    {
        $validated = $request->validate([
            'end_point' => 'required|string',
        ]);

        try {
            $accessToken = $this->getAccessToken();

            $httpMethod = "GET";
            $relativeUrl = "/pso/1.0.0/data/" . $validated['end_point'];
            $timestamp = $this->getTimestamp();
            $requestBody = "";

            $signature = $this->generateSignature($httpMethod, $relativeUrl, $accessToken, $timestamp, $requestBody);

            $response = Http::withHeaders([
                'Authorization'   => 'Bearer ' . $accessToken,
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
                'X-POS-Key'       => self::API_KEY,
                'X-POS-Signature' => $signature,
                'X-POS-Timestamp' => $timestamp,
            ])->get(self::BASE_URL . $relativeUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                Log::error('API request failed for endpoint: ' . $validated['end_point'], [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json($response->json(), $response->status());
            }
        } catch (Exception $e) {
            Log::error('Exception in ApiControllerV2: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getAccessToken(): string
    {
        $authorization = base64_encode(self::CLIENT_ID . ':' . self::CLIENT_SECRET);

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Basic ' . $authorization,
        ])->post(self::BASE_URL . '/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful() && isset($response->json()['access_token'])) {
            return $response->json()['access_token'];
        } else {
            throw new Exception("Gagal mendapatkan access token. Response: " . $response->body());
        }
    }

    private function generateSignature(string $httpMethod, string $relativeUrl, string $accessToken, string $timestamp, string $requestBody): string
    {
        $hash = hash('sha256', $requestBody);
        $stringToSign = $httpMethod . ":" . $relativeUrl . ":" . $accessToken . ":" . $hash . ":" . $timestamp;
        return hash_hmac('sha256', $stringToSign, self::SECRET_KEY);
    }

    private function getTimestamp(): string
    {
        return Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }
}
