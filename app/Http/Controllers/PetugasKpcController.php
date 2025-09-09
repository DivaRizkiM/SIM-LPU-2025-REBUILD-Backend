<?php

namespace App\Http\Controllers;

use App\Models\PetugasKPC;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PetugasKpcController extends Controller
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
            $petugaskpcsQuery = PetugasKPC::orderByRaw($order);
            $total_data = $petugaskpcsQuery->count();
               $data = $petugaskpcsQuery ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $petugaskpcsQuery->where('nama', 'like', "%$search%");
            }

            $petugaskpcs = $petugaskpcsQuery->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data'=> $total_data,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $petugaskpc = PetugasKPC::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function getBykpc(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'required|numeric|exists:kpc,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            // Cari data Kprk berdasarkan ID
            $petugaskpc = PetugasKPC::where('id_kpc', $request->id_kpc)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'required|string|exists:kpc,id',
                'nippos' => 'required|numeric',
                'nama_petugas' => 'required',
                'pangkat' => 'required',
                'masa_kerja' => 'required|numeric',
                'jabatan' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            $get = PetugasKPC::find($request->nippos);
            if ($get) {
                return response()->json(['status' => 'ERROR', 'message' => 'NIPPOS Sudah Terdaftar']);
            }
            $data = $request->all();
            $data['id'] = $request->nippos;
            $data['id_user'] = $user->id; // Assuming 'id_user' is the field to store the user ID
            $data['tanggal_update'] = now(); // Assuming 'id_user' is the field to store the user ID

            $petugaskpc = PetugasKPC::create($data);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Petugas KCP',
                'modul' => 'Petugas KCP',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);

            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'nullable|string|exists:kpc,id',
                'nippos' => 'nullable|numeric',
                'nama_petugas' => 'nullable',
                'pangkat' => 'nullable',
                'masa_kerja' => 'nullable|numeric',
                'jabatan' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $user = Auth::user();

            // Retrieve the record to update
            $petugaskpc = PetugasKPC::where('id', $id)->first();

            // Ensure the record exists before updating
            if (!$petugaskpc) {
                return response()->json(['status' => 'ERROR', 'message' => 'Record not found'], 404);
            }

            // Merge user ID into the request data
            $data = $request->all();
            if ($request->nippos) {
                $data['id'] = $request->nippos;
            }

            $data['id_user'] = $user->id;
            $data['tanggal_update'] = now();

            // Perform the update
            $petugaskpc->update($data);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Petugas KCP',
                'modul' => 'Petugas KCP',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function destroy($id)
    {
        try {
            $petugaskpc = PetugasKPC::where('id', $id)->first();
            $petugaskpc->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Petugas KCP',
                'modul' => 'Petugas KCP',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'PetugasKPC deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
