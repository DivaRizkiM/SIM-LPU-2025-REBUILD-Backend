<?php

namespace App\Http\Controllers;

use App\Exports\LaporanPrognosaPendapatanExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanPrognosaPendapatanController extends Controller
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

            // Ambil semua nomor_dirian dari KPC
            $nomorDirianList = $result->pluck('nomor_dirian')->toArray();

            // Ambil semua produksi detail untuk seluruh KPC sekaligus
            $allDetails = DB::table('produksi')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->select(
                    'produksi.id_kpc',
                    'produksi_detail.kategori_produksi',
                    DB::raw('SUM(produksi_detail.pelaporan_prognosa) AS hasil_verifikasi')
                )
                ->whereIn('produksi.id_kpc', $nomorDirianList)
                ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc', 'produksi_detail.kategori_produksi')
                ->get();

            // Kelompokkan hasil detail
            $groupedDetails = [];
            foreach ($allDetails as $detail) {
                $id_kpc = $detail->id_kpc;
                $kategori = $detail->kategori_produksi;
                $hasil = round($detail->hasil_verifikasi ?? 0);

                if (!isset($groupedDetails[$id_kpc])) {
                    $groupedDetails[$id_kpc] = [
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                $groupedDetails[$id_kpc]['kategori_produksi'][$kategori] = $hasil;

                switch ($kategori) {
                    case 'LAYANAN POS UNIVERSAL':
                        $groupedDetails[$id_kpc]['total_lpu'] = $hasil;
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $groupedDetails[$id_kpc]['total_lpk'] = $hasil;
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $groupedDetails[$id_kpc]['total_lbf'] = $hasil;
                        break;
                }
            }

            // Satukan data ke array final
            $dataBaru = [];
            foreach ($result as $value) {
                $nomor_dirian = $value->nomor_dirian;
                $details = $groupedDetails[$nomor_dirian] ?? [
                    'total_lpu' => 0,
                    'total_lpk' => 0,
                    'total_lbf' => 0,
                    'kategori_produksi' => [],
                ];

                $dataBaru[] = [
                    'nama_regional' => $value->nama_regional,
                    'nama_kprk' => $value->nama_kprk,
                    'nama_kpc' => $value->nama_kpc,
                    'nomor_dirian' => $value->nomor_dirian,
                    'total_lpu' => $details['total_lpu'],
                    'total_lpk' => $details['total_lpk'],
                    'total_lbf' => $details['total_lbf'],
                    'jumlah_pendapatan' =>
                    $details['total_lpu'] +
                        $details['total_lpk'] +
                        $details['total_lbf'],
                    'kategori_produksi' => $details['kategori_produksi'],
                ];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $dataBaru,
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

            // Ambil semua nomor_dirian dari KPC
            $nomorDirianList = $result->pluck('nomor_dirian')->toArray();

            // Ambil semua produksi detail untuk seluruh KPC sekaligus
            $allDetails = DB::table('produksi')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->select(
                    'produksi.id_kpc',
                    'produksi_detail.kategori_produksi',
                    DB::raw('SUM(produksi_detail.pelaporan_prognosa) AS hasil_verifikasi')
                )
                ->whereIn('produksi.id_kpc', $nomorDirianList)
                ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc', 'produksi_detail.kategori_produksi')
                ->get();

            // Kelompokkan hasil detail
            $groupedDetails = [];
            foreach ($allDetails as $detail) {
                $id_kpc = $detail->id_kpc;
                $kategori = $detail->kategori_produksi;
                $hasil = round($detail->hasil_verifikasi ?? 0);

                if (!isset($groupedDetails[$id_kpc])) {
                    $groupedDetails[$id_kpc] = [
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                $groupedDetails[$id_kpc]['kategori_produksi'][$kategori] = $hasil;

                switch ($kategori) {
                    case 'LAYANAN POS UNIVERSAL':
                        $groupedDetails[$id_kpc]['total_lpu'] = $hasil;
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $groupedDetails[$id_kpc]['total_lpk'] = $hasil;
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $groupedDetails[$id_kpc]['total_lbf'] = $hasil;
                        break;
                }
            }

            // Satukan data ke array final
            $dataBaru = [];
            foreach ($result as $value) {
                $nomor_dirian = $value->nomor_dirian;
                $details = $groupedDetails[$nomor_dirian] ?? [
                    'total_lpu' => 0,
                    'total_lpk' => 0,
                    'total_lbf' => 0,
                    'kategori_produksi' => [],
                ];

                $dataBaru[] = [
                    'nama_regional' => $value->nama_regional,
                    'nama_kprk' => $value->nama_kprk,
                    'nama_kpc' => $value->nama_kpc,
                    'nomor_dirian' => $value->nomor_dirian,
                    'total_lpu' => $details['total_lpu'],
                    'total_lpk' => $details['total_lpk'],
                    'total_lbf' => $details['total_lbf'],
                    'jumlah_pendapatan' =>
                    $details['total_lpu'] +
                        $details['total_lpk'] +
                        $details['total_lbf'],
                    'kategori_produksi' => $details['kategori_produksi'],
                ];
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Cetak Laporan Prognosa Pendapatan',
                'modul' => 'Laporan Prognosa Pendapatan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $export = new LaporanPrognosaPendapatanExport($dataBaru);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-prognosa-pendapatan.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
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
