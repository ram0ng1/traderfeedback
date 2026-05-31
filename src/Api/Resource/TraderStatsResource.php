<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\TraderStats;

/**
 * Recurso read-only servido sobretudo como relação `traderStats` do
 * `UserResource` (badge de reputação, card de usuário). Sem endpoints CRUD —
 * a leitura por usuário usa o controller plano `ShowTraderStatsController`.
 *
 * @extends AbstractDatabaseResource<TraderStats>
 */
class TraderStatsResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'trader-stats';
    }

    public function model(): string
    {
        return TraderStats::class;
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Integer::make('positive_count')->writable($serverOnly),
            Schema\Integer::make('negative_count')->writable($serverOnly),
            Schema\Integer::make('neutral_count')->writable($serverOnly),
            Schema\Number::make('score')->writable($serverOnly),
            Schema\DateTime::make('last_updated')->nullable()->writable($serverOnly),
        ];
    }
}
