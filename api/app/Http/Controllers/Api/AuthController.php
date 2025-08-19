<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserWithBooksResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

/**
 * @OA\Info(
 *     title="LivroLog API",
 *     version="1.0.0",
 *     description="Personal library management system with Google Books API integration",
 *
 *     @OA\Contact(
 *         email="support@livrolog.com"
 *     ),
 *
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="{scheme}://{host}",
 *     description="LivroLog API Server",
 *
 *     @OA\ServerVariable(
 *         serverVariable="scheme",
 *         enum={"https", "http"},
 *         default="https"
 *     ),
 *     @OA\ServerVariable(
 *         serverVariable="host",
 *         default="api.dev.livrolog.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Insert the access token obtained from login endpoint. Format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints"
 * )
 * @OA\Tag(
 *     name="Books",
 *     description="Book management and search"
 * )
 * @OA\Tag(
 *     name="Health",
 *     description="Health check endpoints"
 * )
 * @OA\Tag(
 *     name="Reviews",
 *     description="Book reviews and ratings"
 * )
 * @OA\Tag(
 *     name="Social",
 *     description="Follow system and social features"
 * )
 * @OA\Tag(
 *     name="User Library",
 *     description="User's personal library management"
 * )
 * @OA\Tag(
 *     name="Users",
 *     description="User management and profiles"
 * )
 */
class AuthController extends Controller
{
    // Validation constants to avoid duplication
    private const VALIDATION_REQUIRED_STRING = 'required|string';

    private const VALIDATION_REQUIRED_EMAIL = 'required|email';

    private const LIBRARY_SUFFIX = "'s Library";

    // Password validation rules
    private const VALIDATION_PASSWORD_RULES = [
        'required',
        'string',
        'min:8',
        'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
    ];

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     summary="Register new user",
     *     description="Creates a new user account and returns access token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data",
     *
     *         @OA\JsonContent(
     *             required={"display_name","email","username","password"},
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Password (minimum 8 characters)"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Shelf name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string", example="1|aBcDeF123456..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
        $request->validate([
            'display_name' => self::VALIDATION_REQUIRED_STRING.'|max:255',
            'email' => self::VALIDATION_REQUIRED_EMAIL.'|max:255|unique:users',
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'unique:users',
                'regex:/^[a-zA-Z0-9_]+$/', // Only letters, numbers, and underscores
                'not_regex:/^(admin|api|www|root|support|help|about|contact|terms|privacy|settings|profile|user|users|book|books)$/i', // Reserved usernames
            ],
            'password' => self::VALIDATION_PASSWORD_RULES,
        ]);

        $user = User::create([
            'display_name' => $request->display_name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'shelf_name' => $request->shelf_name ?? $request->display_name.self::LIBRARY_SUFFIX,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     operationId="loginUser",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticates user and returns access token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Login credentials",
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="User password")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string", example="1|aBcDeF123456..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => self::VALIDATION_REQUIRED_EMAIL,
            'password' => 'required',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     operationId="logoutUser",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     description="Invalidates the current access token",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     operationId="getCurrentUser",
     *     tags={"Authentication"},
     *     summary="Get current user",
     *     description="Returns the authenticated user information",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User information",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user()
            ->loadCount(['followers', 'following'])
            ->load(['books' => function ($query) {
                $query->orderBy('pivot_added_at', 'desc');
            }]);

        return new UserWithBooksResource($user);
    }

    /**
     * @OA\Put(
     *     path="/password",
     *     operationId="updatePassword",
     *     tags={"Authentication"},
     *     summary="Update user password",
     *     description="Updates the authenticated user's password",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password update data",
     *
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", description="Current password"),
     *             @OA\Property(property="password", type="string", format="password", description="New password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="New password confirmation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => self::VALIDATION_REQUIRED_STRING,
            'password' => array_merge(self::VALIDATION_PASSWORD_RULES, ['confirmed']),
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/forgot-password",
     *     operationId="forgotPassword",
     *     tags={"Authentication"},
     *     summary="Send password reset link",
     *     description="Sends a password reset link to the user's email",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email address",
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Password reset link sent successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error sending password reset link",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unable to send password reset link.")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => self::VALIDATION_REQUIRED_EMAIL]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 400);
    }

    /**
     * @OA\Post(
     *     path="/auth/reset-password",
     *     operationId="resetPassword",
     *     tags={"Authentication"},
     *     summary="Reset user password",
     *     description="Resets the user's password using the token received via email",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password reset data",
     *
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *
     *             @OA\Property(property="token", type="string", description="Password reset token"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="password", type="string", format="password", description="New password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="New password confirmation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Password reset successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error resetting password",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unable to reset password.")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => self::VALIDATION_REQUIRED_STRING,
            'email' => self::VALIDATION_REQUIRED_EMAIL,
            'password' => array_merge(self::VALIDATION_PASSWORD_RULES, ['confirmed']),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->setRememberToken(Str::random(60));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 400);
    }

    /**
     * @OA\Get(
     *     path="/auth/google",
     *     operationId="googleRedirect",
     *     tags={"Authentication"},
     *     summary="Redirect to Google OAuth",
     *     description="Redirects user to Google for OAuth authentication",
     *
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to Google OAuth URL"
     *     )
     * )
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * @OA\Get(
     *     path="/auth/google/callback",
     *     operationId="googleCallback",
     *     tags={"Authentication"},
     *     summary="Google OAuth callback",
     *     description="Handles Google OAuth callback and creates/authenticates user",
     *
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="Authorization code from Google",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authentication successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="OAuth error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="OAuth authentication failed")
     *         )
     *     )
     * )
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Check if user already exists by google_id
            $user = User::where('google_id', $googleUser->id)->first();

            if (! $user) {
                // Check if user exists by email
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // Update existing user with Google data
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                        'email_verified' => true,
                        'email_verified_at' => now(),
                    ]);
                } else {
                    // Create new user
                    $username = $this->generateUniqueUsername($googleUser->name, $googleUser->email);

                    $user = User::create([
                        'google_id' => $googleUser->id,
                        'display_name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'username' => $username,
                        'avatar' => $googleUser->avatar,
                        'password' => Hash::make(Str::random(32)), // Random password since they'll use Google
                        'shelf_name' => $googleUser->name.self::LIBRARY_SUFFIX,
                        'email_verified' => true,
                        'email_verified_at' => now(),
                    ]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'OAuth authentication failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/google",
     *     operationId="googleSignIn",
     *     tags={"Authentication"},
     *     summary="Google Sign In with ID Token",
     *     description="Authenticates user using Google ID Token for frontend integration",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Google ID Token",
     *
     *         @OA\JsonContent(
     *             required={"id_token"},
     *
     *             @OA\Property(property="id_token", type="string", description="Google ID Token from frontend")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authentication successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Invalid Google ID Token")
     *         )
     *     )
     * )
     */
    public function googleSignIn(Request $request)
    {
        $request->validate([
            'id_token' => self::VALIDATION_REQUIRED_STRING,
        ]);

        try {
            // Verify Google ID token
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->id_token);

            if (! $payload) {
                return response()->json(['message' => 'Invalid Google ID Token'], 400);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $avatar = $payload['picture'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;

            // Check if user already exists by google_id
            $user = User::where('google_id', $googleId)->first();

            if (! $user) {
                // Check if user exists by email
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Update existing user with Google data
                    $user->update([
                        'google_id' => $googleId,
                        'avatar' => $avatar,
                        'email_verified' => $emailVerified,
                        'email_verified_at' => $emailVerified ? now() : null,
                    ]);
                } else {
                    // Create new user
                    $username = $this->generateUniqueUsername($name, $email);

                    $user = User::create([
                        'google_id' => $googleId,
                        'display_name' => $name,
                        'email' => $email,
                        'username' => $username,
                        'avatar' => $avatar,
                        'password' => Hash::make(Str::random(32)), // Random password since they'll use Google
                        'shelf_name' => $name.self::LIBRARY_SUFFIX,
                        'email_verified' => $emailVerified,
                        'email_verified_at' => $emailVerified ? now() : null,
                    ]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google Sign In failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate a unique username based on display name and email
     */
    private function generateUniqueUsername($displayName, $email)
    {
        // Try display name first (remove spaces and special characters)
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $displayName));

        // If empty or too short, use email prefix
        if (strlen($baseUsername) < 3) {
            $baseUsername = explode('@', $email)[0];
            $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $baseUsername));
        }

        // Ensure minimum length
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'user';
        }

        $username = $baseUsername;
        $counter = 1;

        // Check for uniqueness - if exists, try with numbers
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername.$counter;
            $counter++;
        }

        return $username;
    }

    /**
     * @OA\Put(
     *     path="/auth/me",
     *     operationId="updateUserProfile",
     *     tags={"Authentication"},
     *     summary="Update user profile and account",
     *     description="Updates the authenticated user's profile and account information",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Profile and account data to update",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Custom shelf name"),
     *             @OA\Property(property="locale", type="string", example="en", description="User preferred language"),
     *             @OA\Property(property="is_private", type="boolean", example=false, description="Whether profile is private")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:100|unique:users,username,'.$user->id,
            'email' => 'sometimes|email|max:255|unique:users,email,'.$user->id,
            'shelf_name' => 'sometimes|string|max:255',
            'locale' => 'sometimes|string|max:10',
            'is_private' => 'sometimes|boolean',
        ]);

        $user->update($request->only([
            'display_name',
            'username',
            'email',
            'shelf_name',
            'locale',
            'is_private',
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->loadCount(['followers', 'following']),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/auth/password",
     *     operationId="updateUserPassword",
     *     tags={"Authentication"},
     *     summary="Update user password",
     *     description="Updates the authenticated user's password",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password update data",
     *
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", description="Current password"),
     *             @OA\Property(property="password", type="string", format="password", description="New password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="New password confirmation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updatePassword2(Request $request)
    {
        return $this->updatePassword($request);
    }

    /**
     * @OA\Delete(
     *     path="/auth/me",
     *     operationId="deleteUserAccount",
     *     tags={"Authentication"},
     *     summary="Delete user account",
     *     description="Permanently deletes the authenticated user's account and all associated data",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Account deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function deleteMe(Request $request)
    {
        $user = $request->user();

        // Delete user's tokens
        $user->tokens()->delete();

        // Delete the user account
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/auth/check-username",
     *     operationId="checkUsernameAvailability",
     *     tags={"Authentication"},
     *     summary="Check username availability",
     *     description="Checks if a username is available for registration or update",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="username",
     *         in="query",
     *         description="Username to check",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="john_doe")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Username availability status",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="exists", type="boolean", example=false, description="Whether username already exists"),
     *             @OA\Property(property="available", type="boolean", example=true, description="Whether username is available for use")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:100',
        ]);

        $username = $request->input('username');
        $exists = User::where('username', $username)->exists();

        return response()->json([
            'exists' => $exists,
            'available' => ! $exists,
        ]);
    }
}
