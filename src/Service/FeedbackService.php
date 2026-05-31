<?php

namespace HuseyinFiliz\TraderFeedback\Service;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\TraderFeedback\Events\FeedbackCreated;
use HuseyinFiliz\TraderFeedback\Events\FeedbackDeleted;
use HuseyinFiliz\TraderFeedback\Models\Feedback;
use HuseyinFiliz\TraderFeedback\Notifications\FeedbackApprovedBlueprint;
use HuseyinFiliz\TraderFeedback\Notifications\NewFeedbackBlueprint;
use HuseyinFiliz\TraderFeedback\Services\StatsService;
use HuseyinFiliz\TraderFeedback\Validators\FeedbackValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Regra de negócio do feedback de trader. Extraída dos antigos controllers
 * Create/Update/Approve/Reject para manter os resources focados em schema,
 * endpoints e visibilidade (§53). Cada método recebe o `Context` do
 * json-api-server e devolve o model resolvido.
 */
class FeedbackService
{
    public function __construct(
        protected FeedbackValidator $validator,
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
        protected Dispatcher $events,
        protected NotificationSyncer $notifications,
        protected LoggerInterface $log,
    ) {
    }

    /**
     * Cria um feedback. Aplica rate-limit, regras de settings
     * (requireDiscussion/allowNegative/onePerDiscussion), validação e
     * sanitização do comentário. Dispara `FeedbackCreated` — o listener
     * recalcula stats e notifica quando aprovado.
     */
    public function create(Context $context): Feedback
    {
        $actor = $context->getActor();
        $actor->assertRegistered();
        $actor->assertCan('huseyinfiliz-traderfeedback.give');

        $data = (array) ($context->body()['data']['attributes'] ?? []);

        $recentFeedback = Feedback::query()
            ->where('from_user_id', $actor->id)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->exists();

        if ($recentFeedback) {
            throw new ValidationException([
                'rate_limit' => $this->translator->trans(
                    'huseyinfiliz-traderfeedback.api.validation.rate_limit_feedback',
                    ['seconds' => 60]
                ),
            ]);
        }

        $requireDiscussion = $this->settings->get('huseyinfiliz.traderfeedback.requireDiscussion', false);
        if ($requireDiscussion && ! ($data['discussion_id'] ?? null)) {
            throw new ValidationException([
                'discussion_id' => 'Discussion URL or ID is required for feedback.',
            ]);
        }

        $allowNegative = $this->settings->get('huseyinfiliz.traderfeedback.allowNegative');
        if (($allowNegative === false || $allowNegative === '0' || $allowNegative === 0)
            && ($data['type'] ?? null) === Feedback::TYPE_NEGATIVE) {
            throw new ValidationException(['type' => 'Negative feedback is not allowed.']);
        }

        $this->validator->assertValid($data);

        $toUserId = (int) ($data['to_user_id'] ?? 0);
        if ((int) $actor->id === $toUserId) {
            throw new ValidationException(['to_user_id' => 'You cannot give feedback to yourself.']);
        }

        $discussionId = $this->parseDiscussionId($data['discussion_id'] ?? null);

        $onePerDiscussion = $this->settings->get('huseyinfiliz.traderfeedback.onePerDiscussion', true);
        if ($onePerDiscussion && $discussionId) {
            $exists = Feedback::query()
                ->where('from_user_id', $actor->id)
                ->where('to_user_id', $toUserId)
                ->where('discussion_id', $discussionId)
                ->exists();

            if ($exists) {
                throw new ValidationException([
                    'discussion_id' => 'You have already given feedback for this user in this discussion.',
                ]);
            }
        }

        if ($discussionId) {
            $discussion = Discussion::find($discussionId);
            if (! $discussion) {
                throw new ValidationException(['discussion_id' => 'The specified discussion does not exist.']);
            }
            if ($discussion->hidden_at !== null) {
                throw new ValidationException(['discussion_id' => 'The specified discussion is not available.']);
            }
        }

        $sanitizedComment = strip_tags((string) ($data['comment'] ?? ''));

        $feedback = new Feedback();
        $feedback->from_user_id = (int) $actor->id;
        $feedback->to_user_id = $toUserId;
        $feedback->type = $data['type'] ?? null;
        $feedback->comment = $sanitizedComment;
        $feedback->role = $data['role'] ?? null;
        $feedback->discussion_id = $discussionId;
        $feedback->is_approved = ! $this->settings->get('huseyinfiliz.traderfeedback.requireApproval', false);
        $feedback->save();

        $feedback->load(['fromUser', 'toUser']);

        $this->events->dispatch(new FeedbackCreated($feedback, $actor));

        return $feedback;
    }

    /**
     * Edição parcial de um feedback (campos type/comment/role). O ator precisa
     * passar pela `FeedbackPolicy::edit` (autor dentro de 24h ou moderador).
     */
    public function update(Context $context): Feedback
    {
        $actor = $context->getActor();
        $feedback = Feedback::query()->findOrFail($context->modelId);
        $actor->assertCan('edit', $feedback);

        $data = (array) ($context->body()['data']['attributes'] ?? []);
        $validationData = [];

        if (isset($data['type'])) {
            $validationData['type'] = $data['type'];
            $feedback->type = $data['type'];
        }
        if (isset($data['comment'])) {
            $comment = strip_tags((string) $data['comment']);
            $validationData['comment'] = $comment;
            $feedback->comment = $comment;
        }
        if (isset($data['role'])) {
            $validationData['role'] = $data['role'];
            $feedback->role = $data['role'];
        }

        if (! empty($validationData)) {
            $this->validator->assertValid($validationData);
        }

        $feedback->save();

        if ($feedback->is_approved) {
            StatsService::updateUserStats($feedback->to_user_id);
        }

        return $feedback;
    }

    /**
     * Aprova um feedback pendente (moderação). Notifica autor e destinatário
     * apenas na transição pendente→aprovado.
     */
    public function approve(Context $context): Feedback
    {
        $actor = $context->getActor();
        $actor->assertCan('huseyinfiliz-traderfeedback.moderate');

        $feedback = Feedback::with(['fromUser', 'toUser'])->findOrFail($context->modelId);
        $wasApproved = $feedback->is_approved;

        $feedback->is_approved = true;
        $feedback->approved_by_id = $actor->id;
        $feedback->save();

        StatsService::updateUserStats($feedback->to_user_id);

        if (! $wasApproved) {
            try {
                if ($feedback->fromUser) {
                    $this->notifications->sync(new FeedbackApprovedBlueprint($feedback), [$feedback->fromUser]);
                }
                if ($feedback->toUser) {
                    $this->notifications->sync(new NewFeedbackBlueprint($feedback), [$feedback->toUser]);
                }
            } catch (\Throwable $e) {
                $this->log->error('[traderfeedback] Failed to send approval notifications', [
                    'feedback_id' => $feedback->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $feedback;
    }

    /**
     * Rejeita um feedback pendente: notifica o autor e faz soft-delete,
     * recalculando os stats do destinatário.
     */
    public function reject(Context $context): Feedback
    {
        $actor = $context->getActor();
        $actor->assertCan('huseyinfiliz-traderfeedback.moderate');

        $feedback = Feedback::with(['fromUser', 'toUser'])->findOrFail($context->modelId);

        if ($feedback->fromUser) {
            try {
                $this->notifications->sync(
                    new \HuseyinFiliz\TraderFeedback\Notifications\FeedbackRejectedBlueprint($feedback),
                    [$feedback->fromUser]
                );
            } catch (\Throwable $e) {
                $this->log->error('[traderfeedback] Failed to send rejection notification', [
                    'feedback_id' => $feedback->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $toUserId = $feedback->to_user_id;
        $this->events->dispatch(new FeedbackDeleted($feedback, $actor));

        $feedback->is_approved = false;
        $feedback->delete();

        StatsService::updateUserStats($toUserId);

        return $feedback;
    }

    /**
     * Extrai um id de discussão de uma URL `/d/{id}` ou de um número puro.
     */
    private function parseDiscussionId(mixed $raw): ?int
    {
        if (! $raw) {
            return null;
        }
        if (is_string($raw) && preg_match('/\/d\/(\d+)/', $raw, $m)) {
            return (int) $m[1];
        }
        return (int) $raw ?: null;
    }
}
