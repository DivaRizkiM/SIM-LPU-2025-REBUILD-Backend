<?php

namespace App\Http\Controllers;

use App\Models\Kprk;
use App\Models\Npp;
use App\Models\UserLog;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LockVerifikasi;
use App\Models\Pendapatan;
use App\Models\VerifikasiBiayaRutinDetail;
use Illuminate\Support\Facades\Artisan;

class NppController extends Controller
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
            $bulan = request()->get('bulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "bulan ASC";
            $orderMappings = [
                'bulanASC' => 'npp.bulan ASC',
                'bulanDESC' => 'npp.bulan DESC',
                'tahunASC' => 'npp.tahun ASC',
                'tahunDESC' => 'npp.tahun DESC',
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

            $nppQuery = Npp::orderByRaw($order)
                ->select('id', 'bulan', 'tahun', 'bsu as nominal', 'id_status', 'id_rekening_biaya');
            $total_data = $nppQuery->count();
            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $bulan, dan $status
            if ($tahun !== '') {
                $nppQuery->where('npp.tahun', $tahun);
            }

            if ($bulan !== '') {
                $nppQuery->where('npp.bulan', $bulan);
            }
            if ($status !== '') {
                $nppQuery->where('npp.id_status', $status);
            }

            $npp = $nppQuery
                ->offset($offset)
                ->limit($limit)->get();
            $npp = $npp->map(function ($npp) {
                $npp->nominal = (int) $npp->nominal;
                return $npp;
            });
            $grand_total = $npp->sum('nominal');
            $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($npp as $item) {
                if ($item->id_status == 9) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
                foreach ($npp as $item) {
                    // Menghapus angka nol di depan bulan
                    $bulan = ltrim($item->bulan, '0');

                    $isLock = LockVerifikasi::where('tahun', $item->tahun)->where('bulan', $bulan)->first();
                    $isLockStatus = false;
                    if ($isLock) {
                        $isLockStatus = $isLock->status;
                    }
                    $item->isLock = $isLockStatus;
                    $item->nama_rekening = $item->rekeningBiaya->nama ?? '-';
                    $item->kode_rekening = $item->rekeningBiaya->kode_rekening ?? '-';
                    unset($item->rekeningBiaya);
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'grand_total' => $grand_total,
                'data' => $npp,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // public function getDetail(Request $request)
    // {
    //     try {

    //         $validator = Validator::make($request->all(), [
    //             'id_npp' => 'required|numeric|exists:npp,id',

    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }
    //         $bulanIndonesia = [
    //             'Januari',
    //             'Februari',
    //             'Maret',
    //             'April',
    //             'Mei',
    //             'Juni',
    //             'Juli',
    //             'Agustus',
    //             'September',
    //             'Oktober',
    //             'November',
    //             'Desember',
    //         ];

    //         $npp = Npp::select(
    //             'npp.id',
    //             'rekening_biaya.kode_rekening',
    //             'rekening_biaya.nama as nama_rekening',
    //             'npp.bulan',
    //             'npp.tahun',
    //             'npp.nama_file',
    //             'npp.bsu as pelaporan',
    //             'npp.verifikasi',
    //             'npp.catatan_pemeriksa',
    //         )
    //             ->where('npp.id', $request->id_npp)
    //             ->join('rekening_biaya', 'npp.id_rekening_biaya', '=', 'rekening_biaya.id')
    //             ->first();
    //         $isLockStatus = false;
    //         if ($npp) {
    //             $npp->periode = $bulanIndonesia[$npp->bulan - 1];
    //             $npp->pelaporan = "Rp " . number_format(round($npp->pelaporan), 0, '', '.');
    //             $npp->verifikasi = "Rp " . number_format(round($npp->verifikasi), 0, '', '.');
    //             $npp->url_file = 'https://lpu.komdigi.go.id/backend/view_image/lampirannpp/' . $npp->nama_file;

    //             $pendapatan_nasional = ProduksiNasional::select(DB::raw('SUM(jml_pendapatan) as jml_pendapatan'))
    //                 ->where('bulan', $npp->bulan)
    //                 ->where('status', 'OUTGOING')
    //                 ->where('tahun', $npp->tahun)->first();
    //             $pendapatan_nasional = ProduksiNasional::select(DB::raw('SUM(jml_pendapatan) as jml_pendapatan'))
    //                 ->where('bulan', $npp->bulan)
    //                 ->where('status', 'OUTGOING')
    //                 ->where('tahun', $npp->tahun)->first();
    //             $npp->pendapatan_nasional = "Rp " . number_format(round($pendapatan_nasional->jml_pendapatan), 0, '', '.');
    //             $pendapatan_kcp_nasional = ProduksiDetail::select(DB::raw('SUM(produksi_detail.pelaporan) as pelaporan'))
    //                 ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->where('produksi_detail.nama_bulan', $npp->bulan)
    //                 ->where('produksi_detail.jenis_produksi', 'PENERIMAAN/OUTGOING')
    //                 ->where('produksi.tahun_anggaran', $npp->tahun)
    //                 ->first();
    //             $npp->pendapatan_kcp_nasional = "Rp " . number_format(round($pendapatan_kcp_nasional->pelaporan), 0, '', '.');
    //             if ($pendapatan_nasional->jml_pendapatan != 0) {
    //                 $proporsi = ($pendapatan_kcp_nasional->pelaporan / $pendapatan_nasional->jml_pendapatan) * 100;
    //                 $npp->proporsi = number_format($proporsi, 2) . '%';
    //             } else {
    //                 $npp->proporsi = '0%';
    //             }

    //             $isLock = LockVerifikasi::where('tahun', $npp->tahun)->where('bulan', $npp->bulan)->first();
    //             if ($isLock) {
    //                 $isLockStatus = $isLock->status;
    //             }
    //         }
    //         if ($isLockStatus == true) {
    //             $npp = [];
    //         } else {
    //             $npp = [$npp];
    //         }


    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'isLock' => $isLockStatus,
    //             'data' => $npp,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }



    public function getDetail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_npp' => 'required|numeric|exists:npp,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                    'status' => 'ERROR',
                ], 400);
            }

            // Pemisahan produksi dan pendapatan berdasarkan dua digit akhir kode rekening
            $produksiRek = ["06", "07", "08", "09"];
            $pendapatanRek = ["10", "11"];

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

            $npp = Npp::select(
                'npp.id',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'npp.bulan',
                'npp.tahun',
                'npp.nama_file',
                'npp.bsu as pelaporan',
                'npp.verifikasi',
                'npp.catatan_pemeriksa',
            )
                ->where('npp.id', $request->id_npp)
                ->join('rekening_biaya', 'npp.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->first();
            $isLockStatus = false;
            if ($npp) {
                $dataType = null;
                $nasionalTotal = null;
                $sumField = null;
                $isLock = LockVerifikasi::where('tahun', $npp->tahun)->where('bulan', $npp->bulan)->first();
                $lastTwoDigits = substr($npp->kode_rekening, -2);
                $npp->last_two_digits = $lastTwoDigits;
                $npp->periode = $bulanIndonesia[$npp->bulan - 1];
                $npp->pelaporan = "Rp " . number_format(round($npp->pelaporan), 0, ',', '.');
                $npp->verifikasi = "Rp " . number_format(round($npp->verifikasi), 0, ',', '.');
                $npp->url_file = 'https://verifikasilpu.komdigi.go.id/backend/storage/lampiran/' . $npp->nama_file;

                // Cek jenis npp apakah produksi atau pendapatan
                if (in_array($lastTwoDigits, $pendapatanRek)) {
                    $sumField = 'jml_pendapatan';
                    $sumFieldKCP = 'bsu_bruto';
                    $dataType = 'pendapatan';
                } elseif (in_array($lastTwoDigits, $produksiRek)) {
                    $sumField = 'jml_produksi';
                    $sumFieldKCP = 'bilangan';
                    $dataType = 'produksi';
                }

                if ($sumField && $dataType) {
                    $nasionalTotal = ProduksiNasional::where('bulan', $npp->bulan)
                        ->where('status', 'OUTGOING')
                        ->where('tahun', '>=', $npp->tahun)
                        ->sum($sumField);

                    $kcpNasionalTotal = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                        ->where('produksi_detail.nama_bulan', $npp->bulan)
                        ->where('produksi_detail.jenis_produksi', 'PENERIMAAN/OUTGOING')
                        ->where('produksi.tahun_anggaran', $npp->tahun)
                        ->sum('produksi_detail.' . $sumFieldKCP);

                    // Mengisi properti secara dinamis
                    $npp->{$dataType . '_nasional'} = "Rp " . number_format(round($nasionalTotal), 0, ',', '.');
                    $npp->{$dataType . '_kcp_nasional'} = "Rp " . number_format(round($kcpNasionalTotal), 0, ',', '.');

                    if ($nasionalTotal > 0) {
                        $proporsi = ($kcpNasionalTotal / $nasionalTotal) * 100;
                        $roundProporsi = round($proporsi, 2);
                        $npp->proporsi = number_format($roundProporsi, 2, ',', '.') . '%';
                    }
                }
                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }
            }

            $lock = LockVerifikasi::where('tahun', $npp->tahun)->where('bulan', '>=', $npp->bulan)->first();

            $isLockStatus = $lock->status ?? false;

            // Removed the code that emptied responseData when locked
            // Frontend already handles read-only mode when isLock is true
            // $responseData = [];
            // if (!$isLockStatus) {
            //     $responseData = [$npp];
            // }

            $responseData = [$npp];

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }






    //     public function getDetail(Request $request)
    // {
    //     try {
    //         $id_npp = $request->get('id_npp');

    //         if ($this->validateInput($request)) {
    //             $npp = $this->fetchNppDetails($id_npp);
    //             $isLockStatus = $this->checkLockStatus($npp);

    //             if ($isLockStatus) {
    //                 $npp = [];
    //             } else {
    //                 $npp = [$npp];
    //             }

    //             return response()->json([
    //                 'status' => 'SUCCESS',
    //                 'isLock' => $isLockStatus,
    //                 'data' => $npp,
    //             ]);
    //         }

    //         return response()->json([
    //             'status' => 'ERROR',
    //             'message' => 'Invalid input parameters',
    //             'errors' => ['id_npp' => 'Invalid or missing parameter.']
    //         ], 400);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    // // Method to validate input
    // private function validateInput(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'id_npp' => 'required|numeric|exists:npp,id',
    //     ]);

    //     return !$validator->fails();
    // }

    // // Method to fetch NPP details
    // private function fetchNppDetails($id_npp)
    // {
    //     $bulanIndonesia = [
    //         'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    //         'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    //     ];

    //     $npp = Npp::select(
    //         'npp.id',
    //         'rekening_biaya.kode_rekening',
    //         'rekening_biaya.nama as nama_rekening',
    //         'npp.bulan',
    //         'npp.tahun',
    //         'npp.nama_file',
    //         'npp.bsu as pelaporan',
    //         'npp.verifikasi',
    //         'npp.catatan_pemeriksa',
    //     )
    //         ->where('npp.id', $id_npp)
    //         ->join('rekening_biaya', 'npp.id_rekening_biaya', '=', 'rekening_biaya.id')
    //         ->first();

    //     if ($npp) {
    //         $npp->periode = $bulanIndonesia[$npp->bulan - 1];
    //         $npp->pelaporan = "Rp " . number_format(round($npp->pelaporan), 0, '', '.');
    //         $npp->verifikasi = "Rp " . number_format(round($npp->verifikasi), 0, '', '.');
    //         $npp->url_file = config('app.env_config_path') . $npp->nama_file;

    //         $pendapatan_nasional = $this->getPendapatanNasional($npp);
    //         $npp->pendapatan_nasional = "Rp " . number_format(round($pendapatan_nasional->jml_pendapatan), 0, '', '.');

    //         $pendapatan_kcp_nasional = $this->getPendapatanKcpNasional($npp);
    //         $npp->pendapatan_kcp_nasional = "Rp " . number_format(round($pendapatan_kcp_nasional->pelaporan), 0, '', '.');

    //         $npp->proporsi = $this->calculateProporsi($pendapatan_kcp_nasional, $pendapatan_nasional);
    //     }

    //     return $npp;
    // }

    // // Method to get Pendapatan Nasional
    // private function getPendapatanNasional($npp)
    // {
    //     return ProduksiNasional::select(DB::raw('SUM(jml_pendapatan) as jml_pendapatan'))
    //         ->where('bulan', $npp->bulan)
    //         ->where('status', 'OUTGOING')
    //         ->where('tahun', $npp->tahun)
    //         ->first();
    // }

    // // Method to get Pendapatan KCP Nasional
    // private function getPendapatanKcpNasional($npp)
    // {
    //     return ProduksiDetail::select(DB::raw('SUM(produksi_detail.pelaporan) as pelaporan'))
    //         ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //         ->where('produksi_detail.nama_bulan', $npp->bulan)
    //         ->where('produksi_detail.jenis_produksi', 'PENERIMAAN/OUTGOING')
    //         ->where('produksi.tahun_anggaran', $npp->tahun)
    //         ->first();
    // }

    // // Method to calculate proporsi
    // private function calculateProporsi($pendapatan_kcp_nasional, $pendapatan_nasional)
    // {
    //     if ($pendapatan_nasional->jml_pendapatan != 0) {
    //         return number_format(($pendapatan_kcp_nasional->pelaporan / $pendapatan_nasional->jml_pendapatan) * 100, 2) . '%';
    //     }

    //     return '0%';
    // }

    // Method to check lock status
    private function checkLockStatus($npp)
    {
        if ($npp) {
            $isLock = LockVerifikasi::where('tahun', $npp->tahun)
                ->where('bulan', $npp->bulan)
                ->first();

            return $isLock ? $isLock->status : false;
        }

        return false;
    }

    // public function verifikasi(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             '*.id_npp' => 'required|string|exists:produksi_detail,id',
    //             '*.verifikasi' => 'required|string',
    //             '*.catatan_pemeriksa' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
    //         }

    //         foreach ($request->all() as $data) {
    //             $id_npp = $data['id_npp'];
    //             $verifikasi = $data['verifikasi'];
    //             $verifikasi = str_replace(['Rp.', '.'], '', $verifikasi);
    //             $verifikasi = str_replace(',', '.', $verifikasi);
    //             $verifikasiFloat = (float) $verifikasi;
    //             $verifikasiFormatted = number_format($verifikasiFloat,0, '', '.');
    //             $catatan_pemeriksa = $data['catatan_pemeriksa'];
    //             $id_validator = Auth::user()->id;
    //             $tanggal_verifikasi = now();

    //             $produksi_detail = ProduksiDetail::find($id_npp);

    //             $produksi_detail->update([
    //                 'verifikasi' => $verifikasiFormatted,
    //                 'catatan_pemeriksa' => $catatan_pemeriksa,
    //                 'id_validator' => $id_validator,
    //                 'tgl_verifikasi' => $tanggal_verifikasi,
    //             ]);

    //             if ($produksi_detail) {
    //                 $produksi = ProduksiDetail::where('id_produksi', $produksi_detail->id_produksi)->get();
    //                 $countValid = $produksi->filter(function ($detail) {
    //                     return $detail->verifikasi != 0.00 && $detail->tgl_verifikasi !== null;
    //                 })->count();

    //                 if ($countValid === $produksi->count()) {
    //                     VerifikasiProduksi::where('id', $id_produksi)->update(['id_status' => 9]);
    //                 }
    //             }
    //         }

    //         return response()->json(['status' => 'SUCCESS']);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function verifikasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_npp' => 'required|string|exists:npp,id',
                'data.*.verifikasi' => 'required|string',
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
            $totalKcp = Kprk::sum('jumlah_kpc_lpu');
            foreach ($verifikasiData as $data) {
                if (!isset($data['id_npp']) || !isset($data['verifikasi'])) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                $id_npp = $data['id_npp'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = round((float) $verifikasi);
                $verifikasiFormatted = (string) $verifikasiFloat;
                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                $npp = Npp::find($id_npp);

                if (!$npp) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], 404);
                }

                $npp->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                    'id_status' => 9,
                ]);

                // Tidak ada update ke biayaRutins

                $updatedData[] = $npp;
            }

            // Tidak ada create UserLog dan cache clear

            DB::commit();

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
