<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\ReviewFieldRating;

/**
 * Nota (1–5) de um campo dentro de um review. Servido como inclusão
 * (review → fieldRatings → field).
 *
 * @extends AbstractDatabaseResource<ReviewFieldRating>
 */
class ReviewFieldRatingResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'tfb-review-field-ratings';
    }

    public function model(): string
    {
        return ReviewFieldRating::class;
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Integer::make('rating')->writable($serverOnly),
            Schema\Str::make('comment')->nullable()->writable($serverOnly),
            Schema\Integer::make('fieldId')->property('field_id')->writable($serverOnly),
            Schema\Relationship\ToOne::make('field')->type('tfb-review-fields')->includable(),
        ];
    }
}
