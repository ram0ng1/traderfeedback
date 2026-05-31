<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\ReviewField;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Campo de nota de uma categoria (ex.: Density, Lather). Servido como inclusão
 * (categoria → fields, e nota-de-campo → field).
 *
 * @extends AbstractDatabaseResource<ReviewField>
 */
class ReviewFieldResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'tfb-review-fields';
    }

    public function model(): string
    {
        return ReviewField::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        $params = $context->request->getQueryParams();
        if (($cat = $params['byCategory'] ?? null) !== null && $cat !== '') {
            $query->where('category_id', (int) $cat);
        }
        $query->orderBy('position')->orderBy('name');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->paginate(100, 500),
            Endpoint\Create::make()->can('administrate'),
            Endpoint\Update::make()->can('administrate'),
            Endpoint\Delete::make()->can('administrate'),
        ];
    }

    public function fields(): array
    {
        $admin = fn ($model, Context $context) => $context->getActor()->isAdmin();

        return [
            Schema\Str::make('name')->required()->maxLength(255)->writable($admin),
            Schema\Integer::make('categoryId')->property('category_id')->writable($admin),
            Schema\Integer::make('position')->writable($admin),
        ];
    }
}
