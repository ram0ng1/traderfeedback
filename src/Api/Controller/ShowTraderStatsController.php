<?php

namespace HuseyinFiliz\TraderFeedback\Api\Controller;

use HuseyinFiliz\TraderFeedback\Services\StatsService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Estatísticas de reputação de um usuário (`GET /api/trader/stats/{id}`).
 * Calcula sob demanda e mantém em cache por 1h. Envelope JSON:API simples
 * (objeto único `trader-stats`) consumido cru pelo frontend.
 */
class ShowTraderStatsController implements RequestHandlerInterface
{
    public function __construct(
        protected CacheRepository $cache,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) ($request->getAttribute('id') ?? ($request->getQueryParams()['id'] ?? 0));

        $cacheKey = "trader_stats_{$userId}";
        $stats = $this->cache->get($cacheKey);

        if (! $stats) {
            $stats = StatsService::updateUserStats($userId);
            $this->cache->put($cacheKey, $stats, 3600);
        }

        return new JsonResponse([
            'data' => [
                'type' => 'trader-stats',
                'id' => (string) $userId,
                'attributes' => [
                    'positive_count' => (int) $stats->positive_count,
                    'negative_count' => (int) $stats->negative_count,
                    'neutral_count' => (int) $stats->neutral_count,
                    'score' => (float) $stats->score,
                    'last_updated' => optional($stats->last_updated)->toRfc3339String(),
                ],
            ],
        ]);
    }
}
