<?php

namespace HuseyinFiliz\TraderFeedback\Models;

use Flarum\Database\AbstractModel;

/**
 * @property int $user_id
 * @property int $likes_received
 */
class UserLikeCount extends AbstractModel
{
    protected $table = 'tfb_user_like_counts';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['user_id', 'likes_received'];
}
