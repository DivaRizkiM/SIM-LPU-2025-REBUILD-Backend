<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGrup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required',
                'password_hash' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $username = $request->input('username');
            $password_hash = $request->input('password_hash');

            $user = User::where('username', $username)->first();
            $role = UserGrup::find($user->id_grup);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            $verificator = User::select('id','id_grup','username','nip')->where('id_grup',8)->get();

            if (!password_verify($password_hash, $user->password_hash)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            $token = JWTAuth::fromUser($user);
            $expiration_time = auth()->factory()->getTTL();
            $user->password_hash = null;
            $user->password_reset_token = null;
            $user->auth_key = null;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'verificator'=>$verificator,
                    'id' => $user->id,
                    'user' => $user->name,
                    'username' => $user->username,
                    'profile' => $user,
                    'role' => $role,
                    'token' => $token,
                    'exp' => $expiration_time,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|username|max:255|unique:users',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $user = User::create([
                'name' => $request->get('name'),
                'username' => $request->get('username'),
                'password' => bcrypt($request->get('password')),
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json(compact('user', 'token'), 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e], 500);
        }
    }

    public function logout()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized, please login again',
                    'error_code' => 'USER_NOT_FOUND',
                ], 401);
            }
            if (!Auth::check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token',
                    'error_code' => 'INVALID_TOKEN',
                ], 401);
            }

            Auth::logout();
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProfile()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'error_code' => 'UNAUTHORIZED',
                ], 401);
            }

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $userData,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getUser($id)
    {
        try {
            $user = User::where('id', $id)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'user not found',
                    'error_code' => 'USER_NOT_FOUND',
                ], 401);
            }

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $userData,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAllUser()
    {
        try {
            $users = User::orderBy('name', 'asc')->get();
            $userArray = [];
            foreach ($users as $user) {
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                ];
                array_push($userArray, $userData);
            }
            return response()->json([
                'status' => 'success',
                'data' => $userArray,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'error_code' => 'USER_NOT_FOUND',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'username' => 'string|username|max:255|unique:users,username,' . $id,
                'password' => 'string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $user->name = $request->name ?? $user->name;
            $user->username = $request->username ?? $user->username;
            $user->password = $request->password ? bcrypt($request->password) : $user->password;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteUser(Request $request)
    {
        try {
            $user = User::find($request->id);
            if ($user) {
                $user->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'User with name ' . $user->name . ' and with username ' . $user->username . ' has been deleted.',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User with name ' . $user->name . ' and with username ' . $user->username . ' not found.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportCSV()
    {
        try {
            $data = User::select('id', 'name', 'username')->orderBy('name', 'asc')->get(); // query data dari database
            $dateStart = date('Ymd');
            $filename = "users_" . $dateStart . ".csv";
            //local
            // $filename_path = public_path('storage/csv/' . $filename); // path to save CSV file in public/storage/csv

            //server
            $filename_path = '/home/doddiplexus/doddi.plexustechdev.com/templete/api/public/csv/' . $filename;

            // buat file CSV
            $handle = fopen($filename_path, 'w');
            fputcsv($handle, ['ID', 'Name', 'username']);
            foreach ($data as $row) {
                fputcsv($handle, [$row->id, $row->name, $row->username]);
            }
            fclose($handle);

            // Kasih balikan nama file & urlnya
            $filename_url = url('csv/' . $filename);
            return response()->json([
                'status' => 'SUCCESS',
                'filename' => $filename,
                'filename_url' => $filename_url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Failed to export CSV: ' . $e->getMessage(),
            ], 500);
        }
    }

}
