<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $position
 */
class ReviewCategory extends AbstractModel
{
    protected $table = 'tfb_review_categories';

    public $timestamps = false;

    protected $fillable = ['id', 'name', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(ReviewField::class, 'category_id')->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
