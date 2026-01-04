<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
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
 *     description="Personal library management system with Google Books API integration, social features, privacy controls, and comprehensive follow system with request management",
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

    private const VALIDATION_LOCALE = 'nullable|string|max:10';

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
            'locale' => self::VALIDATION_LOCALE, // Accept locale from frontend
        ]);

        $user = User::create([
            'display_name' => $request->display_name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'shelf_name' => $request->shelf_name ?? $request->display_name.self::LIBRARY_SUFFIX,
            'locale' => $request->has('locale') ? $this->normalizeLocale($request->input('locale')) : 'en',
        ]);

        // Send email verification notification
        $user->sendEmailVerificationNotification();

        $token = $user->createToken('auth_token')->plainTextToken;

        $user = $user
            ->loadCount(['followers', 'following'])
            ->load(['books' => function ($query) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
            }]);

        return response()->json([
            'user' => new UserWithBooksResource($user),
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
            'locale' => self::VALIDATION_LOCALE, // Accept locale from frontend
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Set locale if not already set
        if (is_null($user->locale) && $request->has('locale')) {
            $user->locale = $this->normalizeLocale($request->input('locale'));
            $user->save();
        }

        $user = $user
            ->loadCount(['followers', 'following'])
            ->load(['books' => function ($query) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
            }]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserWithBooksResource($user),
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
        $user = $request->user();

        // If user is authenticated, invalidate their token/session
        if ($user) {
            // Support both token-based and session-based auth
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            // Only attempt session logout if using session-based auth (web guard)
            if ($request->hasSession() && Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        // Always return success - logout is idempotent
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
            ->loadCount(['followers', 'following']);

        $resource = new UserResource($user);
        $userData = $resource->toArray(request());
        $userData['pending_follow_requests_count'] = $user->pending_follow_requests_count;

        // Add account status information
        $userData['email'] = $user->email;
        $userData['email_verified'] = ! is_null($user->email_verified_at);
        $userData['email_verified_at'] = $user->email_verified_at;
        $userData['has_password_set'] = $user->hasPasswordSet();
        $userData['has_google_connected'] = $user->hasGoogleConnected();

        return response()->json($userData);
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
        $user = $request->user();

        // Check if user has a password set
        $hasPassword = $user->hasPasswordSet();

        // Different validation rules based on whether user has a password
        $rules = [
            'password' => array_merge(self::VALIDATION_PASSWORD_RULES, ['confirmed']),
        ];

        // Only require current password if user already has one set
        if ($hasPassword) {
            $rules['current_password'] = self::VALIDATION_REQUIRED_STRING;
        }

        $request->validate($rules);

        // Verify current password if user has one set
        if ($hasPassword) {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'The current password is incorrect.',
                    'errors' => ['current_password' => ['The current password is incorrect.']],
                ], 422);
            }
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Reload user with fresh data including relationships
        $user = $user->fresh()
            ->loadCount(['followers', 'following'])
            ->load(['books' => function ($query) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
            }]);

        // Add account status information
        $resource = new UserWithBooksResource($user);
        $userData = $resource->toArray(request());
        $userData['pending_follow_requests_count'] = $user->pending_follow_requests_count;
        $userData['has_password_set'] = $user->hasPasswordSet();
        $userData['has_google_connected'] = $user->hasGoogleConnected();

        return response()->json([
            'message' => $hasPassword ? 'Password updated successfully' : 'Password set successfully',
            'user' => $userData,
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
                    // Update existing user with Google data - Google accounts are always verified
                    $user->google_id = $googleUser->id;
                    $user->avatar = $googleUser->avatar;
                    $user->email_verified = true;
                    $user->email_verified_at = now();
                    $user->save();
                } else {
                    // Create new user
                    $username = $this->generateUniqueUsername($googleUser->name, $googleUser->email);

                    $user = User::create([
                        'display_name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'username' => $username,
                        'avatar' => $googleUser->avatar,
                        'password' => null, // No password for Google users
                        'shelf_name' => $googleUser->name.self::LIBRARY_SUFFIX,
                    ]);
                    $user->google_id = $googleUser->id;
                    $user->email_verified = true;
                    $user->email_verified_at = now();
                    $user->save();
                }
            }

            $user = $user
                ->loadCount(['followers', 'following'])
                ->load(['books' => function ($query) {
                    $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                }]);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => new UserWithBooksResource($user),
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
            'locale' => self::VALIDATION_LOCALE, // Accept locale from frontend
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

            // Check if user already exists by google_id
            $user = User::where('google_id', $googleId)->first();

            if (! $user) {
                // Check if user exists by email
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Update existing user with Google data - Google accounts are always verified
                    $user->google_id = $googleId;
                    $user->avatar = $avatar;
                    $user->email_verified = true; // Google accounts are always verified
                    $user->email_verified_at = now();
                    if (is_null($user->locale) && $request->has('locale')) {
                        $user->locale = $this->normalizeLocale($request->input('locale'));
                    }
                    $user->save();
                } else {
                    // Create new user
                    $username = $this->generateUniqueUsername($name, $email);

                    $user = User::create([
                        'display_name' => $name,
                        'email' => $email,
                        'username' => $username,
                        'avatar' => $avatar,
                        'password' => null, // No password for Google users
                        'shelf_name' => $name.self::LIBRARY_SUFFIX,
                        'locale' => $request->has('locale') ? $this->normalizeLocale($request->input('locale')) : 'en',
                    ]);
                    $user->google_id = $googleId;
                    $user->email_verified = true; // Google accounts are always verified
                    $user->email_verified_at = now();
                    $user->save();
                }
            }

            $user = $user
                ->loadCount(['followers', 'following'])
                ->load(['books' => function ($query) {
                    $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                }]);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => new UserWithBooksResource($user),
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
     * Normalize locale to supported language codes
     * Converts browser locales (pt-BR, en-US) to language codes (pt, en)
     */
    private function normalizeLocale(?string $locale): string
    {
        if (! $locale) {
            return 'en'; // Default to English (most universal)
        }

        // Extract language code from locale (e.g., pt-BR -> pt)
        $languageCode = strtolower(explode('-', $locale)[0]);

        // Map common language codes to Google Books supported codes
        $supportedLocales = [
            'pt' => 'pt',     // Portuguese
            'en' => 'en',     // English
            'es' => 'es',     // Spanish
            'fr' => 'fr',     // French
            'de' => 'de',     // German
            'it' => 'it',     // Italian
            'ja' => 'ja',     // Japanese
            'ko' => 'ko',     // Korean
            'zh' => 'zh',     // Chinese
            'ru' => 'ru',     // Russian
            'ar' => 'ar',     // Arabic
            'hi' => 'hi',     // Hindi
            'nl' => 'nl',     // Dutch
            'pl' => 'pl',     // Polish
            'tr' => 'tr',     // Turkish
        ];

        return $supportedLocales[$languageCode] ?? 'en';
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

        $user = $user->fresh()
            ->loadCount(['followers', 'following'])
            ->load(['books' => function ($query) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
            }]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserWithBooksResource($user),
        ]);
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
     * @OA\Delete(
     *     path="/auth/google",
     *     operationId="disconnectGoogle",
     *     tags={"Authentication"},
     *     summary="Disconnect Google account",
     *     description="Removes Google account connection from the authenticated user",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Google account disconnected successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Google account disconnected successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Cannot disconnect without password",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Please set a password before disconnecting Google account")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function disconnectGoogle(Request $request)
    {
        $user = $request->user();

        // Check if user has Google connected
        if (! $user->hasGoogleConnected()) {
            return response()->json(['message' => 'Google account is not connected'], 400);
        }

        // Check if user has a password set
        if (! $user->hasPasswordSet()) {
            return response()->json([
                'message' => 'Please set a password before disconnecting Google account',
            ], 400);
        }

        // Disconnect Google
        $user->update([
            'google_id' => null,
        ]);

        return response()->json(['message' => 'Google account disconnected successfully']);
    }

    /**
     * @OA\Put(
     *     path="/auth/google",
     *     operationId="connectGoogleAccount",
     *     tags={"Authentication"},
     *     summary="Connect Google account to existing user",
     *     description="Connects a Google account to the authenticated user's account",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"id_token", "action"},
     *
     *             @OA\Property(property="id_token", type="string", description="Google ID token"),
     *             @OA\Property(property="action", type="string", enum={"connect", "update_email"}, description="Action to perform")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Google account connected successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Google account connected successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Google account already connected to another user")
     * )
     */
    public function connectGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'action' => 'required|string|in:connect,update_email',
        ]);

        $user = $request->user();
        $idToken = $request->input('id_token');
        $action = $request->input('action');

        try {
            $client = new \Google_Client;
            $client->setClientId(config('services.google.client_id'));

            $payload = $client->verifyIdToken($idToken);
            if (! $payload) {
                return response()->json(['message' => 'Invalid Google token'], 400);
            }

            $googleId = $payload['sub'];
            $googleEmail = $payload['email'];
            $googleName = $payload['name'] ?? '';
            $googleAvatar = $payload['picture'] ?? null;

            $existingUser = User::where('google_id', $googleId)->first();
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json(['message' => 'Google account is already connected to another user'], 409);
            }

            if ($action === 'update_email') {
                $emailUser = User::where('email', $googleEmail)->where('id', '!=', $user->id)->first();
                if ($emailUser) {
                    return response()->json(['message' => 'Email is already used by another user'], 409);
                }
            }

            $user->google_id = $googleId;
            $user->email_verified = true;
            $user->email_verified_at = now();

            if ($action === 'update_email') {
                $user->email = $googleEmail;
            }

            if (! $user->avatar && $googleAvatar) {
                $user->avatar = $googleAvatar;
            }

            if (! $user->display_name && $googleName) {
                $user->display_name = $googleName;
            }

            $user->save();

            $user = $user->fresh()
                ->loadCount(['followers', 'following'])
                ->load(['books' => function ($query) {
                    $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                }]);

            $resource = new UserWithBooksResource($user);
            $userData = $resource->toArray(request());
            $userData['pending_follow_requests_count'] = $user->pending_follow_requests_count;

            $userData['email'] = $user->email;
            $userData['email_verified'] = ! is_null($user->email_verified_at);
            $userData['email_verified_at'] = $user->email_verified_at;
            $userData['has_password_set'] = $user->hasPasswordSet();
            $userData['has_google_connected'] = $user->hasGoogleConnected();

            return response()->json([
                'message' => 'Google account connected successfully',
                'user' => $userData,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to connect Google account: '.$e->getMessage()], 500);
        }
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
