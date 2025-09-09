<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\LockVerifikasi;
use App\Models\Npp;
use App\Models\PegawaiDetail;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Status;
use App\Models\UserLog;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiLtk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BiayaGajiController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'bulan' => 'nullable|numeric',
                'status' => 'nullable|string|in:7,9',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun = request()->get('tahun', '');
            $bulan = (int) request()->get('bulan', '12');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "bulan ASC";
            $orderMappings = [
                'bulanASC' => 'pegawai_detail.bulan ASC',
                'bulanDESC' => 'pegawai_detail.bulan DESC',
                'tahunASC' => 'pegawai_detail.tahun ASC',
                'tahunDESC' => 'pegawai_detail.tahun DESC',
            ];

            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $bulanIndonesia = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];

            // $verifikasiBiayaGajiPegawaiQuery = VerifikasiBiayaRutin::orderByRaw($order)
            //     ->select('verifikasi_biaya_rutin.id', "verifikasi_biaya_rutin.keterangan_detail as keterangan", "verifikasi_biaya_rutin.bulan as bulan", "verifikasi_biaya_rutin.tahun as tahun", 'regional.nama as kantor')
            //     ->join('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
            //     ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.kode_rekening', '=', 'rekening_biaya.id')
            //     ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
            //     ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', "5101010101");
            $verifikasiBiayaGajiPegawaiQuery = PegawaiDetail::orderByRaw($order)->select("pegawai.nama as nama_pegawai", "pegawai.jabatan as jabatan", "pegawai.nama_bagian as nama_bagian", "pegawai_detail.id", DB::raw("CONCAT('" . $bulanIndonesia[$bulan - 1] . "') AS bulan"), "pegawai_detail.tahun as tahun", "regional.nama as kantor")
                ->join('pegawai', 'pegawai_detail.id_pegawai', '=', 'pegawai.id')
                ->join('rekening_biaya', 'pegawai_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('regional', 'pegawai.id_regional', '=', 'regional.id')
                ->where('pegawai_detail.id_rekening_biaya', "5101010101");

            $total_data = $verifikasiBiayaGajiPegawaiQuery->count();
            if ($tahun !== '') {
                $verifikasiBiayaGajiPegawaiQuery->where('pegawai_detail.tahun', $tahun);
            }

            if ($bulan !== '') {
                $verifikasiBiayaGajiPegawaiQuery->where('pegawai_detail.bulan', $bulan);
            }

            if ($status !== '') {
                $verifikasiBiayaGajiPegawaiQuery->where('pegawai_detail.id_status', $status);
            }

            $verifikasiBiayaGajiPegawai = $verifikasiBiayaGajiPegawaiQuery
                ->offset($offset)
                ->limit($limit)->get();

            $grand_total_raw = $verifikasiBiayaGajiPegawai->sum('pelaporan');
            $grand_total = "Rp " . number_format($grand_total_raw, 2, ',', '.');
            foreach ($verifikasiBiayaGajiPegawai as $item) {
                if ($item->id_status == 9) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }

                $isLock = LockVerifikasi::where('tahun', $item->tahun)->where('bulan', $item->bulan)->first();
                $isLockStatus = false;
                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }
                $item->isLock = $isLockStatus;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'grand_total' => $grand_total,
                'data' => $verifikasiBiayaGajiPegawai,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }


    public function getDetail(Request $request)
    {
        try {
            $id_pegawai = request()->get('id_pegawai', '');

            $validator = Validator::make($request->all(), [
                'id_pegawai' => 'required|string|exists:pegawai_detail,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }


            $bulanIndonesia = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];


            $pegawai = PegawaiDetail::select(
                "pegawai.nama as nama_pegawai",
                "pegawai.jabatan as jabatan",
                "pegawai.nama_bagian as nama_bagian",
                'regional.nama as kantor',
                'pegawai_detail.id as id',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'pegawai_detail.tahun',
                'pegawai_detail.bulan',
                'pegawai_detail.pelaporan',
                'pegawai_detail.verifikasi',
                'pegawai_detail.catatan_pemeriksa',
                'pegawai_detail.nama_file'
            )
                ->join('pegawai', 'pegawai_detail.id_pegawai', '=', 'pegawai.id')
                ->join('regional', 'pegawai.id_regional', '=', 'regional.id')
                ->join('rekening_biaya', 'pegawai_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('pegawai_detail.id', $id_pegawai)
                ->get();

            $isLockStatus = false;

            foreach ($pegawai as $item) {
                $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
                $item->nama_pegawai = $item->nama_pegawai;
                $item->nama_bagian = $item->nama_bagian;
                $item->jabatan = $item->jabatan;
                $item->url_file = 'https://lpu.komdigi.go.id/backend/view_image/lampiranltk/' . $item->nama_file;
                $item->periode = $bulanIndonesia[$item->bulan - 1];
            }

            if ($isLockStatus) {
                $pegawai = [];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'link' => config('app.env_config_path'),
                'data' => $pegawai,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_pegawai_detail' => 'required|string|exists:pegawai_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');

            if (is_null($verifikasiData) || !is_array($verifikasiData)) {
                return response()->json(['status' => 'ERROR', 'message' => 'Struktur data tidak valid'], 400);
            }

            $updatedData = [];
            $totalData = count($verifikasiData);
            $processedCount = 0;

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_pegawai_detail'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Struktur data tidak valid, pegawai tidak ditemukan'], 400);
                }

                $pegawai = PegawaiDetail::find($data['id_pegawai_detail']);

                if (!$pegawai) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya gaji pegawai tidak ditemukan'], 404);
                }

                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = round((float) $verifikasi);  
                $verifikasiFormatted = (string) $verifikasiFloat;                 
                $pegawai->update([
                    'verifikasi' => $verifikasi,
                    'id_status' => 9,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                ]);
                $updatedData[] = $pegawai->fresh();

                Log::info('Verifikasi: Membandingkan pelaporan dan verifikasi', [
                    'pelaporan' => $pegawai->pelaporan,
                    'verifikasi' => $pegawai->verifikasi,
                    'is_equal' => round($pegawai->pelaporan, 2) == round($pegawai->verifikasi, 2)
                ]);
                if (round($pegawai->pelaporan, 2) == round($pegawai->verifikasi, 2)) {
                    $processedCount++;
                }
            }

            Log::info('Verifikasi: Cek kecocokan total data', ['processed_count' => $processedCount, 'total_data' => $totalData]);
            if ($processedCount == $totalData) {
                foreach ($updatedData as $pegawai_detail) {
                    $pegawai = $pegawai_detail->pegawai;
                    if ($pegawai) {
                        preg_match('/\d+/', $pegawai->nama, $matches);
                        $nipPegawai = $matches[0] ?? null;
                        Log::info('Verifikasi: Mencocokkan NIP', ['pegawai_nama' => $pegawai->nama, 'nip_terekstrak' => $nipPegawai]);
                        if ($nipPegawai) {
                            $biayaRutinDetail = VerifikasiBiayaRutinDetail::where('id_rekening_biaya', $pegawai_detail->id_rekening_biaya)->where('bulan', $pegawai_detail->bulan)->where('keterangan', 'LIKE', '%' . $nipPegawai . '%')->whereHas('verifikasiBiayaRutin', function ($query) use ($pegawai_detail) {
                                $query->where('tahun', $pegawai_detail->tahun);
                            })->first();;
                            Log::info('Verifikasi: Hasil query VerifikasiBiayaRutinDetail', ['query_params' => [
                                'id_rekening_biaya' => $pegawai_detail->id_rekening_biaya,
                                'bulan' => $pegawai_detail->bulan,
                                'nip_pegawai' => $nipPegawai,
                                'tahun' => $pegawai_detail->tahun
                            ], 'biaya_rutin_detail_found' => (bool) $biayaRutinDetail]);

                            if ($biayaRutinDetail) {
                                $biayaRutinDetail->update([
                                    'verifikasi' => $biayaRutinDetail->pelaporan,
                                ]);
                                Log::info('Verifikasi: Berhasil update VerifikasiBiayaRutinDetail', ['id' => $biayaRutinDetail->id, 'verifikasi_baru' => $biayaRutinDetail->pelaporan]);
                            }
                        }
                    }
                }
            }


            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Data Biaya Gaji Pegaai',
                'modul' => 'PEGAWAI',
                'id_user' => Auth::user(),
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
