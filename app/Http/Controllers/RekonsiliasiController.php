<?php

namespace App\Http\Controllers;

use App\Models\Rekonsiliasi;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Auth;

class RekonsiliasiController extends Controller
{
    public function index()
    {
        try {
            // Ambil parameter offset, limit, order, dan search dari permintaan
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $idPenyelenggara = request()->get('id_penyelenggara', '');
            $idProvinsi = request()->get('id_provinsi', '');
            $idKab = request()->get('id_kabupaten_kota', '');
            $idKec = request()->get('id_kecamatan', '');
            $idKel = request()->get('id_kelurahan', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "rekonsiliasi.id ASC";
            $orderMappings = [
                'idASC' => 'kabupaten_kota.id ASC',
                'idDESC' => 'kabupaten_kota.id DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
                'namapenyelenggaraASC' => 'penyelenggara.nama ASC',
                'namapenyelenggaraDESC' => 'penyelenggara.nama DESC',
                'jeniskantorASC' => 'jenis_kantor.nama ASC',
                'jeniskantorDESC' => 'jenis_kantor.nama DESC',
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
                'id_penyelenggara' => 'integer|exists:penyelenggara,id',
                'id_provinsi' => 'integer|exists:provinsi,id',
                'id_kabupaten_kota' => 'integer|exists:id_kabupaten_kota,id',
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'id_penyelenggara' => $idPenyelenggara,
                'id_kabupaten_kota' => $idKab,
                'id_provinsi' => $idProvinsi,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Query kabupaten/kota with search condition if search keyword is provided
            $rekonsiliasisQuery = Rekonsiliasi::leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftJoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
                ->select('rekonsiliasi.*', 'penyelenggara.nama as nama_penyelenggara', 'jenis_kantor.nama as nama_jenis_kantor', 'kelurahan.nama as nama_kelurahan', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi','kecamatan.id as id_kecamatan','kabupaten_kota.id as id_kabupaten_kota','provinsi.id as id_provinsi');
        $total_data = $rekonsiliasisQuery->count();

            // Filter by id_penyelenggara if provided
            if ($idPenyelenggara) {
                $rekonsiliasisQuery->where('rekonsiliasi.id_penyelenggara', $idPenyelenggara);
            }
            if ($idKel) {
                $rekonsiliasisQuery->where('kelurahan.id', $idKel);
            }
            if ($idKab) {
                $rekonsiliasisQuery->where('kabupaten_kota.id', $idKab);
            }
            if ($idProvinsi) {
                $rekonsiliasisQuery->where('provinsi.id', $idProvinsi);
            }

            if ($search !== '') {
                $rekonsiliasisQuery->where(function ($query) use ($search) {
                    $query->where('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%")
                        ->orWhere('penyelenggara.nama', 'like', "%$search%")
                        ->orWhere('jenis_kantor.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%");
                });
            }

            $rekonsiliasis = $rekonsiliasisQuery
            ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data'=>$total_data,
                'data' => $rekonsiliasis,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $rekonsiliasi = Rekonsiliasi::leftJoin('kelurahan', 'rekonsiliasi.id_kelurahan', '=', 'kelurahan.id')
                ->leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->leftJoin('penyelenggara', 'rekonsiliasi.id_penyelenggara', '=', 'penyelenggara.id')
                ->leftJoin('jenis_kantor', 'rekonsiliasi.id_jenis_kantor', '=', 'jenis_kantor.id')
                ->select('rekonsiliasi.*', 'penyelenggara.nama as nama_penyelenggara', 'jenis_kantor.nama as nama_jenis_kantor', 'kelurahan.nama as nama_kelurahan', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi','kecamatan.id as id_kecamatan','kabupaten_kota.id as id_kabupaten_kota','provinsi.id as id_provinsi')->where('rekonsiliasi.id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $rekonsiliasi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_penyelenggara' => 'required|exists:penyelenggara,id',
                'id_kelurahan' => 'required|exists:kelurahan,id',
                'id_jenis_kantor' => 'required',
                'id_kantor' => 'required|integer',
                'nama_kantor' => 'required|string',
                'alamat' => 'required|string',
                'longitude' => 'required|numeric',
                'latitude' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $rekonsiliasi = Rekonsiliasi::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Rekonsiliasi',
                'modul' => 'Rekonsiliasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $rekonsiliasi], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_penyelenggara' => 'exists:penyelenggara,id',
                // 'id_provinsi' => 'exists:provinsi,id',
                // 'id_kabupaten_kota' => 'exists:kabupaten_kota,id',
                // 'id_kecamatan' => 'exists:kecamatan,id',
                'id_kelurahan' => 'exists:kelurahan,id',
                'id_jenis_kantor' => 'exists:jenis_kantor,id',
                'id_kantor' => 'integer',
                'nama' => 'string',
                'nama_kantor' => 'string',
                'alamat' => 'string',
                'longitude' => 'numeric',
                'latitude' => 'numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $rekonsiliasi = Rekonsiliasi::where('id', $id)->first();
            $rekonsiliasi->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Rekonsiliasi',
                'modul' => 'Rekonsiliasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $rekonsiliasi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $rekonsiliasi = Rekonsiliasi::where('id', $id)->first();
            $rekonsiliasi->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Rekonsiliasi',
                'modul' => 'Rekonsiliasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Rekonsiliasi deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function multistore(Request $request)
    {
        // Validasi request
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls', // memastikan file yang diunggah adalah file Excel
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            // Mengimpor data dari file Excel
            $importedData = $this->multiimportData($request->file('file'));

            // Simpan data ke database
            $store = $this->multisaveToDatabase($importedData);

            if ($store == true) {
                // Jika berhasil diimpor dan disimpan
                $userLog=[
                    'timestamp' => now(),
                    'aktifitas' =>'Multi Create Rekonsiliasi',
                    'modul' => 'Rekonsiliasi',
                    'id_user' => Auth::user(),
                ];

                $userLog = UserLog::create($userLog);
                return response()->json(['status' => 'success', 'message' => 'Data imported and saved successfully'], 200);
            } else {
                // Jika terjadi kesalahan saat menyimpan
                return response()->json(['error' => 'Failed to save data to the database'], 500);

                // return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            // Jika terjadi kesalahan, kembalikan respons error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function multiimportData($file)
    {
        // Menggunakan library PHPSpreadsheet untuk membaca file Excel
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($file->getPathname());
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        // Menghapus baris kosong dari hasil impor
        $sheetData = array_filter($sheetData);

        return $sheetData;
    }

    private function multisaveToDatabase($data)
    {
        // Status default
        $status = false;

        // Start from the second row (index 1) to skip the header
        foreach ($data as $index => $row) {
            if ($index === 0) {
                continue; // Skip the header row
            }

            $id_penyelenggara = $row[0];
            $id_jenis_kantor = $row[1];
            $id_kelurahan = $row[2];
            $id_kantor = $row[3];
            $nama_kantor = $row[4];
            $alamat = $row[5];
            $longitude = $row[6];
            $latitude = $row[7];
            if($id_penyelenggara=='' ||$id_penyelenggara==[]||!$id_penyelenggara){
                $status = false;
            }else{

            // Cari record dengan kombinasi kolom yang unik
            $getdata = Rekonsiliasi::where('id_penyelenggara', $id_penyelenggara)
                ->where('id_kelurahan', $id_kelurahan)
                ->where('id_jenis_kantor', $id_jenis_kantor)
                ->where('id_kantor', $id_kantor)
                ->first();

            if ($getdata) {
                // Set status menjadi true karena ada record yang ditemukan
                $status = true;
                $getdata->update([
                    'nama_kantor' => $nama_kantor,
                    'alamat' => $alamat,
                    'longitude' => $longitude,
                    'latitude' => $latitude,
                ]);
            } else {
                    $status = true;
                    Rekonsiliasi::create([
                        'id_penyelenggara' => $id_penyelenggara,
                        'id_kelurahan' => $id_kelurahan,
                        'id_jenis_kantor' => $id_jenis_kantor,
                        'id_kantor' => $id_kantor,
                        'nama_kantor' => $nama_kantor,
                        'alamat' => $alamat,
                        'longitude' => $longitude,
                        'latitude' => $latitude,
                    ]);
                }
            }
        }
        return $status;
    }
}
