<?php

namespace HuseyinFiliz\TraderFeedback\Notifications;

use Flarum\Database\AbstractModel;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use HuseyinFiliz\TraderFeedback\Models\Feedback;

class NewFeedbackBlueprint implements BlueprintInterface
{
    public function __construct(
        public Feedback $feedback,
    ) {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->feedback;
    }

    public function getFromUser(): ?User
    {
        return User::find($this->feedback->from_user_id);
    }

    public function getData(): mixed
    {
        return [
            'feedbackId' => $this->feedback->id,
            'feedbackType' => $this->feedback->type,
            'role' => $this->feedback->role,
            'comment' => mb_substr((string) $this->feedback->comment, 0, 50) . '...',
        ];
    }

    public static function getType(): string
    {
        return 'newFeedback';
    }

    public static function getSubjectModel(): string
    {
        return Feedback::class;
    }
}
