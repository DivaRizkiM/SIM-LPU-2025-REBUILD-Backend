<?php

namespace App\Http\Controllers;

use App\Models\Kprk;
use App\Models\LayananJasaKeuangan;
use App\Models\LayananKurir;
use App\Models\LockVerifikasi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Status;
use App\Models\UserLog;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiLtk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\LtkHelper;

class LtkController extends Controller
{
    protected LtkHelper $ltkHelper;

    public function __construct(LtkHelper $ltkHelper)
    {
        $this->ltkHelper = $ltkHelper;
    }

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
            $bulan = request()->get('bulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "bulan ASC";
            $orderMappings = [
                'bulanASC' => 'verifikasi_ltk.bulan ASC',
                'bulanDESC' => 'verifikasi_ltk.bulan DESC',
                'tahunASC' => 'verifikasi_ltk.tahun ASC',
                'tahunDESC' => 'verifikasi_ltk.tahun DESC',
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

            $verifikasiLtkQuery = VerifikasiLtk::orderByRaw($order)
                ->select('verifikasi_ltk.id', 'verifikasi_ltk.keterangan', 'verifikasi_ltk.id_status',  'verifikasi_ltk.nama_rekening as nama_rekening', 'verifikasi_ltk.kode_rekening', 'verifikasi_ltk.mtd_akuntansi', 'verifikasi_ltk.verifikasi_akuntansi', 'verifikasi_ltk.biaya_pso',  'verifikasi_ltk.verifikasi_pso', 'verifikasi_ltk.mtd_biaya_pos as mtd_ltk_pelaporan', 'verifikasi_ltk.mtd_biaya_hasil as mtd_ltk_verifikasi', 'verifikasi_ltk.proporsi_rumus', 'verifikasi_ltk.verifikasi_proporsi', 'tahun', 'bulan')
                ->whereNot('kategori_cost', 'PENDAPATAN');
            $total_data = $verifikasiLtkQuery->count();
            if ($tahun !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.tahun', $tahun);
            }

            if ($bulan !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.bulan', $bulan);
            }
            if ($status !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.id_status', $status);
            }

            $verifikasiLtk = $verifikasiLtkQuery
                ->offset($offset)
                ->limit($limit)->get();
            $verifikasiLtk = $verifikasiLtk->map(function ($verifikasiLtk) {
                $verifikasiLtk->id = (string) $verifikasiLtk->id;
                $verifikasiLtk->nominal = (int) $verifikasiLtk->nominal;
                $verifikasiLtk->proporsi_rumus = (float) $verifikasiLtk->proporsi_rumus ?? "0.00";
                $verifikasiLtk->verifikasi_pso = (float) $verifikasiLtk->verifikasi_pso ?? "0.00";
                $verifikasiLtk->verifikasi_akuntansi = (float) $verifikasiLtk->verifikasi_akuntansi ?? "0.00";
                $verifikasiLtk->verifikasi_proporsi = (float) $verifikasiLtk->verifikasi_proporsi ?? "0.00";
                $verifikasiLtk->mtd_ltk_pelaporan = (float) $verifikasiLtk->mtd_ltk_pelaporan ?? "0.00";
                $verifikasiLtk->mtd_ltk_verifikasi = (float) $verifikasiLtk->mtd_ltk_verifikasi ?? "0.00";
                $verifikasiLtk->proporsi_rumus = $verifikasiLtk->keterangan;
                $verifikasiLtk->tahun = $verifikasiLtk->tahun ?? '';
                $verifikasiLtk->bulan = $verifikasiLtk->bulan ?? '';
                return $verifikasiLtk;
            });
            $grand_total_fase_1 = 0;
            foreach ($verifikasiLtk as $item) {
                $kategoriCost = $item->keterangan;
                $mtd_ltk_verifikasi = $item->mtd_ltk_verifikasi ?? 0;
                $fase1 = $this->ltkHelper->calculateProporsiByCategory(
                    $mtd_ltk_verifikasi,
                    $kategoriCost,
                    $item->tahun,
                    $item->bulan
                );
                $hasilFase1 = isset($fase1['hasil_perhitungan_fase_1_raw']) ? $fase1['hasil_perhitungan_fase_1_raw'] : 0;
                $grand_total_fase_1 += (float) $hasilFase1;
            }
            $grand_total = "Rp " . number_format(round($grand_total_fase_1), 0, '', '.');

            foreach ($verifikasiLtk as $item) {
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
                'data' => $verifikasiLtk,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function getDetail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_ltk' => 'required|numeric|exists:verifikasi_ltk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                    'status' => 'ERROR',
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
                'Desember'
            ];

            $ltk = VerifikasiLtk::select(
                    'verifikasi_ltk.id',
                    'verifikasi_ltk.kode_rekening',
                    'verifikasi_ltk.nama_rekening as nama_rekening',
                    'verifikasi_ltk.bulan',
                    'verifikasi_ltk.tahun',
                    'verifikasi_ltk.mtd_akuntansi',
                    'verifikasi_ltk.verifikasi_akuntansi',
                    'verifikasi_ltk.biaya_pso',
                    'verifikasi_ltk.verifikasi_pso',
                    'verifikasi_ltk.mtd_biaya_pos as mtd_ltk_pelaporan',
                    'verifikasi_ltk.mtd_biaya_hasil as mtd_ltk_verifikasi',
                    'verifikasi_ltk.proporsi_rumus',
                    'verifikasi_ltk.verifikasi_proporsi',
                    'verifikasi_ltk.keterangan',
                    'verifikasi_ltk.catatan_pemeriksa',
                    'verifikasi_ltk.nama_file',
                    'verifikasi_ltk.kategori_cost',
                )->where('verifikasi_ltk.id', $request->id_ltk)
                ->where('verifikasi_ltk.keterangan', $request->proporsi_rumus ?? '!=', '0%')
                ->first();

            if (!$ltk) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data LTK tidak ditemukan'
                ], 404);
            }

            $kategoriCost = $ltk->keterangan;

            // MTD AKUNTANSI & BIAYA PSO ASLI → untuk hitung MTD BIAYA FINAL di helper
            $mtd_ltk_verifikasi = $ltk->mtd_ltk_verifikasi ?? 0;

            $proporsiCalculation = $this->ltkHelper->calculateProporsiByCategory(
                $mtd_ltk_verifikasi,
                $kategoriCost,
                $ltk->tahun,
                $ltk->bulan
            );

            $isLock = LockVerifikasi::where('tahun', $ltk->tahun)->where('bulan', $ltk->bulan)->first();
            $isLockStatus = $isLock->status ?? false;

            $lastTwoDigits = substr($ltk->kode_rekening, -2);

            $ltk->id = (string) $ltk->id;
            $ltk->last_two_digits = $lastTwoDigits;
            $ltk->periode = $bulanIndonesia[$ltk->bulan - 1];
            $ltk->url_file = 'https://lpu.komdigi.go.id/backend/view_image/lampiranltk/' . $ltk->nama_file;
            $ltk->verifikasi_pso = round($ltk->verifikasi_pso ?? 0);
            $ltk->mtd_akuntansi = "Rp " . number_format(round($ltk->mtd_akuntansi ?? 0), 0, ',', '.');
            $ltk->verifikasi_akuntansi = round($ltk->verifikasi_akuntansi ?? 0);
            $ltk->biaya_pso = "Rp " . number_format(round($ltk->biaya_pso ?? 0), 0, ',', '.');
            $ltk->mtd_ltk_pelaporan = "Rp " . number_format(round($ltk->mtd_ltk_pelaporan ?? 0), 0, ',', '.');
            $ltk->mtd_ltk_verifikasi = round($ltk->mtd_ltk_verifikasi ?? 0);
            $ltk->proporsi_rumus = $ltk->keterangan ?? $ltk->proporsi_rumus;

            foreach ($proporsiCalculation as $key => $value) {
                $ltk->$key = $value;
            }

            $ltk->proporsi_rumus_fase_1 = $ltk->verifikasi_proporsi > 0
                ? $ltk->verifikasi_proporsi
                : ($proporsiCalculation['proporsi_rumus_fase_1'] ?? null);

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => [$ltk],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_ltk' => 'required|numeric|exists:verifikasi_ltk,id',
                'data.*.verifikasi_akuntansi' => 'required|string',
                'data.*.verifikasi_pso' => 'required|string',
                'data.*.mtd_ltk_verifikasi' => 'required|string',
                'data.*.verifikasi_proporsi' => 'required|string',
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
            foreach ($verifikasiData as $data) {
                if (!isset($data['id_ltk'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Struktur data tidak valid, id_ltk tidak ditemukan'], 400);
                }

                $ltk = VerifikasiLtk::find($data['id_ltk']);

                if (!$ltk) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], 404);
                }

                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $verifikasiAkuntansi = (float) str_replace(['Rp', '.', ','], '', $data['verifikasi_akuntansi']);
                $verifikasiPso = (float) str_replace(['Rp', '.', ','], '', $data['verifikasi_pso']);
                $verifikasiMTDLTK = (float) str_replace(['Rp', '.', ','], '', $data['mtd_ltk_verifikasi']);
                $verifikasiProporsi = (float) str_replace(['%', ','], ['', '.'], $data['verifikasi_proporsi']);
                $tahun = $ltk->tahun;

                $ltk->update([
                    'verifikasi_akuntansi' => $verifikasiAkuntansi,
                    'verifikasi_pso' => $verifikasiPso,
                    'mtd_biaya_hasil' => $verifikasiMTDLTK,
                    'verifikasi_proporsi' => $verifikasiProporsi,
                    'id_status' => 9,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                ]);

                $updatedData[] = $ltk->fresh();
            }

            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Data LTK',
                'modul' => 'LTK',
                'id_user' => Auth::user()->id,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
