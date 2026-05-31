import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import LinkButton from 'flarum/common/components/LinkButton';

/**
 * Adiciona o link "Reviews" à navegação lateral do índice (IndexSidebar,
 * lazy chunk → extend por string).
 */
export default function addReviewsNav() {
  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items: any) {
    items.add(
      'reviews',
      LinkButton.component(
        { href: app.route('reviews'), icon: 'fas fa-star' },
        app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.nav_link')
      ),
      50
    );
  });
}
