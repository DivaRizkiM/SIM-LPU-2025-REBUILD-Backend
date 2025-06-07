<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\Rekonsiliasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Ambil parameter offset, limit, dan order dari permintaan
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');
            $id_regional = $request->get('id_regional', '');
            $id_kprk = $request->get('id_kprk', '');
            $id_provinsi = $request->get('id_provinsi', '');
            $id_kabupaten_kota = $request->get('id_kabupaten_kota', '');
            $id_kecamatan = $request->get('id_kecamatan', '');
            $id_kelurahan = $request->get('id_kelurahan', '');
            $id_penyelenggara = $request->get('id_penyelenggara', '');
            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu');
            $jenis_kantor = $request->get('jenis_kantor', '');
            $id_jenis_kantor = $request->get('id_jenis_kantor', '');

            // Tentukan aturan urutan default dan pemetaan urutan



            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',

            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,

            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $data = [];
            $total_data =[];
            if($type_penyelenggara == 'lpu' ||$type_penyelenggara == 'lpk'){
                $query = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('kabupaten_kota', 'kprk.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                ->select('kpc.id as id_kpc',
                    'kpc.koordinat_longitude',
                    'kpc.koordinat_latitude',
                    'kpc.alamat',
                    'kpc.nama as nama_kpc',
                    'regional.nama as nama_regional',
                    'kprk.nama as nama_kprk',
                    'provinsi.id as id_provinsi',
                    'kabupaten_kota.id as id_kabupaten_kota',
                    'kecamatan.id as id_kecamatan',
                    'kelurahan.id as id_kelurahan');
                $total_data = $query->count();
                if ($search !== '') {
                    $query->where(function ($query) use ($search) {
                        $query->where('kpc.nama', 'like', "%$search%")->where('kprk.nama', 'like', "%$search%")
                            ->orWhere('regional.nama', 'like', "%$search%");
                    });
                }
                if ($id_provinsi) {
                    $query->where('kpc.id_provinsi', $id_provinsi);
                }
                if ($id_kabupaten_kota) {
                    $query->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
                }
                if ($id_kecamatan) {
                    $query->where('kpc.id_kecamatan', $id_kecamatan);
                }
                if ($id_kelurahan) {
                    $query->where('kpc.id_kelurahan', $id_kelurahan);
                }
                if ($id_regional) {
                    $query->where('kpc.id_regional', $id_regional);
                }
                if ($id_kprk) {
                    $query->where('kprk.id', $id_kprk);
                }
                if ($jenis_kantor) {
                    $query->where('kpc.jenis_kantor', $jenis_kantor);
                }
                if ($search !== '') {
                    $query->where('kpc.nama', 'like', "%$search%");
                }

                $data = $query->offset($offset)
                    ->limit($limit)->get();

            }else{
                $query = Rekonsiliasi::select('rekonsiliasi.id as id_rekonsiliasi',
                'rekonsiliasi.longitude as koordinat_longitude',
                'rekonsiliasi.latitude as koordinat_latitude',
                'rekonsiliasi.alamat',
                'rekonsiliasi.id_jenis_kantor',
                'rekonsiliasi.id_penyelenggara',
                'provinsi.id as id_provinsi',
                'kabupaten_kota.id as id_kabupaten_kota',
                'kecamatan.id as id_kecamatan',
                'kelurahan.id as id_kelurahan')
                ->leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id');
                $total_data = $query->count();
                if ($id_provinsi) {
                    $query->where('provinsi.id', $id_provinsi);
                }
                if ($id_kabupaten_kota) {
                    $query->where('kecamatan.id_kabupaten_kota', $id_kabupaten_kota);
                }
                if ($id_kecamatan) {
                    $query->where('kelurahan.id_kecamatan', $id_kecamatan);
                }
                if ($id_kelurahan) {
                    $query->where('rekonsiliasi.id_kelurahan', $id_kelurahan);
                }
                if ($id_penyelenggara) {
                    $query->where('rekonsiliasi.id_penyelenggara', $id_penyelenggara);
                }
                if ($id_jenis_kantor) {
                    $query->where('rekonsiliasi.id_jenis_kantor', $id_jenis_kantor);
                }
                if ($search !== '') {
                    $query->where('rekonsiliasi.nama_kantor', 'like', "%$search%");
                }
                $data = $query->offset($offset)
                ->limit($limit)->get();
            }
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function show(Request $request)
    {
        try {

            $type_penyelenggara = $request->get('type_penyelenggara', 'lpu');
            $id_kpc =$request->get('id_kpc', '');
            $id_rekonsiliasi =$request->get('id_rekonsiliasi', '');
            $data = [];

            if($type_penyelenggara == 'lpu' ||$type_penyelenggara == 'lpk'){
                $data = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('kabupaten_kota', 'kprk.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
                ->select('kpc.id as id_kpc',
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
                    ->where('kpc.id',$id_kpc)->first();

            }else{
                $data = Rekonsiliasi::select('rekonsiliasi.id as id_rekonsiliasi',
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
                'kelurahan.nama as nama_kelurahan')
                ->leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftJoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
                ->where('rekonsiliasi.id',$id_rekonsiliasi)->first();

            }
            return response()->json([
                'status' => 'SUCCESS',
                'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
