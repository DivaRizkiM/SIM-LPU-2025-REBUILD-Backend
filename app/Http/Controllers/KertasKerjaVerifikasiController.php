<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKertasKerjaVerifikasiDetailExport;
use App\Exports\LaporanKertasKerjaVerifikasiExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KertasKerjaVerifikasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun'       => 'nullable|numeric',
                'triwulan'    => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'offset'      => 'integer|min:0',
                'limit'       => 'integer|min:1|max:1000',
                'order'       => 'nullable|string',
                'id_kprk'     => 'nullable|numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset      = (int) $request->get('offset', 0);
            $limit       = (int) $request->get('limit', 100);
            $getOrder    = $request->get('order', '');
            $id_regional = $request->get('id_regional');
            $id_kprk     = $request->get('id_kprk');
            $tahun       = $request->get('tahun');
            $triwulan    = $request->get('triwulan');

            $orderMappings = [
                'namaASC'     => ['kprk.nama', 'ASC'],
                'namaDESC'    => ['kprk.nama', 'DESC'],
                // kolom triwulan/tahun tidak ada di select utama, jadi fallback ke id
                'triwulanASC' => ['kprk.id', 'ASC'],
                'triwulanDESC' => ['kprk.id', 'DESC'],
                'tahunASC'    => ['kprk.id', 'ASC'],
                'tahunDESC'   => ['kprk.id', 'DESC'],
            ];
            [$orderCol, $orderDir] = $orderMappings[$getOrder] ?? ['kprk.id', 'ASC'];

            // Subquery Biaya Rutin
            $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($request->filled('triwulan'), fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                ->when($request->filled('tahun'), fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                ->when($request->filled('id_regional'), fn($q) => $q->where('verifikasi_biaya_rutin.id_regional', $id_regional))
                ->groupBy('id_kpc');

            // Subquery Produksi
            $subquery_pd = DB::table('produksi_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($request->filled('triwulan'), fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->when($request->filled('tahun'), fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($request->filled('id_regional'), fn($q) => $q->where('produksi.id_regional', $id_regional))
                ->groupBy('id_kpc');

            // Rekap per KPRK
            $baseQuery = DB::table('kprk')
                ->select(
                    'kprk.id AS id_kprk',
                    'kprk.nama AS nama_kprk',
                    DB::raw('COALESCE(SUM(vbrd.pelaporan),0) AS hasil_pelaporan_biaya'),
                    DB::raw('COALESCE(SUM(vbrd.hasil_verifikasi),0) AS hasil_verifikasi_biaya'),
                    DB::raw('COALESCE(SUM(pd.pelaporan),0) AS hasil_pelaporan_pendapatan'),
                    DB::raw('COALESCE(SUM(pd.hasil_verifikasi),0) AS hasil_verifikasi_pendapatan')
                )
                ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoinSub($subquery_vbrd, 'vbrd', fn($join) => $join->on('kpc.id', '=', 'vbrd.id_kpc'))
                ->leftJoinSub($subquery_pd, 'pd', fn($join) => $join->on('kpc.id', '=', 'pd.id_kpc'))
                ->when($request->filled('id_regional'), fn($q) => $q->where('kprk.id_regional', $id_regional))
                ->when($request->filled('id_kprk'), fn($q) => $q->where('kprk.id', $id_kprk))
                ->groupBy('kprk.id');

            $result = $baseQuery->orderBy($orderCol, $orderDir)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $countQuery = DB::table('kprk')
                ->when($request->filled('id_regional'), fn($q) => $q->where('kprk.id_regional', $id_regional))
                ->when($request->filled('id_kprk'), fn($q) => $q->where('kprk.id', $id_kprk))
                ->distinct('kprk.id')
                ->count('kprk.id');

            $data = collect($result)->map(fn($item) => [
                'id_kprk'                    => $item->id_kprk,
                'nama_kprk'                  => $item->nama_kprk,
                'hasil_pelaporan_biaya'      => round($item->hasil_pelaporan_biaya),
                'hasil_pelaporan_pendapatan' => round($item->hasil_pelaporan_pendapatan),
                'hasil_verifikasi_biaya'     => round($item->hasil_verifikasi_biaya),
                'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan),
                'deviasi_biaya'              => round($item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya),
                'deviasi_produksi'           => round($item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan),
                'deviasi_akhir'              => round($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan),
            ]);

            return response()->json([
                'status'      => 'SUCCESS',
                'offset'      => $offset,
                'limit'       => $limit,
                'order'       => $getOrder,
                'total_data'  => $countQuery,
                'data'        => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun'       => 'nullable|numeric',
                'triwulan'    => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'id_kprk'     => 'nullable|numeric|exists:kprk,id',
                'offset'      => 'integer|min:0',
                'limit'       => 'integer|min:1|max:1000',
                'order'       => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset      = (int) $request->get('offset', 0);
            $limit       = (int) $request->get('limit', 100);
            $getOrder    = $request->get('order', '');
            $id_regional = $request->get('id_regional');
            $id_kprk     = $request->get('id_kprk');
            $tahun       = $request->get('tahun');
            $triwulan    = $request->get('triwulan');

            $orderMappings = [
                'namaASC'  => ['kpc.nama', 'ASC'],
                'namaDESC' => ['kpc.nama', 'DESC'],
            ];
            [$orderCol, $orderDir] = $orderMappings[$getOrder] ?? ['kpc.id', 'ASC'];

            $mainQuery = DB::table('kprk')
                ->join('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->when($request->filled('id_regional'), fn($q) => $q->where('kprk.id_regional', $id_regional))
                ->when($request->filled('id_kprk'), fn($q) => $q->where('kprk.id', $id_kprk))
                ->select('kpc.id', 'kpc.nomor_dirian as id_kpc', 'kpc.nama as nama_kpc')
                ->orderBy($orderCol, $orderDir);

            $total  = (clone $mainQuery)->count();
            $result = $mainQuery->offset($offset)->limit($limit)->get();
            $kpcIds = $result->pluck('id')->all();

            $biaya = DB::table('verifikasi_biaya_rutin_detail')
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->whereIn('id_kpc', $kpcIds)
                ->when($request->filled('tahun'), fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                ->groupBy('id_kpc')
                ->get()
                ->keyBy('id_kpc');

            $produksi = DB::table('produksi_detail')
                ->join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->whereIn('id_kpc', $kpcIds)
                ->when($request->filled('tahun'), fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('id_kpc')
                ->get()
                ->keyBy('id_kpc');

            $data = $result->map(function ($item) use ($biaya, $produksi) {
                $b = $biaya[$item->id]    ?? (object)['pelaporan' => 0, 'hasil_verifikasi' => 0];
                $p = $produksi[$item->id] ?? (object)['pelaporan' => 0, 'hasil_verifikasi' => 0];

                return [
                    'id_kpc'                      => $item->id_kpc,
                    'nama_kpc'                    => $item->nama_kpc,
                    'hasil_pelaporan_biaya'       => round($b->pelaporan),
                    'hasil_verifikasi_biaya'      => round($b->hasil_verifikasi),
                    'hasil_pelaporan_pendapatan'  => round($p->pelaporan),
                    'hasil_verifikasi_pendapatan' => round($p->hasil_verifikasi),
                    'deviasi_biaya'               => round($b->pelaporan - $b->hasil_verifikasi),
                    'deviasi_produksi'            => round($p->pelaporan - $p->hasil_verifikasi),
                    'deviasi_akhir'               => round($b->hasil_verifikasi - $p->hasil_verifikasi),
                ];
            });

            return response()->json([
                'status'     => 'SUCCESS',
                'offset'     => $offset,
                'limit'      => $limit,
                'order'      => $getOrder,
                'data'       => $data,
                'total_data' => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun'       => 'nullable|numeric',
                'triwulan'    => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $id_regional = $request->get('id_regional');
            $id_kprk     = $request->get('id_kprk');
            $tahun       = $request->get('tahun');
            $triwulan    = $request->get('triwulan');

            // vbrd per KPC (bukan per KPRK)
            $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($request->filled('triwulan'), fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                ->when($request->filled('tahun'), fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                ->when($request->filled('id_regional'), fn($q) => $q->where('verifikasi_biaya_rutin.id_regional', $id_regional))
                ->groupBy('id_kpc');

            // produksi per KPC
            $subquery_pd = DB::table('produksi_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($request->filled('triwulan'), fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->when($request->filled('tahun'), fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($request->filled('id_regional'), fn($q) => $q->where('produksi.id_regional', $id_regional))
                ->groupBy('id_kpc');

            $result = DB::table('kprk')
                ->select(
                    'kprk.id AS id_kprk',
                    'kprk.nama AS nama_kprk',
                    DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
                    DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
                    DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
                    DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
                )
                ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
                ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')
                ->when($request->filled('id_regional'), fn($q) => $q->where('kprk.id_regional', $id_regional))
                ->when($request->filled('id_kprk'), fn($q) => $q->where('kprk.id', $id_kprk))
                ->groupBy('kprk.id')
                ->get();

            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    'id_kprk'                     => $item->id_kprk,
                    'nama_kprk'                   => $item->nama_kprk,
                    'hasil_pelaporan_biaya'       => round($item->hasil_pelaporan_biaya ?? 0),
                    'hasil_pelaporan_pendapatan'  => round($item->hasil_pelaporan_pendapatan ?? 0),
                    'hasil_verifikasi_biaya'      => round($item->hasil_verifikasi_biaya ?? 0),
                    'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan ?? 0),
                    'deviasi_biaya'               => round(($item->hasil_pelaporan_biaya ?? 0) - ($item->hasil_verifikasi_biaya ?? 0)),
                    'deviasi_produksi'            => round(($item->hasil_pelaporan_pendapatan ?? 0) - ($item->hasil_verifikasi_pendapatan ?? 0)),
                    'deviasi_akhir'               => round(($item->hasil_verifikasi_biaya ?? 0) - ($item->hasil_verifikasi_pendapatan ?? 0)),
                ];
            }

            // Logging sederhana
            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Cetak Kertas Kerja Verifikasi',
                'modul'     => 'Kertas Kerja Verifikasi',
                'id_user'   => Auth::id(),
            ]);

            $export      = new LaporanKertasKerjaVerifikasiExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer      = new Xlsx($spreadsheet);

            $filename = 'laporan-kertas-kerja-verifikasi.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportDetail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun'       => 'nullable|numeric',
                'triwulan'    => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'id_kprk'     => 'nullable|numeric|exists:kprk,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $id_kprk     = $request->get('id_kprk');
            $id_regional = $request->get('id_regional');
            $tahun       = $request->get('tahun');
            $triwulan    = $request->get('triwulan');

            $qAlokasi = DB::table('alokasi_dana')
                ->select('id_kpc', DB::raw('SUM(alokasi_dana_lpu) AS alokasi_dana_lpu'))
                ->when($request->filled('tahun'), fn($q) => $q->where('tahun', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('triwulan', $triwulan))
                ->groupBy('id_kpc');

            $qAtribusi = DB::table('biaya_atribusi_detail')
                ->select(
                    'biaya_atribusi.id_kprk',
                    DB::raw('SUM(biaya_atribusi_detail.pelaporan) AS pelaporan'),
                    DB::raw('SUM(biaya_atribusi_detail.verifikasi) AS verifikasi')
                )
                ->leftJoin('biaya_atribusi', 'biaya_atribusi.id', '=', 'biaya_atribusi_detail.id_biaya_atribusi')
                ->when($request->filled('tahun'), fn($q) => $q->where('biaya_atribusi.tahun_anggaran', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('biaya_atribusi.triwulan', $triwulan))
                ->when($request->filled('id_regional'), fn($q) => $q->where('biaya_atribusi.id_regional', $id_regional))
                ->groupBy('biaya_atribusi.id_kprk');

            $qBiayaRutin = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'verifikasi_biaya_rutin.id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS verifikasi')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($request->filled('tahun'), fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                ->when($request->filled('id_regional'), fn($q) => $q->where('verifikasi_biaya_rutin.id_regional', $id_regional))
                ->groupBy('verifikasi_biaya_rutin.id_kpc');

            $qVerifikasiLPU = DB::table('produksi_detail')
                ->selectRaw('
                    produksi.id_kpc as id_kpc,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_outgoing_lpu,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_incoming_lpu,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_sisa_layanan_lpu,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_outgoing_lpu,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_incoming_lpu,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_sisa_layanan_lpu
                ')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->whereIn('kategori_produksi', ['LAYANAN POS UNIVERSAL', 'LAYANAN POS KOMERSIL'])
                ->when($request->filled('tahun'), fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc');

            $qVerifikasi = DB::table('produksi_detail')
                ->selectRaw('
                    produksi.id_kpc as id_kpc,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_outgoing,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_sisa_layanan,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_sisa_layanan,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_outgoing,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_incoming,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_incoming
                ')
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($request->filled('tahun'), fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($request->filled('triwulan'), fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc');

            $query = Kpc::query()
                ->where('kpc.id_kprk', $id_kprk)
                ->select([
                    'kpc.id AS id_kpc',
                    'kpc.nama AS nama_kpc',
                    'verifikasi.pelaporan_outgoing',
                    'verifikasi.pelaporan_incoming',
                    'verifikasi.verifikasi_incoming',
                    'verifikasi.pelaporan_sisa_layanan',
                    'verifikasi.verifikasi_outgoing',
                    'verifikasi.verifikasi_sisa_layanan',
                    'atribusi.pelaporan AS pelaporan_atribusi',
                    'atribusi.verifikasi AS verifikasi_atribusi',
                    'biaya.pelaporan AS pelaporan_rutin',
                    'biaya.verifikasi AS verifikasi_rutin',
                    'alokasi_dana.alokasi_dana_lpu AS alokasi_dana_lpu',
                    'verifikasi_lpu.pelaporan_outgoing_lpu',
                    'verifikasi_lpu.pelaporan_incoming_lpu',
                    'verifikasi_lpu.pelaporan_sisa_layanan_lpu',
                    'verifikasi_lpu.verifikasi_outgoing_lpu',
                    'verifikasi_lpu.verifikasi_incoming_lpu',
                    'verifikasi_lpu.verifikasi_sisa_layanan_lpu',
                    'kpc.id_kprk as id_kprk',
                ])
                ->leftJoinSub($qAlokasi,      'alokasi_dana',   'alokasi_dana.id_kpc', '=', 'kpc.id')
                ->leftJoinSub($qAtribusi,     'atribusi',       'atribusi.id_kprk',    '=', 'kpc.id_kprk')
                ->leftJoinSub($qBiayaRutin,   'biaya',          'biaya.id_kpc',        '=', 'kpc.id')
                ->leftJoinSub($qVerifikasiLPU, 'verifikasi_lpu', 'verifikasi_lpu.id_kpc', '=', 'kpc.id')
                ->leftJoinSub($qVerifikasi,   'verifikasi',     'verifikasi.id_kpc',   '=', 'kpc.id')
                ->groupBy('kpc.id');

            $result = $query->get();

            $data = [];
            foreach ($result as $item) {
                $pelaporan_kprk_biaya      = ($item->pelaporan_atribusi ?? 0) + ($item->pelaporan_rutin ?? 0);
                $pelaporan_transfer_pricing = ($item->pelaporan_outgoing_lpu ?? 0) + ($item->pelaporan_incoming_lpu ?? 0);
                $total_laporan_kprk        = ($item->pelaporan_sisa_layanan_lpu ?? 0) + $pelaporan_transfer_pricing;

                $hasil_biaya               = ($item->verifikasi_atribusi ?? 0) + ($item->verifikasi_rutin ?? 0);
                $hasil_transfer_pricing    = ($item->verifikasi_outgoing_lpu ?? 0) + ($item->verifikasi_incoming_lpu ?? 0);
                $total_hasil_verifikasi    = ($item->verifikasi_sisa_layanan_lpu ?? 0) + $hasil_transfer_pricing;

                $deviasi_biaya             = $pelaporan_kprk_biaya - $hasil_biaya;
                $deviasi_sisa              = ($item->pelaporan_sisa_layanan_lpu ?? 0) - ($item->verifikasi_sisa_layanan_lpu ?? 0);
                $deviasi_transfer          = $pelaporan_transfer_pricing - $hasil_transfer_pricing;
                $deviasi_produksi          = $total_laporan_kprk - $total_hasil_verifikasi;
                $deviasi_akhir             = $total_hasil_verifikasi - $hasil_biaya;

                $data[] = [
                    'id_kprk'                    => $item->id_kprk,
                    'nama_kpc'                   => $item->nama_kpc,
                    'alokasi_dana_lpu'           => $item->alokasi_dana_lpu ?? 0,
                    'pelaporan_kprk_biaya'       => $pelaporan_kprk_biaya,
                    'pelaporan_sisa_layanan'     => $item->pelaporan_sisa_layanan ?? 0,
                    'pelaporan_transfer_pricing' => $pelaporan_transfer_pricing,
                    'total_laporan_kprk'         => $total_laporan_kprk,
                    'hasil_biaya'                => $hasil_biaya,
                    'verifikasi_sisa_layanan'    => $item->verifikasi_sisa_layanan ?? 0,
                    'hasil_transfer_pricing'     => $hasil_transfer_pricing,
                    'total_hasil_verifikasi'     => $total_hasil_verifikasi,
                    'deviasi_biaya'              => $deviasi_biaya,
                    'deviasi_sisa'               => $deviasi_sisa,
                    'deviasi_transfer'           => $deviasi_transfer,
                    'deviasi_produksi'           => $deviasi_produksi,
                    'deviasi_akhir'              => $deviasi_akhir,
                ];
            }

            $export      = new LaporanKertasKerjaVerifikasiDetailExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer      = new Xlsx($spreadsheet);

            $filename = 'laporan-kertas-kerja-verifikasi-detail.xlsx';
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
