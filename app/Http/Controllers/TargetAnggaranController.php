<?php

namespace App\Http\Controllers;

use App\Models\TargetAnggaran;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class TargetAnggaranController extends Controller
{
    public function index()
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun = request()->get('tahun', '');
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'tahunASC' => 'tahun ASC',
                'tahunDESC' => 'tahun DESC',
                'nominalASC' => 'nominal ASC',
                'nominalDESC' => 'nominal DESC',
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
            $targetAnggaransQuery = TargetAnggaran::orderByRaw($order);
            $total_data = $targetAnggaransQuery->count();
            if ($search !== '') {
                $targetAnggaransQuery->where('tahun', 'like', "%$search%")->orwhere('nominal', 'like', "%$search%");
            }
            if ($tahun) {
                $targetAnggaransQuery->where('tahun', $tahun);
            }

            $targetAnggarans = $targetAnggaransQuery
                ->offset($offset)
                ->limit($limit)->get();
            $targetAnggarans = $targetAnggarans->map(function ($targetAnggaran) {
                $targetAnggaran->nominal = (int) $targetAnggaran->nominal;
                return $targetAnggaran;
            });

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $targetAnggarans,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $targetAnggaran = TargetAnggaran::where('id', $id)->first();
            if ($targetAnggaran) {
                $targetAnggaran->nominal = (int) $targetAnggaran->nominal;
            }
            return response()->json(['status' => 'SUCCESS', 'data' => $targetAnggaran]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function showDB()
    {
        try {
            $tahun = date('Y');
            $targetAnggaran = TargetAnggaran::where('tahun', $tahun)->sum('nominal');
            if ($targetAnggaran) {
                // You don't need to access nominal since targetAnggaran is a numeric value
                $targetAnggaran = (int) $targetAnggaran;
            }
            return response()->json(['status' => 'SUCCESS', 'data' => $targetAnggaran]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }



    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'required|integer',
                'nominal' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $targetAnggaran = TargetAnggaran::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Laporan Realisasi SO LPU',
                'modul' => 'Laporan Realisasi SO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $targetAnggaran], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [

                'tahun' => 'required|integer',
                'nominal' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $targetAnggaran = TargetAnggaran::where('id', $id)->first();
            if (!$targetAnggaran) {
                return response()->json(['status' => 'ERROR', 'message' => 'Target anggaran not found'], 404);
            }

            $targetAnggaran->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Laporan Realisasi SO LPU',
                'modul' => 'Laporan Realisasi SO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $targetAnggaran]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $targetAnggaran = TargetAnggaran::where('id', $id)->first();
            $targetAnggaran->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Laporan Realisasi SO LPU',
                'modul' => 'Laporan Realisasi SO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'TargetAnggaran deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
