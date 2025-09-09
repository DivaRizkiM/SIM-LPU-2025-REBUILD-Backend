<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    public function getToken()
    {
        // // local
        // $clientId = 'iZ7xi4wV2S6y_Cq0byTDcn9Q46Ua';
        // $clientSecret = '9dqwxTmtsUsfdtyawFdbjBxDpaUa';

        // prod
        $clientId = '4ssTjIkuI9fNa5emFcHQ8fSnCQwa';
        $clientSecret = 'v6viEvfPmcSIvggXcjhzc8Kkc6ka';

        // Kombinasikan Client ID dan Client Secret dalam format yang benar untuk otentikasi HTTP Basic
        $authorization = base64_encode($clientId . ':' . $clientSecret);

        // $response = Http::asForm()->withHeaders([
        //     'Authorization' => 'Basic ' . $authorization,
        // ])->post('https://api.posindonesia.co.id:8245/token', [
        //     'grant_type' => 'client_credentials',
        // ]);

        $response = Http::timeout(0)->asForm()->withHeaders([
            'Authorization' => 'Basic ' . $authorization,
        ])->post('https://api.posindonesia.co.id:8245/token', [
            'grant_type' => 'client_credentials',
        ]);

        // Periksa status respons
        if ($response->successful()) {
            // Ambil body respons dalam format JSON
            $accessToken = $response->json();
            return $accessToken;
        } else {
            // Jika respons tidak berhasil, kembalikan pesan kesalahan
            return $response->json();
        }
    }
    
    public static function generateSignature()
    {
        // dd(Auth::user());
        $relativeUrl = 'https://api.posindonesia.co.id:8245/pso/1.0.0/data/tipe_bisnis';
        $HttpMethod = "GET";
        $timestamp = Carbon::now()->toIso8601String();
        // dd($timestamp);
        $access_token = 'a3b2529b-7147-381c-8835-4de5d98f18b0';
        $secret_key = 'a29taW5mbw==94d47c6213b485df4d50b66526b3a366fed1c0b331ad5664786c5a1bb794f268';
        $RequestBody = "";
        $hash = hash('sha256', $RequestBody);
        $StringToSign = $HttpMethod . ":" . $relativeUrl . ":" . $access_token . ":" . $hash . ":" . $timestamp;
        $auth_signature = hash_hmac('sha256', $StringToSign, $secret_key);

        return [
            'timestamp' => $timestamp,
            'auth_signature' => $auth_signature,
        ];
    }
    public static function makeRequest(Request $request)
    {
        // Ganti dengan Client ID dan Client Secret yang benar
        // $clientId = 'iZ7xi4wV2S6y_Cq0byTDcn9Q46Ua';
        // $clientSecret = '9dqwxTmtsUsfdtyawFdbjBxDpaUa';

        $clientId = '4ssTjIkuI9fNa5emFcHQ8fSnCQwa';
        $clientSecret = 'v6viEvfPmcSIvggXcjhzc8Kkc6ka';

        // Kombinasikan Client ID dan Client Secret dalam format yang benar untuk otentikasi HTTP Basic
        $authorization = base64_encode($clientId . ':' . $clientSecret);

        // $response = Http::asForm()->withHeaders([
        //     'Authorization' => 'Basic ' . $authorization,
        // ])->post('https://api.posindonesia.co.id:8245/token', [
        //     'grant_type' => 'client_credentials',
        // ]);

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Basic ' . $authorization,
        ])->post('https://api.posindonesia.co.id:8245/token', [
            'grant_type' => 'client_credentials',
        ]);
        $accessToken = '';
        // Periksa status respons
        if ($response->successful()) {
            // Ambil body respons dalam format JSON
            $accessToken = $response['access_token'];
        }
        // dd($accessToken);

        // dd($request->access_token);
        $url = 'https://api.posindonesia.co.id:8245';
        // $url = 'https://api.posindonesia.co.id:8245';
        $relativeUrl = '/pso/1.0.0/data/' . $request->end_point;
        $HttpMethod = "GET";
        $timestamp = Carbon::now()->toIso8601String();
        $access_token = $accessToken;
        $secret_key = 'a29taW5mbw==94d47c6213b485df4d50b66526b3a366fed1c0b331ad5664786c5a1bb794f268';
        $RequestBody = "";
        $hash = hash('sha256', $RequestBody);
        $StringToSign = $HttpMethod . ":" . $relativeUrl . ":" . $access_token . ":" . $hash . ":" . $timestamp;
        $auth_signature = hash_hmac('sha256', $StringToSign, $secret_key);
        // dd($auth_signature);

        $client = new Client();

        try {
            $response = $client->request('GET', $url . $relativeUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    //'Origin' => 'api.jmc.co.id',
                    'X-POS-Key' => 'a29taW5mbw==dEpUaDhDRXg3dw==',
                    'X-POS-Timestamp' => $timestamp,
                    // 'X-POS-Signature' => hash_hmac('sha256', $StringToSign, $secret_key),
                    'X-POS-Signature' => $auth_signature,
                ],
            ]);
            $responseData = json_decode($response->getBody()->getContents(), true);
            // dd($responseData);
            $responseData['access_token'] = $accessToken;
            // dd($reponseData);
            return $responseData;
        } catch (RequestException $e) {
            Log::error("error making request:" . $e);
            // Tangani kesalahan permintaan
            if ($e->hasResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }
}
