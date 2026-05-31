<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use HuseyinFiliz\TraderFeedback\Models\ProductReview;
use HuseyinFiliz\TraderFeedback\Service\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Reviews de produto (com nota média, preço, fotos e notas por campo).
 *
 * @extends AbstractDatabaseResource<ProductReview>
 */
class ProductReviewResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ReviewService $reviews,
    ) {
    }

    public function type(): string
    {
        return 'tfb-product-reviews';
    }

    public function model(): string
    {
        return ProductReview::class;
    }

    /**
     * `?forProduct=N` (reviews de um produto) ou `?forUser=N` (reviews escritos
     * por um usuário — aba Reviews do perfil). camelCase obrigatório.
     */
    public function scope(Builder $query, BaseContext $context): void
    {
        $params = $context->request->getQueryParams();

        if (($pid = $params['forProduct'] ?? null) !== null && $pid !== '') {
            $query->where('product_id', (int) $pid);
        }
        if (($uid = $params['forUser'] ?? null) !== null && $uid !== '') {
            $query->where('user_id', (int) $uid);
        }

        $query->orderBy('created_at', 'desc');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 50)
                ->defaultInclude(['user', 'photos', 'fieldRatings', 'fieldRatings.field', 'merchant', 'product'])
                ->eagerLoad(['user', 'photos', 'fieldRatings', 'merchant']),

            Endpoint\Show::make()
                ->defaultInclude(['user', 'photos', 'fieldRatings', 'fieldRatings.field', 'merchant', 'product'])
                ->eagerLoad(['user', 'photos', 'fieldRatings']),

            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user', 'photos', 'fieldRatings', 'fieldRatings.field', 'product'])
                ->action(fn (Context $context) => $this->reviews->createReview($context)),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.reviews.delete')
                ->route('DELETE', '/{id}')
                ->authenticated()
                ->action(function (Context $context) {
                    $this->reviews->deleteReview($context);
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Integer::make('productId')->property('product_id')->writable($serverOnly),
            Schema\Integer::make('userId')->property('user_id')->nullable()->writable($serverOnly),
            Schema\Integer::make('merchantUserId')->property('merchant_user_id')->nullable()->writable($serverOnly),
            Schema\Str::make('price')->nullable()->writable($serverOnly),
            Schema\Str::make('url')->nullable()->writable($serverOnly),
            Schema\Str::make('comment')->writable($serverOnly),
            Schema\Number::make('rating')->writable($serverOnly),
            Schema\DateTime::make('createdAt')->property('created_at')->writable($serverOnly),

            Schema\Boolean::make('canDelete')
                ->get(function (ProductReview $review, Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    return (int) $actor->id === (int) $review->user_id
                        || $actor->hasPermission(ReviewService::PERM_MODERATE);
                }),

            Schema\Relationship\ToOne::make('product')->type('tfb-products')->includable(),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('merchant')->type('users')->nullable()->includable(),
            Schema\Relationship\ToMany::make('photos')->type('tfb-review-photos')->includable(),
            Schema\Relationship\ToMany::make('fieldRatings')->type('tfb-review-field-ratings')->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt')->column('created_at'),
            SortColumn::make('rating'),
        ];
    }
}
