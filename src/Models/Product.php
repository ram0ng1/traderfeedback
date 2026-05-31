<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property int|null $user_id
 * @property int $views
 * @property float $cached_rating
 * @property int $review_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Product extends AbstractModel
{
    protected $table = 'tfb_products';

    protected $fillable = ['id', 'category_id', 'name', 'user_id', 'views', 'cached_rating', 'review_count'];

    protected $casts = [
        'category_id' => 'integer',
        'user_id' => 'integer',
        'views' => 'integer',
        'cached_rating' => 'float',
        'review_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReviewCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'product_id');
    }
}
