<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Http\Resources\PaginatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserWithBooksResource;
use App\Models\User;
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
            ->orderBy('pivot_read_in', 'desc')
            ->limit(20)
            ->get();

        // Generate the shelf image
        $image = $this->generateShelfImage($user, $books);

        return response($image, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            'Last-Modified' => now()->toRfc7231String(),
        ]);
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

        // Colors
        $backgroundColor = imagecolorallocate($image, 14, 165, 233); // #0ea5e9 (theme color)
        $whiteColor = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 255, 255, 255);

        // Fill background with gradient-like effect
        imagefill($image, 0, 0, $backgroundColor);

        // Add semi-transparent overlay for better text readability
        $overlayColor = imagecolorallocatealpha($image, 0, 0, 0, 40);
        imagefilledrectangle($image, 0, 0, $width, $height, $overlayColor);

        // Add title text
        $fontSize = 32;
        $shelfName = $user->shelf_name ?: $user->display_name;
        $titleText = $shelfName . ' - LivroLog';

        // Add text with fallback for missing font
        $fontPath = $this->getFontPath();
        if ($fontPath && file_exists($fontPath)) {
            // Use TTF font
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $titleText);
            $textWidth = $textBox[2] - $textBox[0];
            $textX = ($width - $textWidth) / 2;
            $textY = 80;

            // Add text with shadow effect
            imagettftext($image, $fontSize, 0, $textX + 2, $textY + 2, imagecolorallocate($image, 0, 0, 0), $fontPath, $titleText);
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $titleText);
        } else {
            // Use built-in font
            $textWidth = strlen($titleText) * 10; // Approximate width
            $textX = ($width - $textWidth) / 2;
            $textY = 80;

            imagestring($image, 5, $textX + 1, $textY + 1, $titleText, imagecolorallocate($image, 0, 0, 0));
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
                $subtitleY = 120;

                imagettftext($image, $subtitleSize, 0, $subtitleX + 1, $subtitleY + 1, imagecolorallocate($image, 0, 0, 0), $fontPath, $subtitleText);
                imagettftext($image, $subtitleSize, 0, $subtitleX, $subtitleY, $textColor, $fontPath, $subtitleText);
            } else {
                $subtitleWidth = strlen($subtitleText) * 8;
                $subtitleX = ($width - $subtitleWidth) / 2;
                $subtitleY = 120;

                imagestring($image, 3, $subtitleX + 1, $subtitleY + 1, $subtitleText, imagecolorallocate($image, 0, 0, 0));
                imagestring($image, 3, $subtitleX, $subtitleY, $subtitleText, $textColor);
            }
        }

        // Add book covers in a grid
        if ($booksCount > 0) {
            $this->addBookCoversToImage($image, $books, $width, $height);
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
    private function addBookCoversToImage($image, $books, $width, $height)
    {
        $coverWidth = 60;
        $coverHeight = 90;
        $spacing = 10;
        $startY = 200;

        // Calculate grid layout
        $coversPerRow = min(8, count($books));
        $totalWidth = ($coversPerRow * $coverWidth) + (($coversPerRow - 1) * $spacing);
        $startX = ($width - $totalWidth) / 2;

        $rows = ceil(count($books) / $coversPerRow);
        $maxRows = min(3, $rows); // Maximum 3 rows

        $bookIndex = 0;
        for ($row = 0; $row < $maxRows && $bookIndex < count($books); $row++) {
            $y = $startY + ($row * ($coverHeight + $spacing));

            for ($col = 0; $col < $coversPerRow && $bookIndex < count($books); $col++) {
                $x = $startX + ($col * ($coverWidth + $spacing));
                $book = $books[$bookIndex];

                // Try to load book cover
                if ($book->thumbnail && $coverImage = $this->loadImageFromUrl($book->thumbnail)) {
                    // Resize and add cover
                    $resizedCover = imagecreatetruecolor($coverWidth, $coverHeight);
                    imagecopyresampled($resizedCover, $coverImage, 0, 0, 0, 0, $coverWidth, $coverHeight, imagesx($coverImage), imagesy($coverImage));

                    // Add border
                    $borderColor = imagecolorallocate($image, 200, 200, 200);
                    imagerectangle($image, $x - 1, $y - 1, $x + $coverWidth, $y + $coverHeight, $borderColor);

                    // Copy to main image
                    imagecopy($image, $resizedCover, $x, $y, 0, 0, $coverWidth, $coverHeight);

                    imagedestroy($resizedCover);
                    imagedestroy($coverImage);
                } else {
                    // Draw placeholder rectangle
                    $placeholderColor = imagecolorallocate($image, 100, 100, 100);
                    imagefilledrectangle($image, $x, $y, $x + $coverWidth, $y + $coverHeight, $placeholderColor);

                    // Add book icon or text
                    $placeholderText = 'BOOK';
                    imagestring($image, 3, $x + 10, $y + 35, $placeholderText, imagecolorallocate($image, 255, 255, 255));
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
            if (!$this->isAllowedDomain($url)) {
                return null;
            }

            $imageData = @file_get_contents($url);
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
        if (!isset($parsed['host']) || !isset($parsed['scheme'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
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
