<?php

namespace App\Http\Controllers;

use App\Exports\ProfileBoLpuExport;
use App\Models\AlokasiDana;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
class ProfileBoLpuController extends Controller
{
    public function index(Request $request)
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
            $query = AlokasiDana::join('kpc', 'kpc.id', '=', 'alokasi_dana.id_kpc')
                ->join('regional', 'regional.id', '=', 'kpc.id_regional')
                ->join('kprk', 'kprk.id', '=', 'kpc.id_kprk')
                ->select('alokasi_dana.*', 'kpc.id as id_kpc', 'regional.nama as nama_regional', 'kprk.nama as nama_kprk', 'kpc.nama as nama_kpc');
                $total_data = $query->count();
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
            $data = $query
            ->orderByRaw($order)
            ->offset($offset)
            ->limit($limit)->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' =>$total_data,
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
                'aktifitas' =>'Cetak Profile BO LPU',
                'modul' => 'Profile BO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return Excel::download(new ProfileBoLpuExport($data), 'template_laporan_profil_bo_lpu.xlsx');
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
