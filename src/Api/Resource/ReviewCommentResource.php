<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\ReviewComment;
use HuseyinFiliz\TraderFeedback\Service\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Comentários em reviews/produtos.
 *
 * @extends AbstractDatabaseResource<ReviewComment>
 */
class ReviewCommentResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ReviewService $reviews,
    ) {
    }

    public function type(): string
    {
        return 'tfb-review-comments';
    }

    public function model(): string
    {
        return ReviewComment::class;
    }

    /**
     * `?forProduct=N` ou `?forReview=N` (camelCase — o json-api-server rejeita
     * query params só-minúsculas).
     */
    public function scope(Builder $query, BaseContext $context): void
    {
        $params = $context->request->getQueryParams();

        if (($pid = $params['forProduct'] ?? null) !== null && $pid !== '') {
            $query->where('product_id', (int) $pid);
        }
        if (($rid = $params['forReview'] ?? null) !== null && $rid !== '') {
            $query->where('review_id', (int) $rid);
        }

        $query->orderBy('created_at', 'asc');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->paginate(50, 200)->defaultInclude(['user'])->eagerLoad(['user']),

            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user'])
                ->action(fn (Context $context) => $this->reviews->createComment($context)),
        ];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Str::make('comment')->writable($serverOnly),
            Schema\Integer::make('productId')->property('product_id')->writable($serverOnly),
            Schema\Integer::make('reviewId')->property('review_id')->nullable()->writable($serverOnly),
            Schema\DateTime::make('createdAt')->property('created_at')->writable($serverOnly),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
        ];
    }
}
