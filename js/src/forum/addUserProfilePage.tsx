import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import app from 'flarum/forum/app';
import TraderFeedbackPage from './Pages/ProfilePage';
import type ItemList from 'flarum/common/utils/ItemList';

export default function addUserProfilePage() {
  app.routes['user.feedbacks'] = {
    path: '/u/:username/feedbacks',
    component: TraderFeedbackPage
  };

  // Flarum 2: core components are lazy-loaded chunks — extend() by module path
  // string (not Component.prototype) so the patch applies when the chunk loads.
  extend('flarum/forum/components/UserPage', 'navItems', function (items: ItemList<any>) {
      items.add(
        'traderFeedbacksLink',
        <LinkButton href={app.route('user.feedbacks', { username: this.user?.slug() })} name="feedbacks" icon="fas fa-exchange-alt">
			{app.translator.trans('huseyinfiliz-traderfeedback.forum.nav.feedback_link')}
		</LinkButton>,
        79,
      );

      items.add(
        'userReviewsLink',
        <LinkButton href={app.route('user.reviews', { username: this.user?.slug() })} name="reviews" icon="fas fa-star">
			{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.nav_link')}
		</LinkButton>,
        78,
      );
  });
  
  // NotificationGrid'e feedback notification tiplerini ekle
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function(items) {
    items.add('newFeedback', {
      name: 'newFeedback',
      icon: 'fas fa-exchange-alt',
      label: app.translator.trans('huseyinfiliz-traderfeedback.forum.settings.notify_new_feedback_label')
    });
    
    items.add('feedbackApproved', {
      name: 'feedbackApproved',
      icon: 'fas fa-check-circle',
      label: app.translator.trans('huseyinfiliz-traderfeedback.forum.settings.notify_feedback_approved_label')
    });
    
    items.add('feedbackRejected', {
      name: 'feedbackRejected',
      icon: 'fas fa-times-circle',
      label: app.translator.trans('huseyinfiliz-traderfeedback.forum.settings.notify_feedback_rejected_label')
    });
  });
}