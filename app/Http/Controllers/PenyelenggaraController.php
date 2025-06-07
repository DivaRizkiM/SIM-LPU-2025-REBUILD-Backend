<?php

namespace App\Http\Controllers;

use App\Models\Penyelenggara;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class PenyelenggaraController extends Controller
{
    // public function index()
    // {
    //     try {
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 10);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $defaultOrder = $getOrder ? $getOrder : "id ASC";
    //         $orderMappings = [
    //             'idASC' => 'id ASC',
    //             'idDESC' => 'id DESC',
    //             'namaASC' => 'nama ASC',
    //             'namaDESC' => 'nama DESC',
    //         ];

    //         // Set the order based on the mapping or use the default order if not found
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;
    //         // Validation rules for input parameters
    //         $validOrderValues = implode(',', array_keys($orderMappings));
    //         $rules = [
    //             'offset' => 'integer|min:0',
    //             'limit' => 'integer|min:1',
    //             'order' => "in:$validOrderValues",
    //         ];

    //         $validator = Validator::make([
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //         ], $rules);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }
    //         $penyelenggarasQuery = Penyelenggara::orderByRaw($order);
    //         $total_data = $penyelenggarasQuery->count();
    //         if ($search !== '') {
    //             $penyelenggarasQuery->where('nama', 'like', "%$search%");
    //         }

    //         $penyelenggaras = $penyelenggarasQuery
    //             ->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $penyelenggaras,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function index(Request $request)
{
    try {
        $offset = $request->get('offset');
            $limit = $request->get('limit');
            $page = $request->get('page');
            $perPage = $request->get('per-page');
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $loopCount = $request->get('loopCount');
            if (is_null($page) && is_null($perPage) && is_null($loopCount)) {
                $offset = $offset ?? 0; // Default offset
                $limit = $limit ?? 10; // Default limit
            } else {
                $page = $page ?? 1; // Default halaman ke 1
                $perPage = $perPage ?? 10; // Default per halaman ke 10
                $loopCount = $loopCount ?? 1; // Default loopCount ke 1

                // Hitung offset dan limit berdasarkan page, per-page, dan loopCount
                $offset = ($page - 1) * $perPage * $loopCount;
                $limit = $perPage * $loopCount;
            }

        // Tentukan aturan urutan default dan pemetaan urutan
        $defaultOrder = $getOrder ? $getOrder : "id ASC";
        $orderMappings = [
            'idASC' => 'id ASC',
            'idDESC' => 'id DESC',
            'namaASC' => 'nama ASC',
            'namaDESC' => 'nama DESC',
        ];

        // Atur urutan berdasarkan pemetaan atau gunakan urutan default
        $order = $orderMappings[$getOrder] ?? $defaultOrder;

        // Validasi parameter masukan
        $validOrderValues = implode(',', array_keys($orderMappings));
        $rules = [
            'page' => 'integer|min:1|nullable',
            'per-page' => 'integer|min:1|nullable',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1',
            'order' => "in:$validOrderValues",
            'loopCount' => 'integer|min:1|nullable',
        ];
        $validator = Validator::make([
            'offset' => $offset,
            'limit' => $limit,
            'order' => $getOrder,
            'page' => $page,
            'per-page' => $perPage,
        ], $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Query untuk mengambil data penyelenggara
        $penyelenggarasQuery = Penyelenggara::orderByRaw($order);

        // Hitung total data sebelum penerapan limit dan offset
        $total_data = $penyelenggarasQuery->count();

        // Tambahkan kondisi pencarian jika diberikan
        if ($search !== '') {
            $penyelenggarasQuery->where('nama', 'like', "%$search%");
        }

        // Ambil data dengan limit dan offset
        $penyelenggaras = $penyelenggarasQuery
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'page' => $page,
            'per-page' => $perPage,
            'offset' => $offset,
            'limit' => $limit,
            'order' => $getOrder,
            'search' => $search,
            'total_data' => $total_data,
            'data' => $penyelenggaras,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function show($id)
    {
        try {
            $penyelenggara = Penyelenggara::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $penyelenggara]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $penyelenggara = Penyelenggara::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Penyelenggara',
                'modul' => 'Penyelenggara',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $penyelenggara], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $penyelenggara = Penyelenggara::where('id', $id)->first();
            $penyelenggara->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Penyelenggara',
                'modul' => 'Penyelenggara',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $penyelenggara]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $penyelenggara = Penyelenggara::where('id', $id)->first();
            $penyelenggara->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Penyelenggara',
                'modul' => 'Penyelenggara',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Penyelenggara deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
