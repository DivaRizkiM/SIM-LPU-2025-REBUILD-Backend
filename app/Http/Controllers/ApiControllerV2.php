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
        $bulan = $request->input('bulan', '12');
        $tahun = $request->input('tahun', '2022');
        $nopend = $request->input('nopend', '');
        $kd_bisnis = $request->input('kd_bisnis', '');
        $endpoint = "produksi_bulanan?bulan=$bulan&tahun=$tahun&nopend=$nopend&kd_bisnis=$kd_bisnis";

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

            // ✅ PERBAIKAN: Decode API_KEY dengan benar
            // Format: 'a29taW5mbw==dEpUaDhDRXg3dw=='
            // Ini sepertinya 2 bagian base64 yang digabung dengan '=='
            
            // Cara 1: Decode setiap bagian terpisah
            $apiKeyRaw = str_replace('==', '==|', self::API_KEY); // Tambah delimiter
            $apiKeyParts = explode('|', $apiKeyRaw);
            $decodedApiKey = '';
            
            foreach ($apiKeyParts as $part) {
                $cleanPart = str_replace('==', '', $part);
                if (!empty($cleanPart)) {
                    $decodedApiKey .= base64_decode($cleanPart);
                }
            }

            // Debug: Lihat hasil decode
            Log::debug('API Key Decoded', [
                'original' => self::API_KEY,
                'decoded' => $decodedApiKey,
                'decoded_length' => strlen($decodedApiKey)
            ]);

            $response = Http::withHeaders([
                'Authorization'   => 'Bearer ' . $accessToken,
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
                'X-POS-Key'       => $decodedApiKey,
                'X-POS-Signature' => $signature,
                'X-POS-Timestamp' => $timestamp,
            ])->get(self::BASE_URL . $relativeUrl);

            // ✅ Tambahkan logging untuk debugging
            Log::debug('API Request', [
                'url' => self::BASE_URL . $relativeUrl,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

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
        // ✅ PERBAIKAN: Decode SECRET_KEY dengan cara yang sama seperti API_KEY
        $secretKeyRaw = str_replace('==', '==|', self::SECRET_KEY);
        $secretKeyParts = explode('|', $secretKeyRaw);
        $decodedSecret = '';
        
        foreach ($secretKeyParts as $part) {
            $cleanPart = str_replace('==', '', $part);
            if (!empty($cleanPart)) {
                $decodedSecret .= base64_decode($cleanPart);
            }
        }
        
        $hash = hash('sha256', $requestBody);
        $stringToSign = $httpMethod . ":" . $relativeUrl . ":" . $accessToken . ":" . $hash . ":" . $timestamp;
        
        $signature = hash_hmac('sha256', $stringToSign, $decodedSecret);
        
        // Debug logging
        Log::debug('Signature Generation', [
            'http_method' => $httpMethod,
            'relative_url' => $relativeUrl,
            'timestamp' => $timestamp,
            'body_hash' => $hash,
            'secret_decoded' => $decodedSecret,
            'signature' => $signature
        ]);
        
        return $signature;
    }

    private function getTimestamp(): string
    {
        return Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }
}
