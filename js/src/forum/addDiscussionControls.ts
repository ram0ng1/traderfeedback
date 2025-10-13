import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import Button from 'flarum/common/components/Button';
import FeedbackModal from './modals/FeedbackModal';

export default function addDiscussionControls() {
  extend(DiscussionPage.prototype, 'sidebarItems', function (items) {
    const discussion = this.discussion;

    // Ayar kontrolü
    if (!app.forum.attribute('huseyinfiliz.traderfeedback.showFeedbackBelowReply')) {
      return;
    }

    // Kullanıcı giriş yapmış mı?
    if (!app.session.user) {
      return;
    }

    // Tag filter kontrolü
    if (!shouldShowInDiscussion(discussion)) {
      return;
    }

    // Lock kontrolü
    if (!shouldShowWhenLocked(discussion)) {
      return;
    }

    // Konu sahibi mi kontrolü - konu sahibi ise kullanıcı seçici göster
    const isDiscussionOwner = app.session.user.id() === discussion?.user()?.id();

    items.add(
      'giveFeedbackButton',
      Button.component(
        {
          className: 'Button Button--primary',
          icon: 'fas fa-exchange-alt',
          onclick: () => {
            if (isDiscussionOwner) {
              // Konu sahibi ise: Tartışmadaki kullanıcıları göster
              showUserSelectorModal(discussion);
            } else {
              // Normal kullanıcı ise: Direkt konu sahibine feedback modal aç
              app.modal.show(FeedbackModal, {
                user: discussion.user(),
                discussionUrl: window.location.href,
                autoFillDiscussion: true,
              });
            }
          },
        },
        app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.give_feedback')
      ),
      99
    );
  });
}

// Kullanıcı seçici modal'ı göster
function showUserSelectorModal(discussion: any) {
  // Tartışmadaki unique kullanıcıları topla
  const posts = discussion.posts();
  const uniqueUsers = new Map();
  const currentUserId = app.session.user?.id();

  if (posts) {
    posts.forEach((post: any) => {
      const postUser = post.user();
      if (postUser && postUser.id() !== currentUserId) {
        uniqueUsers.set(postUser.id(), postUser);
      }
    });
  }

  const userArray = Array.from(uniqueUsers.values());

  if (userArray.length === 0) {
    app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.no_users_found'));
    return;
  }

  // Eğer sadece 1 kullanıcı varsa, direkt feedback modal'ını aç
  if (userArray.length === 1) {
    app.modal.show(FeedbackModal, {
      user: userArray[0],
      discussionUrl: window.location.href,
      autoFillDiscussion: true,
    });
    return;
  }

  // Birden fazla kullanıcı varsa, kullanıcı seçim listesi göster
  // Basit bir alert ile kullanıcı listesini göster ve seçim yap
  const userList = userArray.map((user: any, index: number) => 
    `${index + 1}. ${user.displayName()}`
  ).join('\n');
  
  const message = app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.select_user_prompt') + '\n\n' + userList;
  const selection = prompt(message, '1');
  
  if (selection) {
    const index = parseInt(selection) - 1;
    if (index >= 0 && index < userArray.length) {
      app.modal.show(FeedbackModal, {
        user: userArray[index],
        discussionUrl: window.location.href,
        autoFillDiscussion: true,
      });
    }
  }
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