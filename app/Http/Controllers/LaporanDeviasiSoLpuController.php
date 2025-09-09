<?php

namespace App\Http\Controllers;

use App\Exports\LaporanDeviasiSoLpuExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanDeviasiSoLpuController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                // 'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $id_regional = request()->get('id_regional', '');
            $id_kprk = request()->get('id_kprk', '');
            $tahun = request()->get('tahun', '');

            $triwulan = request()->get('triwulan', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
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

            // Query untuk alokasi_dana
            $queryAlokasiDana = DB::table('alokasi_dana')
                ->select('alokasi_dana.id_kpc AS id_kpc', 'alokasi_dana.tahun AS tahun', 'alokasi_dana.triwulan AS triwulan')
                ->selectRaw('SUM(alokasi_dana_lpu) AS alokasi_dana_lpu')
                ->leftJoin('kpc', 'kpc.id', '=', 'alokasi_dana.id_kpc')
                ->groupBy('id_kpc');

            $queryVerifikasiLpu = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpu')
                ->selectRaw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpu')
                ->selectRaw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpu')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                ->groupBy('id_kpc');
            $queryVerifikasiLpk = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpk')
                ->selectRaw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpk')
                ->selectRaw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpk')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                ->groupBy('id_kpc');
            $queryVerifikasiLbf = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(verifikasi*rtarif*(tpkirim/100)) AS outgoing_lbf')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                ->groupBy('id_kpc');
            $queryVerifikasiBiaya = DB::table('verifikasi_biaya_rutin_detail')
                ->select('verifikasi_biaya_rutin.id_kpc AS id_kpc', 'verifikasi_biaya_rutin.tahun AS tahun', 'verifikasi_biaya_rutin.triwulan AS triwulan')
                ->selectRaw('SUM(IF(id_verifikasi_biaya_rutin,verifikasi,0)) AS verifikasi_rutin')
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->groupBy('id_kpc');
            $queryAtribusi = DB::table('iaya_atribusi_detail')
                ->select('biaya_atribusi.id_kpc AS id_kpc', 'verifikasi_biaya_atribusi.tahun AS tahun', 'verifikasi_biaya_atribusi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(id_biaya_atribusi,verifikasi,0)) AS atribusi')
                ->leftJoin('biaya_atribusi', 'biaya_atribusi.id', '=', 'iaya_atribusi_detail.id_biaya_atribusi')
                ->groupBy('id_kpc');

            $query = Kpc::select([
                'kpc.nama AS nama',
                'kpc.nomor_dirian AS nomor_dirian',
                'produksi.tahun_anggaran AS tahun',
                'produksi.triwulan AS triwulan',
                'alokasi_dana.alokasi_dana_lpu AS sum_alokasi_dana_lpu',
                'verifikasi_lpu.outgoing_lpu AS verifikasi_outgoing_lpu',
                'verifikasi_lpu.incoming_lpu AS verifikasi_incoming_lpu',
                'verifikasi_lpu.sisa_layanan_lpu AS verifikasi_sisa_layanan_lpu',
                'verifikasi_lpk.outgoing_lpk AS verifikasi_outgoing_lpk',
                'verifikasi_lpk.incoming_lpk AS verifikasi_incoming_lpk',
                'verifikasi_lpk.sisa_layanan_lpk AS verifikasi_sisa_layanan_lpk',
                'verifikasi_lbf.outgoing_lbf AS verifikasi_outgoing_lbf',
                'verifikasi_biaya.verifikasi_rutin AS verifikasi_rutin',
                'atribusi.verifikasi_atribusi AS verifikasi_atribusi',
            ])
                ->join('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('kpc.id_regional', $id_regional);
                })
                ->when($id_kprk !== '', function ($query) use ($id_kprk) {
                    return $query->where('kpc.id', $id_kprk);
                })
                ->leftJoinSub($queryAlokasiDana, 'alokasi_dana', function ($join) use ($triwulan, $tahun) {
                    $join->on('alokasi_dana.id_kpc', '=', 'kpc.id')
                        ->on('alokasi_dana.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('alokasi_dana.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLpu, 'verifikasi_lpu', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpu.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpu.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLpk, 'verifikasi_lpk', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpk.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpk.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpk.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLbf, 'verifikasi_lbf', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lbf.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lbf.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lbf.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiBiaya, 'verifikasi_biaya', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_biaya.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_biaya.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_biaya.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryAtribusi, 'atribusi', function ($join) use ($triwulan, $tahun) {
                    $join->on('atribusi.id_kpc', '=', 'kpc.id')
                        ->on('atribusi.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('atribusi.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                });
            $total_data = $query->count();
            $result = $query
            ->groupBy('kpc.id')
            ->orderByRaw($order)
            ->offset($offset)
            ->limit($limit)->get();
            $data = [];
            foreach ($result as $item) {
                $biaya = $item->verifikasi_rutin + $item->verifikasi_atribusi;
                $verifikasi_incoming = $item->verifikasi_incoming_lpu + $item->verifikasi_incoming_lpk;
                $verifikasi_outgoing = $item->verifikasi_outgoing_lpu + $item->verifikasi_outgoing_lpk + $item->verifikasi_outgoing_lbf;
                $verifikasi_sisa_layanan = $item->verifikasi_sisa_layanan_lpu + $item->verifikasi_sisa_layanan_lpk;
                $jumlah = $verifikasi_incoming + $verifikasi_outgoing + $verifikasi_sisa_layanan;
                $realisasi = $jumlah - $biaya;
                $data[] = [
                    'nomor_dirian' => $item->nomor_dirian,
                    'nama_kpc' => $item->nama,
                    'sum_alokasi_dana_lpu' => $item->sum_alokasi_dana_lpu ?? 0,
                    'realisasi' => $realisasi ?? 0,
                    'deviasi' => $item->sum_alokasi_dana_lpu - $realisasi ?? 0,
                ];

            }
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data'=>$total_data,
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                // 'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $id_regional = request()->get('id_regional', '');
            $id_kprk = request()->get('id_kprk', '');
            $tahun = request()->get('tahun', '');

            $triwulan = request()->get('triwulan', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
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

            // Query untuk alokasi_dana
            $queryAlokasiDana = DB::table('alokasi_dana')
                ->select('alokasi_dana.id_kpc AS id_kpc', 'alokasi_dana.tahun AS tahun', 'alokasi_dana.triwulan AS triwulan')
                ->selectRaw('SUM(alokasi_dana_lpu) AS alokasi_dana_lpu')
                ->leftJoin('kpc', 'kpc.id', '=', 'alokasi_dana.id_kpc')
                ->groupBy('id_kpc');

            $queryVerifikasiLpu = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpu')
                ->selectRaw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpu')
                ->selectRaw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpu')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                ->groupBy('id_kpc');
            $queryVerifikasiLpk = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpk')
                ->selectRaw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpk')
                ->selectRaw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpk')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                ->groupBy('id_kpc');
            $queryVerifikasiLbf = DB::table('produksi_detail')
                ->select('produksi.id_kpc AS id_kpc', 'produksi.tahun_anggaran AS tahun_anggaran', 'produksi.triwulan AS triwulan')
                ->selectRaw('SUM(verifikasi*rtarif*(tpkirim/100)) AS outgoing_lbf')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                ->groupBy('id_kpc');
            $queryVerifikasiBiaya = DB::table('verifikasi_biaya_rutin_detail')
                ->select('verifikasi_biaya_rutin.id_kpc AS id_kpc', 'verifikasi_biaya_rutin.tahun AS tahun', 'verifikasi_biaya_rutin.triwulan AS triwulan')
                ->selectRaw('SUM(IF(id_verifikasi_biaya_rutin,verifikasi,0)) AS verifikasi_rutin')
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->groupBy('id_kpc');
            $queryAtribusi = DB::table('iaya_atribusi_detail')
                ->select('biaya_atribusi.id_kpc AS id_kpc', 'verifikasi_biaya_atribusi.tahun AS tahun', 'verifikasi_biaya_atribusi.triwulan AS triwulan')
                ->selectRaw('SUM(IF(id_biaya_atribusi,verifikasi,0)) AS verifikasi_atribusi')
                ->leftJoin('verifikasi_biaya_atribusi', 'biaya_atribusi.id', '=', 'iaya_atribusi_detail.id_biaya_atribusi')
                ->groupBy('id_kpc');

            $query = Kpc::select([
                'kpc.nama AS nama',
                'kpc.nomor_dirian AS nomor_dirian',
                'produksi.tahun_anggaran AS tahun',
                'produksi.triwulan AS triwulan',
                'alokasi_dana.alokasi_dana_lpu AS sum_alokasi_dana_lpu',
                'verifikasi_lpu.outgoing_lpu AS verifikasi_outgoing_lpu',
                'verifikasi_lpu.incoming_lpu AS verifikasi_incoming_lpu',
                'verifikasi_lpu.sisa_layanan_lpu AS verifikasi_sisa_layanan_lpu',
                'verifikasi_lpk.outgoing_lpk AS verifikasi_outgoing_lpk',
                'verifikasi_lpk.incoming_lpk AS verifikasi_incoming_lpk',
                'verifikasi_lpk.sisa_layanan_lpk AS verifikasi_sisa_layanan_lpk',
                'verifikasi_lbf.outgoing_lbf AS verifikasi_outgoing_lbf',
                'verifikasi_biaya.verifikasi_rutin AS verifikasi_rutin',
                'atribusi.verifikasi_atribusi AS verifikasi_atribusi',
            ])
                ->join('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->leftJoinSub($queryAlokasiDana, 'alokasi_dana', function ($join) use ($triwulan, $tahun) {
                    $join->on('alokasi_dana.id_kpc', '=', 'kpc.id')
                        ->on('alokasi_dana.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('alokasi_dana.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLpu, 'verifikasi_lpu', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpu.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpu.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLpk, 'verifikasi_lpk', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpk.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpk.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpk.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiLbf, 'verifikasi_lbf', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lbf.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lbf.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lbf.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryVerifikasiBiaya, 'verifikasi_biaya', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_biaya.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_biaya.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_biaya.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($queryAtribusi, 'atribusi', function ($join) use ($triwulan, $tahun) {
                    $join->on('atribusi.id_kpc', '=', 'kpc.id')
                        ->on('atribusi.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('atribusi.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->groupBy('kpc.id');

            $result = $query->get();
            $data = [];
            foreach ($result as $item) {
                $biaya = $item->verifikasi_rutin + $item->verifikasi_atribusi;
                $verifikasi_incoming = $item->verifikasi_incoming_lpu + $item->verifikasi_incoming_lpk;
                $verifikasi_outgoing = $item->verifikasi_outgoing_lpu + $item->verifikasi_outgoing_lpk + $item->verifikasi_outgoing_lbf;
                $verifikasi_sisa_layanan = $item->verifikasi_sisa_layanan_lpu + $item->verifikasi_sisa_layanan_lpk;
                $jumlah = $verifikasi_incoming + $verifikasi_outgoing + $verifikasi_sisa_layanan;
                $realisasi = $jumlah - $biaya;
                $data[] = [
                    'nomor_dirian' => $item->nomor_dirian,
                    'nama_kpc' => $item->nama,
                    'sum_alokasi_dana_lpu' => $item->sum_alokasi_dana_lpu ?? 0,
                    'realisasi' => $realisasi ?? 0,
                    'deviasi' => $item->sum_alokasi_dana_lpu - $realisasi ?? 0,
                ];

            }
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Laporan Deviasi SO LPU',
                'modul' => 'Laporan Deviasi SO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $export = new LaporanDeviasiSoLpuExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-deviasi-dana-lpu.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {

            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }

    }
}
