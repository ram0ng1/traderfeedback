import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import FeedbackModal from './modals/FeedbackModal';

declare const flarum: any;

export default function addUserControls() {
    // Flarum 2: UserControls is a lazy-loaded util OBJECT (not a class), so the
    // string-form of extend() — which applies to `module.prototype` — doesn't fit.
    // Register an onLoad on the registry and extend the object directly when the
    // chunk loads.
    const [ns, id] = flarum.reg.namespaceAndIdFromPath('flarum/forum/utils/UserControls');
    flarum.reg.onLoad(ns, id, (UserControls: any) => {
        extend(UserControls, 'userControls', (items: any, user: any) => {
            // Kendine feedback veremez
            if (app.session.user && app.session.user.id() !== user.id()) {
                items.add('giveFeedback',
                    Button.component({
                        icon: 'fas fa-exchange-alt',
                        onclick() {
                            app.modal.show(FeedbackModal, {
                                user: user,
                            });
                        },
                    }, app.translator.trans('huseyinfiliz-traderfeedback.forum.form.submit_button')),
                    100
                );
            }
        });
    });
}
