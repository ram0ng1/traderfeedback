<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property int|null $merchant_user_id
 * @property string|null $price
 * @property string|null $url
 * @property string $comment
 * @property float $rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductReview extends AbstractModel
{
    protected $table = 'tfb_product_reviews';

    protected $fillable = ['id', 'product_id', 'user_id', 'merchant_user_id', 'price', 'url', 'comment', 'rating'];

    protected $casts = [
        'product_id' => 'integer',
        'user_id' => 'integer',
        'merchant_user_id' => 'integer',
        'rating' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function fieldRatings(): HasMany
    {
        return $this->hasMany(ReviewFieldRating::class, 'review_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class, 'review_id')->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'review_id');
    }
}
