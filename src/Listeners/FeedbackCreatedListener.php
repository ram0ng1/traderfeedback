<?php

namespace HuseyinFiliz\TraderFeedback\Listeners;

use Flarum\Notification\NotificationSyncer;
use HuseyinFiliz\TraderFeedback\Events\FeedbackCreated;
use HuseyinFiliz\TraderFeedback\Notifications\NewFeedbackBlueprint;
use HuseyinFiliz\TraderFeedback\Services\StatsService;

/**
 * Ao criar um feedback aprovado, recalcula os stats do destinatário e o
 * notifica. Feedback pendente não dispara nada — só após a aprovação.
 */
class FeedbackCreatedListener
{
    public function __construct(
        protected NotificationSyncer $notifications,
    ) {
    }

    public function handle(FeedbackCreated $event): void
    {
        $feedback = $event->feedback;

        if (! $feedback->is_approved) {
            return;
        }

        $feedback->loadMissing('toUser');

        StatsService::updateUserStats($feedback->to_user_id);

        if ($feedback->toUser && $feedback->toUser->id !== $feedback->from_user_id) {
            $this->notifications->sync(new NewFeedbackBlueprint($feedback), [$feedback->toUser]);
        }
    }
}
