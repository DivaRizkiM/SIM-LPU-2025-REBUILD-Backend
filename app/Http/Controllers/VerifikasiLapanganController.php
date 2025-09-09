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
                            WHEN kt.id_tanya IN (1, 61, 4) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_operasional'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (31, 36, 43, 67) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_sarana'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (17, 22, 25, 27) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_wilayah'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya = 15 THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_pegawai'),
            ])
                ->Join('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->Join('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->Join('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
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
                    'kode_pos' => $kpc->nomor_dirian,
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
                            WHEN kt.id_tanya IN (1, 61, 4) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_operasional'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (31, 36, 43, 67) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_sarana'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (17, 22, 25, 27) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_wilayah'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya = 15 THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_pegawai'),
            ])
                ->Join('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->Join('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->Join('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
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
                    'kode_pos' => $kpc->nomor_dirian,
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

}
