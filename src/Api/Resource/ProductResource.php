<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use HuseyinFiliz\TraderFeedback\Models\Product;
use HuseyinFiliz\TraderFeedback\Models\ReviewPhoto;
use HuseyinFiliz\TraderFeedback\Service\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Produtos do Community Reviews. Index com filtro de categoria/busca/ordem;
 * Show inclui reviews (com autor/fotos/notas) e comentários.
 *
 * @extends AbstractDatabaseResource<Product>
 */
class ProductResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ReviewService $reviews,
    ) {
    }

    public function type(): string
    {
        return 'tfb-products';
    }

    public function model(): string
    {
        return Product::class;
    }

    /**
     * Query params camelCase (json-api-server rejeita só-minúsculas):
     * `?byCategory=N`, `?searchQuery=texto`, `?sortBy=newest|oldest|rating|reviews|views`.
     */
    public function scope(Builder $query, BaseContext $context): void
    {
        $params = $context->request->getQueryParams();

        if (($cat = $params['byCategory'] ?? null) !== null && $cat !== '') {
            $query->where('category_id', (int) $cat);
        }

        $search = trim((string) ($params['searchQuery'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where('name', 'like', $like);
        }

        switch ($params['sortBy'] ?? 'newest') {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'rating':
                $query->orderBy('cached_rating', 'desc');
                break;
            case 'reviews':
                $query->orderBy('review_count', 'desc');
                break;
            case 'views':
                $query->orderBy('views', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(12, 60)
                ->defaultInclude(['category'])
                ->eagerLoad(['category']),

            Endpoint\Show::make()
                ->defaultInclude([
                    'category',
                    'reviews', 'reviews.user', 'reviews.photos',
                    'reviews.fieldRatings', 'reviews.fieldRatings.field', 'reviews.merchant',
                ])
                ->eagerLoad(['category', 'reviews.user', 'reviews.photos', 'reviews.fieldRatings', 'reviews.merchant']),

            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['category'])
                ->action(fn (Context $context) => $this->reviews->createProduct($context)),

            Endpoint\Update::make()
                ->authenticated()
                ->can(ReviewService::PERM_MODERATE)
                ->defaultInclude(['category']),

            Endpoint\Delete::make()
                ->authenticated()
                ->can(ReviewService::PERM_MODERATE),
        ];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;
        $moderate = fn ($model, Context $context) => $context->getActor()->hasPermission(ReviewService::PERM_MODERATE);

        return [
            Schema\Str::make('name')->required()->maxLength(255)->writable($moderate),
            Schema\Integer::make('categoryId')->property('category_id')->writable($moderate),
            Schema\Integer::make('userId')->property('user_id')->nullable()->writable($serverOnly),
            Schema\Integer::make('views')->writable($serverOnly),
            Schema\Number::make('cachedRating')->property('cached_rating')->writable($serverOnly),
            Schema\Integer::make('reviewCount')->property('review_count')->writable($serverOnly),
            Schema\DateTime::make('createdAt')->property('created_at')->writable($serverOnly),

            Schema\Boolean::make('canManage')
                ->get(fn (Product $p, Context $context) =>
                    ! $context->getActor()->isGuest()
                    && $context->getActor()->hasPermission(ReviewService::PERM_MODERATE)),

            // Primeira foto de review do produto (thumb do card no índice).
            Schema\Str::make('thumbnailUrl')
                ->nullable()
                ->get(fn (Product $p) => ReviewPhoto::query()
                    ->join('tfb_product_reviews', 'tfb_product_reviews.id', '=', 'tfb_review_photos.review_id')
                    ->where('tfb_product_reviews.product_id', $p->id)
                    ->orderBy('tfb_review_photos.position')
                    ->value('tfb_review_photos.thumbnail_url')),

            Schema\Relationship\ToOne::make('category')->type('tfb-review-categories')->includable(),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToMany::make('reviews')->type('tfb-product-reviews')->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt')->column('created_at'),
            SortColumn::make('cachedRating')->column('cached_rating'),
            SortColumn::make('reviewCount')->column('review_count'),
            SortColumn::make('views'),
        ];
    }
}
