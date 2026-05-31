<?php

namespace HuseyinFiliz\TraderFeedback\Api\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Participantes de uma discussão para o seletor de usuário do feedback
 * (`GET /api/trader/discussions/{id}/participants`). Devolve objetos JSON:API
 * `users` mínimos (username/displayName/avatarUrl), excluindo o próprio ator.
 */
class ListDiscussionParticipantsController implements RequestHandlerInterface
{
    private const LIMIT = 200;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $discussionId = (int) ($request->getAttribute('id') ?? ($request->getQueryParams()['id'] ?? 0));

        $discussion = Discussion::query()->findOrFail($discussionId);
        $actor->assertCan('view', $discussion);

        $participants = $discussion->participants()
            ->where('users.id', '!=', $actor->id)
            ->limit(self::LIMIT)
            ->get();

        $data = $participants->map(fn (User $user) => [
            'type' => 'users',
            'id' => (string) $user->id,
            'attributes' => [
                'username' => $user->username,
                'displayName' => $user->display_name ?? $user->username,
                'avatarUrl' => $user->avatar_url,
            ],
        ])->all();

        return new JsonResponse(['data' => $data]);
    }
}
