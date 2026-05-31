<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\Product;
use HuseyinFiliz\TraderFeedback\Models\ReviewCategory;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Categorias de review (Brushes, Soaps…). Leitura pública; admin pode criar.
 *
 * @extends AbstractDatabaseResource<ReviewCategory>
 */
class ReviewCategoryResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'tfb-review-categories';
    }

    public function model(): string
    {
        return ReviewCategory::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->paginate(50, 100)->defaultInclude(['fields']),
            Endpoint\Show::make()->defaultInclude(['fields']),
            Endpoint\Create::make()->can('administrate'),
            Endpoint\Update::make()->can('administrate'),
            Endpoint\Delete::make()->can('administrate'),
        ];
    }

    public function fields(): array
    {
        $admin = fn ($model, \Flarum\Api\Context $context) => $context->getActor()->isAdmin();

        return [
            Schema\Str::make('name')->required()->maxLength(255)->writable($admin),
            Schema\Integer::make('position')->writable($admin),
            Schema\Integer::make('productCount')
                ->get(fn (ReviewCategory $cat) => Product::query()->where('category_id', $cat->id)->count()),
            Schema\Relationship\ToMany::make('fields')->type('tfb-review-fields')->includable(),
        ];
    }
}
