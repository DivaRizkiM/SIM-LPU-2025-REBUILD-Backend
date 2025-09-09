<?php

namespace App\Http\Controllers;

use App\Exports\LaporanVerifikasiPendapatanExport;
use App\Models\Kpc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

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
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_regional = request()->get('id_regional', '');
            $id_kprk = request()->get('id_kprk', '');
            $tahun = request()->get('tahun', '');

            $triwulan = request()->get('triwulan', '');
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
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
            $query = Kpc::select([
                'kpc.nomor_dirian AS nomor_dirian',
                'kpc.nama AS nama_kpc',
                'kpc.id_regional AS id_regional',
                'kpc.id_kprk AS id_kprk',
                'kprk.nama AS nama_kprk',
                'regional.nama AS nama_regional',
                'produksi_detail.kategori_produksi AS kategori_produksi',
                'produksi_detail.jenis_produksi AS jenis_produksi',
                DB::raw('(SUM(produksi_detail.verifikasi)) AS hasil_verifikasi'),
            ])
                ->leftJoin('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->leftJoin('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoin('regional', 'regional.id', '=', 'kpc.id_regional')
                ->groupBy('nomor_dirian', 'kategori_produksi')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $query->where('kpc.nama', 'like', "%$search%");
            }

            if ($tahun !== '') {
                $query->where('produksi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $query->where('produksi.triwulan', $triwulan);
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
                $nomor_dirian = $value->nomor_dirian;

                if (!isset($dataBaru[$nomor_dirian])) {
                    $dataBaru[$nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_lpu' => 0, // Inisialisasi total_lpu
                        'total_lpk' => 0, // Inisialisasi total_lpk
                        'total_lbf' => 0, // Inisialisasi total_lbf
                        'jumlah_pendapatan' => 0, // Inisialisasi jumlah_pendapatan
                        'kategori_produksi' => [],
                    ];
                }

                $dataBaru[$nomor_dirian]['kategori_produksi'][$value->kategori_produksi] = round($value->hasil_verifikasi ?? 0);
                switch ($value->kategori_produksi) {
                    case 'LAYANAN POS UNIVERSAL':
                        $dataBaru[$nomor_dirian]['total_lpu'] = round($value->hasil_verifikasi ?? 0);
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $dataBaru[$nomor_dirian]['total_lpk'] = round($value->hasil_verifikasi ?? 0);
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $dataBaru[$nomor_dirian]['total_lbf'] = round($value->hasil_verifikasi ?? 0);
                        break;
                }

                $dataBaru[$nomor_dirian]['jumlah_pendapatan'] =
                    $dataBaru[$nomor_dirian]['total_lpu'] +
                    $dataBaru[$nomor_dirian]['total_lpk'] +
                    $dataBaru[$nomor_dirian]['total_lbf'];
            }

            // Format ulang data ke dalam array yang sesuai
            $datas = array_values($dataBaru);
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $datas,
            ]);
        } catch (\Exception $e) {
            // dd($e);
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
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_regional = request()->get('id_regional', '');
            $id_kprk = request()->get('id_kprk', '');
            $tahun = request()->get('tahun', '');

            $triwulan = request()->get('triwulan', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.nomor_dirian ASC";
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
            $query = Kpc::select([
                'kpc.nomor_dirian AS nomor_dirian',
                'kpc.nama AS nama_kpc',
                'kpc.id_regional AS id_regional',
                'kpc.id_kprk AS id_kprk',
                'kprk.nama AS nama_kprk',
                'regional.nama AS nama_regional',
                'produksi_detail.kategori_produksi AS kategori_produksi',
                'produksi_detail.jenis_produksi AS jenis_produksi',
                DB::raw('(SUM(produksi_detail.verifikasi)) AS hasil_verifikasi'),
            ])
                ->leftJoin('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->leftJoin('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoin('regional', 'regional.id', '=', 'kpc.id_regional')
                ->groupBy('nomor_dirian', 'kategori_produksi')
                ->orderByRaw($order);

            if ($search !== '') {
                $query->where('kpc.nama', 'like', "%$search%");
            }

            if ($tahun !== '') {
                $query->where('produksi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $query->where('produksi.triwulan', $triwulan);
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
                $nomor_dirian = $value->nomor_dirian;

                if (!isset($dataBaru[$nomor_dirian])) {
                    $dataBaru[$nomor_dirian] = [
                        'nama_regional' => $value->nama_regional,
                        'nama_kprk' => $value->nama_kprk,
                        'nama_kpc' => $value->nama_kpc,
                        'nomor_dirian' => $value->nomor_dirian,
                        'total_lpu' => 0, // Inisialisasi total_lpu
                        'total_lpk' => 0, // Inisialisasi total_lpk
                        'total_lbf' => 0, // Inisialisasi total_lbf
                        'jumlah_pendapatan' => 0, // Inisialisasi jumlah_pendapatan
                        'kategori_produksi' => [],
                    ];
                }


                $dataBaru[$nomor_dirian]['kategori_produksi'][$value->kategori_produksi] = round($value->hasil_verifikasi ?? 0);
                switch ($value->kategori_produksi) {
                    case 'LAYANAN POS UNIVERSAL':
                        $dataBaru[$nomor_dirian]['total_lpu'] = round($value->hasil_verifikasi ?? 0);
                        break;
                    case 'LAYANAN POS KOMERSIL':
                        $dataBaru[$nomor_dirian]['total_lpk'] = round($value->hasil_verifikasi ?? 0);
                        break;
                    case 'LAYANAN BERBASIS FEE':
                        $dataBaru[$nomor_dirian]['total_lbf'] = round($value->hasil_verifikasi ?? 0);
                        break;
                }

                $dataBaru[$nomor_dirian]['jumlah_pendapatan'] =
                    $dataBaru[$nomor_dirian]['total_lpu'] +
                    $dataBaru[$nomor_dirian]['total_lpk'] +
                    $dataBaru[$nomor_dirian]['total_lbf'];
            }

            // Format ulang data ke dalam array yang sesuai
            $datas = array_values($dataBaru);
            return Excel::download(new LaporanVerifikasiPendapatanExport($datas), 'template_laporan_pendapatan.xlsx');

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
