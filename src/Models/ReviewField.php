<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property int $position
 */
class ReviewField extends AbstractModel
{
    protected $table = 'tfb_review_fields';

    public $timestamps = false;

    protected $fillable = ['id', 'category_id', 'name', 'position'];

    protected $casts = [
        'category_id' => 'integer',
        'position' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReviewCategory::class, 'category_id');
    }
}
