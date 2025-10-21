<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\MitraLpu;
use App\Models\Rekonsiliasi;
use App\Models\PencatatanKantor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MonitoringController extends Controller
{
    /**
     * GET /monitoring
     * Params utama:
     * - type_penyelenggara: lpu | lpk | mitra | penyelenggara
     * - id_regional, id_kprk, id_provinsi, id_kabupaten_kota, id_kecamatan, id_kelurahan
     * - id_kpc (bisa kpc.id atau nomor_dirian) / id_rekonsiliasi
     * - search, offset, limit
     */
    public function index(Request $request)
    {
        try {
            // --- Params & validasi ringan
            $offset             = (int) $request->get('offset', 0);
            $limit              = min((int) $request->get('limit', 500), 5000);
            $search             = trim((string) $request->get('search', ''));
            $id_regional        = $request->get('id_regional', '');
            $id_kprk            = $request->get('id_kprk', '');
            $id_provinsi        = $request->get('id_provinsi', '');
            $id_kabupaten_kota  = $request->get('id_kabupaten_kota', '');
            $id_kecamatan       = $request->get('id_kecamatan', '');
            $id_kelurahan       = $request->get('id_kelurahan', '');
            $id_penyelenggara   = $request->get('id_penyelenggara', '');
            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu');
            $jenis_kantor       = $request->get('jenis_kantor', '');
            $id_jenis_kantor    = $request->get('id_jenis_kantor', '');
            $id_kpc_param       = $request->get('id_kpc', $request->get('id_rekonsiliasi', ''));

            $validator = Validator::make(
                compact('offset', 'limit'),
                ['offset' => 'integer|min:0', 'limit' => 'integer|min:1|max:5000']
            );
            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors'  => $validator->errors(),
                ], 400);
            }

            // === LPU/LPK (tabel KPC)
            if (in_array($type_penyelenggara, ['lpu', 'lpk'], true)) {
                $query = Kpc::query()
                    ->leftJoin('regional',       'kpc.id_regional',       '=', 'regional.id')
                    ->leftJoin('kprk',           'kpc.id_kprk',           '=', 'kprk.id')
                    ->leftJoin('provinsi',       'kpc.id_provinsi',       '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan',      'kpc.id_kecamatan',      '=', 'kecamatan.id')
                    ->leftJoin('kelurahan',      'kpc.id_kelurahan',      '=', 'kelurahan.id')
                    ->selectRaw("
                        kpc.id AS id_kpc,
                        REPLACE(COALESCE(kpc.koordinat_longitude,''), ',', '.') AS koordinat_longitude,
                        REPLACE(COALESCE(kpc.koordinat_latitude,''),  ',', '.') AS koordinat_latitude,
                        kpc.alamat,
                        kpc.nama AS nama,
                        kpc.jenis_kantor,
                        regional.nama       AS nama_regional,
                        kprk.nama           AS nama_kprk,
                        provinsi.id         AS id_provinsi,
                        provinsi.nama       AS nama_provinsi,
                        kabupaten_kota.id   AS id_kabupaten_kota,
                        kabupaten_kota.nama AS nama_kabupaten,
                        kecamatan.id        AS id_kecamatan,
                        kecamatan.nama      AS nama_kecamatan,
                        kelurahan.id        AS id_kelurahan,
                        kelurahan.nama      AS nama_kelurahan,
                        'lpu'               AS sumber
                    ");

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('kpc.nama', 'like', "%{$search}%")
                            ->orWhere('kprk.nama', 'like', "%{$search}%")
                            ->orWhere('regional.nama', 'like', "%{$search}%")
                            ->orWhere('kabupaten_kota.nama', 'like', "%{$search}%")
                            ->orWhere('provinsi.nama', 'like', "%{$search}%");
                    });
                }
                if ($id_regional)        $query->where('kpc.id_regional', $id_regional);
                if ($id_kprk)            $query->where('kprk.id', $id_kprk);
                if ($id_provinsi)        $query->where('kpc.id_provinsi', $id_provinsi);
                if ($id_kabupaten_kota)  $query->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)       $query->where('kpc.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)       $query->where('kpc.id_kelurahan', $id_kelurahan);
                if ($jenis_kantor)       $query->where('kpc.jenis_kantor', $jenis_kantor);
                if ($id_kpc_param) {
                    $query->where(function ($q) use ($id_kpc_param) {
                        $q->where('kpc.id', $id_kpc_param)
                            ->orWhere('kpc.nomor_dirian', $id_kpc_param);
                    });
                }

                // === Mitra LPU
            } elseif ($type_penyelenggara === 'mitra') {
                $query = MitraLpu::query()
                    ->leftJoin('kpc',            'mitra_lpu.nopend',        '=', 'kpc.nomor_dirian')
                    ->leftJoin('regional',       'kpc.id_regional',         '=', 'regional.id')
                    ->leftJoin('kprk',           'kpc.id_kprk',             '=', 'kprk.id')
                    ->leftJoin('provinsi',       'kpc.id_provinsi',         '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota',   '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan',      'kpc.id_kecamatan',        '=', 'kecamatan.id')
                    ->leftJoin('kelurahan',      'kpc.id_kelurahan',        '=', 'kelurahan.id')
                    ->selectRaw("
                        mitra_lpu.nib AS id_kpc,
                        REPLACE(COALESCE(mitra_lpu.`long`, ''), ',', '.') AS koordinat_longitude,
                        REPLACE(COALESCE(mitra_lpu.`lat`,  ''), ',', '.') AS koordinat_latitude,
                        mitra_lpu.alamat_mitra AS alamat,
                        mitra_lpu.nama_mitra   AS nama,
                        'mitra'                AS sumber,
                        regional.nama          AS nama_regional,
                        kprk.nama              AS nama_kprk,
                        provinsi.id            AS id_provinsi,
                        provinsi.nama          AS nama_provinsi,
                        kabupaten_kota.id      AS id_kabupaten_kota,
                        kabupaten_kota.nama    AS nama_kabupaten,
                        kecamatan.id           AS id_kecamatan,
                        kecamatan.nama         AS nama_kecamatan,
                        kelurahan.id           AS id_kelurahan,
                        kelurahan.nama         AS nama_kelurahan
                    ");

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('mitra_lpu.nama_mitra', 'like', "%{$search}%")
                            ->orWhere('regional.nama', 'like', "%{$search}%")
                            ->orWhere('kprk.nama', 'like', "%{$search}%");
                    });
                }
                if ($id_regional)        $query->where('kpc.id_regional', $id_regional);
                if ($id_kprk)            $query->where('kprk.id', $id_kprk);
                if ($id_provinsi)        $query->where('kpc.id_provinsi', $id_provinsi);
                if ($id_kabupaten_kota)  $query->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)       $query->where('kpc.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)       $query->where('kpc.id_kelurahan', $id_kelurahan);
                if ($id_kpc_param) {
                    $query->where(function ($q) use ($id_kpc_param) {
                        $q->where('kpc.id', $id_kpc_param)
                            ->orWhere('kpc.nomor_dirian', $id_kpc_param);
                    });
                }

                // === Penyelenggara lain (rekonsiliasi)
            } else {
                $query = Rekonsiliasi::query()
                    ->leftJoin('kelurahan',      'rekonsiliasi.id_kelurahan',    '=', 'kelurahan.id')
                    ->leftJoin('kecamatan',      'kelurahan.id_kecamatan',       '=', 'kecamatan.id')
                    ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota',  '=', 'kabupaten_kota.id')
                    ->leftJoin('provinsi',       'kabupaten_kota.id_provinsi',   '=', 'provinsi.id')
                    ->selectRaw("
                        rekonsiliasi.id AS id_kpc,
                        REPLACE(COALESCE(rekonsiliasi.longitude,''), ',', '.') AS koordinat_longitude,
                        REPLACE(COALESCE(rekonsiliasi.latitude, ''), ',', '.')  AS koordinat_latitude,
                        rekonsiliasi.alamat,
                        rekonsiliasi.nama_kantor AS nama,
                        'penyelenggara'        AS sumber,
                        provinsi.id            AS id_provinsi,
                        provinsi.nama          AS nama_provinsi,
                        kabupaten_kota.id      AS id_kabupaten_kota,
                        kabupaten_kota.nama    AS nama_kabupaten,
                        kecamatan.id           AS id_kecamatan,
                        kecamatan.nama         AS nama_kecamatan,
                        kelurahan.id           AS id_kelurahan,
                        kelurahan.nama         AS nama_kelurahan
                    ");

                if ($id_provinsi)        $query->where('provinsi.id', $id_provinsi);
                if ($id_kabupaten_kota)  $query->where('kecamatan.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)       $query->where('kelurahan.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)       $query->where('rekonsiliasi.id_kelurahan', $id_kelurahan);
                if ($id_penyelenggara)   $query->where('rekonsiliasi.id_penyelenggara', $id_penyelenggara);
                if ($id_jenis_kantor)    $query->where('rekonsiliasi.id_jenis_kantor', $id_jenis_kantor);
                if ($search !== '')      $query->where('rekonsiliasi.nama_kantor', 'like', "%{$search}%");
                if ($id_kpc_param)       $query->where('rekonsiliasi.id', $id_kpc_param);
            }

            $total_data = (clone $query)->count();
            $data = $query->offset($offset)->limit($limit)->get();

            return response()->json([
                'status'     => 'SUCCESS',
                'offset'     => $offset,
                'limit'      => $limit,
                'search'     => $search,
                'total_data' => $total_data,
                'data'       => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /monitoring/show
     * Detail satu titik (LPU/LPK/mitra/penyelenggara) + foto (pencatatan_kantor_file)
     */
    public function show(Request $request)
    {
        try {
            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu');
            $id_kpc             = $request->get('id_kpc', '');
            $id_rekonsiliasi    = $request->get('id_rekonsiliasi', '');

            // === Mitra
            if ($type_penyelenggara === 'mitra') {
                $data = MitraLpu::query()
                    ->leftJoin('kpc',            'mitra_lpu.nopend',        '=', 'kpc.nomor_dirian')
                    ->leftJoin('regional',       'kpc.id_regional',         '=', 'regional.id')
                    ->leftJoin('kprk',           'kpc.id_kprk',             '=', 'kprk.id')
                    ->leftJoin('provinsi',       'kpc.id_provinsi',         '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota',   '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan',      'kpc.id_kecamatan',        '=', 'kecamatan.id')
                    ->leftJoin('kelurahan',      'kpc.id_kelurahan',        '=', 'kelurahan.id')
                    ->selectRaw("
                        mitra_lpu.*,
                        kpc.id              as id_kpc_ref,
                        kpc.nomor_dirian    as nomor_dirian_ref,
                        regional.nama       as nama_regional,
                        kprk.nama           as nama_kprk,
                        provinsi.nama       as nama_provinsi,
                        kabupaten_kota.nama as nama_kabupaten,
                        kecamatan.nama      as nama_kecamatan,
                        kelurahan.nama      as nama_kelurahan,
                        kpc.jam_kerja_senin_kamis, kpc.jam_kerja_jumat, kpc.jam_kerja_sabtu
                    ")
                    ->when($id_kpc !== '', fn($q) => $q->where('mitra_lpu.nib', $id_kpc))
                    ->first();

                $foto = [];
                if ($data && $data->id_kpc_ref) {
                    $foto = PencatatanKantor::with('files:id,id_parent,nama,file')
                        ->where('id_kpc', $data->id_kpc_ref)
                        ->get()
                        ->flatMap(fn($p) => $p->files->map(fn($f) => [
                            'id' => $f->id,
                            'nama' => $f->nama,
                            'url' => Storage::url($f->file),
                        ]))->values()->all();
                }
                if ($data) $data->setAttribute('foto', $foto);

                return response()->json(['status' => 'SUCCESS', 'data' => $data]);
            }

            // === LPU/LPK
            if ($type_penyelenggara === 'lpu' || $type_penyelenggara === 'lpk') {
                $data = Kpc::leftJoin('regional',       'kpc.id_regional',       '=', 'regional.id')
                    ->leftJoin('kprk',           'kpc.id_kprk',           '=', 'kprk.id')
                    ->leftJoin('provinsi',       'kpc.id_provinsi',       '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan',      'kpc.id_kecamatan',      '=', 'kecamatan.id')
                    ->leftJoin('kelurahan',      'kpc.id_kelurahan',      '=', 'kelurahan.id')
                    ->select(
                        'kpc.id as id_kpc',
                        'kpc.*',
                        'regional.nama as nama_regional',
                        'kprk.nama as nama_kprk',
                        'provinsi.nama as nama_provinsi',
                        'kabupaten_kota.nama as nama_kabupaten',
                        'kecamatan.nama as nama_kecamatan',
                        'kelurahan.nama as nama_kelurahan'
                    )
                    ->where('kpc.id', $id_kpc)
                    ->orWhere('kpc.nomor_dirian', $id_kpc)
                    ->first();

                if ($data) {
                    $idForPhoto = $data->id_kpc ?? $data->id;
                    $foto = PencatatanKantor::with('files:id,id_parent,nama,file')
                        ->where('id_kpc', $idForPhoto)
                        ->get()
                        ->flatMap(fn($p) => $p->files->map(fn($f) => [
                            'id' => $f->id,
                            'nama' => $f->nama,
                            'url' => Storage::url($f->file),
                        ]))->values()->all();
                    $data->setAttribute('foto', $foto);
                }

                return response()->json(['status' => 'SUCCESS', 'data' => $data]);
            }

            // === Penyelenggara lainnya
            $data = Rekonsiliasi::selectRaw("
                    rekonsiliasi.*,
                    provinsi.nama       as nama_provinsi,
                    kabupaten_kota.nama as nama_kabupaten,
                    kecamatan.nama      as nama_kecamatan,
                    kelurahan.nama      as nama_kelurahan
                ")
                ->leftJoin('kelurahan',      'rekonsiliasi.id_kelurahan',    '=', 'kelurahan.id')
                ->leftJoin('kecamatan',      'kelurahan.id_kecamatan',       '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota',  '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi',       'kabupaten_kota.id_provinsi',   '=', 'provinsi.id')
                ->when($id_rekonsiliasi !== '', fn($q) => $q->where('rekonsiliasi.id', $id_rekonsiliasi))
                ->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
