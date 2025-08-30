<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Http\Resources\PaginatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserWithBooksResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

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

        // Check if profile is private and user is not the owner or following
        $isOwner = $currentUser && $currentUser->id === $user->id;
        // Only consider as following if the follow status is 'accepted'
        $isFollowing = false;
        if ($currentUser) {
            $isFollowing = $currentUser->followingRelationships()
                ->where('followed_id', $user->id)
                ->where('status', 'accepted')
                ->exists();

        }

        // Load books only if profile is public, user is owner, or user is following
        if (! $user->is_private || $isOwner || $isFollowing) {
            $user->load(['books' => function ($query) use ($isOwner) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                // Only show private books to the owner
                if (! $isOwner) {
                    $query->wherePivot('is_private', false);
                }
            }]);
        }

        $resource = new UserWithBooksResource($user);
        $userData = $resource->toArray(request());

        // Add follow status and request status if user is authenticated
        if ($currentUser && $currentUser->id !== $user->id) {
            $userData['is_following'] = $isFollowing;

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

        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'username' => 'required|string|max:100|unique:users,username,'.$user->id,
            'shelf_name' => 'nullable|string|max:255',
        ]);

        $user->update($request->all());

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
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
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
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Generated shelf image",
     *         @OA\MediaType(
     *             mediaType="image/jpeg"
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function shelfImage(string $id)
    {
        $user = User::findOrFail($id);

        // Get user's books with covers - limit to 20 for display
        $books = $user->books()
            ->whereNotNull('thumbnail')
            ->orderBy('pivot_added_at', 'desc')
            ->limit(20)
            ->get();

        // Compute a version for cache-busting based on last change in user's shelf
        $version = $this->getShelfVersion($user);

        // Serve from on-disk cache if available
        $cachePath = storage_path("app/public/og/shelf-{$user->id}-{$version}.jpg");
        if (is_file($cachePath)) {
            return response()->file($cachePath, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($cachePath)) . ' GMT',
            ]);
        }

        // If GD is unavailable, gracefully fall back to a static OG image
        if (!function_exists('imagecreatetruecolor')) {
            $fallback = rtrim(config('app.frontend_url'), '/') . '/screenshot-web.jpg';
            return redirect()->away($fallback, 302, [
                'Cache-Control' => 'public, max-age=1800',
            ]);
        }

        try {
            // Generate the shelf image
            $image = $this->generateShelfImage($user, $books);

            // Ensure directory exists and save to disk cache
            Storage::makeDirectory('public/og');
            file_put_contents($cachePath, $image);

            return response($image, 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'Last-Modified' => now()->toRfc7231String(),
            ]);
        } catch (\Throwable $e) {
            // On any error, fall back to static OG image to avoid blank previews
            $fallback = rtrim(config('app.frontend_url'), '/') . '/screenshot-web.jpg';
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
        if (!$ts) {
            $ts = $user->updated_at ?: now();
        }
        return is_string($ts) ? (string) strtotime($ts) : (string) strtotime((string) $ts);
    }

    /**
     * Generate the actual shelf image using GD
     */
    private function generateShelfImage(User $user, $books)
    {
        // Image dimensions optimized for Open Graph (1.91:1 ratio)
        $width = 1200;
        $height = 630;

        // Create canvas
        $image = imagecreatetruecolor($width, $height);

        // Base background (light grey)
        $bgColor = imagecolorallocate($image, 245, 245, 245);
        imagefill($image, 0, 0, $bgColor);

        // Try to use the same wooden shelf textures used by the frontend
        $frontend = rtrim(config('app.frontend_url'), '/');
        $leftUrl = $frontend . '/assets/shelfleft-LBalqrtB.jpg';
        $rightUrl = $frontend . '/assets/shelfright-BniE6HMr.jpg';
        $centerUrl = $frontend . '/assets/shelfcenter-BJQyKgxt.jpg';

        $leftImg = $this->loadImageFromUrl($leftUrl);
        $rightImg = $this->loadImageFromUrl($rightUrl);
        $centerImg = $this->loadImageFromUrl($centerUrl);

        $paddingTop = 70;   // space for title
        $paddingBottom = 40; // space for branding
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

        // Text color
        $textColor = imagecolorallocate($image, 20, 20, 20);

        // Add title text
        $fontSize = 34;
        $shelfName = $user->shelf_name ?: $user->display_name;
        $titleText = $shelfName . ' - LivroLog';

        // Add text with fallback for missing font
        $fontPath = $this->getFontPath();
        if ($fontPath && file_exists($fontPath)) {
            // Use TTF font
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $titleText);
            $textWidth = $textBox[2] - $textBox[0];
            $textX = ($width - $textWidth) / 2;
            $textY = 56;
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $titleText);
        } else {
            // Use built-in font
            $textWidth = strlen($titleText) * 10; // Approximate width
            $textX = ($width - $textWidth) / 2;
            $textY = 56;

            imagestring($image, 5, $textX, $textY, $titleText, $textColor);
        }

        // Add book count subtitle
        $booksCount = count($books);
        if ($booksCount > 0) {
            $subtitleText = "{$booksCount} livros";
            if ($fontPath && file_exists($fontPath)) {
                $subtitleSize = 18;
                $subtitleBox = imagettfbbox($subtitleSize, 0, $fontPath, $subtitleText);
                $subtitleWidth = $subtitleBox[2] - $subtitleBox[0];
                $subtitleX = ($width - $subtitleWidth) / 2;
                $subtitleY = 86;
                imagettftext($image, $subtitleSize, 0, $subtitleX, $subtitleY, $textColor, $fontPath, $subtitleText);
            } else {
                $subtitleWidth = strlen($subtitleText) * 8;
                $subtitleX = ($width - $subtitleWidth) / 2;
                $subtitleY = 86;

                imagestring($image, 3, $subtitleX, $subtitleY, $subtitleText, $textColor);
            }
        }

        // Add book covers in a grid aligned to the wooden rows
        if ($booksCount > 0) {
            $this->addBookCoversToImage($image, $books, $width, $height, $paddingTop, $paddingBottom, $rows);
        }

        // Add LivroLog logo/branding (bottom right)
        $brandText = 'LivroLog.com';
        if ($fontPath && file_exists($fontPath)) {
            $brandSize = 14;
            $brandBox = imagettfbbox($brandSize, 0, $fontPath, $brandText);
            $brandWidth = $brandBox[2] - $brandBox[0];
            $brandX = $width - $brandWidth - 20;
            $brandY = $height - 20;

            imagettftext($image, $brandSize, 0, $brandX + 1, $brandY + 1, imagecolorallocate($image, 0, 0, 0), $fontPath, $brandText);
            imagettftext($image, $brandSize, 0, $brandX, $brandY, $textColor, $fontPath, $brandText);
        } else {
            $brandWidth = strlen($brandText) * 6;
            $brandX = $width - $brandWidth - 20;
            $brandY = $height - 20;

            imagestring($image, 2, $brandX + 1, $brandY + 1, $brandText, imagecolorallocate($image, 0, 0, 0));
            imagestring($image, 2, $brandX, $brandY, $brandText, $textColor);
        }

        // Convert to JPEG
        ob_start();
        imagejpeg($image, null, 85); // 85% quality
        $imageData = ob_get_contents();
        ob_end_clean();

        // Clean up memory
        imagedestroy($image);

        return $imageData;
    }

    /**
     * Add book covers to the image in a grid layout
     */
    private function addBookCoversToImage($image, $books, $width, $height, $paddingTop = 70, $paddingBottom = 40, $rows = 4)
    {
        $availableHeight = $height - $paddingTop - $paddingBottom;
        $rowHeight = (int) floor($availableHeight / $rows);
        $verticalMargin = (int) round($rowHeight * 0.1);
        $coverHeight = $rowHeight - (2 * $verticalMargin);
        $coverWidth = (int) round($coverHeight * 0.66);
        $horizontalPadding = 60; // keep away from wood sides
        $spacing = 12;

        $usableWidth = $width - (2 * $horizontalPadding);
        $coversPerRow = max(3, (int) floor(($usableWidth + $spacing) / ($coverWidth + $spacing)));
        $totalGridWidth = ($coversPerRow * $coverWidth) + (($coversPerRow - 1) * $spacing);
        $startX = (int) (($width - $totalGridWidth) / 2);

        $bookIndex = 0;
        for ($row = 0; $row < $rows && $bookIndex < count($books); $row++) {
            $y = $paddingTop + ($row * $rowHeight) + $verticalMargin;
            for ($col = 0; $col < $coversPerRow && $bookIndex < count($books); $col++) {
                $x = $startX + ($col * ($coverWidth + $spacing));
                $book = $books[$bookIndex];

                if ($book->thumbnail && $coverImage = $this->loadImageFromUrl($book->thumbnail)) {
                    $resizedCover = imagecreatetruecolor($coverWidth, $coverHeight);
                    imagecopyresampled($resizedCover, $coverImage, 0, 0, 0, 0, $coverWidth, $coverHeight, imagesx($coverImage), imagesy($coverImage));
                    // subtle border
                    $borderColor = imagecolorallocate($image, 220, 220, 220);
                    imagerectangle($image, $x - 1, $y - 1, $x + $coverWidth, $y + $coverHeight, $borderColor);
                    imagecopy($image, $resizedCover, $x, $y, 0, 0, $coverWidth, $coverHeight);
                    imagedestroy($resizedCover);
                    imagedestroy($coverImage);
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
            // Local cache for remote covers (TTL 7 days)
            $cacheDir = storage_path('app/cache/covers');
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            $hash = sha1($url);
            $cacheFile = $cacheDir . '/' . $hash . '.jpg';
            $ttl = 60 * 60 * 24 * 7; // 7 days

            if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
                $imageData = @file_get_contents($cacheFile);
            } else {
                $imageData = @file_get_contents($url);
                if ($imageData !== false) {
                    @file_put_contents($cacheFile, $imageData);
                }
            }
            if ($imageData === false) return null;

            $image = @imagecreatefromstring($imageData);
            return $image ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get font path - fallback to default if custom font not available
     */
    private function getFontPath()
    {
        $fontPath = storage_path('app/fonts/arial.ttf');

        // If custom font doesn't exist, use built-in font (return null for imagestring functions)
        if (!file_exists($fontPath)) {
            return null;
        }

        return $fontPath;
    }
}
