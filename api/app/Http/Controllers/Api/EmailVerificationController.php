<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/verify-email",
     *     operationId="sendVerificationEmail",
     *     tags={"Authentication"},
     *     summary="Send email verification link",
     *     description="Sends an email verification link to the authenticated user",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Verification link sent",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Verification link sent successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Email already verified",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email already verified")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent successfully']);
    }

    /**
     * @OA\Get(
     *     path="/auth/verify-email",
     *     operationId="verifyEmail",
     *     tags={"Authentication"},
     *     summary="Verify email address",
     *     description="Verifies the user's email address using the link sent via email",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="hash",
     *         in="query",
     *         description="Verification hash",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         description="Expiration timestamp",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="URL signature",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email verified successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired link",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Invalid or expired verification link")
     *         )
     *     )
     * )
     */
    public function verifyEmail(Request $request)
    {
        $user = User::find($request->id);
        $frontendUrl = config('app.frontend_url', 'http://localhost:8001');
        $isJsonRequest = $request->wantsJson() || $request->expectsJson();

        if (! $user) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'User not found'], 404);
            }

            return redirect($frontendUrl.'/settings/account?error=user_not_found');
        }

        // Check if already verified
        if ($user->email_verified) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'Email already verified'], 400);
            }

            return redirect($frontendUrl.'/settings/account?status=already_verified');
        }

        // Verify the URL signature
        if (! URL::hasValidSignature($request)) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'Invalid or expired verification link'], 400);
            }

            return redirect($frontendUrl.'/settings/account?error=invalid_link');
        }

        // Verify the hash matches
        if (! hash_equals((string) $request->hash, sha1($user->email))) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'Invalid verification link'], 400);
            }

            return redirect($frontendUrl.'/settings/account?error=invalid_hash');
        }

        // Mark email as verified
        $user->email_verified = true;
        $user->email_verified_at = now();
        $user->save();

        event(new Verified($user));

        if ($isJsonRequest) {
            return response()->json(['message' => 'Email verified successfully']);
        }

        return redirect($frontendUrl.'/settings/account?status=verified');
    }
}
