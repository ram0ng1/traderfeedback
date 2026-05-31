<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\ReviewPhoto;

/**
 * Foto de um review. Servido como inclusão (review → photos).
 *
 * @extends AbstractDatabaseResource<ReviewPhoto>
 */
class ReviewPhotoResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'tfb-review-photos';
    }

    public function model(): string
    {
        return ReviewPhoto::class;
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Str::make('url')->writable($serverOnly),
            Schema\Str::make('thumbnailUrl')->property('thumbnail_url')->nullable()->writable($serverOnly),
            Schema\Integer::make('position')->writable($serverOnly),
        ];
    }
}
