<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Http\Resources\PaginatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserWithBooksResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use HandlesPagination;

    /**
     * @OA\Get(
     *     path="/users",
     *     operationId="getUsers",
     *     tags={"Users"},
     *     summary="List users",
     *     description="Returns paginated list of users with followers/following counts",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=15, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Search filter for name, username, or email",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="john")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users list",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = User::withCount(['books', 'followers', 'following']);

        // Add is_following and has_pending_follow_request status for authenticated users
        if ($authUser = $request->user()) {
            $query->withExists(['followers as is_following' => function ($q) use ($authUser) {
                $q->where('follower_id', $authUser->id)
                    ->where('status', 'accepted');
            }])
                ->addSelect(['has_pending_follow_request' => function ($subQuery) use ($authUser) {
                    $subQuery->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
                        ->from('follows')
                        ->where('follower_id', $authUser->id)
                        ->where('status', 'pending')
                        ->whereColumn('followed_id', 'users.id');
                }]);
        }

        // Global filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                    ->orWhere('display_name', 'like', "%{$filter}%")
                    ->orWhere('username', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%");
            });
        }

        // Order by books count descending (users with more books first)
        $query->orderByDesc('books_count');

        $users = $this->applyPagination($query, $request);

        return new PaginatedUserResource($users);
    }

    /**
     * @OA\Post(
     *     path="/users",
     *     operationId="createUser",
     *     tags={"Users"},
     *     summary="Create new user",
     *     description="Creates a new user account",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data",
     *
     *         @OA\JsonContent(
     *             required={"display_name","email","username"},
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Shelf name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
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
    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\User::class);
        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:100|unique:users',
            'shelf_name' => 'nullable|string|max:255',
        ]);

        $user = User::create($request->all());

        return new UserResource($user);
    }

    /**
     * @OA\Get(
     *     path="/users/{identifier}",
     *     operationId="getUser",
     *     tags={"Users"},
     *     summary="Get user by ID or username",
     *     description="Returns user information with books if profile is public or user is following",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         description="User ID (starts with U-) or username",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="john_doe")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User information with privacy-aware book visibility",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Profile is private and user is not following",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This profile is private")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $identifier)
    {
        $currentUser = $request->user();

        // If identifier starts with "U-", it's an ID, otherwise it's a username
        $user = str_starts_with($identifier, 'U-')
            ? User::withCount(['followers', 'following'])->findOrFail($identifier)
            : User::withCount(['followers', 'following'])->where('username', $identifier)->firstOrFail();

        $access = $this->getProfileAccessInfo($currentUser, $user);

        // Load books only if user has access to the profile
        if ($access['hasAccess']) {
            $user->load(['books' => function ($query) use ($access) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                // Only show private books to the owner
                if (! $access['isOwner']) {
                    $query->wherePivot('is_private', false);
                }
            }]);
        }

        $resource = new UserWithBooksResource($user);
        $userData = $resource->toArray(request());

        // Add follow status and request status if user is authenticated
        if ($currentUser && $currentUser->id !== $user->id) {
            $userData['is_following'] = $access['isFollowing'];

            $hasPendingRequest = $currentUser->followingRelationships()
                ->where('followed_id', $user->id)
                ->where('status', 'pending')
                ->exists();
            $userData['has_pending_follow_request'] = $hasPendingRequest;
        }

        return response()->json($userData);
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Updates user information",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data to update",
     *
     *         @OA\JsonContent(
     *             required={"display_name","email","username"},
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Shelf name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
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
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $rules = [
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'username' => 'required|string|max:100|unique:users,username,'.$user->id,
            'shelf_name' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:user,admin',
        ];

        // Only admins can set password for users
        if ($request->user()->isAdmin() && $request->filled('password')) {
            $rules['password'] = 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/';
        }

        $request->validate($rules);

        // Only admins can change user roles and set passwords
        $data = $request->only(['display_name', 'email', 'username', 'shelf_name']);
        if ($request->user()->isAdmin()) {
            if ($request->has('role')) {
                $data['role'] = $request->input('role');
            }
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->input('password'));
            }
        }

        $user->update($data);

        return new UserResource($user);
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Deletes a user account",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get reading statistics for a user
     *
     * @OA\Get(
     *     path="/users/{username}/stats",
     *     operationId="getUserStats",
     *     tags={"Users"},
     *     summary="Get user reading statistics",
     *     description="Returns reading statistics for a user (by status, by month, by category)",
     *
     *     @OA\Parameter(
     *         name="username",
     *         in="path",
     *         description="Username",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="john_doe")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reading statistics",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="by_status", type="object",
     *                 @OA\Property(property="want_to_read", type="integer"),
     *                 @OA\Property(property="reading", type="integer"),
     *                 @OA\Property(property="read", type="integer"),
     *                 @OA\Property(property="abandoned", type="integer"),
     *                 @OA\Property(property="on_hold", type="integer"),
     *                 @OA\Property(property="re_reading", type="integer")
     *             ),
     *             @OA\Property(property="by_month", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="year", type="integer"),
     *                     @OA\Property(property="month", type="integer"),
     *                     @OA\Property(property="count", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="by_category", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="category", type="string"),
     *                     @OA\Property(property="count", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Profile is private and user is not following"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function stats(Request $request, string $username)
    {
        $currentUser = $request->user();
        $user = User::where('username', $username)->firstOrFail();

        $access = $this->getProfileAccessInfo($currentUser, $user);

        if (! $access['hasAccess']) {
            return response()->json(['message' => 'This profile is private'], 403);
        }

        // Build base query for user's books (respecting privacy)
        $baseQuery = DB::table('users_books')
            ->join('books', 'users_books.book_id', '=', 'books.id')
            ->where('users_books.user_id', $user->id);

        // Only include private books if viewing own profile
        if (! $access['isOwner']) {
            $baseQuery->where('users_books.is_private', false);
        }

        // 1. Count by reading status
        $byStatus = (clone $baseQuery)
            ->select('users_books.reading_status', DB::raw('COUNT(*) as count'))
            ->groupBy('users_books.reading_status')
            ->pluck('count', 'reading_status')
            ->toArray();

        // Ensure all statuses are present
        $allStatuses = ['want_to_read', 'reading', 'read', 'abandoned', 'on_hold', 're_reading'];
        $byStatusComplete = [];
        foreach ($allStatuses as $status) {
            $byStatusComplete[$status] = $byStatus[$status] ?? 0;
        }

        // 2. Count by month (all books with read_at date)
        // Use database-agnostic date extraction (SQLite uses strftime, MySQL uses YEAR/MONTH)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $yearExpr = "CAST(strftime('%Y', users_books.read_at) AS INTEGER)";
            $monthExpr = "CAST(strftime('%m', users_books.read_at) AS INTEGER)";
        } else {
            $yearExpr = 'YEAR(users_books.read_at)';
            $monthExpr = 'MONTH(users_books.read_at)';
        }

        $byMonth = (clone $baseQuery)
            ->whereNotNull('users_books.read_at')
            ->select(
                DB::raw("{$yearExpr} as year"),
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw($yearExpr), DB::raw($monthExpr))
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
                'count' => (int) $row->count,
            ])
            ->toArray();

        // 3. Count by category (grouped by main category with subcategories)
        // Count UNIQUE BOOKS per main category, not sum of subcategory occurrences
        $booksWithCategories = (clone $baseQuery)
            ->whereNotNull('books.categories')
            ->select('books.id', 'books.categories')
            ->get();

        // Track unique books per main category and subcategory counts
        $mainCategoryBooks = []; // main_category => Set of book IDs
        $subcategoryCounts = []; // main_category => [subcategory => count]

        foreach ($booksWithCategories as $book) {
            $categories = json_decode($book->categories, true);
            if (! is_array($categories)) {
                continue;
            }

            // Track which main categories this book belongs to (to avoid double counting)
            $bookMainCategories = [];

            foreach ($categories as $category) {
                $category = trim($category);
                if (! $category) {
                    continue;
                }

                $parts = explode(' / ', $category);
                $mainCategory = $parts[0];
                $subcategory = count($parts) > 1 ? implode(' / ', array_slice($parts, 1)) : 'General';

                // Count each book only once per main category
                if (! in_array($mainCategory, $bookMainCategories)) {
                    $bookMainCategories[] = $mainCategory;
                    if (! isset($mainCategoryBooks[$mainCategory])) {
                        $mainCategoryBooks[$mainCategory] = [];
                    }
                    $mainCategoryBooks[$mainCategory][] = $book->id;
                }

                // Count subcategories (these can be counted multiple times)
                if (! isset($subcategoryCounts[$mainCategory])) {
                    $subcategoryCounts[$mainCategory] = [];
                }
                $subcategoryCounts[$mainCategory][$subcategory] = ($subcategoryCounts[$mainCategory][$subcategory] ?? 0) + 1;
            }
        }

        // Build grouped categories with correct totals
        $groupedCategories = [];
        foreach ($mainCategoryBooks as $mainCategory => $bookIds) {
            $uniqueBookCount = count(array_unique($bookIds));

            $subcategories = [];
            if (isset($subcategoryCounts[$mainCategory])) {
                foreach ($subcategoryCounts[$mainCategory] as $subName => $count) {
                    $subcategories[] = ['name' => $subName, 'count' => $count];
                }
                // Sort subcategories by count descending
                usort($subcategories, fn ($a, $b) => $b['count'] - $a['count']);
            }

            $groupedCategories[] = [
                'main_category' => $mainCategory,
                'total' => $uniqueBookCount,
                'subcategories' => $subcategories,
            ];
        }

        // Sort main categories by total descending
        usort($groupedCategories, fn ($a, $b) => $b['total'] - $a['total']);

        // Take top 10 main categories
        $byCategory = array_slice($groupedCategories, 0, 10);

        return response()->json([
            'by_status' => $byStatusComplete,
            'by_month' => $byMonth,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Generate shelf image for social sharing
     *
     * @OA\Get(
     *     path="/users/{id}/shelf-image",
     *     operationId="getUserShelfImage",
     *     tags={"Users"},
     *     summary="Generate user's shelf image for social sharing",
     *     description="Returns a generated image of user's bookshelf for Open Graph sharing",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Generated shelf image",
     *
     *         @OA\MediaType(
     *             mediaType="image/jpeg"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function shelfImage(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        // Get user's public books for display - up to 100
        $books = $user->books()
            ->orderBy('pivot_added_at', 'desc')
            ->wherePivot('is_private', false)
            ->limit(100)
            ->get();

        // Compute a version for cache-busting based on last change in user's shelf
        $version = $this->getShelfVersion($user);

        // Serve from on-disk cache if available (use the 'public' disk for portability)
        // Include an auto-updating render version (based on code/assets mtimes)
        $renderVersion = $this->getRendererVersion();
        $relative = "og/shelf-v{$renderVersion}-{$user->id}-{$version}.jpg";
        $cachePath = Storage::disk('public')->path($relative);
        if (Storage::disk('public')->exists($relative)) {
            return response()->file(Storage::disk('public')->path($relative), [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'Last-Modified' => gmdate('D, d M Y H:i:s', @filemtime($cachePath) ?: time()).' GMT',
            ]);
        }

        // If GD is unavailable, gracefully fall back to a static OG image
        if (! function_exists('imagecreatetruecolor')) {
            if ($request->boolean('debug')) {
                return response()->json(['error' => 'GD extension not available'], 500);
            }
            $fallback = rtrim(config('app.frontend_url'), '/').'/screenshot-web.jpg';

            return redirect()->away($fallback, 302, [
                'Cache-Control' => 'public, max-age=1800',
            ]);
        }

        try {
            // Generate the shelf image
            $image = $this->generateShelfImage($user, $books);

            // Ensure directory exists and save to disk cache (public disk)
            Storage::disk('public')->makeDirectory('og');
            Storage::disk('public')->put($relative, $image);

            if ($request->boolean('debug')) {
                return response()->json([
                    'gd' => extension_loaded('gd'),
                    'books_public' => count($books),
                    'bytes' => strlen($image),
                    'textures' => [
                        'left' => file_exists(public_path('og/textures/shelfleft.jpg')),
                        'center' => file_exists(public_path('og/textures/shelfcenter.jpg')),
                        'right' => file_exists(public_path('og/textures/shelfright.jpg')),
                    ],
                    'storage_writable' => is_writable(storage_path('app/public')),
                    'stored' => Storage::disk('public')->exists($relative),
                    'stored_path' => Storage::disk('public')->path($relative),
                ]);
            }

            return response($image, 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'Last-Modified' => now()->toRfc7231String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('shelf-image generation failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            if ($request->boolean('debug')) {
                return response()->json(['error' => 'generation_failed', 'message' => $e->getMessage()], 500);
            }
            // On any error, fall back to static OG image to avoid blank previews
            $fallback = rtrim(config('app.frontend_url'), '/').'/screenshot-web.jpg';

            return redirect()->away($fallback, 302, [
                'Cache-Control' => 'public, max-age=1800',
            ]);
        }
    }

    /**
     * Compute a stable version for the user's shelf, used for cache busting
     */
    private function getShelfVersion(User $user): string
    {
        $ts = DB::table('users_books')
            ->where('user_id', $user->id)
            ->max('updated_at');
        if (! $ts) {
            $ts = $user->updated_at ?: now();
        }

        return is_string($ts) ? (string) strtotime($ts) : (string) strtotime((string) $ts);
    }

    /**
     * Generate the actual shelf image using GD
     */
    private function generateShelfImage(User $user, $books)
    {
        // Ensure OG textures are available locally
        $this->ensureOgTextures();
        // Ensure we have a decent TTF font available (prefer Roboto)
        $this->ensureOgFont();
        // Image dimensions optimized for Open Graph (1.91:1 ratio)
        $width = 1200;
        $height = 630;

        // Create canvas
        $image = imagecreatetruecolor($width, $height);

        // Base background (white to match site background)
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);

        // Load shelf wood textures directly from local filesystem to avoid HTTP self-fetch issues
        $leftPath = public_path('og/textures/shelfleft.jpg');
        $rightPath = public_path('og/textures/shelfright.jpg');
        $centerPath = public_path('og/textures/shelfcenter.jpg');

        $leftImg = (is_file($leftPath) ? @imagecreatefromjpeg($leftPath) : null);
        $rightImg = (is_file($rightPath) ? @imagecreatefromjpeg($rightPath) : null);
        $centerImg = (is_file($centerPath) ? @imagecreatefromjpeg($centerPath) : null);

        // Title: shelf name above the shelf, aligned to top-left
        $titleText = ($user->shelf_name ?: $user->display_name) ?: '';
        $titleColor = imagecolorallocate($image, 33, 37, 41); // dark gray, similar to site text
        $titleFontPath = $this->getFontPath();
        $titleSize = 32;
        $titleLeft = 24;
        $titleBaseline = 48; // initial baseline

        // Measure title height if TTF available
        $titleHeight = 28; // default approx
        if ($titleFontPath && file_exists($titleFontPath)) {
            $bbox = imagettfbbox($titleSize, 0, $titleFontPath, $titleText);
            // bbox: [llx,lly,lrx,lry, urx, ury, ulx, uly]
            $titleHeight = abs($bbox[7] - $bbox[1]);
            // Draw the text
            imagettftext($image, $titleSize, 0, $titleLeft, $titleBaseline, $titleColor, $titleFontPath, $titleText);
        } else {
            // Built-in font fallback
            imagestring($image, 5, $titleLeft, $titleBaseline - 18, $titleText, $titleColor);
            $titleHeight = 18;
        }

        // Layout paddings: leave space for the title above, fill bottom with shelves
        $paddingTop = $titleBaseline + (int) round($titleHeight * 0.45); // space below the title before first shelf row
        $paddingBottom = 0; // fill bottom entirely with shelves
        $shelfAreaHeight = $height - $paddingTop - $paddingBottom;
        $rows = max(1, min(4, (int) ceil(count($books) / 10)));
        $rowHeight = (int) floor($shelfAreaHeight / $rows);

        // Draw rows with wood textures
        if ($centerImg) {
            $leftWidth = $leftImg ? imagesx($leftImg) : 60;
            $rightWidth = $rightImg ? imagesx($rightImg) : 60;

            for ($r = 0; $r < $rows; $r++) {
                $y1 = $paddingTop + ($r * $rowHeight);
                $y2 = $y1 + $rowHeight;

                // Left
                if ($leftImg) {
                    imagecopyresampled($image, $leftImg, 0, $y1, 0, 0, $leftWidth, $rowHeight, imagesx($leftImg), imagesy($leftImg));
                }

                // Right
                if ($rightImg) {
                    imagecopyresampled($image, $rightImg, $width - $rightWidth, $y1, 0, 0, $rightWidth, $rowHeight, imagesx($rightImg), imagesy($rightImg));
                }

                // Center tile across remaining width
                $centerStartX = $leftWidth;
                $centerWidthAvail = $width - $leftWidth - $rightWidth;
                $tileW = imagesx($centerImg);
                $tileH = imagesy($centerImg);
                $x = $centerStartX;
                while ($x < $centerStartX + $centerWidthAvail) {
                    $w = min($tileW, ($centerStartX + $centerWidthAvail) - $x);
                    imagecopyresampled($image, $centerImg, $x, $y1, 0, 0, $w, $rowHeight, $tileW, $tileH);
                    $x += $w;
                }
            }
        }

        // No title/subtitle/branding text overlays
        $booksCount = count($books);

        // Add book covers in a grid aligned to the wooden rows
        if ($booksCount > 0) {
            $this->addBookCoversToImage($image, $books, $width, $height, $paddingTop, $paddingBottom, $rows);
        }

        // Branding removed per request

        // Convert to JPEG
        ob_start();
        imagejpeg($image, null, 85); // 85% quality
        $imageData = ob_get_contents();
        ob_end_clean();

        // Clean up memory
        imagedestroy($image);

        return $imageData;
    }

    private function ensureOgTextures(): void
    {
        $destDir = public_path('og/textures');
        if (! is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $files = [
            'shelfleft.jpg',
            'shelfright.jpg',
            'shelfcenter.jpg',
        ];

        // Try to copy from the front-end assets if available in monorepo
        foreach ($files as $f) {
            $dest = $destDir.DIRECTORY_SEPARATOR.$f;
            if (! file_exists($dest)) {
                $src = base_path('../webapp/src/assets/textures/'.$f);
                if (file_exists($src)) {
                    @copy($src, $dest);
                }
            }
        }
    }

    /**
     * Ensure Roboto fonts are available locally for high-quality text rendering
     */
    private function ensureOgFont(): void
    {
        $publicDir = public_path('og/fonts');
        $storageDir = storage_path('app/fonts');
        if (! is_dir($publicDir)) {
            @mkdir($publicDir, 0775, true);
        }
        if (! is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        $fonts = [
            [
                'name' => 'Roboto-Bold.ttf',
                'urls' => [
                    'https://raw.githubusercontent.com/googlefonts/roboto/main/src/hinted/Roboto-Bold.ttf',
                    'https://raw.githubusercontent.com/google/fonts/main/apache/roboto/Roboto-Bold.ttf',
                ],
            ],
            [
                'name' => 'Roboto-Regular.ttf',
                'urls' => [
                    'https://raw.githubusercontent.com/googlefonts/roboto/main/src/hinted/Roboto-Regular.ttf',
                    'https://raw.githubusercontent.com/google/fonts/main/apache/roboto/Roboto-Regular.ttf',
                ],
            ],
        ];

        foreach ($fonts as $font) {
            $pubPath = $publicDir.DIRECTORY_SEPARATOR.$font['name'];
            $storPath = $storageDir.DIRECTORY_SEPARATOR.$font['name'];
            if (file_exists($pubPath) || file_exists($storPath)) {
                // Ensure both locations have a copy
                if (file_exists($pubPath) && ! file_exists($storPath)) {
                    @copy($pubPath, $storPath);
                } elseif (file_exists($storPath) && ! file_exists($pubPath)) {
                    @copy($storPath, $pubPath);
                }

                continue;
            }

            // Try downloading from known URLs
            foreach ($font['urls'] as $url) {
                try {
                    $data = @file_get_contents($url);
                    if ($data !== false && strlen($data) > 1000) { // crude sanity check
                        @file_put_contents($pubPath, $data);
                        @file_put_contents($storPath, $data);
                        break;
                    }
                } catch (\Throwable $e) {
                    // ignore and try next url
                }
            }
        }
    }

    /**
     * Compute a renderer version based on last modified times of code and assets.
     * This automatically busts the cache when we change layout code, textures, or fonts.
     */
    private function getRendererVersion(): string
    {
        $files = [
            __FILE__,
            public_path('og/textures/shelfleft.jpg'),
            public_path('og/textures/shelfright.jpg'),
            public_path('og/textures/shelfcenter.jpg'),
            public_path('og/fonts/Roboto-Bold.ttf'),
            public_path('og/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/Roboto-Bold.ttf'),
            storage_path('app/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/arial.ttf'),
        ];
        $latest = 0;
        foreach ($files as $f) {
            $t = @filemtime($f);
            if ($t && $t > $latest) {
                $latest = $t;
            }
        }
        if (! $latest) {
            $latest = time();
        }

        return (string) $latest;
    }

    /**
     * Add book covers to the image in a grid layout
     */
    private function addBookCoversToImage($image, $books, $width, $height, $paddingTop = 70, $paddingBottom = 40, $rows = 1)
    {
        $n = max(0, count($books));
        if ($n === 0) {
            return;
        }

        // Compute row metrics to match the wooden shelves we drew earlier
        $rows = max(1, (int) $rows);
        $availableHeight = $height - $paddingTop - $paddingBottom;
        $rowHeight = (int) floor($availableHeight / $rows);

        // Cover size: occupy ~80% of row height and ensure a minimum top clearance
        $bottomClearance = 6;      // gap from the shelf surface
        $minTopClearance = 12;     // ensure books don't touch the shelf above
        $targetHeight = (int) max(50, round($rowHeight * 0.8));
        $maxByClearance = $rowHeight - $bottomClearance - $minTopClearance;
        $coverHeight = (int) min($targetHeight, $maxByClearance);
        if ($coverHeight > ($rowHeight - $bottomClearance)) {
            $coverHeight = $rowHeight - $bottomClearance;
        }
        $coverWidth = (int) max(34, round($coverHeight * 0.66)); // typical book aspect ratio

        // Horizontal layout
        $horizontalPadding = 80; // avoid side pillars
        $spacing = 14; // space between books
        $usableWidth = $width - (2 * $horizontalPadding);
        $cols = max(3, (int) floor(($usableWidth + $spacing) / ($coverWidth + $spacing)));

        // Center grid horizontally
        $totalGridWidth = ($cols * $coverWidth) + (($cols - 1) * $spacing);
        $startX = (int) (($width - $totalGridWidth) / 2);

        $bookIndex = 0;
        for ($row = 0; $row < $rows && $bookIndex < $n; $row++) {
            $rowTop = $paddingTop + ($row * $rowHeight);
            $y = $rowTop + $rowHeight - $coverHeight - $bottomClearance; // align to shelf

            for ($col = 0; $col < $cols && $bookIndex < $n; $col++) {
                $x = $startX + ($col * ($coverWidth + $spacing));
                $book = $books[$bookIndex];

                if (! empty($book->thumbnail) && ($coverImage = $this->loadImageFromUrl($book->thumbnail))) {
                    $resizedCover = imagecreatetruecolor($coverWidth, $coverHeight);
                    imagecopyresampled($resizedCover, $coverImage, 0, 0, 0, 0, $coverWidth, $coverHeight, imagesx($coverImage), imagesy($coverImage));
                    imagecopy($image, $resizedCover, $x, $y, 0, 0, $coverWidth, $coverHeight);
                    imagedestroy($resizedCover);
                    imagedestroy($coverImage);
                } else {
                    // Placeholder for books without cover
                    $bg = imagecolorallocate($image, 210, 210, 210);
                    imagefilledrectangle($image, $x, $y, $x + $coverWidth, $y + $coverHeight, $bg);
                    $border = imagecolorallocate($image, 190, 190, 190);
                    imagerectangle($image, $x, $y, $x + $coverWidth, $y + $coverHeight, $border);
                }

                $bookIndex++;
            }
        }
    }

    /**
     * Load image from URL with error handling
     */
    private function loadImageFromUrl($url)
    {
        try {
            if (! $this->isAllowedDomain($url)) {
                return null;
            }

            // Local cache for remote covers (TTL 7 days)
            $cacheDir = storage_path('app/cache/covers');
            if (! is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            $hash = sha1($url);
            $cacheFile = $cacheDir.'/'.$hash.'.jpg';
            $ttl = 60 * 60 * 24 * 7; // 7 days

            if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
                $imageData = @file_get_contents($cacheFile);
            } else {
                $imageData = @file_get_contents($url);
                if ($imageData !== false) {
                    @file_put_contents($cacheFile, $imageData);
                }
            }
            if ($imageData === false) {
                return null;
            }

            $image = @imagecreatefromstring($imageData);

            return $image ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Basic allowlist validation to prevent SSRF when fetching external images
     */
    private function isAllowedDomain(string $url): bool
    {
        $allowed = [
            'books.google.com',
            'books.googleapis.com',
            'lh3.googleusercontent.com',
            'ssl.gstatic.com',
        ];

        $parsed = parse_url($url);
        if (! isset($parsed['host']) || ! isset($parsed['scheme'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current user has access to a profile.
     *
     * @return array{isOwner: bool, isFollowing: bool, hasAccess: bool}
     */
    private function getProfileAccessInfo(?User $currentUser, User $targetUser): array
    {
        $isOwner = $currentUser && $currentUser->id === $targetUser->id;

        $isFollowing = false;
        if ($currentUser && ! $isOwner) {
            $isFollowing = $currentUser->followingRelationships()
                ->where('followed_id', $targetUser->id)
                ->where('status', 'accepted')
                ->exists();
        }

        $hasAccess = ! $targetUser->is_private || $isOwner || $isFollowing;

        return [
            'isOwner' => $isOwner,
            'isFollowing' => $isFollowing,
            'hasAccess' => $hasAccess,
        ];
    }

    private function getFontPath()
    {
        // Prefer Roboto (to match frontend), then Arial fallback
        $candidates = [
            public_path('og/fonts/Roboto-Bold.ttf'),
            public_path('og/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/Roboto-Bold.ttf'),
            storage_path('app/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/arial.ttf'),
        ];
        foreach ($candidates as $p) {
            if ($p && file_exists($p)) {
                return $p;
            }
        }

        // If no custom font, use built-in font (return null for imagestring functions)
        return null;
    }
}
