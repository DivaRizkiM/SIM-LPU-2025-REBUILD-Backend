<?php

namespace App\Http\Controllers;

use App\Exports\CakupanWilayahExport;
use App\Models\Kelurahan;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

class CakupanWilayahController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'nullable|numeric|exists:provinsi,id',
                'id_kabupaten_kota' => 'nullable|numeric|exists:kabupaten_kota,id',
                'id_kecamatan' => 'nullable|numeric|exists:kecamatan,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            // Ambil parameter offset, limit, search, dan order dari permintaan
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $idProvinsi = request()->get('id_provinsi', '');
            $idKab = request()->get('id_kabupaten_kota', '');
            $idKec = request()->get('id_kecamatan', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "kelurahan.id ASC";
            $orderMappings = [
                'idASC' => 'kelurahan.id ASC',
                'idDESC' => 'kelurahan.id DESC',
                'namakelurahanASC' => 'kelurahan.nama ASC',
                'namakelurahanDESC' => 'kelurahan.nama DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
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
            $query = Kelurahan::query()
                ->select(
                    'rekonsiliasi.*',
                    'penyelenggara.nama AS nama_penyelenggara',
                    'kelurahan.nama AS nama_kelurahan',
                    'kelurahan.id AS id_kelurahan',
                    'jenis_kantor.nama AS nama_jenis_kantor',
                    // 'kantor.nama AS nama_kantor',
                    'kelurahan.nama AS nama_kelurahan',
                    'kecamatan.nama as nama_kecamatan',
                    'kabupaten_kota.nama as nama_kabupaten',
                    'provinsi.nama as nama_provinsi'
                )
                ->leftjoin('rekonsiliasi', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftjoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftjoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
            // ->join('kantor', 'rekonsiliasi.id_kantor', '=', 'kantor.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id');
                $total_data = $query->count();
            if ($idProvinsi) {
                $query->where('kelurahan.id_provinsi', $idProvinsi);
            }
            if ($idKab) {
                $query->where('kelurahan.id_kabupaten_kota', $idKab);
            }
            if ($idKec) {
                $query->where('kelurahan.id_kecamatan', $idKec);
            }
            if ($search !== '') {
                $kelurahansQuery->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }
            $data = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();
            // Lakukan grouping berdasarkan id_kelurahan untuk mengecek kondisi
            $dataGrouped = $data->groupBy('id_kelurahan');

            foreach ($dataGrouped as $group) {
                $JNT = 'tidak ada';
                $SICEPAT = 'tidak ada';

                // Lakukan pengecekan untuk setiap baris dalam kelompok data
                foreach ($group as $item) {
                    if ($item->id_penyelenggara == 1) {
                        $JNT = 'ada';
                    } elseif ($item->id_penyelenggara == 2) {
                        $SICEPAT = 'ada';
                    }
                }

                // Tambahkan nilai kolom baru JNT dan SICEPAT ke setiap baris dalam kelompok data
                foreach ($group as $item) {
                    $item->JNT = $JNT;
                    $item->SICEPAT = $SICEPAT;
                }
            }

            $dataFiltered = collect();
            foreach ($dataGrouped as $group) {
                // Ambil satu baris saja untuk setiap kelurahan
                $dataFiltered = $dataFiltered->merge($group->unique('id_kelurahan'));
            }

            // Hasil akhir
            $dataFinal = $dataFiltered->sortBy($order)
                ->splice($offset)
                ->take($limit)
                ->all();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $dataFinal,
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
                'id_provinsi' => 'nullable|numeric|exists:provinsi,id',
                'id_kabupaten_kota' => 'nullable|numeric|exists:kabupaten_kota,id',
                'id_kecamatan' => 'nullable|numeric|exists:kecamatan,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            // Ambil parameter offset, limit, search, dan order dari permintaan
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $idProvinsi = request()->get('id_provinsi', '');
            $idKab = request()->get('id_kabupaten_kota', '');
            $idKec = request()->get('id_kecamatan', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "kelurahan.id ASC";
            $orderMappings = [
                'idASC' => 'kelurahan.id ASC',
                'idDESC' => 'kelurahan.id DESC',
                'namakelurahanASC' => 'kelurahan.nama ASC',
                'namakelurahanDESC' => 'kelurahan.nama DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
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
            $query = Kelurahan::query()
                ->select(
                    'rekonsiliasi.*',
                    'penyelenggara.nama AS nama_penyelenggara',
                    'kelurahan.nama AS nama_kelurahan',
                    'kelurahan.id AS id_kelurahan',
                    'jenis_kantor.nama AS nama_jenis_kantor',
                    // 'kantor.nama AS nama_kantor',
                    'kelurahan.nama AS nama_kelurahan',
                    'kecamatan.nama as nama_kecamatan',
                    'kabupaten_kota.nama as nama_kabupaten',
                    'provinsi.nama as nama_provinsi'
                )
                ->leftjoin('rekonsiliasi', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftjoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftjoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
            // ->join('kantor', 'rekonsiliasi.id_kantor', '=', 'kantor.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id');
            if ($idProvinsi) {
                $query->where('kelurahan.id_provinsi', $idProvinsi);
            }
            if ($idKab) {
                $query->where('kelurahan.id_kabupaten_kota', $idKab);
            }
            if ($idKec) {
                $query->where('kelurahan.id_kecamatan', $idKec);
            }
            if ($search !== '') {
                $kelurahansQuery->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }
            $data = $query->get();
            // Lakukan grouping berdasarkan id_kelurahan untuk mengecek kondisi
            $dataGrouped = $data->groupBy('id_kelurahan');

            foreach ($dataGrouped as $group) {
                $JNT = 'tidak ada';
                $SICEPAT = 'tidak ada';

                // Lakukan pengecekan untuk setiap baris dalam kelompok data
                foreach ($group as $item) {
                    if ($item->id_penyelenggara == 1) {
                        $JNT = 'ada';
                    } elseif ($item->id_penyelenggara == 2) {
                        $SICEPAT = 'ada';
                    }
                }

                // Tambahkan nilai kolom baru JNT dan SICEPAT ke setiap baris dalam kelompok data
                foreach ($group as $item) {
                    $item->JNT = $JNT;
                    $item->SICEPAT = $SICEPAT;
                }
            }

            $dataFiltered = collect();
            foreach ($dataGrouped as $group) {
                // Ambil satu baris saja untuk setiap kelurahan
                $dataFiltered = $dataFiltered->merge($group->unique('id_kelurahan'));
            }

            // Hasil akhir
            $dataFinal = $dataFiltered->sortBy($order)
                ->all();

                $userLog=[
                    'timestamp' => now(),
                    'aktifitas' =>'Export Cakupan Wilayah',
                    'modul' => 'Cakupan Wilayah',
                    'id_user' => Auth::user(),
                ];

                $userLog = UserLog::create($userLog);


            return Excel::download(new CakupanWilayahExport($dataFinal), 'cakupan_wilayah.xlsx');
        } catch (\Exception $e) {
            // dd($e);
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

}
