<?php

namespace HuseyinFiliz\TraderFeedback\Listeners;

use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use HuseyinFiliz\TraderFeedback\Models\UserLikeCount;

/**
 * Mantém `tfb_user_like_counts.likes_received` em dia conforme posts são
 * curtidos/descurtidos. O contador é do AUTOR do post (`post.user_id`), não de
 * quem curtiu. Registrado via 'Classe@metodo' (§20) pois são métodos de
 * instância distintos por evento.
 */
class PostLikeListener
{
    public function liked(PostWasLiked $event): void
    {
        $this->adjust((int) ($event->post->user_id ?? 0), 1);
    }

    public function unliked(PostWasUnliked $event): void
    {
        $this->adjust((int) ($event->post->user_id ?? 0), -1);
    }

    private function adjust(int $authorId, int $delta): void
    {
        if ($authorId <= 0) {
            return;
        }

        $row = UserLikeCount::firstOrNew(['user_id' => $authorId]);
        $row->likes_received = max(0, (int) $row->likes_received + $delta);
        $row->save();
    }
}
