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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];
            $order = $orderMappings[$getOrder] ?? 'kpc.nomor_dirian ASC';

            // Ambil data KPC
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
                ->offset($offset)
                ->limit($limit)
                ->get();

            $nomorDirianList = $result->pluck('nomor_dirian')->toArray();

            // Ambil seluruh data produksi untuk semua KPC sekaligus
            $produksiDetails = DB::table('produksi')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->select(
                    'produksi.id_kpc',
                    'produksi_detail.kategori_produksi',
                    DB::raw('SUM(produksi_detail.verifikasi) as hasil_verifikasi')
                )
                ->whereIn('produksi.id_kpc', $nomorDirianList)
                ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc', 'produksi_detail.kategori_produksi')
                ->get();

            // Kelompokkan produksi berdasarkan id_kpc
            $produksiByKpc = [];
            foreach ($produksiDetails as $detail) {
                $id_kpc = $detail->id_kpc;
                $kategori = $detail->kategori_produksi;
                $hasil = round($detail->hasil_verifikasi ?? 0);

                if (!isset($produksiByKpc[$id_kpc])) {
                    $produksiByKpc[$id_kpc] = [
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                $produksiByKpc[$id_kpc]['kategori_produksi'][$kategori] = $hasil;

                switch ($kategori) {
                    case 'LAYANAN POS UNIVERSAL':
                        $produksiByKpc[$id_kpc]['total_lpu'] = $hasil;
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $produksiByKpc[$id_kpc]['total_lpk'] = $hasil;
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $produksiByKpc[$id_kpc]['total_lbf'] = $hasil;
                        break;
                }
            }

            // Gabungkan ke data utama
            $dataBaru = [];
            foreach ($result as $row) {
                $nomor_dirian = $row->nomor_dirian;
                $produksi = $produksiByKpc[$nomor_dirian] ?? [
                    'total_lpu' => 0,
                    'total_lpk' => 0,
                    'total_lbf' => 0,
                    'kategori_produksi' => [],
                ];
                $totalPendapatan = $produksi['total_lpu'] +
                    $produksi['total_lpk'] +
                    $produksi['total_lbf'];
                $dataBaru[] = [
                    'nama_regional' => $row->nama_regional,
                    'nama_kprk' => $row->nama_kprk,
                    'nama_kpc' => $row->nama_kpc,
                    'nomor_dirian' => $nomor_dirian,
                    'total_lpu' => round($produksi['total_lpu']),
                    'total_lpk' => round($produksi['total_lpk']),
                    'total_lbf' => round($produksi['total_lbf']),
                    'jumlah_pendapatan' => round($totalPendapatan),
                    'kategori_produksi' => $produksi['kategori_produksi'],
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

            // Ambil semua data (tanpa limit)
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $id_regional = $request->get('id_regional', '');
            $id_kprk = $request->get('id_kprk', '');
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');

            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
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

            $result = $query->orderByRaw($order)->get();

            $nomorDirianList = $result->pluck('nomor_dirian')->toArray();

            $produksiDetails = DB::table('produksi')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->select(
                    'produksi.id_kpc',
                    'produksi_detail.kategori_produksi',
                    DB::raw('SUM(produksi_detail.verifikasi) as hasil_verifikasi')
                )
                ->whereIn('produksi.id_kpc', $nomorDirianList)
                ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('produksi.id_kpc', 'produksi_detail.kategori_produksi')
                ->get();

            $produksiByKpc = [];
            foreach ($produksiDetails as $detail) {
                $id_kpc = $detail->id_kpc;
                $kategori = $detail->kategori_produksi;
                $hasil = round($detail->hasil_verifikasi ?? 0);

                if (!isset($produksiByKpc[$id_kpc])) {
                    $produksiByKpc[$id_kpc] = [
                        'total_lpu' => 0,
                        'total_lpk' => 0,
                        'total_lbf' => 0,
                        'kategori_produksi' => [],
                    ];
                }

                $produksiByKpc[$id_kpc]['kategori_produksi'][$kategori] = $hasil;

                switch ($kategori) {
                    case 'LAYANAN POS UNIVERSAL':
                        $produksiByKpc[$id_kpc]['total_lpu'] = $hasil;
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $produksiByKpc[$id_kpc]['total_lpk'] = $hasil;
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $produksiByKpc[$id_kpc]['total_lbf'] = $hasil;
                        break;
                }
            }

            $dataBaru = [];
            foreach ($result as $row) {
                $nomor_dirian = $row->nomor_dirian;
                $produksi = $produksiByKpc[$nomor_dirian] ?? [
                    'total_lpu' => 0,
                    'total_lpk' => 0,
                    'total_lbf' => 0,
                    'kategori_produksi' => [],
                ];
                $totalPendapatan = $produksi['total_lpu'] +
                    $produksi['total_lpk'] +
                    $produksi['total_lbf'];
                $dataBaru[] = [
                    'nama_regional' => $row->nama_regional,
                    'nama_kprk' => $row->nama_kprk,
                    'nama_kpc' => $row->nama_kpc,
                    'nomor_dirian' => $nomor_dirian,
                    'total_lpu' => $produksi['total_lpu'],
                    'total_lpk' => $produksi['total_lpk'],
                    'total_lbf' => $produksi['total_lbf'],
                    'jumlah_pendapatan' => $totalPendapatan,
                ];
            }

            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Cetak Laporan Verifikasi Pendapatan',
                'modul' => 'Laporan Verifikasi Pendapatan',
                'id_user' => Auth::id(),
            ]);

            $export = new LaporanVerifikasiPendapatanExport($dataBaru);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-kertas-kerja-verifikasi-pendapatan.xlsx';
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
