import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import StarRating from './StarRating';

/**
 * Aba "Reviews" do perfil: lista os reviews de produto escritos pelo usuário
 * (espelha a aba User reviews do MyBB).
 */
export default class UserReviewsPage extends UserPage {
  reviews: any[] = [];
  loading = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: any) {
    super.show(user);
    app.store
      .find('tfb-product-reviews', { forUser: user.id(), page: { limit: 50 } })
      .then((res: any) => {
        this.reviews = Array.from(res);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  content() {
    if (this.loading) {
      return (
        <div className="UserReviews">
          <LoadingIndicator />
        </div>
      );
    }

    if (this.reviews.length === 0) {
      return (
        <div className="UserReviews">
          <p className="muted">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.user_no_reviews')}</p>
        </div>
      );
    }

    return (
      <div className="UserReviews">
        {this.reviews.map((r: any) => {
          const product = r.product();
          return (
            <div className="UserReviewRow" key={r.id()}>
              <div className="UserReviewRow-head">
                {product ? (
                  <Link href={app.route('reviews.product', { id: product.id() })} className="UserReviewRow-product">
                    {product.name()}
                  </Link>
                ) : (
                  <span className="UserReviewRow-product">—</span>
                )}
                <StarRating rating={r.rating()} />
                <span className="UserReviewRow-date">{humanTime(r.createdAt())}</span>
              </div>
              <div className="UserReviewRow-comment">{r.comment()}</div>
            </div>
          );
        })}
      </div>
    );
  }
}
