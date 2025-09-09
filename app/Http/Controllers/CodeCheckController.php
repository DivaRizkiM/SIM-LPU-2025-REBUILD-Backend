<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ResetCodePassword;
use App\Http\Requests\Auth\CodeCheckRequest;

class CodeCheckController extends Controller
{
    /**
     * Check if the code is valid and not expired (Step 2)
     *
     * @param CodeCheckRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(CodeCheckRequest $request)
    {
        try {
            $passwordReset = ResetCodePassword::where('code', $request->code)->first();
    
            if (!$passwordReset) {
                return response()->json(['error' => trans('invalid code')], 422);
            }
    
            if ($passwordReset->isExpire()) {
                return response()->json(['error' => trans('code is expired')], 422);
            }
    
            return response()->json(['code' => $passwordReset->code], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
}
