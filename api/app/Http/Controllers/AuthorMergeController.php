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
