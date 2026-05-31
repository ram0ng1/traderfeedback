import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import FeedbackModal from './modals/FeedbackModal';

declare const flarum: any;

export default function addPostControls() {
  // Flarum 2: PostControls is a lazy-loaded util OBJECT (not a class) — extend it
  // directly via the registry onLoad (string-form extend targets `.prototype`,
  // which a plain object doesn't have).
  const [ns, id] = flarum.reg.namespaceAndIdFromPath('flarum/forum/utils/PostControls');
  flarum.reg.onLoad(ns, id, (PostControls: any) => {
  extend(PostControls, 'userControls', function (items: any, post: any) {
    const user = post.user();
    
    // Kendine feedback veremez
    if (!app.session.user || app.session.user.id() === user?.id()) {
      return;
    }

    // Ayar kontrolü
    if (!app.forum.attribute('huseyinfiliz.traderfeedback.showFeedbackInPostMenu')) {
      return;
    }

    // Tag filter kontrolü
    if (!shouldShowInDiscussion(post.discussion())) {
      return;
    }

    // Lock kontrolü
    if (!shouldShowWhenLocked(post.discussion())) {
      return;
    }

    items.add(
      'giveFeedback',
      Button.component(
        {
          icon: 'fas fa-exchange-alt',
          onclick: () => {
            app.modal.show(FeedbackModal, {
              user: user,
              discussionId: post.discussion().id(),
              autoFillDiscussion: true,
            });
          },
        },
        app.translator.trans('huseyinfiliz-traderfeedback.forum.post_actions.give_feedback')
      ),
      10 // Pozitif priority - user controls'da
    );
  });
  });
}

// Tag filter kontrolü
function shouldShowInDiscussion(discussion: any): boolean {
  const tagFilterJson = app.forum.attribute('huseyinfiliz.traderfeedback.feedbackActionTagFilter') || '[]';
  let allowedTags: string[] = [];
  
  try {
    allowedTags = JSON.parse(tagFilterJson);
  } catch (e) {
    allowedTags = [];
  }

  if (allowedTags.length === 0) {
    return true;
  }

  if (!discussion) return false;
  
  const discussionTags = discussion.tags ? discussion.tags() : [];
  if (!discussionTags || discussionTags.length === 0) return false;

  return discussionTags.some((tag: any) => 
    allowedTags.includes(tag.id())
  );
}

// Lock kontrolü
function shouldShowWhenLocked(discussion: any): boolean {
  const onlyWhenLocked = app.forum.attribute('huseyinfiliz.traderfeedback.feedbackOnlyWhenLocked');
  
  if (!onlyWhenLocked) {
    return true;
  }

  if (!discussion) return false;
  
  return discussion.isLocked && discussion.isLocked();
}