<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\SignupAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ZeptomailService;

class AccessCodeController extends BaseController
{
    /**
     * @OA\Post(
     *   path="/api/v1/admin/generate-code",
     *   tags={"Admin"},
     *   summary="Generate sign up access code",
     *   description="Generate a unique, case-sensitive, 8-digit alphanumeric access code for a new user. 
     *                If a valid code already exists, return it with remaining expiration time.",
     *   operationId="GenerateCode",
     *
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       required={"first_name", "last_name", "email"},
     *       @OA\Property(property="first_name", type="string", example="John"),
     *       @OA\Property(property="middle_name", type="string", example="A."),
     *       @OA\Property(property="last_name", type="string", example="Doe"),
     *       @OA\Property(property="email", type="string", example="john@example.com")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Code generated successfully or existing code returned",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Access code generated successfully"),
     *       @OA\Property(property="code", type="string", example="Ab3X9zPq"),
     *       @OA\Property(property="expires_in_mins", type="integer", example=1430)
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=500, description="Server error"),
     *   security={{"bearer_token": {}}}
     * )
    */

    public function generateCode(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'first_name'  => 'required',
            'last_name'   => 'required',
            'middle_name' => 'nullable',
            'email'       => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $adminId = auth()->id();
            $data['role_id'] = 3;

            // ✅ Find or create user
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                $user = User::create($data);
            }

            // ✅ Check if user already has a code
            $existingToken = SignupAccessToken::where('user_id', $user->id)->latest()->first();

            if ($existingToken) {
                // Case 1: Still valid
                if ($existingToken->status === 'available' && Carbon::now()->lt($existingToken->expiration_date)) {
                    $remainingMinutes = Carbon::now()->diffInMinutes($existingToken->expiration_date, false);

                    DB::commit();
                    return response()->json([
                        'message'         => 'Existing code is still valid',
                        'code'            => $existingToken->code,
                        'expires_in_mins' => $remainingMinutes,
                    ]);
                }

                // Case 2: Used → just return with human readable time since usage
                if ($existingToken->status === 'used') {
                    $usedAgo = Carbon::parse($existingToken->updated_at)->diffForHumans();

                    DB::commit();
                    return response()->json([
                        'message' => "Code was already used ({$usedAgo})",
                        'code'    => $existingToken->code,
                    ]);
                }

                // Case 3: Expired → generate a new one
                if (Carbon::now()->gte($existingToken->expiration_date)) {
                    do {
                        $newCode = Str::random(8);
                    } while (SignupAccessToken::where('code', $newCode)->exists());

                    $existingToken->update([
                        'code'            => $newCode,
                        'status'          => 'available',
                        'generated_by'    => $adminId,
                        'expiration_date' => Carbon::now()->addDay(),
                    ]);

                    $body = "
                        <p>Hello {$request['first_name']} {$request['last_name']},</p>
                        <h5>Your previous code expired. Below is your new signup access code</h5>
                        <p style='margin-bottom: 2px'>Code: {$existingToken->code}</p>
                        <p>Expires in mins: " . Carbon::now()->diffInMinutes($existingToken->expiration_date, false) . "</p>";

                    ZeptomailService::sendMailZeptoMail("New SignUp Access Code", $body, $data['email']);

                    DB::commit();
                    return response()->json([
                        'message' => 'Old code expired. A new access code has been generated',
                        'code'    => $existingToken->code,
                        'expires_in_mins' => Carbon::now()->diffInMinutes($existingToken->expiration_date, false),
                    ]);
                }
            }

            // Case 4: No code at all → create new
            do {
                $code = Str::random(8);
            } while (SignupAccessToken::where('code', $code)->exists());

            $token = SignupAccessToken::create([
                'user_id'        => $user->id,
                'code'           => $code,
                'status'         => 'available',
                'generated_by'   => $adminId,
                'expiration_date'=> Carbon::now()->addDay(),
            ]);

            $body = "
                <p>Hello {$request['first_name']} {$request['last_name']},</p>
                <h5>Below is your signup access code</h5>
                <p style='margin-bottom: 2px'>Code: {$token->code}</p>
                <p>Expires in mins: " . Carbon::now()->diffInMinutes($token->expiration_date, false) . "</p>";

            ZeptomailService::sendMailZeptoMail("SignUp Access Code", $body, $data['email']);

            DB::commit();

            return response()->json([
                'message' => 'Access code generated successfully',
                'code'    => $token->code,
                'expires_in_mins' => Carbon::now()->diffInMinutes($token->expiration_date, false),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to generate code',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *   path="/api/v1/admin/access-codes",
     *   tags={"Admin"},
     *   summary="Get all access codes",
     *   description="Retrieve a list of all generated access codes with user and admin details.",
     *   operationId="GetAccessCodes",
     *
     *   @OA\Response(
     *     response=200,
     *     description="List of codes",
     *     @OA\JsonContent(
     *       type="array",
     *       @OA\Items(
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="code", type="string", example="Ab3X9zPq"),
     *         @OA\Property(property="status", type="string", example="available"),
     *         @OA\Property(property="expiration_date", type="string", format="date-time", example="2025-09-07 15:43:58"),
     *         @OA\Property(property="user", type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="email", type="string", example="user@example.com")
     *         ),
     *         @OA\Property(property="admin", type="object",
     *           @OA\Property(property="id", type="integer", example=2),
     *           @OA\Property(property="email", type="string", example="admin@example.com")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   security={{"bearer_token": {}}}
     * )
    */
    public function index()
    {
        $codes = SignupAccessToken::with(['user', 'generatedBy'])
            ->whereHas('user')
            ->whereNotNull('code')
            ->get();

        return response()->json($codes);
    }

    /**
     * @OA\Patch(
     *   path="/api/v1/admin/access-codes/{id}/status",
     *   tags={"Admin"},
     *   summary="Update access code status",
     *   description="Update the status of an access code (Available, Used, Expired).",
     *   operationId="UpdateCodeStatus",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="ID of the access code",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status", type="string", enum={"used", "expired"}, example="used")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Status updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Code status updated"),
     *       @OA\Property(property="code", type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="status", type="string", example="used"),
     *         @OA\Property(property="code", type="string", example="Ab3X9zPq")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=404, description="Code not found"),
     *   @OA\Response(response=422, description="Validation error"),
     *   security={{"bearer_token": {}}}
     * )
    */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,used,expired',
        ]);

        $token = SignupAccessToken::findOrFail($id);
        $token->status = $request->status;
        $token->save();

        return response()->json([
            'message' => 'Code status updated',
            'code'    => $token,
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/user/validate-code",
     *   tags={"User"},
     *   summary="Validate sign up access code",
     *   description="Validate if an access code is valid, unused, and not expired. Returns user details if valid.",
     *   operationId="ValidateCode",
     *
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       required={"email", "code"},
     *       @OA\Property(property="email", type="string", example="john@example.com"),
     *       @OA\Property(property="code", type="string", example="Ab3X9zPq")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Code validated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Code validated successfully"),
     *       @OA\Property(property="first_name", type="string", example="John"),
     *       @OA\Property(property="last_name", type="string", example="Doe"),
     *       @OA\Property(property="middle_name", type="string", example="A."),
     *       @OA\Property(property="code", type="string", example="Ab3X9zPq")
     *     )
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="Invalid or expired code",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Code does not exist, has already been used, or is expired")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function validateCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found with this email',
                ], 400);
            }

            $token = SignupAccessToken::where('user_id', $user->id)
                ->where('code', $request->code)
                ->latest()
                ->first();

            if (!$token) {
                return response()->json([
                    'message' => 'Code does not exist',
                ], 400);
            }

            // Check if already used
            if ($token->status === 'used') {
                return response()->json([
                    'message' => 'Code has already been used',
                ], 400);
            }

            // Check if expired
            if (Carbon::now()->gte($token->expiration_date)) {
                return response()->json([
                    'message' => 'Code is already expired',
                ], 400);
            }

            // $token->update(['status' => 'used']);
            return response()->json([
                'message'     => 'Code validated successfully',
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'middle_name' => $user->middle_name,
                'code'        => $token->code,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
