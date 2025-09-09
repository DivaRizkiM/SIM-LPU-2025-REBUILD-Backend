<?php

namespace App\Http\Controllers;

use App\Models\LockVerifikasi;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class LockVerifikasiController extends Controller
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
                'tahunASC' => 'tahun ASC',
                'tahunDESC' => 'tahun DESC',
                'bulanASC' => 'bulan ASC',
                'bulanDESC' => 'bulan DESC',
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

            $lockVerifikasisQuery = LockVerifikasi::orderByRaw($order);
            $total_data = $lockVerifikasisQuery->count();
            if ($search !== '') {
                $lockVerifikasisQuery->where(function ($query) use ($search) {
                    $query->where('tahun', 'like', "%$search%")
                        ->orWhere('bulan', 'like', "%$search%");
                });
            }
            $lockVerifikasis = $lockVerifikasisQuery->offset($offset)
                ->limit($limit)->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $lockVerifikasis,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $lockVerifikasi = LockVerifikasi::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $lockVerifikasi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'required|integer',
                'bulan' => 'required|integer|min:1|max:12',
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $lockVerifikasi = LockVerifikasi::create($request->all());
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Create Lock Verifikasi',
                'modul' => 'Lock Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $lockVerifikasi], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'integer',
                'bulan' => 'integer|min:1|max:12',
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $lockVerifikasi = LockVerifikasi::where('id', $id)->first();
            if (!$lockVerifikasi) {
                return response()->json(['status' => 'ERROR', 'message' => 'LockVerifikasi not found'], 404);
            }

            $lockVerifikasi->update($request->all());
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Lock Verifikasi',
                'modul' => 'Lock Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $lockVerifikasi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'integer',
                'bulan' => 'integer|min:1|max:12',
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $lockVerifikasi = LockVerifikasi::where('id', $id)->first();
            if (!$lockVerifikasi) {
                return response()->json(['status' => 'ERROR', 'message' => 'LockVerifikasi not found'], 404);
            }

            $lockVerifikasi->update($request->all());
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Lock Verifikasi',
                'modul' => 'Lock Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $lockVerifikasi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(LockVerifikasi $lockVerifikasi)
    {
        try {
            if (!$lockVerifikasi) {
                return response()->json(['status' => 'ERROR', 'message' => 'LockVerifikasi not found'], 404);
            }
            $lockVerifikasi->delete();
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Delete Lock Verifikasi',
                'modul' => 'Lock Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'LockVerifikasi deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function hapus(string $id_lock)
    {
        try {
            $lockVerifikasi = LockVerifikasi::find($id_lock);
            if (!$lockVerifikasi) {
                return response()->json(['status' => 'ERROR', 'message' => 'LockVerifikasi not found'], 404);
            }
            $lockVerifikasi->delete();
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Delete Lock Verifikasi',
                'modul' => 'Lock Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'LockVerifikasi deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
