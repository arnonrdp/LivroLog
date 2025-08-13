<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorMergeController extends Controller
{
    public function merge(Request $request)
    {
        $request->validate([
            'from_author_id' => 'required|integer|exists:authors,id',
            'to_author_id' => 'required|integer|exists:authors,id|different:from_author_id',
        ]);

        $fromId = $request->input('from_author_id');
        $toId = $request->input('to_author_id');

        DB::transaction(function () use ($fromId, $toId) {
            [$fromAuthor, $toAuthor] = $this->getAuthors($fromId, $toId);

            $this->mergeBooks($fromAuthor, $toAuthor);
            $fromAuthor->delete();
        });

        return response()->json(['message' => 'Authors merged successfully.']);
    }

    private function getAuthors(int $fromId, int $toId): array
    {
        return [
            Author::findOrFail($fromId),
            Author::findOrFail($toId),
        ];
    }

    private function mergeBooks(Author $fromAuthor, Author $toAuthor): void
    {
        $bookIds = $fromAuthor->books->pluck('id')->toArray();
        if ($bookIds) {
            $toAuthor->books()->syncWithoutDetaching($bookIds);
        }
    }
}
