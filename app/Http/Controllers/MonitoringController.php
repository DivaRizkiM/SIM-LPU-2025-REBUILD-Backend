<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\MitraLpu;
use App\Models\Rekonsiliasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MonitoringController extends Controller
{
    /**
     * GET /monitoring
     * type_penyelenggara: lpu | lpk | mitra | penyelenggara
     */
    public function index(Request $request)
    {
        try {
            // Params
            $offset             = (int) $request->get('offset', 0);
            $limit              = min((int) $request->get('limit', 10), 5000);
            $search             = trim((string) $request->get('search', ''));
            $id_regional        = $request->get('id_regional', '');
            $id_kprk            = $request->get('id_kprk', '');
            $id_provinsi        = $request->get('id_provinsi', '');
            $id_kabupaten_kota  = $request->get('id_kabupaten_kota', '');
            $id_kecamatan       = $request->get('id_kecamatan', '');
            $id_kelurahan       = $request->get('id_kelurahan', '');
            $id_penyelenggara   = $request->get('id_penyelenggara', '');
            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu'); // default lpu
            $jenis_kantor       = $request->get('jenis_kantor', '');
            $id_jenis_kantor    = $request->get('id_jenis_kantor', '');

            // Backward compatible: terima id_kpc atau id_rekonsiliasi
            $id_kpc_param       = $request->get('id_kpc', $request->get('id_rekonsiliasi', ''));

            // Validasi ringan
            $validator = Validator::make(
                compact('offset', 'limit'),
                [
                    'offset' => 'integer|min:0',
                    'limit'  => 'integer|min:1|max:5000',
                ]
            );
            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors'  => $validator->errors(),
                ], 400);
            }

            // =========================
            // CABANG LPU / LPK (tabel KPC)
            // =========================
            if (in_array($type_penyelenggara, ['lpu', 'lpk'], true)) {
                $query = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                    ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                    ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kprk.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                    ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                    ->selectRaw('
                        kpc.id AS id_kpc,
                        REPLACE(kpc.koordinat_longitude, ",", ".") AS koordinat_longitude,
                        REPLACE(kpc.koordinat_latitude,  ",", ".") AS koordinat_latitude,
                        kpc.alamat,
                        kpc.nama AS nama,
                        regional.nama AS nama_regional,
                        kprk.nama AS nama_kprk,
                        provinsi.id AS id_provinsi,
                        kabupaten_kota.id AS id_kabupaten_kota,
                        kecamatan.id AS id_kecamatan,
                        kelurahan.id AS id_kelurahan
                    ');

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('kpc.nama', 'like', "%{$search}%")
                            ->orWhere('kprk.nama', 'like', "%{$search}%")
                            ->orWhere('regional.nama', 'like', "%{$search}%");
                    });
                }
                if ($id_provinsi)       $query->where('kpc.id_provinsi', $id_provinsi);
                if ($id_kabupaten_kota) $query->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)      $query->where('kpc.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)      $query->where('kpc.id_kelurahan', $id_kelurahan);
                if ($id_regional)       $query->where('kpc.id_regional', $id_regional);
                if ($id_kprk)           $query->where('kprk.id', $id_kprk);
                if ($jenis_kantor)      $query->where('kpc.jenis_kantor', $jenis_kantor);

                // id_kpc_param bisa berupa kpc.id atau kpc.nomor_dirian
                if ($id_kpc_param) {
                    $query->where(function ($q) use ($id_kpc_param) {
                        $q->where('kpc.id', $id_kpc_param)
                            ->orWhere('kpc.nomor_dirian', $id_kpc_param);
                    });
                }

                // =========================
                // CABANG BARU: MITRA LPU
                // =========================
            } elseif ($type_penyelenggara === 'mitra') {
                $query = MitraLpu::query()
                    ->leftJoin('kpc', 'mitra_lpu.nopend', '=', 'kpc.nomor_dirian')
                    ->leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                    ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                    ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                    ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                    ->selectRaw("
                        mitra_lpu.nib AS id_kpc,
                        REPLACE(COALESCE(mitra_lpu.`long`, ''), ',', '.') AS koordinat_longitude,
                        REPLACE(COALESCE(mitra_lpu.`lat`,  ''), ',', '.') AS koordinat_latitude,
                        mitra_lpu.alamat_mitra AS alamat,
                        mitra_lpu.nama_mitra   AS nama,
                        provinsi.id            AS id_provinsi,
                        kabupaten_kota.id      AS id_kabupaten_kota,
                        kecamatan.id           AS id_kecamatan,
                        kelurahan.id           AS id_kelurahan,
                        'mitra'                AS sumber
                    ");

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('mitra_lpu.nama_mitra', 'like', "%{$search}%")
                            ->orWhere('regional.nama', 'like', "%{$search}%")
                            ->orWhere('kprk.nama', 'like', "%{$search}%");
                    });
                }
                // filter by wilayah dari tabel kpc (join lewat nopend)
                if ($id_provinsi)       $query->where('kpc.id_provinsi', $id_provinsi);
                if ($id_kabupaten_kota) $query->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)      $query->where('kpc.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)      $query->where('kpc.id_kelurahan', $id_kelurahan);
                if ($id_regional)       $query->where('kpc.id_regional', $id_regional);
                if ($id_kprk)           $query->where('kprk.id', $id_kprk);

                // jika FE kirim id_kpc (bisa nomor_dirian atau id kpc), kita filter via join KPC
                if ($id_kpc_param) {
                    $query->where(function ($q) use ($id_kpc_param) {
                        $q->where('kpc.id', $id_kpc_param)
                            ->orWhere('kpc.nomor_dirian', $id_kpc_param);
                    });
                }

                // =========================
                // CABANG PENYELENGGARA LAIN (rekonsiliasi)
                // =========================
            } else {
                $query = Rekonsiliasi::leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                    ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                    ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                    ->selectRaw('
                        rekonsiliasi.id AS id_kpc,
                        REPLACE(rekonsiliasi.longitude, ",", ".") AS koordinat_longitude,
                        REPLACE(rekonsiliasi.latitude,  ",", ".") AS koordinat_latitude,
                        rekonsiliasi.alamat,
                        rekonsiliasi.nama_kantor AS nama,
                        provinsi.id AS id_provinsi,
                        kabupaten_kota.id AS id_kabupaten_kota,
                        kecamatan.id AS id_kecamatan,
                        kelurahan.id AS id_kelurahan
                    ');

                if ($id_provinsi)       $query->where('provinsi.id', $id_provinsi);
                if ($id_kabupaten_kota) $query->where('kecamatan.id_kabupaten_kota', $id_kabupaten_kota);
                if ($id_kecamatan)      $query->where('kelurahan.id_kecamatan', $id_kecamatan);
                if ($id_kelurahan)      $query->where('rekonsiliasi.id_kelurahan', $id_kelurahan);
                if ($id_penyelenggara)  $query->where('rekonsiliasi.id_penyelenggara', $id_penyelenggara);
                if ($id_jenis_kantor)   $query->where('rekonsiliasi.id_jenis_kantor', $id_jenis_kantor);
                if ($search !== '')     $query->where('rekonsiliasi.nama_kantor', 'like', "%{$search}%");
                if ($id_kpc_param)      $query->where('rekonsiliasi.id', $id_kpc_param);
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
            return response()->json([
                'status'  => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /monitoring/show
     * Detail satu titik (LPU/LPK/mitra/penyelenggara)
     */
    public function show(Request $request)
    {
        try {
            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu');
            $id_kpc             = $request->get('id_kpc', '');
            $id_rekonsiliasi    = $request->get('id_rekonsiliasi', '');

            if ($type_penyelenggara === 'mitra') {
                // detail mitra: join kpc buat metadata lokasi
                $data = MitraLpu::query()
                    ->leftJoin('kpc', 'mitra_lpu.nopend', '=', 'kpc.nomor_dirian')
                    ->leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                    ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                    ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                    ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                    ->selectRaw("
                        mitra_lpu.*,
                        kpc.id              as id_kpc_ref,
                        kpc.nama            as nama_kpc_ref,
                        regional.nama       as nama_regional,
                        kprk.nama           as nama_kprk,
                        provinsi.id         as id_provinsi,
                        provinsi.nama       as nama_provinsi,
                        kabupaten_kota.id   as id_kabupaten_kota,
                        kabupaten_kota.nama as nama_kabupaten,
                        kecamatan.id        as id_kecamatan,
                        kecamatan.nama      as nama_kecamatan,
                        kelurahan.id        as id_kelurahan,
                        kelurahan.nama      as nama_kelurahan
                    ")
                    // FE bisa kirim id_kpc (kita pakai sebagai NIB mitra di detail)
                    ->when($id_kpc !== '', function ($q) use ($id_kpc) {
                        $q->where('mitra_lpu.nib', $id_kpc);
                    })
                    ->first();

                return response()->json([
                    'status' => 'SUCCESS',
                    'data'   => $data,
                ]);
            }

            if ($type_penyelenggara === 'lpu' || $type_penyelenggara === 'lpk') {
                $data = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                    ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                    ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                    ->leftJoin('kabupaten_kota', 'kprk.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                    ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                    ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                    ->select(
                        'kpc.id as id_kpc',
                        'kpc.*',
                        'regional.nama as nama_regional',
                        'kprk.nama as nama_kprk',
                        'provinsi.id as id_provinsi',
                        'provinsi.nama as nama_provisi',
                        'kabupaten_kota.id as id_kabupaten_kota',
                        'kabupaten_kota.nama as nama_kabupaten',
                        'kecamatan.id as id_kecamatan',
                        'kecamatan.nama as nama_kecamatan',
                        'kelurahan.id as id_kelurahan',
                        'kelurahan.nama as nama_kelurahan'
                    )
                    ->where('kpc.id', $id_kpc)
                    ->orWhere('kpc.nomor_dirian', $id_kpc)
                    ->first();

                return response()->json([
                    'status' => 'SUCCESS',
                    'data'   => $data
                ]);
            }

            // penyelenggara lain
            $data = Rekonsiliasi::select(
                'rekonsiliasi.id as id_rekonsiliasi',
                'rekonsiliasi.*',
                'jenis_kantor.nama as nama_jenis_kantor',
                'rekonsiliasi.id_penyelenggara',
                'penyelenggara.nama as nama_penyelenggara',
                'provinsi.id as id_provinsi',
                'provinsi.nama as nama_provisi',
                'kabupaten_kota.id as id_kabupaten_kota',
                'kabupaten_kota.nama as nama_kabupaten',
                'kecamatan.id as id_kecamatan',
                'kecamatan.nama as nama_kecamatan',
                'kelurahan.id as id_kelurahan',
                'kelurahan.nama as nama_kelurahan'
            )
                ->leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftJoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
                ->where('rekonsiliasi.id', $id_rekonsiliasi)
                ->first();

            return response()->json([
                'status' => 'SUCCESS',
                'data'   => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
