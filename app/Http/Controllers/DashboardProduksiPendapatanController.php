<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DashboardProduksiPendapatan;
use App\Models\LockVerifikasi;
use App\Models\Status;
use App\Models\UserLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardProduksiPendapatanController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tanggal' => 'nullable|numeric',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $tanggal = $request->get('tanggal', '');
            $status = $request->get('status', '');
            $groupProduk = $request->get('group_produk', '');
            $bisnis = $request->get('bisnis', '');
            $defaultOrder = $getOrder ? $getOrder : "tanggal ASC";
            $orderMappings = [
                'tanggalASC' => 'dashboard_produksi_pendapatan.tanggal ASC',
                'tanggalDESC' => 'dashboard_produksi_pendapatan.tanggal DESC',
                'group_produkASC' => 'dashboard_produksi_pendapatan.group_produk ASC',
                'group_produkDESC' => 'dashboard_produksi_pendapatan.group_produk DESC',
                'bisnisASC' => 'dashboard_produksi_pendapatan.bisnis ASC',
                'bisnisDESC' => 'dashboard_produksi_pendapatan.bisnis DESC',
                'statusASC' => 'dashboard_produksi_pendapatan.status ASC',
                'statusDESC' => 'dashboard_produksi_pendapatan.status DESC',
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

            $dppQuery = DashboardProduksiPendapatan::orderByRaw($order)
                ->select('id', 'group_produk', 'bisnis', 'status', 'id_status' ,  'tanggal', 'jml_produksi', 'jml_pendapatan', 'koefisien', 'transfer_pricing', 'verifikasi_jml_produksi as verifikasi_jumlah_produksi', 'verifikasi_jml_pendapatan as verifikasi_jumlah_pendapatan', 'verifikasi_koefisien');

            if ($tanggal !== '') {
                $dppQuery->where('tanggal', 'LIKE', '%' . $tanggal . '%');
            }
            if ($status !== '') {
                $dppQuery->where('status', 'LIKE', '%' . $tanggal . '%');
            }
            if ($groupProduk !== '') {
                $dppQuery->where('group_produk', 'LIKE', '%' . $groupProduk . '%');
            }
            if ($bisnis !== '') {
                $dppQuery->where('bisnis', 'LIKE', '%' . $bisnis . '%');
            }

            $total_data = $dppQuery->count();
            $dpp = $dppQuery->offset($offset)
                ->limit($limit)
                ->get();

            $grand_total = $dpp->sum('jml_pendapatan');
            $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

            foreach ($dpp as $item) {
                if ($item->id_status == 9) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
                // $statusItem = Statu::find($item->status);
                // $item->status_label = $statusItem ? $statusItem->nama : 'Unknown';
                $tanggal = Carbon::createFromFormat('Ym', $item->tanggal);

                $isLock = LockVerifikasi::where('tahun', date('Y', $tanggal->year))
                    ->where('bulan', $tanggal->month)
                    ->first();

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
                'data' => $dpp,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function getDetail(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_dpp' => 'required|numeric|exists:dashboard_produksi_pendapatan,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                    'status' => 'ERROR',
                ], 400);
            }

            // Ambil data DPP dengan kolom yang spesifik
            $dpp = DashboardProduksiPendapatan::find($request->id_dpp);

            if (!$dpp) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Record not found.',
                ], 404);
            }

            // Hitung verifikasi jumlah pendapatan
            // Asumsi: verifikasi_jml_pendapatan = verifikasi_jml_produksi * koefisien
            $verifikasiJmlPendapatan = $dpp->jml_produksi * $dpp->koefisien;

            // Siapkan data untuk respon
            $responseData = [
                'id' => $dpp->id,
                'tanggal' => $dpp->tanggal,
                'tahun' => date('Y', strtotime($dpp->tanggal)),
                'nama_group_produk' => $dpp->group_produk,
                'bisnis' => $dpp->bisnis,
                'jumlah_produksi' => (float)$dpp->jml_produksi,
                'verifikasi_jumlah_produksi' => (float)($dpp->verifikasi_jml_produksi ?? 0),
                'jumlah_pendapatan' => "Rp " . number_format(round($dpp->jml_pendapatan), 0, ',', '.'),
                'verifikasi_jumlah_pendapatan' => "Rp " . number_format(round($verifikasiJmlPendapatan), 0, ',', '.'),
                'koefisien' => (float)$dpp->koefisien,
                'verifikasi_koefisien' => (float) $dpp->verifikasi_koefisien,
                'transfer_pricing' => (float)$dpp->transfer_pricing,
                'nama_file' => $dpp->nama_file,
                'url_file' => 'https://lpu.komdigi.go.id/backend/view_image/lampirandpp/' . $dpp->nama_file,
                'catatan_pemeriksa' => $dpp->catatan_pemeriksa,
            ];

            // Cek status lock
            $isLock = LockVerifikasi::where('tahun', date('Y', strtotime($dpp->tanggal)))
                ->where('bulan', date('n', strtotime($dpp->tanggal)))
                ->first();
            $isLockStatus = $isLock->status ?? false;

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => [$responseData],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }


    public function verifikasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_dpp' => 'required|string|exists:dashboard_produksi_pendapatan,id',
                'data.*.verifikasi_jumlah_produksi' => 'required|string',
                'data.*.verifikasi_jumlah_pendapatan' => 'required|string',
                'data.*.verifikasi_koefisien' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');

            if (is_null($verifikasiData) || !is_array($verifikasiData)) {
                return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
            }

            $updatedData = [];

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_dpp']) || !isset($data['verifikasi_jumlah_produksi'])) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                $id_dpp = $data['id_dpp'];
                $verifikasi_produksi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi_jumlah_produksi']);
                $verifikasiProduksiFloat = round((float) $verifikasi_produksi);
                $verifikasiProduksiFormatted = (string) $verifikasiProduksiFloat;

                $verifikasi_pendapatan = str_replace(['Rp.', ',', '.'], '', $data['verifikasi_jumlah_pendapatan']);
                $verifikasiPendapatanFloat = round((float) $verifikasi_pendapatan);
                $verifikasiPendapatanFormatted = (string) $verifikasiPendapatanFloat;

                $verifikasi_koefisien = str_replace(['Rp.', ',', '.'], '', $data['verifikasi_koefisien']);
                $verifikasiKoefisienFloat = round((float) $verifikasi_koefisien);
                $verifikasiKoefisienFormatted = (string) $verifikasiKoefisienFloat;

                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                // Temukan entri DPP
                $dpp = DashboardProduksiPendapatan::find($id_dpp);

                // Cek apakah entri ditemukan
                if (!$dpp) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Data produksi pendapatan tidak ditemukan'], 404);
                }

                // Update entri DPP
                $dpp->update([
                    'verifikasi_jml_produksi' => $verifikasiProduksiFormatted,
                    'verifikasi_jml_pendapatan' => $verifikasiPendapatanFormatted,
                    'verifikasi_koefisien' => $verifikasiKoefisienFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'id_status' => 9,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                // Tambahkan entri yang diperbarui ke array hasil
                $updatedData[] = $dpp;
            }

            // Log aktivitas user
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Dashboard Produksi Pendapatan',
                'modul' => 'DPP',
                'id_user' => Auth::user()->id,
            ];

            UserLog::create($userLog);

            DB::commit();
            Artisan::call('cache:clear');

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function hapus(Request $request, string $id_dpp)
    {

        DB::beginTransaction();
        try {
            $dpp = DashboardProduksiPendapatan::find($id_dpp);

            if (!$dpp) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data produksi pendapatan tidak ditemukan',
                ], 404);
            }

            $tahun = date('Y', strtotime($dpp->tanggal));
            $bulan = ltrim(date('m', strtotime($dpp->tanggal)), '0');

            $isLock = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
            if ($isLock && $isLock->status) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data terkunci dan tidak dapat dihapus',
                ], 403);
            }

            if ($dpp->nama_file) {
                $filePath = storage_path('app/public/lampiran_dpp/' . $dpp->nama_file);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $dpp->delete();

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Hapus Dashboard Produksi Pendapatan',
                'modul' => 'DPP',
                'id_user' => Auth::user()->id,
            ];

            UserLog::create($userLog);

            DB::commit();
            Artisan::call('cache:clear');

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Data berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'group_produk' => 'required|string|max:255',
                'bisnis' => 'required|string|max:255',
                'jml_produksi' => 'required|numeric|min:0',
                'jml_pendapatan' => 'required|numeric|min:0',
                'koefisien' => 'required|numeric|min:0',
                'transfer_pricing' => 'required|numeric|min:0',
                'tanggal' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dpp = DashboardProduksiPendapatan::find($id);

            if (!$dpp) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data produksi pendapatan tidak ditemukan',
                ], 404);
            }

            $tahun = date('Y', strtotime($dpp->tanggal));
            $bulan = ltrim(date('m', strtotime($dpp->tanggal)), '0');

            $isLock = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
            if ($isLock && $isLock->status) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data terkunci dan tidak dapat diedit',
                ], 403);
            }

            $dpp->update([
                'group_produk' => $request->group_produk,
                'bisnis' => $request->bisnis,
                'jml_produksi' => $request->jml_produksi,
                'jml_pendapatan' => $request->jml_pendapatan,
                'koefisien' => $request->koefisien,
                'transfer_pricing' => $request->transfer_pricing,
                'tanggal' => $request->tanggal,
                'updated_by' => Auth::user()->id,
            ]);

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Edit Dashboard Produksi Pendapatan',
                'modul' => 'DPP',
                'id_user' => Auth::user()->id,
            ];

            UserLog::create($userLog);

            DB::commit();
            Artisan::call('cache:clear');

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Data berhasil diupdate',
                'data' => $dpp,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function toggleLock(Request $request, $tahun, $bulan)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $lock = LockVerifikasi::firstOrCreate(
                ['tahun' => $tahun, 'bulan' => $bulan],
                ['status' => false, 'created_by' => Auth::user()->id]
            );

            $lock->update([
                'status' => $request->status,
                'updated_by' => Auth::user()->id,
            ]);

            $statusText = $request->status ? 'Kunci' : 'Buka Kunci';
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => $statusText . ' Verifikasi DPP - ' . $tahun . '/' . $bulan,
                'modul' => 'DPP',
                'id_user' => Auth::user()->id,
            ];

            UserLog::create($userLog);

            DB::commit();
            Artisan::call('cache:clear');

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Status lock berhasil diupdate',
                'data' => $lock,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function checkLockStatus($dpp)
    {
        if ($dpp) {
            $tahun = date('Y', strtotime($dpp->tanggal));
            $bulan = ltrim(date('m', strtotime($dpp->tanggal)), '0');

            $isLock = LockVerifikasi::where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->first();

            return $isLock ? $isLock->status : false;
        }

        return false;
    }
}
