<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\{ActivityLog,AdminLogin};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Mail\ForgotPasswordMail;

class AdminLoginController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/admin/admin-login",
     *   tags={"Admin"},
     *   summary="Authentication",
     *   operationId="Admin login Authentication",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email", "password"},
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="password", type="string"),
     *          ),
     *      ),
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   )
     *)
     **/
    public function login(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email'=>'required|email',
            'password'=>'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        if(!Auth::guard('admin')->attempt($data))
        {
            return $this->sendError("invalid login credentials",[], 401);
        }

        config(['auth.guards.api.provider' => 'admins']);


        $admin = AdminLogin::find(auth()->guard('admin')->user()->id);
        $admin->last_seen_at = Carbon::now()->format('Y-m-d H:i:s');
        $admin->save();

        $token = auth()->guard('admin')->user()->createToken('access_token');

        return $this->successfulResponse([
            "admin" => $admin,
            "token" => $token->accessToken,
            "expires_at" => Carbon::parse($token->token->expires_at)->toDateTimeString()
        ], 'Logged in successfully');



    }


    /**
     * @OA\Get(
     ** path="/api/v1/admin/logout",
     *   tags={"Admin"},
     *   summary="Admin Logout",
     *   operationId="Adminlogout",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *)
     **/
    public function logout()
    {
        Auth::user()->token()->revoke();
        $data2=array(
            'activity' => 'Admin Logout',
            'user_id' => Auth::user()->id
        );

        ActivityLog::createActivity($data2);

        return response([ 'message' => 'logged out successfully'],200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/admin-profile",
     *   tags={"Admin"},
     *   summary="Admin Profile",
     *   operationId="AdminProfile",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *)
     **/
    public function adminProfile()
    {
        config(['auth.guards.api.provider' => 'admins']);

        $admin = AdminLogin::find(Auth::user()->id);
        if(!$admin)
        {
            return $this->sendError("Authourized user",[], 401);
        }

        return response()->json([$admin,'admin profile successfully retrieved'], 200);
    }

}
