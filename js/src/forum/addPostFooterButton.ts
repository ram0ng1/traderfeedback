import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';
import Button from 'flarum/common/components/Button';
import FeedbackModal from './modals/FeedbackModal';

export default function addPostFooterButton() {
  // Post footer'a (sol alt) "Feedback Ver" butonu ekle
  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;
    const user = post.user();
    
    // Kendine feedback veremez
    if (!app.session.user || app.session.user.id() === user?.id()) {
      return;
    }

    // Ayar kontrolü
    if (!app.forum.attribute('huseyinfiliz.traderfeedback.showFeedbackInPostFooter')) {
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
      'giveFeedbackFooter',
      Button.component(
        {
          className: 'Button Button--link',
          icon: 'fas fa-exchange-alt',
          onclick: () => {
            app.modal.show(FeedbackModal, {
              user: user,
              discussionUrl: window.location.href,
              autoFillDiscussion: true,
            });
          },
        },
        app.translator.trans('huseyinfiliz-traderfeedback.forum.post_actions.give_feedback_short')
      ),
      -1  // Negatif değer ile en sola (like'ın soluna) yerleştir
    );
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