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
use Illuminate\Support\Facades\Log;
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

            $offset     = (int) $request->get('offset', 0);
            $limit      = (int) $request->get('limit', 10);
            $search     = (string) $request->get('search', '');
            $getOrder   = (string) $request->get('order', '');
            $idProvinsi = $request->get('id_provinsi', '');
            $idKab      = $request->get('id_kabupaten_kota', '');
            $idKec      = $request->get('id_kecamatan', '');
            $idKel      = $request->get('id_kelurahan', '');
            $tahun      = $request->get('tahun', '');
            $bulan      = $request->get('bulan', '');

            $defaultOrder = $getOrder ?: "pencatatan_kantor.created DESC";
            $orderMappings = [
                'namaASC'       => 'kprk.nama ASC',                 // pastikan kolom ini valid kalau dipakai
                'namaDESC'      => 'kprk.nama DESC',
                'triwulanASC'   => 'biaya_atribusi.triwulan ASC',   // hati-hati: tabel/kolom ini tidak muncul di query kamu
                'triwulanDESC'  => 'biaya_atribusi.triwulan DESC',
                'tahunASC'      => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC'     => 'biaya_atribusi.tahun_anggaran DESC',
            ];
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            $validator = Validator::make([
                'offset' => $offset,
                'limit'  => $limit,
                'order'  => $getOrder,
            ], [
                'offset' => 'integer|min:0',
                'limit'  => 'integer|min:1',
                'order'  => 'in:' . implode(',', array_keys($orderMappings)),
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors'  => $validator->errors(),
                ], 400);
            }

            $base = PencatatanKantor::query()
                ->leftJoin('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->leftJoin('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->leftJoin('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
                ->leftJoin('kelurahan', 'pencatatan_kantor.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'pencatatan_kantor.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'pencatatan_kantor.id_kabupaten', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'pencatatan_kantor.id_provinsi', '=', 'provinsi.id')
                ->whereRaw("LOWER(TRIM(pencatatan_kantor.jenis)) = ?", ['verifikasi lapangan']);

            if (!empty($idProvinsi)) $base->where('pencatatan_kantor.id_provinsi', $idProvinsi);
            if (!empty($idKab))      $base->where('pencatatan_kantor.id_kabupaten', $idKab);
            if (!empty($idKec))      $base->where('pencatatan_kantor.id_kecamatan', $idKec);
            if (!empty($idKel))      $base->where('pencatatan_kantor.id_kelurahan', $idKel);
            if (!empty($tahun))      $base->whereYear('pencatatan_kantor.created', $tahun); // pastikan kolom 'created' memang datetime
            if (!empty($bulan))      $base->whereMonth('pencatatan_kantor.created', $bulan);

            if ($search !== '') {
                $base->where(function ($q) use ($search) {
                    $q->where('kelurahan.nama', 'like', "%$search%")
                    ->orWhere('kecamatan.nama', 'like', "%$search%")
                    ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                    ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }

            $total_data = (clone $base)->distinct()->count('pencatatan_kantor.id');

            $query = (clone $base)
                ->select([
                    'pencatatan_kantor.*',
                    DB::raw('ROUND(SUM(CASE WHEN kt.id_tanya IN (1,61,4) THEN COALESCE(kj.skor,0) ELSE 0 END), 2) AS aspek_operasional'),
                    DB::raw('ROUND(SUM(CASE WHEN kt.id_tanya IN (31,36,43,67) THEN COALESCE(kj.skor,0) ELSE 0 END), 2) AS aspek_sarana'),
                    DB::raw('ROUND(SUM(CASE WHEN kt.id_tanya IN (17,22,25,27) THEN COALESCE(kj.skor,0) ELSE 0 END), 2) AS aspek_wilayah'),
                    DB::raw('ROUND(SUM(CASE WHEN kt.id_tanya = 15 THEN COALESCE(kj.skor,0) ELSE 0 END), 2) AS aspek_pegawai'),
                ])
                ->groupBy('pencatatan_kantor.id')     // agregasi per pencatatan
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            $result = $query->get();

            $data = [];
            foreach ($result as $item) {
                $kpc       = Kpc::find($item->id_kpc);
                $provinsi  = Provinsi::find($item->id_provinsi);
                $kabupaten = KabupatenKota::find($item->id_kabupaten);
                $kecamatan = Kecamatan::find($item->id_kecamatan);
                $kelurahan = Kelurahan::find($item->id_kelurahan);
                $petugas   = PetugasKPC::select('nama_petugas')->where('id_kpc', $item->id_kpc)->get();

                // ✅ Ambil semua foto dari pencatatan_kantor_file
                $fotos = DB::table('pencatatan_kantor_file')
                    ->where('id_parent', $item->id)
                    ->select('file', 'file_name', 'nama')
                    ->get();

                // ✅ Filter foto berdasarkan nama
                $fotoTampakDepan = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Depan #1') !== false;
                });
                $fotoTampakBelakang = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Belakang #1') !== false;
                });
                $fotoTampakSamping = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Samping #1') !== false;
                });
                $fotoTampakDalam = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Dalam #1') !== false;
                });

                $nilai_akhir = (
                    ($item->aspek_operasional ?? 0) +
                    ($item->aspek_sarana ?? 0) +
                    ($item->aspek_wilayah ?? 0) +
                    ($item->aspek_pegawai ?? 0)
                ) / 4;

                $data[] = [
                    'id'                    => $item->id,
                    'tanggal'               => $item->created,
                    'petugas_list'          => $petugas,
                    'kode_pos'              => $kpc->nomor_dirian ?? "",
                    'provinsi'              => $provinsi->nama ?? "",
                    'kabupaten'             => $kabupaten->nama ?? "",
                    'kecamatan'             => $kecamatan->nama ?? "",
                    'kelurahan'             => $kelurahan->nama ?? "",
                    'kantor_lpu'            => $kpc->nama ?? "",
                    'aspek_operasional'     => round($item->aspek_operasional),
                    'aspek_sarana'          => round($item->aspek_sarana),
                    'aspek_wilayah'         => round($item->aspek_wilayah),
                    'aspek_pegawai'         => round($item->aspek_pegawai),
                    'nilai_akhir'           => round($nilai_akhir),
                    'kesimpulan'            => ($nilai_akhir < 50
                        ? 'Tidak Diusulkan Mendapatkan Subsidi Operasional LPU'
                        : 'Melanjutkan Mendapatkan Subsidi Operasional LPU'),
                    // ✅ 4 Kolom foto terpisah
                    'foto_tampak_depan'     => $fotoTampakDepan ? 'https://verifikasilpu.komdigi.go.id/backend/storage/' . $fotoTampakDepan->file : null,
                    'foto_tampak_belakang'  => $fotoTampakBelakang ? 'https://verifikasilpu.komdigi.go.id/backend/storage/' . $fotoTampakBelakang->file : null,
                    'foto_tampak_samping'   => $fotoTampakSamping ? 'https://verifikasilpu.komdigi.go.id/backend/storage/' . $fotoTampakSamping->file : null,
                    'foto_tampak_dalam'     => $fotoTampakDalam ? 'https://verifikasilpu.komdigi.go.id/backend/storage/' . $fotoTampakDalam->file : null,
                ];
            }

            return response()->json([
                'status'     => 'SUCCESS',
                'offset'     => $offset,
                'limit'      => $limit,
                'order'      => $getOrder,
                'search'     => $search,
                'total_data' => $total_data,
                'data'       => $data,
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
            $result = $query->orderByRaw($order)->get();

            $data = [];
            foreach ($result as $item) {
                $kpc = Kpc::find($item->id_kpc);
                $provinsi = Provinsi::find($item->id_provinsi);
                $kabupaten = KabupatenKota::find($item->id_kabupaten);
                $kecamatan = Kecamatan::find($item->id_kecamatan);
                $kelurahan = Kelurahan::find($item->id_kelurahan);
                $petugas = PetugasKPC::select('nama_petugas')->where('id_kpc', $item->id_kpc)->get();

                // ✅ Ambil semua foto dari pencatatan_kantor_file
                $fotos = DB::table('pencatatan_kantor_file')
                    ->where('id_parent', $item->id)
                    ->select('file', 'file_name', 'nama')
                    ->get();

                // ✅ Filter foto berdasarkan nama
                $fotoTampakDepan = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Depan #1') !== false;
                });
                $fotoTampakBelakang = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Belakang #1') !== false;
                });
                $fotoTampakSamping = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Samping #1') !== false;
                });
                $fotoTampakDalam = $fotos->first(function($f) {
                    return stripos($f->nama, 'Tampak Dalam #1') !== false;
                });

                $nilai_akhir = (
                    $item->aspek_operasional +
                    $item->aspek_sarana +
                    $item->aspek_wilayah +
                    $item->aspek_pegawai
                ) / 4;

                $kesimpulan = ($nilai_akhir < 50
                    ? 'Tidak Diusulkan Mendapatkan Subsidi Operasional LPU'
                    : 'Melanjutkan Mendapatkan Subsidi Operasional LPU');

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
                    // ✅ 4 Kolom foto terpisah (untuk Excel)
                    'foto_tampak_depan' => $fotoTampakDepan ? 'https://verifikasilpu.komdigi.go.id/backend/storage/pencatatan/' . $fotoTampakDepan->file : '',
                    'foto_tampak_belakang' => $fotoTampakBelakang ? 'https://verifikasilpu.komdigi.go.id/backend/storage/pencatatan/' . $fotoTampakBelakang->file : '',
                    'foto_tampak_samping' => $fotoTampakSamping ? 'https://verifikasilpu.komdigi.go.id/backend/storage/pencatatan/' . $fotoTampakSamping->file : '',
                    'foto_tampak_dalam' => $fotoTampakDalam ? 'https://verifikasilpu.komdigi.go.id/backend/storage/pencatatan/' . $fotoTampakDalam->file : '',
                ];
            }

            $userLog = [
                'timestamp' => now(),
                'aktifitas' =>'Cetak Verifikasi Lapangan',
                'modul' => 'Verifikasi Lapangan',
                'id_user' => Auth::id(),
            ];
            UserLog::create($userLog);

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
            // Log request info untuk debug
            Log::info('=== VERLAP STORE: Request Received ===', [
                'timestamp' => now()->toDateTimeString(),
                'content_length' => $request->header('Content-Length'),
                'content_type' => $request->header('Content-Type'),
                'has_multipart_files' => $request->hasFile('pencatatan_kantor_kuis'),
                'all_files' => $request->allFiles(),
                'request_keys' => array_keys($request->all()),
                'has_pencatatan_kantor' => $request->has('pencatatan_kantor'),
                'has_pencatatan_kantor_kuis' => $request->has('pencatatan_kantor_kuis'),
                'ip' => $request->ip(),
            ]);

            $rules = [
                'pencatatan_kantor' => 'required|array',
                'pencatatan_kantor.id_kpc' => 'required|string', // ✅ Changed to string (KPC ID bisa alphanumeric)
                'pencatatan_kantor.id_user' => 'required|numeric',
                'pencatatan_kantor.id_provinsi' => 'nullable|numeric',
                'pencatatan_kantor.id_kabupaten' => 'nullable|numeric',
                'pencatatan_kantor.id_kecamatan' => 'nullable|numeric',
                'pencatatan_kantor.id_kelurahan' => 'nullable|numeric',
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
                Log::error('VERLAP STORE: Validation Failed', [
                    'errors' => $validator->errors()->toArray(),
                    'payload_keys' => array_keys($payload),
                    'pencatatan_kantor' => $payload['pencatatan_kantor'] ?? 'missing',
                ]);
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
                     'id_parent' => $pencatatan->id,
                     'id_user' => $pkUser['id_user'] ?? $pk['id_user'],
                 ];

                DB::table('pencatatan_kantor_user')->insert($insertUser);
            }

            $kuisList = $payload['pencatatan_kantor_kuis'] ?? [];

            // ambil file multipart jika ada
            $uploadedFiles = $request->file('pencatatan_kantor_kuis') ?? [];
            $allUploadedFiles = $request->file('file') ?? []; // ✅ Android kirim dengan key "file"

            Log::info('VERLAP STORE: Processing files', [
                'total_kuis' => count($kuisList),
                'has_pencatatan_kantor_kuis_files' => !empty($uploadedFiles),
                'has_file_key' => !empty($allUploadedFiles),
                'file_structure' => array_keys($allUploadedFiles),
            ]);

            foreach ($kuisList as $index => $kuis) {
                $kuisInsert = [
                    'id_parent' => $pencatatan->id,
                    'id_tanya' => $kuis['id_tanya'] ?? null,
                    'id_jawab' => $kuis['id_jawab'] ?? null,
                    'data' => $kuis['data'] ?? null,
                ];

                DB::table('pencatatan_kantor_kuis')->insert($kuisInsert);

                // ===== Handle UploadedFile from Android =====
                // Try multiple file structures
                $fileFromRequest = null;

                // Structure 1: file[index]
                if (isset($allUploadedFiles[$index])) {
                    $fileFromRequest = $allUploadedFiles[$index];
                }
                // Structure 2: file[index+1] (1-indexed)
                elseif (isset($allUploadedFiles[$index + 1])) {
                    $fileFromRequest = $allUploadedFiles[$index + 1];
                }
                // Structure 3: pencatatan_kantor_kuis[index][file][file]
                elseif (isset($uploadedFiles[$index]['file']['file'])) {
                    $fileFromRequest = $uploadedFiles[$index]['file']['file'];
                }

                Log::info('VERLAP STORE: Checking file for kuis', [
                    'index' => $index,
                    'id_tanya' => $kuis['id_tanya'] ?? null,
                    'has_file_object' => is_object($fileFromRequest),
                    'file_class' => is_object($fileFromRequest) ? get_class($fileFromRequest) : null,
                ]);

                if ($fileFromRequest && is_object($fileFromRequest) && method_exists($fileFromRequest, 'getClientOriginalName')) {
                    // store uploaded file
                    $storePath = 'pencatatan_kantor/' . $pencatatan->id . '/kuis/';
                    $origName = $fileFromRequest->getClientOriginalName();
                    $ext = $fileFromRequest->getClientOriginalExtension() ?: pathinfo($origName, PATHINFO_EXTENSION);
                    $storedName = ($kuis['id_tanya'] ?? 'tanya') . '_' . time() . '_' . Str::random(6) . '.' . ($ext ?: 'bin');

                    // make directory and store
                    Storage::disk('public')->makeDirectory($storePath);
                    $fullPath = $fileFromRequest->storeAs($storePath, $storedName, 'public');

                    $fileRecord = [
                        'id_parent' => $pencatatan->id,
                        'nama' => $kuis['file']['nama'] ?? $origName ?? $storedName,
                        'file' => $fullPath,
                        'file_name' => $origName ?? $storedName,
                        'file_type' => $fileFromRequest->getClientMimeType() ?? ($kuis['file']['file_type'] ?? null),
                        'created' => now(),
                        'updated' => now(),
                    ];

                    DB::table('pencatatan_kantor_file')->insert($fileRecord);

                    Log::info('VERLAP STORE: UploadedFile saved to DB', [
                        'id_parent' => $pencatatan->id,
                        'file_name' => $fileRecord['file_name'],
                        'file_path' => $fileRecord['file'],
                        'file_type' => $fileRecord['file_type'],
                        'kuis_index' => $index,
                        'id_tanya' => $kuis['id_tanya'] ?? null,
                    ]);

                    continue; // lanjut ke next kuis item
                }
                // ===== end UploadedFile handling =====

                if (!empty($kuis['file']) && is_array($kuis['file']) && !empty($kuis['file']['file'])) {
                    $file = $kuis['file'];
                    $b64 = $file['file'];
                    if (strpos($b64, ';base64,') !== false) {
                        $parts = explode(';base64,', $b64);
                        $b64 = $parts[1] ?? '';
                    }
                    $decoded = base64_decode($b64);
                    if ($decoded === false || $decoded === null || strlen($decoded) === 0) {
                        // skip invalid base64
                        Log::warning('VERLAP STORE: Base64 decode FAILED', [
                            'id_parent' => $pencatatan->id,
                            'id_tanya' => $kuis['id_tanya'] ?? null,
                            'file_nama' => $file['nama'] ?? 'unknown',
                            'base64_preview' => substr($b64, 0, 50) . '...',
                            'base64_length' => strlen($b64),
                        ]);
                        continue;
                    }

                    // normalize original filename
                    $origName = $file['file_name'] ?? $file['nama'] ?? null;
                    if (!$origName || preg_match('/_?tmp(\.|$)/i', $origName)) {
                        $origName = null;
                    }

                    // detect ext/mime
                    $ext = $origName ? pathinfo($origName, PATHINFO_EXTENSION) : '';
                    if (empty($ext)) {
                        $finfoType = finfo_open(FILEINFO_MIME_TYPE);
                        $detectedMime = finfo_buffer($finfoType, $decoded);
                        finfo_close($finfoType);
                        $map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'application/pdf' => 'pdf',
                            'text/plain' => 'txt',
                        ];
                        $ext = $map[$detectedMime] ?? 'bin';
                    }

                    $storePath = 'pencatatan_kantor/' . $pencatatan->id . '/kuis/';
                    $storedName = ($kuis['id_tanya'] ?? 'tanya') . '_' . time() . '_' . Str::random(6) . '.' . $ext;
                    $fullPath = $storePath . $storedName;

                    Storage::disk('public')->makeDirectory($storePath);
                    Storage::disk('public')->put($fullPath, $decoded);

                    $fileRecord = [
                        'id_parent' => $pencatatan->id,
                        'nama' => $file['nama'] ?? ($origName ?? $storedName),
                        'file' => $fullPath,
                        'file_name' => $origName ?? $storedName,
                        'file_type' => $file['file_type'] ?? ($detectedMime ?? null),
                        'created' => $file['created'] ?? now(),
                        'updated' => $file['updated'] ?? now(),
                    ];

                    DB::table('pencatatan_kantor_file')->insert($fileRecord);

                    Log::info('VERLAP STORE: File saved to DB', [
                        'id_parent' => $pencatatan->id,
                        'file_name' => $fileRecord['file_name'],
                        'file_path' => $fileRecord['file'],
                        'file_size_kb' => round(strlen($decoded) / 1024, 2),
                        'file_type' => $fileRecord['file_type'],
                    ]);
                }
            }

            DB::commit();

            Log::info('=== VERLAP STORE: SUCCESS ===', [
                'id_pencatatan' => $pencatatan->id,
                'total_files_processed' => count($kuisList),
            ]);

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
