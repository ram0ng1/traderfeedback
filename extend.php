<?php

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;
use Flarum\User\User;
use HuseyinFiliz\TraderFeedback\Access\FeedbackPolicy;
use HuseyinFiliz\TraderFeedback\Access\GlobalPolicy;
use HuseyinFiliz\TraderFeedback\Api\Controller\ListDiscussionParticipantsController;
use HuseyinFiliz\TraderFeedback\Api\Controller\ShowTraderStatsController;
use HuseyinFiliz\TraderFeedback\Api\Controller\StatsSummaryController;
use HuseyinFiliz\TraderFeedback\Api\Resource\FeedbackReportResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\FeedbackResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ProductResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ProductReviewResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ReviewCategoryResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ReviewCommentResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ReviewFieldRatingResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ReviewFieldResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\ReviewPhotoResource;
use HuseyinFiliz\TraderFeedback\Api\Resource\TraderStatsResource;
use HuseyinFiliz\TraderFeedback\Api\UserResourceFields;
use HuseyinFiliz\TraderFeedback\Events\FeedbackCreated;
use HuseyinFiliz\TraderFeedback\Events\FeedbackUpdated;
use HuseyinFiliz\TraderFeedback\Listeners\AddUserPreferencesListener;
use HuseyinFiliz\TraderFeedback\Listeners\FeedbackCreatedListener;
use HuseyinFiliz\TraderFeedback\Listeners\FeedbackUpdatedListener;
use HuseyinFiliz\TraderFeedback\Listeners\PostLikeListener;
use HuseyinFiliz\TraderFeedback\Listeners\UserDeletedListener;
use HuseyinFiliz\TraderFeedback\Models\Feedback;
use HuseyinFiliz\TraderFeedback\Models\TraderStats;
use HuseyinFiliz\TraderFeedback\Models\UserLikeCount;
use HuseyinFiliz\TraderFeedback\Notifications\FeedbackApprovedBlueprint;
use HuseyinFiliz\TraderFeedback\Notifications\FeedbackRejectedBlueprint;
use HuseyinFiliz\TraderFeedback\Notifications\NewFeedbackBlueprint;

return [
    // Forum frontend assets + feedback profile route
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less')
        ->css(__DIR__ . '/resources/less/reviews.less')
        ->route('/u/{username}/feedbacks', 'user.feedbacks')
        ->route('/u/{username}/reviews', 'user.reviews')
        ->route('/reviews', 'reviews')
        ->route('/reviews/c/{id}', 'reviews.category')
        ->route('/reviews/p/{id}', 'reviews.product'),

    // Admin frontend assets
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/resources/less/admin.less'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    // Non-CRUD plain endpoints (aggregates / lookups)
    (new Extend\Routes('api'))
        ->get('/trader/stats/summary', 'trader.stats.summary', StatsSummaryController::class)
        ->get('/trader/stats/{id}', 'trader.stats.show', ShowTraderStatsController::class)
        ->get('/trader/discussions/{id}/participants', 'trader.discussions.participants', ListDiscussionParticipantsController::class),

    // Model relationships
    (new Extend\Model(User::class))
        ->hasMany('feedbacksReceived', Feedback::class, 'to_user_id')
        ->hasMany('feedbacksGiven', Feedback::class, 'from_user_id')
        ->hasOne('traderStats', TraderStats::class, 'user_id')
        ->hasOne('likeCount', UserLikeCount::class, 'user_id'),

    (new Extend\Model(Feedback::class))
        ->belongsTo('fromUser', User::class, 'from_user_id')
        ->belongsTo('toUser', User::class, 'to_user_id')
        ->belongsTo('approvedBy', User::class, 'approved_by_id'),

    // API resources (replace v1 ApiSerializer + ApiController)
    new Extend\ApiResource(FeedbackResource::class),
    new Extend\ApiResource(TraderStatsResource::class),
    new Extend\ApiResource(FeedbackReportResource::class),

    // Community Reviews resources
    new Extend\ApiResource(ProductResource::class),
    new Extend\ApiResource(ProductReviewResource::class),
    new Extend\ApiResource(ReviewCategoryResource::class),
    new Extend\ApiResource(ReviewFieldResource::class),
    new Extend\ApiResource(ReviewFieldRatingResource::class),
    new Extend\ApiResource(ReviewPhotoResource::class),
    new Extend\ApiResource(ReviewCommentResource::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(UserResourceFields::class)
        ->endpoint(
            [Endpoint\Index::class, Endpoint\Show::class],
            fn (Endpoint\Endpoint $endpoint) => $endpoint->eagerLoad(['traderStats', 'likeCount'])
        ),

    // Pré-carrega os stats do autor nos posts/discussões (badge de reputação)
    // — evita N+1 ao serializar os atributos computados de reputação do User.
    (new Extend\ApiResource(PostResource::class))
        ->endpoint(
            [Endpoint\Index::class, Endpoint\Show::class],
            fn (Endpoint\Endpoint $endpoint) => $endpoint->eagerLoad(['user.traderStats', 'user.likeCount'])
        ),

    (new Extend\ApiResource(DiscussionResource::class))
        ->endpoint(
            [Endpoint\Index::class, Endpoint\Show::class],
            fn (Endpoint\Endpoint $endpoint) => $endpoint->eagerLoad(['user.traderStats', 'user.likeCount'])
        ),

    // Notification preferences
    (new Extend\User())
        ->registerPreference('notify_newFeedback_alert', 'boolval', true)
        ->registerPreference('notify_feedbackApproved_alert', 'boolval', true)
        ->registerPreference('notify_feedbackRejected_alert', 'boolval', true),

    // Policies
    (new Extend\Policy())
        ->globalPolicy(GlobalPolicy::class)
        ->modelPolicy(Feedback::class, FeedbackPolicy::class),

    // Notification types
    (new Extend\Notification())
        ->type(NewFeedbackBlueprint::class, ['alert'])
        ->type(FeedbackApprovedBlueprint::class, ['alert'])
        ->type(FeedbackRejectedBlueprint::class, ['alert']),

    // Event listeners
    (new Extend\Event())
        ->listen(\Flarum\User\Event\Saving::class, AddUserPreferencesListener::class)
        ->listen(\Flarum\User\Event\Deleted::class, UserDeletedListener::class)
        ->listen(FeedbackCreated::class, FeedbackCreatedListener::class)
        ->listen(FeedbackUpdated::class, FeedbackUpdatedListener::class)
        ->listen(\Flarum\Likes\Event\PostWasLiked::class, PostLikeListener::class . '@liked')
        ->listen(\Flarum\Likes\Event\PostWasUnliked::class, PostLikeListener::class . '@unliked'),

    // Settings defaults + forum serialization
    (new Extend\Settings())
        ->default('huseyinfiliz.traderfeedback.requireApproval', false)
        ->default('huseyinfiliz.traderfeedback.allowNegative', true)
        ->default('huseyinfiliz.traderfeedback.requireDiscussion', false)
        ->default('huseyinfiliz.traderfeedback.onePerDiscussion', false)
        ->default('huseyinfiliz.traderfeedback.minLength', 10)
        ->default('huseyinfiliz.traderfeedback.maxLength', 1000)
        ->default('huseyinfiliz.traderfeedback.minDays', 0)
        ->default('huseyinfiliz.traderfeedback.minPosts', 0)

        ->default('huseyinfiliz.traderfeedback.showFeedbackInPostMenu', false)
        ->default('huseyinfiliz.traderfeedback.showFeedbackBelowReply', false)
        ->default('huseyinfiliz.traderfeedback.showFeedbackInPostFooter', false)
        ->default('huseyinfiliz.traderfeedback.footerOnlyFirstPost', false)
        ->default('huseyinfiliz.traderfeedback.feedbackActionTagFilter', '[]')
        ->default('huseyinfiliz.traderfeedback.feedbackOnlyWhenLocked', false)

        ->default('huseyinfiliz.traderfeedback.showBadgeInPosts', true)

        ->default('huseyinfiliz.traderfeedback.badgeCustomPrefix', '')
        ->default('huseyinfiliz.traderfeedback.badgeFormat', 'percentage')
        ->default('huseyinfiliz.traderfeedback.badgeCustomFormat', '{total} ({score}%) - {positive}P / {neutral}N / {negative}N')
        ->default('huseyinfiliz.traderfeedback.badgeTagFilter', '[]')
        ->default('huseyinfiliz.traderfeedback.badgeOnlyFirstPost', false)

        ->serializeToForum('huseyinfiliz.traderfeedback.requireApproval', 'huseyinfiliz.traderfeedback.requireApproval', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.allowNegative', 'huseyinfiliz.traderfeedback.allowNegative', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.requireDiscussion', 'huseyinfiliz.traderfeedback.requireDiscussion', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.onePerDiscussion', 'huseyinfiliz.traderfeedback.onePerDiscussion', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.minLength', 'huseyinfiliz.traderfeedback.minLength', 'intval')
        ->serializeToForum('huseyinfiliz.traderfeedback.maxLength', 'huseyinfiliz.traderfeedback.maxLength', 'intval')

        ->serializeToForum('huseyinfiliz.traderfeedback.showFeedbackInPostMenu', 'huseyinfiliz.traderfeedback.showFeedbackInPostMenu', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.showFeedbackBelowReply', 'huseyinfiliz.traderfeedback.showFeedbackBelowReply', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.showFeedbackInPostFooter', 'huseyinfiliz.traderfeedback.showFeedbackInPostFooter', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.footerOnlyFirstPost', 'huseyinfiliz.traderfeedback.footerOnlyFirstPost', 'boolval')
        ->serializeToForum('huseyinfiliz.traderfeedback.feedbackActionTagFilter', 'huseyinfiliz.traderfeedback.feedbackActionTagFilter')
        ->serializeToForum('huseyinfiliz.traderfeedback.feedbackOnlyWhenLocked', 'huseyinfiliz.traderfeedback.feedbackOnlyWhenLocked', 'boolval')

        ->serializeToForum('huseyinfiliz.traderfeedback.showBadgeInPosts', 'huseyinfiliz.traderfeedback.showBadgeInPosts', 'boolval')

        ->serializeToForum('huseyinfiliz.traderfeedback.badgeCustomPrefix', 'huseyinfiliz.traderfeedback.badgeCustomPrefix')
        ->serializeToForum('huseyinfiliz.traderfeedback.badgeFormat', 'huseyinfiliz.traderfeedback.badgeFormat')
        ->serializeToForum('huseyinfiliz.traderfeedback.badgeCustomFormat', 'huseyinfiliz.traderfeedback.badgeCustomFormat')
        ->serializeToForum('huseyinfiliz.traderfeedback.badgeTagFilter', 'huseyinfiliz.traderfeedback.badgeTagFilter')
        ->serializeToForum('huseyinfiliz.traderfeedback.badgeOnlyFirstPost', 'huseyinfiliz.traderfeedback.badgeOnlyFirstPost', 'boolval'),
];
