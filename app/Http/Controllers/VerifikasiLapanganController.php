<?php

namespace App\Http\Controllers;

use App\Exports\VerifikasiLapanganExport;
use App\Models\UserLog;
use App\Models\AlokasiDana;
use App\Models\KabupatenKota;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kpc;
use App\Models\PencatatanKantor;
use App\Models\PetugasKPC;
use App\Models\Provinsi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerifikasiLapanganController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'bulan' => 'nullable|numeric',
                'id_provinsi' => 'nullable|numeric|exists:provinsi,id',
                'id_kabupaten_kota' => 'nullable|numeric|exists:kabupaten_kota,id',
                'id_kecamatan' => 'nullable|numeric|exists:kecamatan,id',
                'id_kelurahan' => 'nullable|numeric|exists:kelurahan,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $idProvinsi = request()->get('id_provinsi', '');
            $idKab = request()->get('id_kabupaten_kota', '');
            $idKec = request()->get('id_kecamatan', '');
            $idKel = request()->get('id_kelurahan', '');
            $tahun = request()->get('tahun', '');
            $bulan = request()->get('bulan', '');

            $defaultOrder = $getOrder ? $getOrder : "pencatatan_kantor.id_kelurahan ASC";
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
            $validOrderitems = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderitems",
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
            // dd($order);
            $query = PencatatanKantor::select([
                'pencatatan_kantor.*',
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (1, 61, 4) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_operasional'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (31, 36, 43, 67) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_sarana'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (17, 22, 25, 27) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_wilayah'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya = 15 THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_pegawai'),
            ])
                // join('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                // join('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                // join('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
                // gunakan LEFT JOIN agar pencatatan tanpa kuis tetap muncul
                ->leftJoin('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->leftJoin('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->leftJoin('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
                 // join lokasi supaya bisa search dan menampilkan nama
                 ->leftJoin('kelurahan', 'pencatatan_kantor.id_kelurahan', '=', 'kelurahan.id')
                 ->leftJoin('kecamatan', 'pencatatan_kantor.id_kecamatan', '=', 'kecamatan.id')
                 ->leftJoin('kabupaten_kota', 'pencatatan_kantor.id_kabupaten', '=', 'kabupaten_kota.id')
                 ->leftJoin('provinsi', 'pencatatan_kantor.id_provinsi', '=', 'provinsi.id')
                // ->where('pencatatan_kantor.jenis', 'Verifikasi Lapangan')
                // case-insensitive trim match supaya variasi 'survey' / spacing tidak mem-filter keluar data
                ->whereRaw("LOWER(TRIM(pencatatan_kantor.jenis)) = ?", ['verifikasi lapangan'])
                 // group by primary key (agregasi per pencatatan_kantor)
                 ->groupBy('pencatatan_kantor.id');

            if ($idProvinsi) {
                $query->where('pencatatan_kantor.id_provinsi', $idProvinsi);
            }
            if ($idKab) {
                $query->where('pencatatan_kantor.id_kabupaten', $idKab);
            }
            if ($idKec) {
                $query->where('pencatatan_kantor.id_kecamatan', $idKec);
            }
            if ($idKel) {
                $query->where('pencatatan_kantor.id_kelurahan', $idKel);
            }
            if ($tahun) {
                $query->whereYear('pencatatan_kantor.created', $tahun);
            }
            if ($bulan) {
                $query->whereMonth('pencatatan_kantor.created', $bulan);
            }

            if ($search !== '') {
                $query->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }

            // hitung total setelah semua filter/search diterapkan
            $total_data = $query->count();

            $result = $query
            ->orderByRaw($order)
            ->offset($offset)
            ->limit($limit)->get();
            // dd($result);
            $data = [];
            foreach ($result as $item) {
                $kpc = Kpc::find($item->id_kpc);
                $provinsi = Provinsi::find($item->id_provinsi);
                // dd($provinsi);
                $kabupaten = KabupatenKota::find($item->id_kabupaten);
                $kecamatan = Kecamatan::find($item->id_kecamatan);
                $kelurahan = Kelurahan::find($item->id_kelurahan);
                // dd($item->id_kelurahan);
                $petugas = PetugasKPC::select('nama_petugas')->where('id_kpc', $item->id_kpc)->get();
                $nilai_akhir = (
                    $item->aspek_operasional +
                    $item->aspek_sarana +
                    $item->aspek_wilayah +
                    $item->aspek_pegawai
                ) / 4; // Membagi dengan jumlah aspek (4)
                $kesimpulan = ($nilai_akhir < 50 ? 'Tidak Diusulkan Mendapatkan Subsidi Operasional LPU' : 'Melanjutkan Mendapatkan Subsidi Operasional LPU');
                $data[] = [
                    'id' => $item->id,
                    'tanggal' => $item->created,
                    'petugas_list' => $petugas,
                    'kode_pos' => $kpc->nomor_dirian ?? "",
                    'provinsi' => $provinsi->nama ?? "",
                    'kabupaten' => $kabupaten->nama ?? "",
                    'kecamatan' => $kecamatan->nama ?? "",
                    'kelurahan' => $kelurahan->nama ?? "",
                    'kantor_lpu' => $kpc->nama ?? "",
                    'aspek_operasional' => round($item->aspek_operasional),
                    'aspek_sarana' => round($item->aspek_sarana),
                    'aspek_wilayah' => round($item->aspek_wilayah),
                    'aspek_pegawai' => round($item->aspek_pegawai),
                    'nilai_akhir' => round($nilai_akhir),
                    'kesimpulan' => $kesimpulan,
                ];

            }

            // dd($data);

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
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
                'bulan' => 'nullable|numeric',
                'id_provinsi' => 'nullable|numeric|exists:provinsi,id',
                'id_kabupaten_kota' => 'nullable|numeric|exists:kabupaten_kota,id',
                'id_kecamatan' => 'nullable|numeric|exists:kecamatan,id',
                'id_kelurahan' => 'nullable|numeric|exists:kelurahan,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $idProvinsi = request()->get('id_provinsi', '');
            $idKab = request()->get('id_kabupaten_kota', '');
            $idKec = request()->get('id_kecamatan', '');
            $idKel = request()->get('id_kelurahan', '');
            $tahun = request()->get('tahun', '');
            $bulan = request()->get('bulan', '');

            $defaultOrder = $getOrder ? $getOrder : "pencatatan_kantor.id_kelurahan ASC";
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
            $validOrderitems = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderitems",
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
            // dd($order);
            $query = PencatatanKantor::select([
                'pencatatan_kantor.*',
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (1, 61, 4) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_operasional'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (31, 36, 43, 67) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_sarana'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (17, 22, 25, 27) THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_wilayah'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya = 15 THEN COALESCE(kj.skor,0)
                            ELSE 0
                            END), 2) AS aspek_pegawai'),
            ])
                ->join('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->join('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->join('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
                ->where('pencatatan_kantor.jenis', 'Verifikasi Lapangan')
                ->groupBy('pencatatan_kantor_kuis.id_parent', 'pencatatan_kantor.id_kpc');
                $total_data = $query->count();

            if ($idProvinsi) {
                $query->where('pencatatan_kantor.id_provinsi', $idProvinsi);
            }
            if ($idKab) {
                $query->where('pencatatan_kantor.id_kabupaten', $idKab);
            }
            if ($idKec) {
                $query->where('pencatatan_kantor.id_kecamatan', $idKec);
            }
            if ($idKel) {
                $query->where('pencatatan_kantor.id_kelurahan', $idKel);
            }
            if ($tahun) {
                $query->whereYear('pencatatan_kantor.created', $tahun);
            }
            if ($bulan) {
                $query->whereMonth('pencatatan_kantor.created', $bulan);
            }

            if ($search !== '') {
                $query->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }
            $result = $query
            ->orderByRaw($order)->get();

            $data = [];
            foreach ($result as $item) {
                $kpc = Kpc::find($item->id_kpc);
                $provinsi = Provinsi::find($item->id_provinsi);
                $kabupaten = KabupatenKota::find($item->id_kabupaten);
                $kecamatan = Kecamatan::find($item->id_kecamatan);
                $kelurahan = Kelurahan::find($item->id_kelurahan);
                $petugas = PetugasKPC::select('nama_petugas')->where('id_kpc', $item->id_kpc)->get();
                $nilai_akhir = (
                    $item->aspek_operasional +
                    $item->aspek_sarana +
                    $item->aspek_wilayah +
                    $item->aspek_pegawai
                ) / 4; // Membagi dengan jumlah aspek (4)
                $kesimpulan = ($nilai_akhir < 50 ? 'Tidak Diusulkan Mendapatkan Subsidi Operasional LPU' : 'Melanjutkan Mendapatkan Subsidi Operasional LPU');
                $data[] = [
                    'id' => $item->id,
                    'tanggal' => $item->created,
                    'petugas_list' => $petugas,
                    'kode_pos' => $kpc->nomor_dirian ?? "",
                    'provinsi' => $provinsi->nama ?? "",
                    'kabupaten' => $kabupaten->nama ?? "",
                    'kecamatan' => $kecamatan->nama ?? "",
                    'kelurahan' => $kelurahan->nama ?? "",
                    'kantor_lpu' => $kpc->nama ?? "",
                    'aspek_operasional' => round($item->aspek_operasional),
                    'aspek_sarana' => round($item->aspek_sarana),
                    'aspek_wilayah' => round($item->aspek_wilayah),
                    'aspek_pegawai' => round($item->aspek_pegawai),
                    'nilai_akhir' => round($nilai_akhir),
                    'kesimpulan' => $kesimpulan,
                ];

            }

            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Verifikasi Lapangan',
                'modul' => 'Verifikasi Lapangan',
                'id_user' => Auth::user(),
            ];
            $userLog = UserLog::create($userLog);
            $export = new VerifikasiLapanganExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new WriterXlsx($spreadsheet);

            $filename = 'verifikasi-lapangan.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {

            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'pencatatan_kantor' => 'required|array',
                'pencatatan_kantor.id_kpc' => 'required|numeric',
                'pencatatan_kantor.id_user' => 'required|numeric',
                'pencatatan_kantor.id_provinsi' => 'nullable|numeric',
                'pencatatan_kantor.id_kabupaten' => 'nullable|numeric',
                'pencatatan_kantor.id_kecamatan' => 'nullable|numeric',
                'pencatatan_kantor.id_kelurahan' => 'nullable|string',
                'pencatatan_kantor.jenis' => 'nullable|string',
                'pencatatan_kantor.latitude' => 'nullable|numeric',
                'pencatatan_kantor.longitude' => 'nullable|numeric',
                'pencatatan_kantor.tanggal' => 'nullable|date',
                'pencatatan_kantor_user' => 'nullable|array',
                'pencatatan_kantor_kuis' => 'nullable|array',
            ];

            $payload = $request->all();
            $raw = $request->getContent();
            if (empty($payload) && !empty($raw)) {
                $decodedRaw = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRaw)) {
                    $payload = $decodedRaw;
                }
            }

            foreach (['pencatatan_kantor', 'pencatatan_kantor_user', 'pencatatan_kantor_kuis'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key])) {
                    $decoded = json_decode($payload[$key], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $payload[$key] = $decoded;
                    }
                }
            }

            if (!is_array($payload)) {
                $payload = [];
            }

            $validator = Validator::make($payload, $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $pk = $payload['pencatatan_kantor'];
            
            if (!empty($pk['id']) && $existing = PencatatanKantor::find($pk['id'])) {
                $existing->fill($pk);
                $existing->save();
                $pencatatan = $existing;
            } else {
                $pencatatan = PencatatanKantor::create($pk);
            }

            $pkUser = $payload['pencatatan_kantor_user'] ?? null;
             if (!empty($pkUser) && is_array($pkUser)) {
                 $insertUser = [
                     'rowid_parent' => $pkUser['rowid_parent'] ?? null,
                     'id_parent' => $pencatatan->id,
                     'id_user' => $pkUser['id_user'] ?? $pk['id_user'],
                 ];
                 
                if (!empty($pkUser['rowid_parent'])) {
                    DB::table('pencatatan_kantor_user')->updateOrInsert(
                        ['rowid_parent' => $pkUser['rowid_parent']],
                        $insertUser
                    );
                } else {
                    DB::table('pencatatan_kantor_user')->insert($insertUser);
                }
            }

            $kuisList = $payload['pencatatan_kantor_kuis'] ?? [];
            foreach ($kuisList as $kuis) {
                $kuisInsert = [
                    'rowid_parent' => $kuis['rowid_parent'] ?? null,
                    'id_parent' => $pencatatan->id,
                    'id_tanya' => $kuis['id_tanya'] ?? null,
                    'id_jawab' => $kuis['id_jawab'] ?? null,
                    'data' => $kuis['data'] ?? null,
                ];

                if (!empty($kuis['rowid_parent'])) {
                    DB::table('pencatatan_kantor_kuis')->updateOrInsert(
                        ['rowid_parent' => $kuis['rowid_parent']],
                        $kuisInsert
                    );
                } else {
                    DB::table('pencatatan_kantor_kuis')->insert($kuisInsert);
                }

                if (!empty($kuis['file']) && is_array($kuis['file']) && !empty($kuis['file']['file'])) {
                    $file = $kuis['file'];
                    $b64 = $file['file'];
                    if (strpos($b64, ';base64,') !== false) {
                        $parts = explode(';base64,', $b64);
                        $b64 = $parts[1];
                    }
                    $decoded = base64_decode($b64);
                    if ($decoded === false) {
                        continue;
                    }

                    $fileName = $file['file_name'] ?? ($file['nama'] ?? (Str::random(8) . '.bin'));
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $storePath = 'pencatatan_kantor/' . $pencatatan->id . '/kuis/';
                    $storedName = ($kuis['id_tanya'] ?? 'tanya') . '_' . Str::random(8) . ($ext ? '.' . $ext : '');
                    $fullPath = $storePath . $storedName;

                    Storage::disk('public')->put($fullPath, $decoded);

                    $fileRecord = [
                        'rowid_parent' => $file['rowid_parent'] ?? null,
                        'id_parent' => $kuis['id_tanya'] ?? $file['id_parent'] ?? $pencatatan->id,
                        'nama' => $file['nama'] ?? $fileName,
                        'file' => $fullPath,
                        'file_name' => $fileName,
                        'file_type' => $file['file_type'] ?? null,
                        'created' => $file['created'] ?? now(),
                        'updated' => $file['updated'] ?? now(),
                    ];

                    if (!empty($file['rowid_parent'])) {
                        DB::table('pencatatan_kantor_file')->updateOrInsert(
                            ['rowid_parent' => $file['rowid_parent']],
                            $fileRecord
                        );
                    } else {
                        DB::table('pencatatan_kantor_file')->insert($fileRecord);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Data saved',
                'id' => $pencatatan->id,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
