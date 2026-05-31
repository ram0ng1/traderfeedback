import app from 'flarum/admin/app';
import TraderFeedbackSettingsPage from './TraderFeedbackSettingsPage';
import { registerReviewModels } from '../forum/reviews/models';

app.initializers.add('huseyinfiliz-traderfeedback', () => {
  registerReviewModels(app.store);

  // Flarum 2: `app.extensionData` was renamed to `app.registry` (AdminRegistry).
  // registerPermission's 3rd arg is now a numeric priority (not a boolean).
  app.registry
    .for('huseyinfiliz-traderfeedback')
    .registerPage(TraderFeedbackSettingsPage)
    .registerPermission({
      icon: 'fas fa-comment',
      label: app.translator.trans('huseyinfiliz-traderfeedback.admin.permissions.give_feedback'),
      permission: 'huseyinfiliz-traderfeedback.give'
    }, 'reply')
    .registerPermission({
      icon: 'fas fa-flag',
      label: app.translator.trans('huseyinfiliz-traderfeedback.admin.permissions.report_feedback'),
      permission: 'huseyinfiliz-traderfeedback.report'
    }, 'reply')
    .registerPermission({
      icon: 'fas fa-trash',
      label: app.translator.trans('huseyinfiliz-traderfeedback.admin.permissions.delete_feedback'),
      permission: 'huseyinfiliz-traderfeedback.delete'
    }, 'moderate')
    .registerPermission({
      icon: 'fas fa-shield-alt',
      label: app.translator.trans('huseyinfiliz-traderfeedback.admin.permissions.moderate_feedback'),
      permission: 'huseyinfiliz-traderfeedback.moderate'
    }, 'moderate');
});