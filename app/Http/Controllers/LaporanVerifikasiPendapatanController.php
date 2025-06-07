<?php

namespace App\Http\Controllers;

use App\Exports\LaporanVerifikasiPendapatanExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
class LaporanVerifikasiPendapatanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $id_regional = $request->get('id_regional', '');
            $id_kprk = $request->get('id_kprk', '');
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');

            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];

            $order = $orderMappings[$getOrder] ?? 'kpc.nomor_dirian ASC';

            $query = Kpc::select([
                'kpc.nomor_dirian AS nomor_dirian',
                'kpc.nama AS nama_kpc',
                'kpc.id_regional AS id_regional',
                'kpc.id_kprk AS id_kprk',
                'kprk.nama AS nama_kprk',
                'regional.nama AS nama_regional',
            ])
                ->leftJoin('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoin('regional', 'regional.id', '=', 'kpc.id_regional');
                $total_data = $query->count();
            if ($search !== '') {
                $query->where('kpc.nama', 'like', "%$search%");
            }


            if ($id_regional !== '') {
                $query->where('kpc.id_regional', $id_regional);
            }

            if ($id_kprk !== '') {
                $query->where('kpc.id_kprk', $id_kprk);
            }



            $result = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $dataBaru = [];

            foreach ($result as $value) {
                $queryDetail = DB::table('produksi')
                    ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                    ->select([
                        'produksi.id AS id_produksi',
                        'produksi_detail.kategori_produksi AS kategori_produksi',
                        'produksi_detail.jenis_produksi AS jenis_produksi',
                        DB::raw('SUM(produksi_detail.verifikasi) AS hasil_verifikasi'),
                    ])
                    ->where('produksi.id_kpc', $value->nomor_dirian)
                    ->groupBy('produksi_detail.kategori_produksi','produksi.id');
                    if ($tahun !== '') {
                        $queryDetail->where('produksi.tahun_anggaran', $tahun);
                    }

                    if ($triwulan !== '') {
                        $queryDetail->where('produksi.triwulan', $triwulan);
                    }

                   $details = $queryDetail->get();

                $nomor_dirian = $value->nomor_dirian;

                if (!isset($dataBaru[$nomor_dirian])) {
                    $dataBaru[$nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'jumlah_pendapatan' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                foreach ($details as $detail) {
                    $dataBaru[$nomor_dirian]['kategori_produksi'][$detail->kategori_produksi] = round($detail->hasil_verifikasi ?? 0);
                    switch ($detail->kategori_produksi) {
                        case 'LAYANAN POS UNIVERSAL':
                            $dataBaru[$nomor_dirian]['total_lpu'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                        case 'LAYANAN POS KOMERSIL':
                            $dataBaru[$nomor_dirian]['total_lpk'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                        case 'LAYANAN BERBASIS FEE':
                            $dataBaru[$nomor_dirian]['total_lbf'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                    }
                }

                $dataBaru[$nomor_dirian]['jumlah_pendapatan'] =
                    $dataBaru[$nomor_dirian]['total_lpu'] +
                    $dataBaru[$nomor_dirian]['total_lpk'] +
                    $dataBaru[$nomor_dirian]['total_lbf'];
            }

            $datas = array_values($dataBaru);
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $datas,
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
                'id_kprk' => 'nullable|numeric|exists:kprk,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $id_regional = $request->get('id_regional', '');
            $id_kprk = $request->get('id_kprk', '');
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');

            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];

            $order = $orderMappings[$getOrder] ?? 'kpc.nomor_dirian ASC';

            $query = Kpc::select([
                'kpc.nomor_dirian AS nomor_dirian',
                'kpc.nama AS nama_kpc',
                'kpc.id_regional AS id_regional',
                'kpc.id_kprk AS id_kprk',
                'kprk.nama AS nama_kprk',
                'regional.nama AS nama_regional',
            ])
                ->leftJoin('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoin('regional', 'regional.id', '=', 'kpc.id_regional');

            if ($search !== '') {
                $query->where('kpc.nama', 'like', "%$search%");
            }


            if ($id_regional !== '') {
                $query->where('kpc.id_regional', $id_regional);
            }

            if ($id_kprk !== '') {
                $query->where('kpc.id_kprk', $id_kprk);
            }

            $total_data = $query->count();

            $result = $query->orderByRaw($order)
                ->get();

            $dataBaru = [];

            foreach ($result as $value) {
                $queryDetail = DB::table('produksi')
                    ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                    ->select([
                        'produksi.id AS id_produksi',
                        'produksi_detail.kategori_produksi AS kategori_produksi',
                        'produksi_detail.jenis_produksi AS jenis_produksi',
                        DB::raw('SUM(produksi_detail.verifikasi) AS hasil_verifikasi'),
                    ])
                    ->where('produksi.id_kpc', $value->nomor_dirian)
                    ->groupBy('produksi_detail.kategori_produksi','produksi.id');
                    if ($tahun !== '') {
                        $queryDetail->where('produksi.tahun_anggaran', $tahun);
                    }

                    if ($triwulan !== '') {
                        $queryDetail->where('produksi.triwulan', $triwulan);
                    }

                   $details = $queryDetail->get();

                $nomor_dirian = $value->nomor_dirian;

                if (!isset($dataBaru[$nomor_dirian])) {
                    $dataBaru[$nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'jumlah_pendapatan' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                foreach ($details as $detail) {
                    $dataBaru[$nomor_dirian]['kategori_produksi'][$detail->kategori_produksi] = round($detail->hasil_verifikasi ?? 0);
                    switch ($detail->kategori_produksi) {
                        case 'LAYANAN POS UNIVERSAL':
                            $dataBaru[$nomor_dirian]['total_lpu'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                        case 'LAYANAN POS KOMERSIL':
                            $dataBaru[$nomor_dirian]['total_lpk'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                        case 'LAYANAN BERBASIS FEE':
                            $dataBaru[$nomor_dirian]['total_lbf'] = round($detail->hasil_verifikasi ?? 0);
                            break;
                    }
                }

                $dataBaru[$nomor_dirian]['jumlah_pendapatan'] =
                    $dataBaru[$nomor_dirian]['total_lpu'] +
                    $dataBaru[$nomor_dirian]['total_lpk'] +
                    $dataBaru[$nomor_dirian]['total_lbf'];
            }
            $datas = array_values($dataBaru);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Laporan  Verifikasi Pendapatan',
                'modul' => 'Laporan  Verifikasi Pendapatan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return Excel::download(new LaporanVerifikasiPendapatanExport($datas), 'laporan_verifikasi_pendapatan.xlsx');
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function collection()
    {
        // Kembalikan data yang ingin diekspor
        return $data;
    }
}
