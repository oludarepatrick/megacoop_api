<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\{User,ActivityLog};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class AuthController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/login",
     *   tags={"Authentication"},
     *   summary="Authentication",
     *   operationId="login Authentication",
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
        $user = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if(!Auth::attempt($user))
        {
            return $this->sendError("Invalid login credentials",[], 401);
        }

        // if(!Auth::user()->verify_email_status){
        //     return $this->sendError("Email verification required",[], 400);
        // }

        $accessToken = Auth::user()->createToken('access_token');
        // if(Auth::user()->role_id == 1){
        //     Log::info('merchant role');
        //     $accessToken = Auth::user()->createToken('access_token', ['merchant']);
        // }else{
        //     Log::info('user role');
        //     $accessToken = Auth::user()->createToken('access_token');
        // }
        $user = Auth::user();

        if ($user->status==1)
        {
            $user->last_seen_at = Carbon::now()->format('Y-m-d H:i:s');
            $user->save();

            $data2['activity']="Login";
            $data2['user_id']=$user->id;

            ActivityLog::createActivity($data2);

            return $this->successfulResponse([
                "user" => new UserResource($user),
                "token" => $accessToken->accessToken,
                "expires_at" => Carbon::parse($accessToken->token->expires_at)->toDateTimeString()
            ], 'Logged in successfully');

        }else return $this->sendError('Your account has been deactivated, contact the admin',[],401);
    }
    
}
