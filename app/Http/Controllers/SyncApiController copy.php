<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSyncBiayaJob;
use App\Models\BiayaAtribusi;
use App\Models\BiayaAtribusiDetail;
use App\Models\JenisBisnis;
use App\Models\KategoriBiaya;
use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\Npp;
use App\Models\PetugasKPC;
use App\Models\Produksi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Regional;
use App\Models\RekeningBiaya;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Response;

class SyncApiController extends Controller
{
    public function syncRegional()
    {
        try {

            $endpoint = 'profil_regional';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataRegional = $response['data'];
            if (!$dataRegional) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRegional as $data) {

                $regional = Regional::find($data['id_regional']);

                if ($regional) {
                    $regional->update([
                        'nama' => $data['nama_regional'],

                    ]);
                } else {

                    Regional::create([
                        'id' => $data['id_regional'],
                        'kode' => $data['kode_regional'],
                        'nama' => $data['nama_regional'],

                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi regional berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKategoriBiaya()
    {
        try {

            $endpoint = 'kategori_biaya';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataKategoriBiaya = $response['data'];
            if (!$dataKategoriBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataKategoriBiaya as $data) {

                $kategoriBiaya = KategoriBiaya::find($data['id']);

                if ($kategoriBiaya) {
                    $kategoriBiaya->update([
                        'nama' => $data['deskripsi'],

                    ]);
                } else {

                    KategoriBiaya::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],

                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi regional berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningBiaya()
    {
        try {

            $endpoint = 'rekening_biaya';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataRekeningBiaya = $response['data'];
            if (!$dataRekeningBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRekeningBiaya as $data) {

                $rekeningBiaya = RekeningBiaya::find($data['id_rekening']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['nama_rekening'],

                    ]);
                } else {

                    RekeningBiaya::create([
                        'id' => $data['id_rekening'],
                        'kode' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],

                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening biaya berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningProduksi()
    {
        try {

            $endpoint = 'rekening_produksi';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataRekeningProduksi = $response['data'];
            if (!$dataRekeningProduksi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRekeningProduksi as $data) {

                $rekeningProduksi = RekeningBiaya::find($data['id_rekening']);

                if ($rekeningProduksi) {
                    $rekeningProduksi->update([

                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],

                    ]);
                } else {

                    RekeningBiaya::create([
                        'id' => $data['id_rekening'],
                        'kode_rekening' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening produksi berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncTipeBisnis()
    {
        try {

            $endpoint = 'tipe_bisnis';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);
            // dd($response);

            $dataTipeBisnis = $response['data'];
            if (!$dataTipeBisnis) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataTipeBisnis as $data) {

                $rekeningBiaya = JenisBisnis::find($data['id']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['deskripsi'],

                    ]);
                } else {

                    JenisBisnis::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],

                    ]);
                }
            }
            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi tipe bisnis berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncPetugasKCP(Request $request)
    {
        try {

            $endpoint = 'petugas_kpc';
            $id_kpc = $request->id_kpc;
            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $url_request = $endpoint . '?id_kpc=' . $id_kpc;
            $request->merge(['end_point' => $url_request]);

            $response = $apiController->makeRequest($request);
            // dd($response);

            $dataPetugasKPC = $response['data'] ?? [];
            if (!$dataPetugasKPC) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataPetugasKPC as $data) {

                $petugasKPC = PetugasKPC::where('id_kpc', $data['id_kpc']);

                if ($petugasKPC) {
                    $petugasKPC->update([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],

                    ]);
                } else {

                    PetugasKPC::create([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],

                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKCU(Request $request)
    {
        try {

            $endpoint = 'profil_kprk';
            $id_kprk = $request->id_kprk;
            // Membuat instance dari ApiController
            $apiController = new ApiController();

            $url_request = $endpoint . '?id_kprk=' . $id_kprk;
            $request->merge(['end_point' => $url_request]);

            $response = $apiController->makeRequest($request);

            $dataKCU = $response['data'] ?? [];
            if (!$dataKCU) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataKCU as $data) {

                $petugasKCU = Kprk::find($data['id_kprk']);

                if ($petugasKCU) {
                    $petugasKCU->update([
                        'id_regional' => $data['regional'],
                        'nama' => $data['nama_kprk'],
                        'id_provinsi' => $data['provinsi'],
                        'id_kabupaten_kota' => $data['kab_kota'],
                        'id_kecamatan' => $data['kecamatan'],
                        'id_kelurahan' => $data['kelurahan'],
                        'jumlah_kpc_lpu' => $data['jumlah_kpc_lpu'],
                        'jumlah_kpc_lpk' => $data['jumlah_kpc_lpk'],
                        'tgl_sinkronisasi' => now(),

                    ]);
                } else {

                    Kprk::create([
                        'id' => $data['id_kprk'],
                        'id_regional' => $data['regional'],
                        'nama' => $data['nama_kprk'],
                        'id_provinsi' => $data['provinsi'],
                        'id_kabupaten_kota' => $data['kab_kota'],
                        'id_kecamatan' => $data['kecamatan'],
                        'id_kelurahan' => $data['kelurahan'],
                        'jumlah_kpc_lpu' => $data['jumlah_kpc_lpu'],
                        'jumlah_kpc_lpk' => $data['jumlah_kpc_lpk'],
                        'tgl_sinkronisasi' => now(),

                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKPC(Request $request)
    {
        try {

            $endpoint = 'daftar_kpc';
            // $id_kpc = $request->id_kprk;
            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $request->merge(['end_point' => $endpoint]);
            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataKCP = $response['data'] ?? [];
            if (!$dataKCP) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            foreach ($dataKCP as $data) {

                $kcp = Kpc::find($data['nopend']);
                if (!$kcp) {
                    Kpc::create([
                        'id' => $data['nopend'],
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi KCP berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function syncBiayaAtribusi(Request $request)
    {
        try {

            $endpoint = '';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $kategori_biaya = $request->kategori_biaya;
            $bulan = $request->bulan;
            $tahun = $request->tahun;

            if ($kategori_biaya == 1) {
                $endpoint = 'biaya_upl';
            } elseif ($kategori_biaya == 2) {
                $endpoint = 'biaya_angkutan_pos_setempat';
            } else {
                $endpoint = 'biaya_sopir_tersier';
            }
            $list_kprk = '';
            if (!$id_kprk) {
                $list_kprk = Kprk::where('id_regional', $id_regional)->get();
            } else {
                $list_kprk = Kprk::where('id', $id_kprk)->get();
            }
            // dd($list_kprk);
            // Membuat instance dari ApiController
            foreach ($list_kprk as $kprk) {
                $apiController = new ApiController();

                $url_request = $endpoint . '?bulan=' . $bulan . '&id_kprk=' . $kprk->id . '&tahun=' . $tahun;
                // dd($url_request);
                $request->merge(['end_point' => $url_request]);
                // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
                $response = $apiController->makeRequest($request);

                $dataBiayaAtribusi = $response['data'] ?? [];
                if (!$dataBiayaAtribusi) {
                    continue;
                } else {
                    DB::beginTransaction();
                    foreach ($dataBiayaAtribusi as $data) {
                        // dd($data['id']);

                        $biayaAtribusi = BiayaAtribusi::where('tahun_anggaran', $data['tahun_anggaran'])
                            ->where('triwulan', $data['triwulan'])
                            ->where('id_kprk', $kprk->id)->first();
                        // dd($biayaAtribusi);
                        $biayaAtribusiDetail = '';
                        if ($biayaAtribusi) {
                            $bulan = ltrim($data['bulan'], '0');

                            $biayaAtribusiDetail = BiayaAtribusiDetail::where('id_rekening_biaya', $data['koderekening'])
                                ->where('bulan', $bulan)
                                ->where('id_biaya_atribusi', $biayaAtribusi->id)
                                ->first();

                            // dd($biayaAtribusiDetail);
                            if ($biayaAtribusiDetail) {
                                $biayaAtribusiDetail->update([
                                    'pelaporan' => $data['nominal'],
                                    'keterangan' => $data['keterangan'],
                                    'lampiran' => $data['lampiran'],
                                ]);
                                $biayaAtribusiDetail = BiayaAtribusiDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'))
                                    ->where('id_biaya_atribusi', $biayaAtribusi->id)
                                    ->first();
                                $biayaAtribusi->update([
                                    'total_biaya' => $biayaAtribusiDetail->total_pelaporan,
                                    'id_status' => 7,
                                    'id_status_kprk' => 7,
                                ]);

                            } else {

                                BiayaAtribusiDetail::create([
                                    'id' => $data['id'],
                                    'id_biaya_atribusi' => $biayaAtribusi->id,
                                    'bulan' => $data['bulan'],
                                    'id_rekening_biaya' => $data['koderekening'],
                                    'pelaporan' => $data['nominal'],
                                    'keterangan' => $data['keterangan'],
                                    'lampiran' => $data['lampiran'],
                                ]);
                            }

                        } else {
                            $biayaAtribusiNew = BiayaAtribusi::create([
                                'id' => $data['id_kprk'] . $data['tahun_anggaran'] . $data['triwulan'],
                                'id_regional' => $kprk->id_regional,
                                'id_kprk' => $data['id_kprk'],
                                'triwulan' => $data['triwulan'],
                                'tahun_anggaran' => $data['tahun_anggaran'],
                                'total_biaya' => $data['nominal'],
                                'tgl_singkronisasi' => now(),
                                'id_status' => 7,
                                'id_status_kprk' => 7,
                            ]);

                            BiayaAtribusiDetail::create([
                                'id' => $data['id'],
                                'id_biaya_atribusi' => $biayaAtribusiNew->id,
                                'bulan' => $data['bulan'],
                                'id_rekening_biaya' => $data['koderekening'],
                                'pelaporan' => $data['nominal'], // Ini mungkin perlu disesuaikan
                                'keterangan' => $data['keterangan'],
                                'lampiran' => $data['lampiran'],
                            ]);

                        }

                    }
                    DB::commit();

                }
            }
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi biaya atribusi berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncBiaya(Request $request)
    {
        try {
            $endpoint = 'biaya';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;

            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            // dd($platform);
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,id',
                'tahun' => 'required',
                'triwulan' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }

            $totalItems = $list->count();

            // Dispatch job sebelum pernyataan return
            $job = ProcessSyncBiayaJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent);

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang di proses',
            ], 200);
        } catch (\Exception $e) {
            // Tangani pengecualian di sini jika diperlukan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function syncBiaya2(Request $request)
    {
        try {

            $endpoint = 'biaya';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;

            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }
            // dd($list);
            foreach ($list as $ls) {
                $kategori_biaya = KategoriBiaya::get();

                foreach ($kategori_biaya as $kb) {
                    // dd($kategori_biaya);
                    $apiController = new ApiController();
                    $url_request = $endpoint . '?kategoribiaya=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $tahun . '&triwulan=' . $triwulan;
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    // dd($response);

                    $dataBiayaRutin = $response['data'] ?? [];
                    if (!$dataBiayaRutin) {
                        continue;
                    } else {
                        DB::beginTransaction();
                        foreach ($dataBiayaRutin as $data) {
                            // dd($data['id']);

                            $biayaRutin = VerifikasiBiayaRutin::where('tahun', $data['tahun_anggaran'])
                                ->where('triwulan', $data['triwulan'])
                                ->where('id_kpc', $data['id_kpc'])->first();
                            // dd($biayaRutin);
                            $biayaRutinDetail = '';
                            if ($biayaRutin) {
                                $bulan = $biayaRutin->bulan;

                                $biayaRutinDetail = VerifikasiBiayaRutinDetail::
                                    where('id_rekening_biaya', $data['koderekening'])
                                    ->where('bulan', $bulan)
                                    ->where('kategori_biaya', $kb->nama)
                                    ->where('id_verifikasi_biaya_rutin', $biayaRutin->id)
                                // where('id', $data['id'])
                                    ->first();

                                // dd($biayaAtribusiDetail);
                                if ($biayaRutinDetail) {
                                    $biayaRutinDetail->update([
                                        'pelaporan' => $data['nominal'],
                                        'keterangan' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);
                                    $biayaRutinDetail = VerifikasiBiayaRutinDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'))
                                        ->where('id_verifikasi_biaya_rutin', $biayaRutin->id)
                                        ->first();
                                    $biayaRutin->update([
                                        'total_biaya' => $biayaRutinDetail->total_pelaporan,
                                        'id_status' => 7,
                                        'id_status_kprk' => 7,
                                    ]);

                                } else {

                                    VerifikasiBiayaRutinDetail::create([
                                        // 'id' => $data['id'],
                                        'id_verifikasi_biaya_rutin' => $biayaRutin->id,
                                        'bulan' => $data['bulan'],
                                        'id_rekening_biaya' => $data['koderekening'],
                                        'pelaporan' => $data['nominal'],
                                        'kategori_biaya' => $kb->nama,
                                        'keterangan' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);
                                }

                            } else {
                                $biayaRutinNew = VerifikasiBiayaRutin::create([
                                    'id' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                                    'id_regional' => $ls->id_regional,
                                    'id_kprk' => $data['id_kprk'],
                                    'id_kpc' => $data['id_kpc'],
                                    'tahun' => $data['tahun_anggaran'],
                                    'triwulan' => $data['triwulan'],
                                    'total_biaya' => $data['nominal'],
                                    'tgl_singkronisasi' => now(),
                                    'id_status' => 7,
                                    'id_status_kprk' => 7,
                                    'id_status_kpc' => 7,
                                    'bulan' => $data['bulan'],
                                ]);
                                // DD($biayaRutinNew);

                                VerifikasiBiayaRutinDetail::create([
                                    // 'id' => $data['id'],
                                    'id_verifikasi_biaya_rutin' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                                    'bulan' => $data['bulan'],
                                    'id_rekening_biaya' => $data['koderekening'],
                                    'pelaporan' => $data['nominal'],
                                    'kategori_biaya' => $kb->nama,
                                    'keterangan' => $data['keterangan'],
                                    'lampiran' => $data['lampiran'],
                                ]);
                            }
                        }
                        DB::commit();
                    }
                }
            }
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi biaya berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncBiayaPrognosa(Request $request)
    {
        try {

            $endpoint = 'biaya_prognosa';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;

            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }
            // dd($list);
            foreach ($list as $ls) {
                $kategori_biaya = KategoriBiaya::get();

                foreach ($kategori_biaya as $kb) {
                    // dd($kategori_biaya);
                    $apiController = new ApiController();
                    $url_request = $endpoint . '?kategoribiaya=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $tahun . '&triwulan=' . $triwulan;
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    // dd($response);

                    $dataBiayaRutin = $response['data'] ?? [];
                    if (!$dataBiayaRutin) {
                        continue;
                    } else {
                        DB::beginTransaction();
                        foreach ($dataBiayaRutin as $data) {
                            // dd($data['id']);

                            $biayaRutin = VerifikasiBiayaRutin::where('tahun', $data['tahun_anggaran'])
                                ->where('triwulan', $data['triwulan'])
                                ->where('id_kpc', $data['id_kpc'])->first();
                            // dd($biayaRutin);
                            $biayaRutinDetail = '';
                            if ($biayaRutin) {
                                $bulan = $biayaRutin->bulan;

                                $biayaRutinDetail = VerifikasiBiayaRutinDetail::
                                    where('id_rekening_biaya', $data['koderekening'])
                                    ->where('bulan', $bulan)
                                    ->where('kategori_biaya', $kb->nama)
                                    ->where('id_verifikasi_biaya_rutin', $biayaRutin->id)
                                // where('id', $data['id'])
                                    ->first();

                                // dd($biayaAtribusiDetail);
                                if ($biayaRutinDetail) {
                                    $biayaRutinDetail->update([
                                        'pelaporan_prognosa' => $data['nominal'],
                                        'keterangan_prognosa' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);
                                    $biayaRutinDetail = VerifikasiBiayaRutinDetail::select(DB::raw('SUM(pelaporan_prognosa) as total_pelaporan'))
                                        ->where('id_verifikasi_biaya_rutin', $biayaRutin->id)
                                        ->first();
                                    $biayaRutin->update([
                                        'total_biaya_prognosa' => $biayaRutinDetail->total_pelaporan,
                                    ]);

                                } else {

                                    VerifikasiBiayaRutinDetail::create([
                                        // 'id' => $data['id'],
                                        'id_verifikasi_biaya_rutin' => $biayaRutin->id,
                                        'bulan' => $data['bulan'],
                                        'id_rekening_biaya' => $data['koderekening'],
                                        'pelaporan_prognosa' => $data['nominal'],
                                        'kategori_biaya' => $kb->nama,
                                        'keterangan_prognosa' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);
                                }

                            } else {
                                $biayaRutinNew = VerifikasiBiayaRutin::create([
                                    'id' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                                    'id_regional' => $ls->id_regional,
                                    'id_kprk' => $data['id_kprk'],
                                    'id_kpc' => $data['id_kpc'],
                                    'tahun' => $data['tahun_anggaran'],
                                    'triwulan' => $data['triwulan'],
                                    'total_biaya_prognosa' => $data['nominal'],
                                    'tgl_singkronisasi' => now(),
                                    'id_status' => 7,
                                    'id_status_kprk' => 7,
                                    'id_status_kpc' => 7,
                                    'bulan' => $data['bulan'],
                                ]);

                                VerifikasiBiayaRutinDetail::create([
                                    // 'id' => $data['id'],
                                    'id_verifikasi_biaya_rutin' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                                    'bulan' => $data['bulan'],
                                    'id_rekening_biaya' => $data['koderekening'],
                                    'pelaporan_prognosa' => $data['nominal'],
                                    'kategori_biaya' => $kb->nama,
                                    'keterangan_prognosa' => $data['keterangan'],
                                    'lampiran' => $data['lampiran'],
                                ]);
                            }
                        }
                        DB::commit();
                    }
                }
            }
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi biaya berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function syncProduksi(Request $request)
    {
        try {

            $endpoint = 'produksi';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;
            $tipe_bisnis = $request->tipe_bisnis;

            $list = [];

            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }
            // dd($list);
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                // dd($list);
                foreach ($list as $ls) {
                    $kategori_bisnis = JenisBisnis::get();
                    if ($tipe_bisnis) {
                        $kategori_bisnis = JenisBisnis::where('id', $tipe_bisnis)->get();
                    }

                    foreach ($kategori_bisnis as $kb) {
                        // dd($kategori_biaya);
                        $apiController = new ApiController();
                        $url_request = $endpoint . '?kd_bisnis=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $tahun . '&triwulan=' . $triwulan;
                        $request->merge(['end_point' => $url_request]);
                        $response = $apiController->makeRequest($request);
                        // dd($response);

                        $dataProduksi = $response['data'] ?? [];

                        if (!$dataProduksi) {
                            continue;
                        } else {
                            DB::beginTransaction();
                            foreach ($dataProduksi as $data) {
                                $produksi = Produksi::where('id', trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']))->first();
                                $produksiDetail = '';
                                if ($produksi) {
                                    $bulan = $produksi->bulan;
                                    $produksiDetail = ProduksiDetail::
                                        where('id', $data['id'])
                                        ->first();
                                    if ($produksiDetail) {
                                        $produksiDetail->update([
                                            'id' => $data['id'],
                                            'id_produksi' => $produksi->id,
                                            'nama_bulan' => $data['nama_bulan'],
                                            'kode_bisnis' => $data['kode_bisnis'],
                                            'kode_rekening' => $data['koderekening'],
                                            'nama_rekening' => $data['nama_rekening'],
                                            'rtarif' => $data['rtarif'],
                                            'tpkirim' => $data['tpkirim'],
                                            'pelaporan' => $data['bsu_pso'],
                                            'jenis_produksi' => $data['jenis'],
                                            'kategori_produksi' => $data['kategori_produksi'],
                                            'keterangan' => $data['keterangan'],
                                            'lampiran' => $data['lampiran'],
                                        ]);
                                        $produksiDetail_lpu = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total_lpu'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                                            ->first();
                                        // dd($produksiDetail_lpu);
                                        $produksiDetail_lpk = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total_lpk'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                                            ->first();
                                        $produksiDetail_lbf = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total_lbf'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                                            ->first();

                                        $produksi->update([
                                            'total_lpu' => $produksiDetail_lpu->total_lpu,
                                            'total_lpk' => $produksiDetail_lpk->total_lpk,
                                            'total_lbf' => $produksiDetail_lbf->total_lbf,
                                            'status_regional' => 7,
                                            'status_kprk' => 7,
                                        ]);

                                    } else {
                                        ProduksiDetail::create([
                                            'id' => $data['id'],
                                            'id_produksi' => $produksi->id,
                                            'nama_bulan' => $data['nama_bulan'],
                                            'kode_bisnis' => $data['kode_bisnis'],
                                            'kode_rekening' => $data['koderekening'],
                                            'nama_rekening' => $data['nama_rekening'],
                                            'rtarif' => $data['rtarif'],
                                            'tpkirim' => $data['tpkirim'],
                                            'pelaporan' => $data['bsu_pso'],
                                            'jenis_produksi' => $data['jenis'],
                                            'kategori_produksi' => $data['kategori_produksi'],
                                            'keterangan' => $data['keterangan'],
                                            'lampiran' => $data['lampiran'],
                                        ]);
                                    }

                                } else {
                                    $total_lpu = $data['kategori_produksi'] == "LAYANAN POS UNIVERSAL" ? $data['bsu_pso'] : 0;
                                    $total_lpk = $data['kategori_produksi'] == "LAYANAN POS KOMERSIL" ? $data['bsu_pso'] : 0;
                                    $total_lbf = $data['kategori_produksi'] == "LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE" ? $data['bsu_pso'] : 0;
                                    $id = trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);
                                    // dd($id);
                                    $produksiNew = Produksi::create([
                                        'id' => $id,
                                        'id_regional' => $data['id_regional'],
                                        'id_kprk' => $data['id_kprk'],
                                        'id_kpc' => $data['id_kpc'],
                                        'tahun_anggaran' => $data['tahun_anggaran'],
                                        'triwulan' => $data['triwulan'],
                                        'total_lpu' => $total_lpu,
                                        'total_lpk' => $total_lpk,
                                        'total_lbf' => $total_lbf,
                                        'tgl_singkronisasi' => now(),
                                        'status_regional' => 7,
                                        'status_kprk' => 7,
                                        'bulan' => $data['nama_bulan'],
                                    ]);
                                    // dd($produksiNew);

                                    ProduksiDetail::create([
                                        'id' => $data['id'],
                                        'id_produksi' => $produksiNew->id,
                                        'nama_bulan' => $data['nama_bulan'],
                                        'kode_bisnis' => $data['kode_bisnis'],
                                        'kode_rekening' => $data['koderekening'],
                                        'nama_rekening' => $data['nama_rekening'],
                                        'rtarif' => $data['rtarif'],
                                        'tpkirim' => $data['tpkirim'],
                                        'pelaporan' => $data['bsu_pso'],
                                        'jenis_produksi' => $data['jenis'],
                                        'kategori_produksi' => $data['kategori_produksi'],
                                        'keterangan' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);

                                }
                            }
                            DB::commit();
                        }
                    }
                }
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Sinkronisasi produksi berhasil'], 200);
            }
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksiPrognosa(Request $request)
    {
        try {

            $endpoint = 'produksi_prognosa';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;
            $tipe_bisnis = $request->tipe_bisnis;

            $list = [];

            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }
            // dd($list);
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                // dd($list);
                foreach ($list as $ls) {
                    $kategori_bisnis = JenisBisnis::get();
                    if ($tipe_bisnis) {
                        $kategori_bisnis = JenisBisnis::where('id', $tipe_bisnis)->get();
                    }

                    foreach ($kategori_bisnis as $kb) {
                        // dd($kategori_biaya);
                        $apiController = new ApiController();
                        $url_request = $endpoint . '?kd_bisnis=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $tahun . '&triwulan=' . $triwulan;
                        $request->merge(['end_point' => $url_request]);
                        $response = $apiController->makeRequest($request);
                        // dd($response);

                        $dataProduksi = $response['data'] ?? [];

                        if (!$dataProduksi) {
                            continue;
                        } else {
                            DB::beginTransaction();
                            foreach ($dataProduksi as $data) {
                                $produksi = Produksi::where('id', trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']))->first();
                                $produksiDetail = '';
                                if ($produksi) {
                                    $bulan = $produksi->bulan;
                                    $produksiDetail = ProduksiDetail::
                                        where('id', $data['id'])
                                        ->first();
                                    if ($produksiDetail) {
                                        $produksiDetail->update([
                                            'id' => $data['id'],
                                            'id_produksi' => $produksi->id,
                                            'nama_bulan' => $data['nama_bulan'],
                                            'kode_bisnis' => $data['kode_bisnis'],
                                            'kode_rekening' => $data['koderekening'],
                                            'nama_rekening' => $data['nama_rekening'],
                                            'rtarif' => $data['rtarif'],
                                            'tpkirim' => $data['tpkirim'],
                                            'pelaporan_prognosa' => $data['bsu_pso'],
                                            'jenis_produksi' => $data['jenis'],
                                            'kategori_produksi' => $data['kategori_produksi'],
                                            'keterangan' => $data['keterangan'],
                                            'lampiran' => $data['lampiran'],
                                        ]);
                                        $produksiDetail_lpu = ProduksiDetail::select(DB::raw('SUM(pelaporan_prognosa) as total_lpu'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                                            ->first();
                                        // dd($produksiDetail_lpu);
                                        $produksiDetail_lpk = ProduksiDetail::select(DB::raw('SUM(pelaporan_prognosa) as total_lpk'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                                            ->first();
                                        $produksiDetail_lbf = ProduksiDetail::select(DB::raw('SUM(pelaporan_prognosa) as total_lbf'))
                                            ->where('id_produksi', $produksi->id)
                                            ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                                            ->first();

                                        $produksi->update([
                                            'total_lpu_prognosa' => $produksiDetail_lpu->total_lpu,
                                            'total_lpk_prognosa' => $produksiDetail_lpk->total_lpk,
                                            'total_lbf_prognosa' => $produksiDetail_lbf->total_lbf,
                                            'status_regional' => 7,
                                            'status_kprk' => 7,
                                        ]);

                                    } else {
                                        ProduksiDetail::create([
                                            'id' => $data['id'],
                                            'id_produksi' => $produksi->id,
                                            'nama_bulan' => $data['nama_bulan'],
                                            'kode_bisnis' => $data['kode_bisnis'],
                                            'kode_rekening' => $data['koderekening'],
                                            'nama_rekening' => $data['nama_rekening'],
                                            'rtarif' => $data['rtarif'],
                                            'tpkirim' => $data['tpkirim'],
                                            'pelaporan_prognosa' => $data['bsu_pso'],
                                            'jenis_produksi' => $data['jenis'],
                                            'kategori_produksi' => $data['kategori_produksi'],
                                            'keterangan' => $data['keterangan'],
                                            'lampiran' => $data['lampiran'],
                                        ]);
                                    }

                                } else {
                                    $total_lpu = $data['kategori_produksi'] == "LAYANAN POS UNIVERSAL" ? $data['bsu_pso'] : 0;
                                    $total_lpk = $data['kategori_produksi'] == "LAYANAN POS KOMERSIL" ? $data['bsu_pso'] : 0;
                                    $total_lbf = $data['kategori_produksi'] == "LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE" ? $data['bsu_pso'] : 0;
                                    $id = trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);
                                    // dd($id);
                                    $produksiNew = Produksi::create([
                                        'id' => $id,
                                        'id_regional' => $data['id_regional'],
                                        'id_kprk' => $data['id_kprk'],
                                        'id_kpc' => $data['id_kpc'],
                                        'tahun_anggaran' => $data['tahun_anggaran'],
                                        'triwulan' => $data['triwulan'],
                                        'total_lpu_prognosa' => $total_lpu,
                                        'total_lpk_prognosa' => $total_lpk,
                                        'total_lbf_prognosa' => $total_lbf,
                                        'tgl_singkronisasi' => now(),
                                        'status_regional' => 7,
                                        'status_kprk' => 7,
                                        'bulan' => $data['nama_bulan'],
                                    ]);
                                    // dd($produksiNew);

                                    ProduksiDetail::create([
                                        'id' => $data['id'],
                                        'id_produksi' => $produksiNew->id,
                                        'nama_bulan' => $data['nama_bulan'],
                                        'kode_bisnis' => $data['kode_bisnis'],
                                        'kode_rekening' => $data['koderekening'],
                                        'nama_rekening' => $data['nama_rekening'],
                                        'rtarif' => $data['rtarif'],
                                        'tpkirim' => $data['tpkirim'],
                                        'pelaporan_prognosa' => $data['bsu_pso'],
                                        'jenis_produksi' => $data['jenis'],
                                        'kategori_produksi' => $data['kategori_produksi'],
                                        'keterangan' => $data['keterangan'],
                                        'lampiran' => $data['lampiran'],
                                    ]);

                                }
                            }
                            DB::commit();
                        }
                    }
                }
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Sinkronisasi produksi prognosa berhasil'], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function syncNpp(Request $request)
    {
        try {

            $endpoint = 'biaya_nasional';
            $tahun = $request->tahun;
            $bulan = $request->bulan;

            $apiController = new ApiController();
            $url_request = $endpoint . '?tahunbulan=' . $tahun . $bulan;
            $request->merge(['end_point' => $url_request]);
            $response = $apiController->makeRequest($request);
            // dd($response);

            $dataNpp = $response['data'] ?? [];
            DB::beginTransaction();
            foreach ($dataNpp as $data) {

                $npp = Npp::find($tahun . $bulan . $data['koderekening']);

                if ($npp) {
                    $npp->update([
                        'bsu' => $data['bsu'],
                        'nama_file' => $data['linkfile'],
                        'id_status' => 7,

                    ]);
                } else {
                    $tahunbulan = $data['bulantahun'];
                    $tahun = substr($tahunbulan, 0, 4);
                    $bulan = substr($tahunbulan, 4, 2);
                    Npp::create([
                        'id' => $tahun . $bulan . $data['koderekening'],
                        'id_rekening_biaya' => $data['koderekening'],
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'bsu' => $data['bsu'],
                        'nama_file' => $data['linkfile'],
                        'id_status' => 7,

                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi NPP Nasional berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksiNasional(Request $request)
    {
        try {

            $endpoint = 'produksi_nasional';
            $tahun = $request->tahun;
            $bulan = $request->bulan;

            $apiController = new ApiController();
            $url_request = $endpoint . '?tahunbulan=' . $tahun . $bulan;
            $request->merge(['end_point' => $url_request]);
            $response = $apiController->makeRequest($request);
            // dd($response);

            $dataProduksiNasional = $response['data'] ?? [];
            DB::beginTransaction();
            foreach ($dataProduksiNasional as $data) {

                $produksiNasional = ProduksiNasional::find($bulan . $tahun . $data['jml_produksi']);

                if ($produksiNasional) {
                    $produksiNasional->update([
                        'jml_pendapatan' => $data['Jml_Pendapatan'],
                        'status' => $data['status'],
                        'produk' => $data['produk'],
                    ]);
                } else {
                    $tahunbulan = $data['tanggal'];
                    $tahun = substr($tahunbulan, 0, 4);
                    $bulan = substr($tahunbulan, 4, 2);
                    ProduksiNasional::create([
                        'id' => $bulan . $tahun . $data['jml_produksi'],
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'jml_produksi' => $data['jml_produksi'],
                        'jml_pendapatan' => $data['Jml_Pendapatan'],
                        'status' => $data['status'],
                        'produk' => $data['produk'],
                        'bisnis' => $data['Bisnis'],
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi Produksi Nasional berhasil'], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
