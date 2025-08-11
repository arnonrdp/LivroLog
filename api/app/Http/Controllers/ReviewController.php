<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Book;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/reviews",
     *     summary="Get paginated list of reviews with visibility filtering",
     *     description="Returns paginated reviews with visibility logic: authenticated users see public reviews + their own reviews (any visibility), non-authenticated users see only public reviews",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="book_id",
     *         in="query",
     *         description="Filter by book ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Filter by rating",
     *         @OA\Schema(type="integer", minimum=1, maximum=5)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ReviewResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             ),
     *             @OA\Property(property="links", type="object",
     *                 @OA\Property(property="first", type="string"),
     *                 @OA\Property(property="last", type="string"),
     *                 @OA\Property(property="prev", type="string", nullable=true),
     *                 @OA\Property(property="next", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - will show only public reviews")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['user:id,display_name,username,avatar', 'book:id,title,thumbnail'])
                      ->orderBy('created_at', 'desc');

        // Filter by book
        if ($request->has('book_id')) {
            $query->forBook($request->book_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->byRating($request->rating);
        }

        // VISIBILITY LOGIC
        $user = Auth::user();

        if ($user) {
            // Authenticated user sees:
            // 1. All public reviews
            // 2. Their own reviews (all visibility levels)
            $query->where(function ($q) use ($user) {
                $q->where('visibility_level', 'public')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            // Non-authenticated users only see public reviews
            $query->where('visibility_level', 'public');
        }

        $reviews = $query->paginate(15);

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'from' => $reviews->firstItem(),
                'last_page' => $reviews->lastPage(),
                'path' => $reviews->path(),
                'per_page' => $reviews->perPage(),
                'to' => $reviews->lastItem(),
                'total' => $reviews->total(),
            ],
            'links' => [
                'first' => $reviews->url(1),
                'last' => $reviews->url($reviews->lastPage()),
                'prev' => $reviews->previousPageUrl(),
                'next' => $reviews->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/reviews",
     *     summary="Create a new review",
     *     description="Create a new review for a book. Users can only have one review per book.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"book_id", "content", "rating"},
     *             @OA\Property(property="book_id", type="string", example="B-XYZ3-UVW4", description="ID of the book being reviewed"),
     *             @OA\Property(property="title", type="string", example="Amazing book!", maxLength=200, description="Optional title for the review"),
     *             @OA\Property(property="content", type="string", example="This book was incredible...", maxLength=2000, description="Review content"),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5, description="Rating from 1 to 5 stars"),
     *             @OA\Property(property="visibility_level", type="string", enum={"private", "friends", "public"}, example="public", description="Review visibility level"),
     *             @OA\Property(property="is_spoiler", type="boolean", example=false, description="Whether the review contains spoilers")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Review created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ReviewResource")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="User already has a review for this book",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You already have a review for this book")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'book_id' => 'required|string|exists:books,id',
            'title' => 'nullable|string|max:200',
            'content' => 'required|string|max:2000',
            'rating' => 'required|integer|min:1|max:5',
            'visibility_level' => ['required', Rule::in(['private', 'friends', 'public'])],
            'is_spoiler' => 'boolean',
        ]);

        // Check if user already has a review for this book
        $existingReview = Review::where('user_id', $user->id)
                               ->where('book_id', $validated['book_id'])
                               ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You already have a review for this book. Please update your existing review instead.',
                'existing_review_id' => $existingReview->id
            ], 409);
        }

        // Verify book exists
        $book = Book::find($validated['book_id']);
        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        $validated['user_id'] = $user->id;
        $validated['is_spoiler'] = $validated['is_spoiler'] ?? false;

        $review = Review::create($validated);
        $review->load(['user:id,display_name,username,avatar', 'book:id,title,thumbnail']);

        return response()->json($review, 201);
    }

    /**
     * @OA\Get(
     *     path="/reviews/{id}",
     *     summary="Get a specific review",
     *     description="Get a specific review by ID with visibility checking. Private reviews can only be viewed by their owners.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Review ID",
     *         @OA\Schema(type="string", example="R-3D6Y-9IO8")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/ReviewResource")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to view this review",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not authorized to view this review")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $review = Review::with(['user:id,display_name,username,avatar', 'book:id,title,thumbnail'])
                       ->find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $user = Auth::user();

        // Check if user can see this review
        if ($review->visibility_level === 'private') {
            if (!$user || $user->id !== $review->user_id) {
                return response()->json(['message' => 'Not authorized to view this review'], 403);
            }
        }

        return response()->json($review);
    }

    /**
     * @OA\Put(
     *     path="/reviews/{id}",
     *     summary="Update a review",
     *     description="Update an existing review. Only the review owner can update their review.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Review ID",
     *         @OA\Schema(type="string", example="R-3D6Y-9IO8")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated title", maxLength=200, description="Optional review title"),
     *             @OA\Property(property="content", type="string", example="Updated content...", maxLength=2000, description="Review content"),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4, description="Rating from 1 to 5 stars"),
     *             @OA\Property(property="visibility_level", type="string", enum={"private", "friends", "public"}, example="public", description="Review visibility level"),
     *             @OA\Property(property="is_spoiler", type="boolean", example=true, description="Whether the review contains spoilers")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ReviewResource")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to update this review",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not authorized to update this review")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $user = Auth::user();

        // Only the review owner can update
        if ($user->id !== $review->user_id) {
            return response()->json(['message' => 'Not authorized to update this review'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'sometimes|required|string|max:2000',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'visibility_level' => ['sometimes', 'required', Rule::in(['private', 'friends', 'public'])],
            'is_spoiler' => 'boolean',
        ]);

        $review->update($validated);
        $review->load(['user:id,display_name,username,avatar', 'book:id,title,thumbnail']);

        return response()->json($review);
    }

    /**
     * @OA\Delete(
     *     path="/reviews/{id}",
     *     summary="Delete a review",
     *     description="Delete an existing review. Only the review owner or admin can delete a review.",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Review ID",
     *         @OA\Schema(type="string", example="R-3D6Y-9IO8")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Review deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized to delete this review",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not authorized to delete this review")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review not found")
     *         )
     *     )
     * )
     */
    public function destroy(Review $review): JsonResponse
    {
        $user = Auth::user();

        // Only the review owner or admin can delete
        if ($user->id !== $review->user_id && !$user->isAdmin()) {
            return response()->json(['message' => 'Not authorized to delete this review'], 403);
        }

        $review->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/reviews/{id}/helpful",
     *     summary="Mark a review as helpful",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review marked as helpful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review marked as helpful"),
     *             @OA\Property(property="helpful_count", type="integer", example=13)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found"
     *     )
     * )
     */
    public function markAsHelpful(Review $review): JsonResponse
    {
        // Only public reviews can be marked as helpful
        if ($review->visibility_level !== 'public') {
            return response()->json(['message' => 'Only public reviews can be marked as helpful'], 403);
        }

        $review->increment('helpful_count');

        return response()->json([
            'message' => 'Review marked as helpful',
            'helpful_count' => $review->helpful_count
        ]);
    }
}
