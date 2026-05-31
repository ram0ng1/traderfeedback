<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $review_id
 * @property int $field_id
 * @property int $rating
 * @property string|null $comment
 */
class ReviewFieldRating extends AbstractModel
{
    protected $table = 'tfb_review_field_ratings';

    public $timestamps = false;

    protected $fillable = ['id', 'review_id', 'field_id', 'rating', 'comment'];

    protected $casts = [
        'review_id' => 'integer',
        'field_id' => 'integer',
        'rating' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'review_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ReviewField::class, 'field_id');
    }
}
