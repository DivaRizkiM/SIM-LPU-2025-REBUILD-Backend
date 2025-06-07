<?php

namespace App\Http\Controllers;

use App\Models\ResetCodePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    /**
     * @param  mixed $request
     * @return void
     */
    public function __invoke(ResetPasswordRequest $request)
    {   
        
        $passwordReset = ResetCodePassword::firstWhere('code', $request->code);

        // Cek apakah kode reset password yang diminta ditemukan
        if (!$passwordReset) {
            return $this->jsonResponse(null, trans('Invalid reset code'), 422);
        }
        if ($passwordReset->isExpire()) {
            return $this->jsonResponse(null, trans('code is expired'), 422);
        }

        $user = User::firstWhere('email', $passwordReset->email);

        // $user->update($request->only('password'));
        $user->update([
            "password" => Hash::make($request->password)
        ]);

        $passwordReset->where('code', $request->code)->delete();

        return $this->jsonResponse(null, trans('password has been successfully reset'), 200);
    }

    public function resetFirstPassword(Request $request)
{
    $user = Auth::user();

    if (empty($user)) {
        return $this->jsonResponse(null, trans('Unauthorized, please login again'), 422);
    }
    $validator = Validator::make($request->all(), [
 
        'old_password' => 'required|string|min:6',
        'password' => 'required|string|min:6',
       
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()
        ], 422);
    }

    // Validasi password lama
    if (!Hash::check($request->old_password, $user->password)) {
        return $this->jsonResponse(null, trans('old password not matched'), 422);
    }

    $user->update([
        "password" => Hash::make($request->password)
    ]);

    return $this->jsonResponse(null, trans('password has been successfully reset'), 200);
}
}