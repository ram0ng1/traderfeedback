<?php

namespace HuseyinFiliz\TraderFeedback\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Discussion\Discussion;
use HuseyinFiliz\TraderFeedback\Models\Feedback;
use HuseyinFiliz\TraderFeedback\Service\FeedbackService;
use HuseyinFiliz\TraderFeedback\Service\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * @extends AbstractDatabaseResource<Feedback>
 */
class FeedbackResource extends AbstractDatabaseResource
{
    public function __construct(
        protected FeedbackService $feedbacks,
        protected ReportService $reports,
    ) {
    }

    public function type(): string
    {
        return 'trader-feedbacks';
    }

    public function model(): string
    {
        return Feedback::class;
    }

    /**
     * Filtros via query params camelCase (json-api-server rejeita `filter[]`
     * custom e nomes não-`[a-z]`; vide `VerificationRequestResource`). Aplicados
     * só em listagem — uma requisição de Show (apenas `{id}`, sem params) não
     * recebe filtro e fica a cargo da `FeedbackPolicy::view`.
     *
     * - `?forUser=N`            → feedbacks aprovados recebidos por N (perfil)
     * - `?byType=positive|...`  → restringe ao tipo
     * - `?sortOrder=oldest`     → inverte a ordenação padrão (newest)
     * - `?pendingOnly=1`        → fila de moderação (is_approved=0), só moderador
     *   (camelCase obrigatório: o json-api-server rejeita query params só-minúsculas)
     */
    public function scope(Builder $query, BaseContext $context): void
    {
        /** @var Context $context */
        $params = $context->request->getQueryParams();
        $actor = $context->getActor();

        $pending = ($params['pendingOnly'] ?? null) === '1' || ($params['pendingOnly'] ?? null) === 1;
        if ($pending && $actor->hasPermission('huseyinfiliz-traderfeedback.moderate')) {
            $query->where('is_approved', false)->orderBy('created_at', 'desc');
            return;
        }

        // Default: só feedback aprovado é listável (evita vazar pendentes num GET público).
        $query->where('is_approved', true);

        $forUser = $params['forUser'] ?? null;
        if ($forUser !== null && $forUser !== '') {
            $query->where('to_user_id', (int) $forUser);

            $type = $params['byType'] ?? null;
            if ($type && $type !== 'all') {
                $query->where('type', (string) $type);
            }
        }

        $query->orderBy('created_at', ($params['sortOrder'] ?? 'newest') === 'oldest' ? 'asc' : 'desc');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 50)
                ->defaultInclude(['fromUser', 'toUser'])
                ->eagerLoad(['fromUser', 'toUser']),

            Endpoint\Show::make()
                ->can('view')
                ->defaultInclude(['fromUser', 'toUser']),

            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['fromUser', 'toUser'])
                ->action(fn (Context $context) => $this->feedbacks->create($context)),

            Endpoint\Update::make()
                ->authenticated()
                ->action(fn (Context $context) => $this->feedbacks->update($context)),

            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.report')
                ->route('POST', '/{id}/report')
                ->authenticated()
                ->action(fn (Context $context) => $this->reports->create($context)),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.approve')
                ->route('POST', '/{id}/approve')
                ->authenticated()
                ->action(fn (Context $context) => $this->feedbacks->approve($context)),

            Endpoint\Endpoint::make('huseyinfiliz.traderfeedback.reject')
                ->route('POST', '/{id}/reject')
                ->authenticated()
                ->action(fn (Context $context) => $this->feedbacks->reject($context))
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        $serverOnly = fn () => false;

        return [
            Schema\Str::make('type')->writable($serverOnly),
            Schema\Str::make('comment')->writable($serverOnly),
            Schema\Str::make('role')->writable($serverOnly),
            Schema\Boolean::make('isApproved')->property('is_approved')->writable($serverOnly),
            Schema\Integer::make('fromUserId')->property('from_user_id')->writable($serverOnly),
            Schema\Integer::make('toUserId')->property('to_user_id')->writable($serverOnly),
            Schema\Integer::make('discussionId')->property('discussion_id')->nullable()->writable($serverOnly),
            Schema\Integer::make('approvedById')->property('approved_by_id')->nullable()->writable($serverOnly),
            Schema\DateTime::make('createdAt')->property('created_at')->writable($serverOnly),
            Schema\DateTime::make('updatedAt')->property('updated_at')->writable($serverOnly),

            Schema\Boolean::make('canEdit')
                ->get(fn (Feedback $f, Context $context) => $context->getActor()->can('edit', $f)),
            Schema\Boolean::make('canDelete')
                ->get(fn (Feedback $f, Context $context) => $context->getActor()->can('delete', $f)),
            Schema\Boolean::make('canReport')
                ->get(fn (Feedback $f, Context $context) => $context->getActor()->can('report', $f)),
            Schema\Boolean::make('canApprove')
                ->get(fn (Feedback $f, Context $context) => $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.moderate')),
            Schema\Boolean::make('canModerate')
                ->get(fn (Feedback $f, Context $context) => $context->getActor()->hasPermission('huseyinfiliz-traderfeedback.moderate')),

            Schema\Boolean::make('discussionExists')
                ->get(fn (Feedback $f, Context $context) => $f->discussion_id !== null && Discussion::find($f->discussion_id) !== null),
            Schema\Boolean::make('canViewDiscussion')
                ->get(function (Feedback $f, Context $context) {
                    if (! $f->discussion_id) {
                        return false;
                    }
                    $discussion = Discussion::find($f->discussion_id);
                    return $discussion !== null && $context->getActor()->can('view', $discussion);
                }),

            Schema\Relationship\ToOne::make('fromUser')->type('users')->includable(),
            Schema\Relationship\ToOne::make('toUser')->type('users')->includable(),
            Schema\Relationship\ToOne::make('approvedBy')->type('users')->nullable()->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt')->column('created_at'),
        ];
    }
}
