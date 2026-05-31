<?php

namespace HuseyinFiliz\TraderFeedback\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;
use HuseyinFiliz\TraderFeedback\Models\TraderStats;

/**
 * Campos que a extensão acrescenta ao `UserResource`: flags de permissão
 * (botões de feedback) e os atributos de reputação consumidos pelo badge de
 * post e pelo card de usuário. Substitui o antigo
 * `Extend\ApiSerializer(UserSerializer)` + os `addInclude('traderStats')`.
 *
 * Os stats são expostos como atributos computados (sempre serializados, sem
 * depender de `?include`) lendo a relação `traderStats` — pré-carregada via
 * `eagerLoad` nos endpoints de User/Post/Discussion (extend.php) para evitar
 * N+1 (§38.1). Espelha o padrão de atributos computados do `verified`.
 */
class UserResourceFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canGiveFeedback')
                ->get(fn (User $user, Context $context) =>
                    $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.give')
                    && (int) $context->getActor()->id !== (int) $user->id),

            Schema\Boolean::make('canReportFeedback')
                ->get(fn (User $user, Context $context) =>
                    $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.report')),

            Schema\Boolean::make('canDeleteFeedback')
                ->get(fn (User $user, Context $context) =>
                    $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.delete')),

            Schema\Boolean::make('canModerateFeedback')
                ->get(fn (User $user, Context $context) =>
                    $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.moderate')),

            Schema\Integer::make('traderTotalFeedback')
                ->get(fn (User $user) => $this->totalFor($user)),

            Schema\Number::make('traderScore')
                ->get(fn (User $user) => (float) (optional($this->statsFor($user))->score ?? 0)),

            Schema\Integer::make('traderPositiveCount')
                ->get(fn (User $user) => (int) (optional($this->statsFor($user))->positive_count ?? 0)),

            Schema\Integer::make('traderNeutralCount')
                ->get(fn (User $user) => (int) (optional($this->statsFor($user))->neutral_count ?? 0)),

            Schema\Integer::make('traderNegativeCount')
                ->get(fn (User $user) => (int) (optional($this->statsFor($user))->negative_count ?? 0)),

            Schema\Integer::make('traderLikesReceived')
                ->get(fn (User $user) => (int) (optional($user->likeCount)->likes_received ?? 0)),
        ];
    }

    private function statsFor(User $user): ?TraderStats
    {
        return $user->traderStats;
    }

    private function totalFor(User $user): int
    {
        $stats = $this->statsFor($user);
        if (! $stats) {
            return 0;
        }
        return (int) $stats->positive_count + (int) $stats->neutral_count + (int) $stats->negative_count;
    }
}
