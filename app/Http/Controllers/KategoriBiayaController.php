<?php

namespace App\Http\Controllers;

use App\Models\KategoriBiaya;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class KategoriBiayaController extends Controller
{
    public function index()
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
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
            $kategori_biayasQuery = KategoriBiaya::orderByRaw($order);
            $total_data = $kategori_biayasQuery->count();

            if ($search !== '') {
                $kategori_biayasQuery->where('nama', 'like', "%$search%");
            }

            $kategori_biayas = $kategori_biayasQuery
                ->offset($offset)
                ->limit($limit)->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $kategori_biayas,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $kategori_biaya = KategoriBiaya::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $kategori_biaya]);
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

            $kategori_biaya = KategoriBiaya::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Kategori Biaya',
                'modul' => 'Kategori Biaya',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kategori_biaya], 201);
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

            $kategori_biaya = KategoriBiaya::where('id', $id)->first();
            $kategori_biaya->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Kategori Biaya',
                'modul' => 'Kategori Biaya',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kategori_biaya]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $kategori_biaya = KategoriBiaya::where('id', $id)->first();
            $kategori_biaya->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Kategori Biaya',
                'modul' => 'Kategori Biaya',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'KategoriBiaya deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function apiList(Request $request)
    {
        try {            
            $data = KategoriBiaya::get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data Tersedia',
                'total_data' => $data->count(),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDetail($id)
    {
        try {
            $data = KategoriBiaya::find($id);
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data Tersedia',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
