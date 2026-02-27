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
        // $tahunbulan = $request->input('tahunbulan', '202503');
        $endpoint = "biaya?kategoribiaya=2&nopend=20987&tahun=2025&triwulan=4";

        // Untuk contoh, ambil endpoint pertama
        $request->merge(['end_point' => $endpoint]);

        return $this->makeRequest($request);
    }

    public function testProduksiPrognosa(Request $request)
    {
        // Ambil parameter dari request
        $nopend_kpc = $request->input('nopend_kpc', ''); // nomor dirian KPC
        $triwulan = $request->input('triwulan', '1');
        $tahun = $request->input('tahun', date('Y'));
        $tipe_bisnis = $request->input('tipe_bisnis', '');

        // Build endpoint dengan parameter
        $params = [];
        if ($nopend_kpc) $params[] = "nopend_kpc={$nopend_kpc}";
        if ($triwulan) $params[] = "triwulan={$triwulan}";
        if ($tahun) $params[] = "tahun={$tahun}";
        if ($tipe_bisnis) $params[] = "tipe_bisnis={$tipe_bisnis}";

        $endpoint = "produksi_prognosa";
        if (!empty($params)) {
            $endpoint .= "?" . implode("&", $params);
        }

        $request->merge(['end_point' => $endpoint]);

        return $this->makeRequest($request);
    }

    public function testBiayaPrognosa(Request $request)
    {
        // Ambil parameter dari request
        $nopend_kpc = $request->input('nopend_kpc', ''); // nomor dirian KPC
        $triwulan = $request->input('triwulan', '1');
        $tahun = $request->input('tahun', date('Y'));

        // Build endpoint dengan parameter
        $params = [];
        if ($nopend_kpc) $params[] = "nopend_kpc={$nopend_kpc}";
        if ($triwulan) $params[] = "triwulan={$triwulan}";
        if ($tahun) $params[] = "tahun={$tahun}";

        $endpoint = "biaya_prognosa";
        if (!empty($params)) {
            $endpoint .= "?" . implode("&", $params);
        }

        $request->merge(['end_point' => $endpoint]);

        return $this->makeRequest($request);
    }

    public function testBiayaRutin(Request $request)
    {
        // Ambil parameter dari request
        $kategoribiaya = $request->input('kategoribiaya', '1'); // 1=Biaya Operasi, 2=Biaya Gaji
        $nopend = $request->input('nopend', ''); // Nomor Dirian KPC
        $tahun = $request->input('tahun', date('Y'));
        $triwulan = $request->input('triwulan', '1');

        // Build endpoint dengan parameter
        $params = [];
        if ($kategoribiaya) $params[] = "kategoribiaya={$kategoribiaya}";
        if ($nopend) $params[] = "nopend={$nopend}";
        if ($tahun) $params[] = "tahun={$tahun}";
        if ($triwulan) $params[] = "triwulan={$triwulan}";

        $endpoint = "biaya";
        if (!empty($params)) {
            $endpoint .= "?" . implode("&", $params);
        }

        $request->merge(['end_point' => $endpoint]);

        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan response mentah dari API produksi nasional
    public function getProduksiNasionalRaw(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        $tahunbulan = $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $endpoint = "produksi_nasional?tahunbulan=" . $tahunbulan;
        $request->merge(['end_point' => $endpoint]);
        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan response mentah dari API LTK POS
    public function getLtkPosRaw(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        $tahunbulan = $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $endpoint = "mtd_ltk?tahunbulan=" . $tahunbulan;
        $request->merge(['end_point' => $endpoint]);
        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan response mentah dari API produksi detail POS
    public function getProduksiDetailPosRaw(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        $tahunbulan = $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $endpoint = "produksi_detail?tahunbulan=" . $tahunbulan;
        $request->merge(['end_point' => $endpoint]);
        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan response mentah dari API POS tentang produksi
    public function getProduksiPosRaw(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        $tahunbulan = $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $endpoint = "produksi?tahunbulan=" . $tahunbulan;
        $request->merge(['end_point' => $endpoint]);
        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan response mentah dari API POS tentang produksi (dengan parameter bisnis, kantor, tahun, triwulan)
    public function getProduksiPosByParamRaw(Request $request)
    {
        $kd_bisnis = $request->input('kd_bisnis');
        $nopend = $request->input('nopend');
        $tahun = $request->input('tahun');
        $triwulan = $request->input('triwulan');

        if (!$kd_bisnis || !$nopend || !$tahun || !$triwulan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter kd_bisnis, nopend, tahun, triwulan wajib diisi.'
            ], 400);
        }

        $endpoint = "produksi?kd_bisnis={$kd_bisnis}&nopend={$nopend}&tahun={$tahun}&triwulan={$triwulan}";
        $request->merge(['end_point' => $endpoint]);
        return $this->makeRequest($request);
    }

    // Endpoint untuk menampilkan data produksi POS per bulan, otomatis ambil semua nopend dari database
    public function getProduksiPosByMonthRaw(Request $request)
    {
        $kd_bisnis = $request->input('kd_bisnis');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $triwulan = $request->input('triwulan');

        if (!$kd_bisnis || !$tahun || !$bulan || !$triwulan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter kd_bisnis, tahun, bulan, triwulan wajib diisi.'
            ], 400);
        }

        // Ambil semua nomor_dirian dari database
        $dirian_list = \App\Models\Kpc::pluck('nomor_dirian')->toArray();
        $results = [];
        foreach ($dirian_list as $nomor_dirian) {
            if (!$nomor_dirian) continue;
            $endpoint = "produksi?kd_bisnis={$kd_bisnis}&nopend={$nomor_dirian}&tahun={$tahun}&triwulan={$triwulan}";
            $req = clone $request;
            $req->merge(['end_point' => $endpoint]);
            $response = $this->makeRequest($req);
            $data = json_decode($response->getContent(), true);
            $results[$nomor_dirian] = $data;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data gabungan produksi POS per bulan',
            'data' => $results
        ]);
    }

    // Endpoint untuk menampilkan data produksi POS per bulan untuk banyak KCP (nopend)
    public function getProduksiPosMultiKcpRaw(Request $request)
    {
        $kd_bisnis = $request->input('kd_bisnis');
        $nopend_list = $request->input('nopend_list'); // array
        $tahun = $request->input('tahun');
        $triwulan = $request->input('triwulan');

        if (!$kd_bisnis || !$nopend_list || !$tahun || !$triwulan || !is_array($nopend_list)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter kd_bisnis, nopend_list (array), tahun, triwulan wajib diisi.'
            ], 400);
        }

        $results = [];
        foreach ($nopend_list as $nopend) {
            $endpoint = "produksi?kd_bisnis={$kd_bisnis}&nopend={$nopend}&tahun={$tahun}&triwulan={$triwulan}";
            $req = clone $request;
            $req->merge(['end_point' => $endpoint]);
            $response = $this->makeRequest($req);
            // Ambil data json dari response
            $data = json_decode($response->getContent(), true);
            $results[$nopend] = $data;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data gabungan produksi POS per KCP',
            'data' => $results
        ]);
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
