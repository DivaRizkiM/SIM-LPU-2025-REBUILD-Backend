<?php

namespace App\Http\Controllers;

use App\Exports\ProfileBoLpuExport;
use App\Models\AlokasiDana;
use App\Models\UserLog;
use App\Models\KabupatenKota;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kpc;
use App\Models\KuisTanyaKantor;
use App\Models\PencatatanKantor;
use App\Models\PetugasKPC;
use App\Models\Provinsi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class MonitoringKantorUsulanController extends Controller
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
                            WHEN kt.id_tanya IN (32, 36, 43, 67) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_sarana'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya IN (18, 22, 25, 27) THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_wilayah'),
                DB::raw('ROUND(SUM(CASE
                            WHEN kt.id_tanya = 12 THEN kj.skor
                            ELSE 0
                            END), 2) AS aspek_keuangan'),
            ])
                ->Join('pencatatan_kantor_kuis', 'pencatatan_kantor_kuis.id_parent', '=', 'pencatatan_kantor.id')
                ->Join('kuis_tanya_kantor as kt', 'pencatatan_kantor_kuis.id_tanya', '=', 'kt.id')
                ->Join('kuis_jawab_kantor as kj', 'pencatatan_kantor_kuis.id_jawab', '=', 'kj.id')
                ->where('pencatatan_kantor.jenis', 'Monitoring Kantor Usulan')
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
            $data = [];
            foreach ($result as $item) {
                $kpc = Kpc::find($item->id_kpc);
                $provinsi = Provinsi::find($item->id_provinsi);
                $kabupaten = KabupatenKota::find($item->id_kabupaten);
                $kecamatan = Kecamatan::find($item->id_kecamatan);
                $kelurahan = Kelurahan::find($item->id_kelurahan);

                $get_nilai_akhir = DB::table(DB::raw('(
                    with recursive flat_persen_jawab(
                    id_tanya_root,
                    id_tanya_parent,
                    id_tanya,
                    id_jawab,
                    persen,
                    skor
                    ) as (
                        select
                            t.id as id_tanya_root,
                            t.id_tanya as id_tanya_parent,
                            t.id as id_tanya,
                            j.id as id_jawab,
                            t.persen as persen,
                            j.skor as skor
                        from kuis_tanya_kantor t
                        left join kuis_jawab_kantor j on (j.id_tanya=t.id)
                        where t.persen is not null
                        union all
                        select
                            f.id_tanya_root,
                            t.id_tanya as id_tanya_parent,
                            t.id as id_tanya,
                            j.id as id_jawab,
                            f.persen as persen,
                            j.skor as skor
                        from kuis_tanya_kantor t
                        join flat_persen_jawab f on (f.id_tanya=t.id_tanya)
                        left join kuis_jawab_kantor j on (j.id_tanya=t.id)
                    )
                    select
                        f.id_tanya_root,
                        f.persen,
                        sum(skor) as skor
                    from
                        flat_persen_jawab f
                    join
                        pencatatan_kantor_kuis k on (k.id_tanya=f.id_tanya and k.id_jawab=f.id_jawab)
                    where
                        (k.id_parent=:item_id)
                    group by
                        f.id_tanya_root, f.persen
                ) as subquery'))
                    ->setBindings(['item_id' => $item->id])
                    ->get();

                // dd($get_nilai_akhir);
                $nilai_akhir = 0;
                foreach ($get_nilai_akhir as $nilai) {
                    $nilai_akhir += $nilai->skor * $nilai->persen;
                }
                $petugas = PetugasKPC::select('nama_petugas')->where('id_kpc', $item->id_kpc)->get();
                // $nilai_akhir = (($item->aspek_operasional * 0.15) + ($item->aspek_sarana * 0.15) + ($item->aspek_wilayah * 0.2) + ($item->aspek_keuangan * 0.5));
                $kesimpulan = ($nilai_akhir < 50 ? 'Tidak Diusulkan Mendapatkan Subsidi Operasional LPU' : 'Melanjutkan Mendapatkan Subsidi Operasional LPU');
                $data[] = [
                    'id_parent' => $item->id,
                    'tanggal' => $item->created,
                    'petugas_list' => $petugas,
                    'kantor_lpu' => $kpc->nama,
                    'kode_pos' => $kpc->nomor_dirian,
                    'provinsi' => $provinsi->nama ?? "",
                    'kabupaten' => $kabupaten->nama ?? "",
                    'kecamatan' => $kecamatan->nama ?? "",
                    'kelurahan' => $kelurahan->nama ?? "",
                    'nilai_akhir' => round($nilai_akhir),
                    'kesimpulan' => $kesimpulan,
                ];
                foreach ($get_nilai_akhir as $nilai) {
                    $get_pertanyaan = KuisTanyaKantor::wherenot('persen', null)->where('id', $nilai->id_tanya_root)->first();

                    $pertanyaan_nama = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\- ]/', '', $get_pertanyaan->nama ?? "")));
                    $pertanyaan_nama = str_replace('__', '_', $pertanyaan_nama);
                    $skor = round($nilai->skor); // Skor sebagai value

                    // Menambahkan data pertanyaan dan skor ke dalam array $data[]
                    $data[count($data) - 1][$pertanyaan_nama] = $skor;
                }
            }

            // dd($data);

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data'=>$total_data,
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
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
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
            $query = AlokasiDana::join('kpc', 'kpc.id', '=', 'alokasi_dana.id_kpc')
                ->join('regional', 'regional.id', '=', 'kpc.id_regional')
                ->join('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->select('alokasi_dana.*', 'kpc.id as id_kpc', 'regional.nama as nama_regional', 'kprk.nama as nama_kprk', 'kpc.nama as nama_kpc');

            if ($id_regional) {
                $query->where('kpc.id_regional', $id_regional);
            }
            if ($id_kprk) {
                $query->where('kpc.id_kprk', $id_kprk);
            }
            if ($tahun) {
                $query->where('alokasi_dana.tahun', $tahun);
            }
            if ($triwulan) {
                $query->where('alokasi_dana.triwulan', $triwulan);
            }
            if ($search !== '') {
                $kelurahansQuery->where(function ($query) use ($search) {
                    $query->where('regional.nama', 'like', "%$search%")
                        ->orWhere('kprk.nama', 'like', "%$search%")
                        ->orWhere('kpc.nama', 'like', "%$search%");

                });
            }
            $data = $query->get();
            // dd($data);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Monitoring Kantor Usulan',
                'modul' => 'Monitoring Kantor Usulan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return Excel::download(new ProfileBoLpuExport($data), 'template_laporan_profil_bo_lpu.xlsx');
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
