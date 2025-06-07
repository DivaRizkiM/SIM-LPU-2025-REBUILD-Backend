<?php

namespace App\Http\Controllers;

use App\Models\ResetCodePassword;
use App\Mail\SendCodeResetPassword;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\Auth\ForgotPasswordRequest;

class ForgotPasswordController extends Controller
{
    /**
     * Send random code to email of user to reset password (Setp 1)
     *
     * @param  mixed $request
     * @return void
     */
    public function __invoke(ForgotPasswordRequest $request)
    {
        try {
            ResetCodePassword::where('email', $request->email)->delete();

            $codeData = ResetCodePassword::create($request->data());

            Mail::to($request->email)->send(new SendCodeResetPassword($codeData->code));

            return $this->jsonResponse(null, trans('email sended'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}