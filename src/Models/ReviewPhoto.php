<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $review_id
 * @property string $url
 * @property string|null $thumbnail_url
 * @property int $position
 */
class ReviewPhoto extends AbstractModel
{
    protected $table = 'tfb_review_photos';

    public $timestamps = false;

    protected $fillable = ['id', 'review_id', 'url', 'thumbnail_url', 'position'];

    protected $casts = [
        'review_id' => 'integer',
        'position' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'review_id');
    }
}
