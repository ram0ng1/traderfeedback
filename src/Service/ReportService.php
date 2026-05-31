<?php

namespace HuseyinFiliz\TraderFeedback\Service;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use HuseyinFiliz\TraderFeedback\Events\FeedbackDeleted;
use HuseyinFiliz\TraderFeedback\Models\Feedback;
use HuseyinFiliz\TraderFeedback\Models\FeedbackReport;
use HuseyinFiliz\TraderFeedback\Services\StatsService;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Regra de negócio dos relatórios de feedback: criação por usuários e
 * moderação (approve = mantém o feedback / reject = remove o feedback /
 * dismiss = arquiva o relatório).
 */
class ReportService
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected Dispatcher $events,
    ) {
    }

    /**
     * Cria um relatório sobre um feedback. Bloqueia duplicatas não resolvidas
     * do mesmo usuário e aplica rate-limit de 1/min.
     */
    public function create(Context $context): FeedbackReport
    {
        $actor = $context->getActor();
        $feedback = Feedback::findOrFail($context->modelId);
        $actor->assertCan('report', $feedback);

        $existing = FeedbackReport::query()
            ->where('feedback_id', $feedback->id)
            ->where('user_id', $actor->id)
            ->where('resolved', false)
            ->exists();

        if ($existing) {
            throw new ValidationException([
                'feedback' => $this->translator->trans('huseyinfiliz-traderfeedback.api.validation.already_reported'),
            ]);
        }

        $recent = FeedbackReport::query()
            ->where('user_id', $actor->id)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->exists();

        if ($recent) {
            throw new ValidationException([
                'rate_limit' => $this->translator->trans(
                    'huseyinfiliz-traderfeedback.api.validation.rate_limit_report',
                    ['seconds' => 60]
                ),
            ]);
        }

        $data = (array) ($context->body()['data']['attributes'] ?? []);

        $report = new FeedbackReport();
        $report->user_id = (int) $actor->id;
        $report->feedback_id = (int) $feedback->id;
        $report->reason = strip_tags((string) ($data['reason'] ?? 'No reason provided'));
        $report->resolved = false;
        $report->save();

        $report->load(['reporter', 'feedback', 'feedback.fromUser', 'feedback.toUser']);

        return $report;
    }

    /**
     * Aprova o relatório: marca como resolvido e mantém o feedback (moderador
     * considerou o feedback legítimo).
     */
    public function approve(Context $context): FeedbackReport
    {
        $actor = $context->getActor();
        $actor->assertCan('huseyinfiliz-traderfeedback.moderate');

        $report = FeedbackReport::findOrFail($context->modelId);
        $this->resolve($report, (int) $actor->id);

        return $report;
    }

    /**
     * Arquiva o relatório sem ação (moderador descartou).
     */
    public function dismiss(Context $context): FeedbackReport
    {
        return $this->approve($context);
    }

    /**
     * Rejeita o relatório: marca como resolvido E remove o feedback reportado,
     * recalculando os stats do destinatário.
     */
    public function reject(Context $context): FeedbackReport
    {
        $actor = $context->getActor();
        $actor->assertCan('huseyinfiliz-traderfeedback.moderate');

        $report = FeedbackReport::findOrFail($context->modelId);
        $feedback = $report->feedback;

        $this->resolve($report, (int) $actor->id);

        if ($feedback) {
            $toUserId = $feedback->to_user_id;
            $this->events->dispatch(new FeedbackDeleted($feedback, $actor));
            $feedback->delete();
            StatsService::updateUserStats($toUserId);
        }

        return $report;
    }

    private function resolve(FeedbackReport $report, int $resolvedById): void
    {
        $report->resolved = true;
        $report->resolved_by_id = $resolvedById;
        $report->updated_at = Carbon::now();
        $report->save();
    }
}
