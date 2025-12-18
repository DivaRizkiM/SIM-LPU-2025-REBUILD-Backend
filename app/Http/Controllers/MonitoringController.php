<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\MitraLpu;
use App\Models\Rekonsiliasi;
use App\Models\PencatatanKantor;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MonitoringController extends Controller
{
        /**
         * Autocomplete untuk pencarian kantor
         * Params: q (search query), jenis_kantor, id_regional, id_kprk, limit
         */
        public function autocompleteKantor(Request $request)
        {
            $search = trim($request->get('q', ''));
            $jenisKantor = $request->get('jenis_kantor');
            $idRegional = $request->get('id_regional');
            $idKprk = $request->get('id_kprk');
            $limit = min((int) $request->get('limit', 10), 50);

            $query = Kpc::select('id', 'nomor_dirian', 'nama', 'jenis_kantor', 'alamat', 'id_regional', 'id_kprk')
                ->where(function($q) use ($search) {
                    if ($search) {
                        $q->where('nama', 'LIKE', "%$search%")
                          ->orWhere('nomor_dirian', 'LIKE', "%$search%")
                          ->orWhere('alamat', 'LIKE', "%$search%");
                    }
                });

            if ($jenisKantor) {
                $query->where('jenis_kantor', $jenisKantor);
            }

            if ($idRegional) {
                $query->where('id_regional', $idRegional);
            }

            if ($idKprk) {
                $query->where('id_kprk', $idKprk);
            }

            // Only show offices with valid coordinates
            $query->whereNotNull('koordinat_latitude')
                  ->whereNotNull('koordinat_longitude')
                  ->where('koordinat_latitude', '!=', 0)
                  ->where('koordinat_longitude', '!=', 0);

            $results = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        }

        /**
         * Cari dua kantor (asal & tujuan) dan hitung jarak antar koordinat.
         * Parameter dapat berupa:
         * - origin_id / destination_id: ID KPC
         * - origin_name / destination_name: Nama kantor (LIKE)
         * - Filter: jenis_kantor, id_regional, id_kprk untuk origin dan destination
         * Jika keduanya dikirim (id dan name), prioritas menggunakan ID.
         * Response berisi data kantor asal, tujuan, dan jarak (km).
         */
        public function searchKantorDistance(Request $request)
        {
            $origin = $this->findKpc(
                $request->get('origin_id'),
                $request->get('origin_name'),
                $request->get('origin_jenis_kantor'),
                $request->get('origin_id_regional'),
                $request->get('origin_id_kprk')
            );

            $destination = $this->findKpc(
                $request->get('destination_id'),
                $request->get('destination_name'),
                $request->get('destination_jenis_kantor'),
                $request->get('destination_id_regional'),
                $request->get('destination_id_kprk')
            );

            if (!$origin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kantor asal tidak ditemukan',
                ], 404);
            }

            if (!$destination) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kantor tujuan tidak ditemukan',
                ], 404);
            }

            // Pastikan koordinat tersedia dan dapat dikonversi ke float
            $oLon = $this->toFloat($origin->koordinat_longitude);
            $oLat = $this->toFloat($origin->koordinat_latitude);
            $dLon = $this->toFloat($destination->koordinat_longitude);
            $dLat = $this->toFloat($destination->koordinat_latitude);

            if ($oLon === null || $oLat === null || $dLon === null || $dLat === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Koordinat kantor tidak lengkap',
                    'origin' => $origin,
                    'destination' => $destination,
                ], 422);
            }

            // Normalize coordinates: if lat looks like longitude (>90) and lng looks like latitude (<=90), swap them
            [$oLat, $oLon] = $this->normalizeLatLng($oLat, $oLon);
            [$dLat, $dLon] = $this->normalizeLatLng($dLat, $dLon);

            $distanceKm = $this->haversine($oLat, $oLon, $dLat, $dLon);

            // Simple path for frontend to render a line between two points
            $pathCoords = [
                ['lat' => $oLat, 'lng' => $oLon],
                ['lat' => $dLat, 'lng' => $dLon],
            ];

            // GeoJSON LineString for map libraries that accept GeoJSON directly
            $geoJson = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [$oLon, $oLat],
                        [$dLon, $dLat],
                    ],
                ],
                'properties' => [
                    'distance_km' => $distanceKm,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'origin' => [
                        'id' => $origin->id,
                        'nama' => $origin->nama_kantor ?? $origin->nama ?? null,
                        'jenis_kantor' => $origin->jenis_kantor ?? null,
                        'alamat' => $origin->alamat ?? null,
                        'longitude' => $oLon,
                        'latitude' => $oLat,
                    ],
                    'destination' => [
                        'id' => $destination->id,
                        'nama' => $destination->nama_kantor ?? $destination->nama ?? null,
                        'jenis_kantor' => $destination->jenis_kantor ?? null,
                        'alamat' => $destination->alamat ?? null,
                        'longitude' => $dLon,
                        'latitude' => $dLat,
                    ],
                    'distance_km' => $distanceKm,
                    'path_coords' => $pathCoords,
                    'geojson' => $geoJson,
                ]
            ]);
        }

        private function findKpc($id = null, $name = null, $jenisKantor = null, $idRegional = null, $idKprk = null)
        {
            $query = Kpc::query();

            if ($id) {
                return $query->find($id);
            }

            if ($jenisKantor) {
                $query->where('jenis_kantor', $jenisKantor);
            }

            if ($idRegional) {
                $query->where('id_regional', $idRegional);
            }

            if ($idKprk) {
                $query->where('id_kprk', $idKprk);
            }

            if ($name) {
                $query->where(function($q) use ($name) {
                    $q->where('nama', 'LIKE', "%$name%")
                      ->orWhere('nomor_dirian', 'LIKE', "%$name%")
                      ->orWhere('alamat', 'LIKE', "%$name%");
                });
            }

            return $query->first();
        }

        private function toFloat($value)
        {
            if ($value === null) return null;
            // Handle values with comma decimal separators stored as strings
            $str = is_string($value) ? str_replace(',', '.', $value) : $value;
            return is_numeric($str) ? floatval($str) : null;
        }

        private function haversine($lat1, $lon1, $lat2, $lon2)
        {
            $earthRadius = 6371; // km
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) +
                 cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                 sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return round($earthRadius * $c, 3);
        }

        private function normalizeLatLng($lat, $lng)
        {
            // Latitude must be in [-90, 90], Longitude in [-180, 180]
            // If values look swapped, fix them.
            if ($lat !== null && $lng !== null) {
                $absLat = abs($lat);
                $absLng = abs($lng);
                if ($absLat > 90 && $absLng <= 90) {
                    // Swap suspected
                    return [$lng, $lat];
                }
            }
            return [$lat, $lng];
        }
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
                    $foto = PencatatanKantor::with('files')
                        ->where('id_kpc', $data->id_kpc_ref)
                        ->get()
                        ->flatMap(fn($p) => $p->files->map(fn($f) => [
                            'id' => $f->rowid_parent ?? $f->id ?? null,
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
                    $foto = PencatatanKantor::with('files')
                        ->where('id_kpc', $idForPhoto)
                        ->get()
                        ->flatMap(fn($p) => $p->files->map(fn($f) => [
                            'id' => $f->rowid_parent ?? $f->id ?? null,
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
