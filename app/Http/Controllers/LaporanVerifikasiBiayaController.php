<?php

namespace App\Http\Controllers;

use App\Exports\LaporanVerifikasiBiayaExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanVerifikasiBiayaController extends Controller
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
                'tahunASC' => 'biaya_atribusi.tahun ASC',
                'tahunDESC' => 'biaya_atribusi.tahun DESC',
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
                $verifikasiQuery = DB::table('verifikasi_biaya_rutin')
                    ->select('verifikasi_biaya_rutin_detail.kategori_biaya AS kategori_biaya')
                    ->selectRaw('(SUM(verifikasi_biaya_rutin_detail.verifikasi)) AS hasil_verifikasi')
                    ->leftJoin('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                    ->where('verifikasi_biaya_rutin.id_kpc', $value->nomor_dirian)->groupBy('verifikasi_biaya_rutin_detail.kategori_biaya','verifikasi_biaya_rutin.id');

                if ($tahun !== '') {
                    $verifikasiQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
                }

                if ($triwulan !== '') {
                    $verifikasiQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
                }

                $verifikasiResults = $verifikasiQuery->get();
                if (!isset($dataBaru[$value->nomor_dirian])) {
                    $dataBaru[$value->nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_biaya_pegawai' => 0,
                        'total_biaya_operasi' => 0,
                        'total_biaya_pemeliharaan' => 0,
                        'total_biaya_administrasi' => 0,
                        'total_biaya_penyusutan' => 0,
                        'jumlah_biaya' => 0,
                        'kategori_biaya' => [],
                    ];
                }
                foreach ($verifikasiResults as $verifikasi) {
                    $kategori = $verifikasi->kategori_biaya;
                    $hasil_verifikasi = round($verifikasi->hasil_verifikasi ?? 0);



                    $dataBaru[$value->nomor_dirian]['kategori_biaya'][$kategori] = $hasil_verifikasi;
                    switch ($kategori) {
                        case 'BIAYA OPERASI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_operasi'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PEGAWAI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_pegawai'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PEMELIHARAAN':
                            $dataBaru[$value->nomor_dirian]['total_biaya_pemeliharaan'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA ADMINISTRASI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_administrasi'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PENYUSUTAN':
                            $dataBaru[$value->nomor_dirian]['total_biaya_penyusutan'] = $hasil_verifikasi;
                            break;
                    }

                    $dataBaru[$value->nomor_dirian]['jumlah_biaya'] =
                        ($dataBaru[$value->nomor_dirian]['total_biaya_pegawai'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_operasi'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_pemeliharaan'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_administrasi'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_penyusutan'] ?? 0);
                }
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
                'tahunASC' => 'biaya_atribusi.tahun ASC',
                'tahunDESC' => 'biaya_atribusi.tahun DESC',
            ];

            $order = $orderMappings[$getOrder] ?? 'kpc.nomor_dirian ASC';

            $query = Kpc::select([
                'kpc.nomor_dirian AS nomor_dirian',
                'kpc.nama AS nama_kpc',
                'kprk.nama AS nama_kprk',
                'regional.nama AS nama_regional',
            ])
                ->leftJoin('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoin('regional', 'regional.id', '=', 'kpc.id_regional')
                ->orderByRaw($order);

            if ($search !== '') {
                $query->where('kpc.nama', 'like', "%$search%");
            }

            if ($id_regional !== '') {
                $query->where('kpc.id_regional', $id_regional);
            }

            if ($id_kprk !== '') {
                $query->where('kpc.id_kprk', $id_kprk);
            }

            $result = $query->get();

            $dataBaru = [];

            foreach ($result as $value) {
                $verifikasiQuery = DB::table('verifikasi_biaya_rutin')
                    ->select('verifikasi_biaya_rutin_detail.kategori_biaya AS kategori_biaya')
                    ->selectRaw('(SUM(verifikasi_biaya_rutin_detail.verifikasi)) AS hasil_verifikasi')
                    ->leftJoin('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                    ->where('verifikasi_biaya_rutin.id_kpc', $value->nomor_dirian);

                if ($tahun !== '') {
                    $verifikasiQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
                }

                if ($triwulan !== '') {
                    $verifikasiQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
                }

                $verifikasiResults = $verifikasiQuery->groupBy('verifikasi_biaya_rutin_detail.kategori_biaya')->get();

                if (!isset($dataBaru[$value->nomor_dirian])) {
                    $dataBaru[$value->nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_biaya_pegawai' => 0,
                        'total_biaya_operasi' => 0,
                        'total_biaya_pemeliharaan' => 0,
                        'total_biaya_administrasi' => 0,
                        'total_biaya_penyusutan' => 0,
                        'jumlah_biaya' => 0,
                        'kategori_biaya' => [],
                    ];
                }
                foreach ($verifikasiResults as $verifikasi) {
                    $kategori = $verifikasi->kategori_biaya;
                    $hasil_verifikasi = round($verifikasi->hasil_verifikasi ?? 0);


                    $dataBaru[$value->nomor_dirian]['kategori_biaya'][$kategori] = $hasil_verifikasi;
                    switch ($kategori) {
                        case 'BIAYA OPERASI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_operasi'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PEGAWAI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_pegawai'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PEMELIHARAAN':
                            $dataBaru[$value->nomor_dirian]['total_biaya_pemeliharaan'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA ADMINISTRASI':
                            $dataBaru[$value->nomor_dirian]['total_biaya_administrasi'] = $hasil_verifikasi;
                            break;
                        case 'BIAYA PENYUSUTAN':
                            $dataBaru[$value->nomor_dirian]['total_biaya_penyusutan'] = $hasil_verifikasi;
                            break;
                    }

                    $dataBaru[$value->nomor_dirian]['jumlah_biaya'] =
                        ($dataBaru[$value->nomor_dirian]['total_biaya_pegawai'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_operasi'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_pemeliharaan'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_administrasi'] ?? 0) +
                        ($dataBaru[$value->nomor_dirian]['total_biaya_penyusutan'] ?? 0);
                }
            }

            $datas = array_values($dataBaru);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Laporan  Verifikasi Biaya',
                'modul' => 'Laporan  Verifikasi Biaya',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $export = new LaporanVerifikasiBiayaExport($dataBaru);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-verifikasi-biaya.xlsx';
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
