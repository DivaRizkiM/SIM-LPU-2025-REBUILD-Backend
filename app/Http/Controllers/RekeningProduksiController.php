<?php

namespace App\Http\Controllers;

use App\Models\RekeningProduksi;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class RekeningProduksiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');

            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
                // Create more order mappings if needed
            ];

            $order = $orderMappings[$getOrder] ?? $defaultOrder;

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
                ], Response::HTTP_BAD_REQUEST);
            }

            $query = RekeningProduksi::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }
            $total_data = $query->count();
            $rekeningProduksi = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $rekeningProduksi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_rekening' => 'required',
                'nama' => 'required',
                'id_produk' => 'required|integer',
                'nama_produk' => 'required',
                'id_tipe_bisnis' => 'required|integer',
                'tgl_sinkronisasi' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $rekeningProduksi = RekeningProduksi::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Rekening Produksi',
                'modul' => 'Rekening Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningProduksi], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $rekeningProduksi = RekeningProduksi::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningProduksi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_rekening' => 'required',
                'nama' => 'required',
                'id_produk' => 'required|integer',
                'nama_produk' => 'required',
                'id_tipe_bisnis' => 'required|integer',
                'tgl_sinkronisasi' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $rekeningProduksi = RekeningProduksi::where('id', $id)->first();
            $rekeningProduksi->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Rekening Produksi',
                'modul' => 'Rekening Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningProduksi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $rekeningProduksi = RekeningProduksi::where('id', $id)->first();
            $rekeningProduksi->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Rekening Produksi',
                'modul' => 'Rekening Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Rekening produksi deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
