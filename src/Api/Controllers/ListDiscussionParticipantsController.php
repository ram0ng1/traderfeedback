<?php

namespace HuseyinFiliz\TraderFeedback\Api\Controllers;

use Flarum\Api\Controller\AbstractListController;
use HuseyinFiliz\TraderFeedback\Api\Serializers\MinimalUserSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListDiscussionParticipantsController extends AbstractListController
{
    public $serializer = MinimalUserSerializer::class;
    public $limit = 10000;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        
        // ✅ /trader/ prefix ile URI parse
        $path = $request->getUri()->getPath();
        
        if (preg_match('/\/trader\/discussions\/(\d+)\/participants/', $path, $matches)) {
            $discussionId = $matches[1];
        } else {
            throw new \Exception('Could not extract discussion ID from path: ' . $path);
        }

        // Discussion'ı bul
        $discussion = Discussion::query()
            ->where('id', $discussionId)
            ->firstOrFail();

        // Permission kontrolü
        $actor->assertCan('view', $discussion);

        // Participants'ı al
        $participants = $discussion->participants()
            ->where('users.id', '!=', $actor->id)
            ->limit($this->limit)
            ->get();

        return $participants;
    }
}