<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use HuseyinFiliz\TraderFeedback\Models\FeedbackReport;
use HuseyinFiliz\TraderFeedback\Service\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Relatórios de feedback (fila de moderação). Index e ações são restritos a
 * quem detém `huseyinfiliz-traderfeedback.moderate`.
 *
 * @extends AbstractDatabaseResource<FeedbackReport>
 */
class FeedbackReportResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ReportService $reports,
    ) {
    }

    public function type(): string
    {
        return 'feedback-reports';
    }

    public function model(): string
    {
        return FeedbackReport::class;
    }

    /**
     * Só relatórios não resolvidos cujo feedback ainda existe.
     */
    public function scope(Builder $query, BaseContext $context): void
    {
        $query->where('resolved', false)->whereHas('feedback')->orderBy('created_at', 'desc');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->can('huseyinfiliz-traderfeedback.moderate')
                ->defaultInclude(['reporter', 'feedback', 'feedback.fromUser', 'feedback.toUser'])
                ->eagerLoad(['reporter', 'feedback', 'feedback.fromUser', 'feedback.toUser']),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.reports.approve')
                ->route('POST', '/{id}/approve')
                ->authenticated()
                ->action(fn (Context $context) => $this->reports->approve($context)),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.reports.reject')
                ->route('POST', '/{id}/reject')
                ->authenticated()
                ->action(fn (Context $context) => $this->reports->reject($context)),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.reports.dismiss')
                ->route('POST', '/{id}/dismiss')
                ->authenticated()
                ->action(fn (Context $context) => $this->reports->dismiss($context))
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Str::make('reason')->writable($serverOnly),
            Schema\Boolean::make('resolved')->writable($serverOnly),
            Schema\DateTime::make('createdAt')->property('created_at')->writable($serverOnly),
            Schema\DateTime::make('updatedAt')->property('updated_at')->writable($serverOnly),

            Schema\Relationship\ToOne::make('reporter')->type('users')->includable(),
            Schema\Relationship\ToOne::make('feedback')->type('trader-feedbacks')->includable(),
            Schema\Relationship\ToOne::make('resolvedBy')->type('users')->nullable()->includable(),
        ];
    }
}
