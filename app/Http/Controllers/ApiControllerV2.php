<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ApiControllerV2 extends Controller
{
    private const BASE_URL = 'https://api.posindonesia.co.id:8245';
    private const CLIENT_ID = '4ssTjIkuI9fNa5emFcHQ8fSnCQwa';
    private const CLIENT_SECRET = 'v6viEvfPmcSIvggXcjhzc8Kkc6ka';
    private const API_KEY = 'a29taW5mbw==dEpUaDhDRXg3dw==';
    private const SECRET_KEY = 'a29taW5mbw==94d47c6213b485df4d50b66526b3a366fed1c0b331ad5664786c5a1bb794f268';

    public function getProfileRegional(Request $request)
    {
        $request->merge(['end_point' => 'produksi?&kd_bisnis=03&nopend=61151&tahun=2025&triwulan=1']);
        // $request->merge(['end_point' => 'lampiran_biaya?id_biaya=22694932']);
        $this->makeRequest($request);
        // return response()->json(['id' => 22135799]);
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
