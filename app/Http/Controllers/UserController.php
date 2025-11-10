<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\User;
use App\Models\UserGrup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Claims\Collection;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Payload;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         // Ambil parameter offset, limit, dan order dari permintaan
    //         $offset = $request->get('offset', 0);
    //         $limit = $request->get('limit', 10);
    //         $search = $request->get('search', '');
    //         $getOrder = $request->get('order', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "id ASC";
    //         $orderMappings = [
    //             'idASC' => 'id ASC',
    //             'idDESC' => 'id DESC',
    //             'nameASC' => 'name ASC',
    //             'nameDESC' => 'name DESC',
    //             // Tambahkan pemetaan urutan lain jika diperlukan
    //         ];

    //         // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;

    //         // Validasi aturan untuk parameter masukan
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
    //         $query = User::query();

    //         if ($search !== '') {
    //             $query->where('nama', 'like', "%$search%");
    //         }
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], Response::HTTP_BAD_REQUEST);
    //         }

    //         // Query data User dengan offset, limit, dan pencarian

    //         $users = $query->orderByRaw($order)
    //             ->offset($offset)
    //             ->limit($limit)
    //             ->get();

    //         // Query kabupaten/kota with search condition if search keyword is provided
    //         $usersQuery = User::leftJoin('status', 'user.id_status', '=', 'status.id')
    //         ->leftJoin('user_grup', 'user.id_grup', '=', 'user_grup.id')
    //         ->select('user.*', 'user_grup.nama as nama_grup', 'status.nama as status');
    //         $total_data =  $usersQuery->count();

    //     if ($search !== '') {
    //         $usersQuery->where(function ($query) use ($search) {
    //             $query->where('user.nama', 'like', "%$search%")
    //                 ->orWhere('user_grup.nama', 'like', "%$search%")
    //                 ->orWhere('status.nama', 'like', "%$search%");
    //         });
    //     }

    //     $users = $usersQuery ->orderByRaw($order)
    //     ->offset($offset)
    //     ->limit($limit)->get();


    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data'=>$total_data,
    //             'data' => $users,
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function index(Request $request)
{
    try {
        $page = $request->get('page');
        $perPage = $request->get('per-page');
        $search = $request->get('search', '');
        $getOrder = $request->get('order', '');
        $offset = $request->get('offset');
        $limit = $request->get('limit');
        $loopCount = $request->get('loopCount');
               // Default nilai jika page, per-page, atau loopCount tidak disediakan
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
            'idASC' => 'user.id ASC',
            'idDESC' => 'user.id DESC',
            'nameASC' => 'user.nama ASC',
            'nameDESC' => 'user.nama DESC',
            'statusASC' => 'status.nama ASC',
            'statusDESC' => 'status.nama DESC',
            'grupASC' => 'user_grup.nama ASC',
            'grupDESC' => 'user_grup.nama DESC',
        ];

        // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
        $order = $orderMappings[$getOrder] ?? $defaultOrder;

        // Validasi aturan untuk parameter masukan
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
        ], $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Query utama untuk mengambil data pengguna
        $usersQuery = User::leftJoin('status', 'user.id_status', '=', 'status.id')
            ->leftJoin('user_grup', 'user.id_grup', '=', 'user_grup.id')
            ->select('user.*', 'user_grup.nama as nama_grup', 'status.nama as status');

        // Tambahkan kondisi pencarian jika diberikan
        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('user.nama', 'like', "%$search%")
                    ->orWhere('user_grup.nama', 'like', "%$search%")
                    ->orWhere('status.nama', 'like', "%$search%");
            });
        }

        // Hitung total data sebelum penerapan limit dan offset
        $total_data = $usersQuery->count();

        // Ambil data dengan offset, limit, dan pengurutan
        $users = $usersQuery->orderByRaw($order)
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
            'data' => $users,
        ]);
    } catch (\Exception $e) {
        return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    }
}

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'nip' => 'string|max:255|unique:user|nullable',
                'username' => 'required|string|max:255|unique:user',
                'password' => 'required|string|min:6',
                'id_grup' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $user = User::create([
                'nama' => $request->get('nama'),
                'nip' => $request->get('nip'),
                'username' => $request->get('username'),
                'password_hash' => bcrypt($request->get('password')),
                'id_grup' => $request->get('id_grup'),
                'id_status' => 2,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $user], 201);

        } catch (\Exception $e) {
            // dd($e);
            return response()->json(['error' => $e], 500);
        }
    }

    public function show($id)
    {
        try {
            // Cari User berdasarkan ID
            $user = User::find($id);

            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], 404);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama' => 'string|max:255',
                'nip' => 'string|max:255',
                'username' => 'string|max:255',
                'password' => 'string|nullable|min:6',
                'id_grup' => 'integer',
                'id_status' => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input data',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Cari dan perbarui data User berdasarkan ID
            $user = User::find($id);
            if($user->username != $request->input('username')){
                $existingUser = User::where('username', $request->input('username'))->first();
                if ($existingUser) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => 'Username already exists',
                    ], Response::HTTP_CONFLICT);
                }
            }
            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], 404);
            }

            // Memperbarui bidang-bidang yang ada dalam permintaan, jika ada
            $user->nama = $request->input('nama') ?? $user->nama;
            $user->username = $request->input('username') ?? $user->username;
            $user->nip = $request->input('nip') ?? $user->nip;

            // Periksa apakah password dikirim dalam permintaan dan setel ulang password_hash jika iya
            if ($request->has('password')) {
                $user->password_hash = bcrypt($request->input('password'));
            }

            // Memperbarui bidang id_grup, jika ada dalam permintaan
            if ($request->has('id_grup')) {
                $user->id_grup = $request->input('id_grup');
            }

            // Memperbarui bidang id_status, jika ada dalam permintaan
            if ($request->has('id_status')) {
                $user->id_status = $request->input('id_status');
            }

            // Simpan perubahan
            $user->save();

            return response()->json(['status' => 'SUCCESS', 'data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Cari dan hapus data User berdasarkan ID
            $user = User::find($id);

            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], 404);
            }
            $user->deleted_by = auth()->user()->id;
            $user->save();
            $user->delete();

            return response()->json(['status' => 'SUCCESS', 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function status()
    {
        try {
            // Cari User berdasarkan ID
            $status = Status::all();

            if (!$status) {
                return response()->json(['status' => 'ERROR', 'message' => 'status not found'], 404);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function grup()
    {
        try {
            // Cari User berdasarkan ID
            $user_grup = UserGrup::all();

            if (!$user_grup) {
                return response()->json(['status' => 'ERROR', 'message' => 'user grup not found'], 404);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $user_grup]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function generateapikey()
    {
        // Membuat payload JWT dengan menggunakan Collection
        $claims = new Collection(['api_key' => true]);
        $access_token = Session::get('accessToken');
        $payload = new Payload($claims, $access_token);

        // Menghasilkan token JWT dengan payload yang diberikan
        $token = JWTAuth::encode($payload);

        // Mengembalikan token sebagai API key
        return response()->json(['api_key' => $token]);
    }

    public function updatePassword(Request $request)
{
    try {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid input data',
                'errors' => $validator->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Ambil user yang sedang login (pastikan menggunakan middleware auth)
        $user = Auth::user();

        // Periksa apakah old_password cocok dengan password saat ini
        if (!password_verify($request->input('old_password'), $user->password_hash)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Old password is incorrect',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Update password dengan new_password
        $user->password_hash = bcrypt($request->input('new_password'));
        $user->save();

        return response()->json(['status' => 'SUCCESS', 'message' => 'Password updated successfully']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    }
}

public function userVerificator(){
    try {
        // Cari User berdasarkan ID
        $user = User::where('id_grup',8)->get();

        if (!$user) {
            return response()->json(['status' => 'ERROR', 'message' => 'User not found'], 404);
        }

        return response()->json(['status' => 'SUCCESS', 'data' => $user]);
    } catch (\Exception $e) {
        return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    }
}

}
