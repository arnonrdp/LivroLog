<?php

namespace App\Transformers;

class BookTransformer
{
    /**
     * Fields that are always included in the response
     */
    private const BASIC_FIELDS = [
        'id',
        'google_id',
        'title',
        'authors',
        'isbn',
        'thumbnail',
        'description',
    ];

    /**
     * Additional fields included when 'details' is requested
     */
    private const DETAIL_FIELDS = [
        'subtitle',
        'isbn_10',
        'isbn_13',
        'publisher',
        'published_date',
        'page_count',
        'language',
        'categories',
        'maturity_rating',
        'preview_link',
        'info_link',
        'edition',
        'format',
        'info_quality',
        'enriched_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Transform a book or array of books based on requested fields
     *
     * @param  mixed  $data  Single book or array of books
     * @param  array  $includes  Array of additional field groups to include
     * @return mixed Transformed book(s)
     */
    public function transform($data, array $includes = []): mixed
    {
        // Handle single book
        if (is_object($data) || (is_array($data) && isset($data['title']))) {
            return $this->transformSingle($data, $includes);
        }

        // Handle array of books
        if (is_array($data)) {
            return array_map(fn ($book) => $this->transformSingle($book, $includes), $data);
        }

        return $data;
    }

    /**
     * Transform a single book
     */
    private function transformSingle($book, array $includes): array
    {
        $fields = self::BASIC_FIELDS;

        // Add detail fields if requested
        if (in_array('details', $includes)) {
            $fields = array_merge($fields, self::DETAIL_FIELDS);
        }

        $result = [];

        // Convert to array if it's an object
        $bookArray = is_object($book) ? (array) $book : $book;

        // For Eloquent models, convert to array
        if (is_object($book) && method_exists($book, 'toArray')) {
            $bookArray = $book->toArray();
        }

        // Extract only requested fields
        foreach ($fields as $field) {
            if (array_key_exists($field, $bookArray)) {
                $result[$field] = $bookArray[$field];
            } elseif ($field === 'id') {
                // For external search results, explicitly set id as null if not present
                $result[$field] = null;
            }
        }

        // Note: 'id' should only be set for books that exist in our database
        // External search results should have id=null and only google_id populated

        // Special handling for provider field (from search results)
        if (isset($bookArray['provider'])) {
            $result['provider'] = $bookArray['provider'];
        }

        return $result;
    }

    /**
     * Get list of available include options
     */
    public static function getAvailableIncludes(): array
    {
        return ['details'];
    }

    /**
     * Parse 'with' parameter from request
     * Accepts both 'with[]=details' and 'with=details' formats
     */
    public static function parseIncludes($withParameter): array
    {
        if (empty($withParameter)) {
            return [];
        }

        // Handle array format (with[]=details)
        if (is_array($withParameter)) {
            return array_intersect($withParameter, self::getAvailableIncludes());
        }

        // Handle string format (with=details or with=details,other)
        if (is_string($withParameter)) {
            $includes = explode(',', $withParameter);

            return array_intersect($includes, self::getAvailableIncludes());
        }

        return [];
    }
}
