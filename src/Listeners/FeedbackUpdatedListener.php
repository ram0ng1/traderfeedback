<?php

namespace HuseyinFiliz\TraderFeedback\Listeners;

use Flarum\Notification\NotificationSyncer;
use HuseyinFiliz\TraderFeedback\Events\FeedbackUpdated;
use HuseyinFiliz\TraderFeedback\Notifications\FeedbackApprovedBlueprint;
use HuseyinFiliz\TraderFeedback\Services\StatsService;

/**
 * Ao atualizar um feedback aprovado, recalcula os stats do destinatário e
 * notifica o autor (exceto se o próprio ator for o autor).
 */
class FeedbackUpdatedListener
{
    public function __construct(
        protected NotificationSyncer $notifications,
    ) {
    }

    public function handle(FeedbackUpdated $event): void
    {
        $feedback = $event->feedback;
        $actor = $event->actor;

        if (! $feedback->is_approved) {
            return;
        }

        $feedback->loadMissing(['fromUser', 'toUser']);

        StatsService::updateUserStats($feedback->to_user_id);

        if ($feedback->fromUser && $feedback->fromUser->id !== $actor->id) {
            $this->notifications->sync(new FeedbackApprovedBlueprint($feedback), [$feedback->fromUser]);
        }
    }
}
