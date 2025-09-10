<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\{User, SignupAccessToken};
use App\Services\ZeptomailService;
use Carbon\Carbon;

class UserController extends BaseController
{
    /**
     * Complete Signup
     *
     * @OA\Post(
     *     path="/api/v1/user/complete-signup",
     *     summary="Complete user signup after code validation",
     *     tags={"User"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone","gender","address","dob","code","email"},
     *             @OA\Property(property="code", type="string", example="4Trdsgq7"),
     *             @OA\Property(property="email", type="string", example="john@gmail.com"),
     *             @OA\Property(property="phone", type="string", example="08012345678"),
     *             @OA\Property(property="gender", type="string", enum={"male","female","other"}, example="male"),
     *             @OA\Property(property="address", type="string", example="123 Lagos Street, Nigeria"),
     *             @OA\Property(property="dob", type="string", format="date", example="1995-06-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Signup completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Signup completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="middle_name", type="string", example="Doe"),
     *                 @OA\Property(property="last_name", type="string", example="Smith"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="08012345678"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(property="address", type="string", example="123 Lagos Street, Nigeria"),
     *                 @OA\Property(property="dob", type="string", example="1995-06-15")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function completeSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone'        => 'required|digits:11',
            //'phone' => ['required', 'regex:/^[0-9]{11}$/'],
            'gender'       => 'required|in:male,female,other',
            'address'      => 'required|string|max:255',
            'dob'          => 'required|date',
            'code'         => 'required|string',
            'email'        => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = SignupAccessToken::join('users', 'users.id', '=', 'signup_access_tokens.user_id')
            ->where('users.email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid code',
            ], 400);
        }

        if ($token->status === 'used') {
            return response()->json([
                'success' => false,
                'message' => 'Code has already been used',
            ], 400);
        }

        if (Carbon::now()->gte($token->expiration_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Code is expired',
            ], 400);
        }

        $user = User::find($token->user_id);
        $user->phone = $request->phone;
        $user->gender = $request->gender;
        $user->address = $request->address;
        $user->dob = $request->dob;
        $user->save();

        // mark token as used
        $token->status = 'used';
        $token->save();

        // send mail
        $body = "<p>Hello {$user->first_name} {$user->last_name},</p><p>Your signup has been completed successfully.</p>";
        ZeptomailService::sendMailZeptoMail("Signup Successfully Completed", $body, $user->email);

        return response()->json([
            'success' => true,
            'message' => 'Signup completed successfully',
            'data'    => $user
        ]);
    }
}
