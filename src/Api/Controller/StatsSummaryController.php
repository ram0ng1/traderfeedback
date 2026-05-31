<?php

namespace HuseyinFiliz\TraderFeedback\Api\Controller;

use Flarum\Http\RequestUtil;
use HuseyinFiliz\TraderFeedback\Models\Feedback;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Contagens agregadas de feedback aprovado para o painel admin
 * (`GET /api/trader/stats/summary`). Restrito a moderadores.
 */
class StatsSummaryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan('huseyinfiliz-traderfeedback.moderate');

        $counts = Feedback::query()
            ->where('is_approved', true)
            ->selectRaw('type, COUNT(*) AS c')
            ->groupBy('type')
            ->pluck('c', 'type');

        $positive = (int) ($counts['positive'] ?? 0);
        $neutral = (int) ($counts['neutral'] ?? 0);
        $negative = (int) ($counts['negative'] ?? 0);

        return new JsonResponse([
            'data' => [
                [
                    'type' => 'trader-stats-summary',
                    'id' => 'summary',
                    'attributes' => [
                        'total' => $positive + $neutral + $negative,
                        'positive' => $positive,
                        'neutral' => $neutral,
                        'negative' => $negative,
                    ],
                ],
            ],
        ]);
    }
}
