<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Author;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Post(
 *     path="/api/authors/merge",
 *     summary="Merge two duplicate authors into one",
 *     description="Moves all books from the source author to the target author and deletes the duplicate author. **Admin access required.**",
 *     tags={"Authors"},
 *     security={{"bearerAuth": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"from_author_id","to_author_id"},
 *             @OA\Property(property="from_author_id", type="string", example="A-9IO8-3D6Y", description="ID of the duplicate author to be merged (will be deleted)"),
 *             @OA\Property(property="to_author_id", type="string", example="A-1ABC-2DEF", description="ID of the main author to keep")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Authors merged successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Authors merged successfully.")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthenticated"),
 *     @OA\Response(response=403, description="Access denied. Admin privileges required."),
 *     @OA\Response(response=422, description="Invalid data")
 * )
 */
class AuthorMergeController extends Controller
{
    public function merge(Request $request)
    {
        $request->validate([
            'from_author_id' => 'required|string|exists:authors,id',
            'to_author_id' => 'required|string|exists:authors,id|different:from_author_id',
        ]);

        $fromId = $request->input('from_author_id');
        $toId = $request->input('to_author_id');

        DB::transaction(function () use ($fromId, $toId) {
            $fromAuthor = Author::findOrFail($fromId);
            $toAuthor = Author::findOrFail($toId);

            // Update all books from the source author to the destination author
            $bookIds = $fromAuthor->books->pluck('id')->toArray();
            if ($bookIds) {
                $toAuthor->books()->syncWithoutDetaching($bookIds);
            }

            // Remove the source author from the pivot table
            $fromAuthor->delete();
        });

        return response()->json(['message' => 'Autores mesclados com sucesso.']);
    }
}
