import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';
import StarRating from './StarRating';
import AddReviewModal from './AddReviewModal';
import EditProductModal from './EditProductModal';

/**
 * Página de um produto: resumo (nota média), lista de reviews (autor, data,
 * estrelas geral, preço/url, fotos, notas por campo, texto) e comentários.
 */
export default class ProductPage extends Page {
  product: any = null;
  comments: any[] = [];
  loading = true;
  newComment = '';
  postingComment = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    const id = m.route.param('id');
    this.loading = true;

    app.store
      .find('tfb-products', id)
      .then((p: any) => {
        this.product = p;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });

    app.store
      .find('tfb-review-comments', { forProduct: id, page: { limit: 100 } })
      .then((res: any) => {
        this.comments = Array.from(res);
        m.redraw();
      })
      .catch(() => {});
  }

  view() {
    if (this.loading) {
      return (
        <div className="ProductPage">
          <div className="container">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (!this.product) {
      return (
        <div className="ProductPage">
          <div className="container">
            <p>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.product_not_found')}</p>
          </div>
        </div>
      );
    }

    const p = this.product;
    const reviews = (p.reviews() || []).filter(Boolean);

    return (
      <div className="ProductPage">
        <div className="container">
          <div className="ProductPage-breadcrumb">
            <Link href={app.route('reviews')}>
              <i className="fas fa-arrow-left"></i> {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.title')}
            </Link>
          </div>

          <div className="ProductSummary">
            <h2 className="ProductSummary-name">
              {p.name()}
              {p.canManage() && (
                <span className="ProductSummary-manage">
                  <Button className="Button Button--icon" icon="fas fa-pen" title={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.edit_product')}
                    onclick={() => app.modal.show(EditProductModal, { product: p, oncreated: () => this.load() })} />
                  <Button className="Button Button--icon Button--danger" icon="fas fa-trash" title={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.delete_product')}
                    onclick={() => this.deleteProduct(p)} />
                </span>
              )}
            </h2>
            <div className="ProductSummary-rating">
              <StarRating rating={p.cachedRating()} />
              <span className="ProductSummary-score">{Number(p.cachedRating()).toFixed(2)}</span>
              <span className="ProductSummary-meta">
                <i className="fas fa-comment"></i> {p.reviewCount()} &nbsp; <i className="fas fa-eye"></i> {p.views()}
              </span>
              {app.session.user && (
                <Button
                  className="Button Button--primary ProductSummary-addReview"
                  icon="fas fa-plus"
                  onclick={() => app.modal.show(AddReviewModal, { product: p, oncreated: () => this.load() })}
                >
                  {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.add_review')}
                </Button>
              )}
            </div>
          </div>

          <div className="ProductReviews">
            {reviews.length === 0 ? (
              <p className="ProductReviews-empty">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.no_reviews')}</p>
            ) : (
              reviews.map((r: any) => this.reviewItem(r))
            )}
          </div>

          <div className="ProductComments">
            <h3>
              <i className="fas fa-comments"></i> {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.comments')} ({this.comments.length})
            </h3>
            {this.comments.map((c: any) => this.commentItem(c))}

            {app.session.user && (
              <form className="ProductComments-form" onsubmit={(e: any) => this.postComment(e)}>
                <textarea
                  className="FormControl"
                  rows="2"
                  value={this.newComment}
                  placeholder={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.comment_placeholder')}
                  oninput={(e: any) => (this.newComment = e.target.value)}
                />
                <Button type="submit" className="Button Button--primary" loading={this.postingComment} disabled={!this.newComment.trim()}>
                  {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.post_comment')}
                </Button>
              </form>
            )}
          </div>
        </div>
      </div>
    );
  }

  reviewItem(r: any) {
    const user = r.user();
    const merchant = r.merchant();
    const photos = (r.photos() || []).filter(Boolean);
    const fieldRatings = (r.fieldRatings() || []).filter(Boolean);

    return (
      <div className="ReviewItem" key={r.id()}>
        <div className="ReviewItem-header">
          <div className="ReviewItem-author">
            {user ? (
              <Link className="ReviewItem-userLink" href={app.route('user', { username: user.slug() })}>
                {Avatar.component({ user })}
                <span className="ReviewItem-username">{username(user)}</span>
              </Link>
            ) : (
              <span className="ReviewItem-username">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.unknown_user')}</span>
            )}
            <span className="ReviewItem-date">{humanTime(r.createdAt())}</span>
          </div>
          <StarRating rating={r.rating()} />
        </div>

        <div className="ReviewItem-info">
          {r.price() && (
            <span className="ReviewItem-price">
              <strong>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.price')}</strong> {r.price()}
            </span>
          )}
          {r.url() && (
            <a className="ReviewItem-url" href={this.safeUrl(r.url())} target="_blank" rel="noopener noreferrer">
              <i className="fas fa-external-link-alt"></i> {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.product_url')}
            </a>
          )}
          {merchant && (
            <span className="ReviewItem-merchant">
              <i className="fas fa-store"></i> {username(merchant)}
            </span>
          )}
        </div>

        {photos.length > 0 && (
          <div className="ReviewItem-photos">
            {photos.map((ph: any) => (
              <a key={ph.id()} href={ph.url()} target="_blank" rel="noopener noreferrer">
                <img src={ph.thumbnailUrl() || ph.url()} alt="" loading="lazy" />
              </a>
            ))}
          </div>
        )}

        {fieldRatings.length > 0 && (
          <div className="ReviewItem-fields">
            {fieldRatings.map((fr: any) => {
              const field = fr.field();
              return (
                <div className="ReviewField" key={fr.id()}>
                  <div className="ReviewField-head">
                    <span className="ReviewField-name">{field ? field.name() : '—'}</span>
                    <StarRating rating={fr.rating()} />
                  </div>
                  {fr.comment() && <div className="ReviewField-comment">{fr.comment()}</div>}
                </div>
              );
            })}
          </div>
        )}

        <div className="ReviewItem-comment">{r.comment()}</div>

        {r.canDelete() && (
          <div className="ReviewItem-actions">
            <Button className="Button Button--link Button--danger" icon="fas fa-trash" onclick={() => this.deleteReview(r)}>
              {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.delete_review')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  deleteReview(review: any) {
    if (!confirm(app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.confirm_delete_review'))) return;

    review
      .delete()
      .then(() => {
        this.load();
        app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.review_deleted'));
      })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_error'));
      });
  }

  commentItem(c: any) {
    const user = c.user();
    return (
      <div className="ReviewComment" key={c.id()}>
        <div className="ReviewComment-header">
          {user ? (
            <Link className="ReviewComment-userLink" href={app.route('user', { username: user.slug() })}>
              {Avatar.component({ user })}
              <span className="ReviewComment-username">{username(user)}</span>
            </Link>
          ) : (
            <span className="ReviewComment-username">—</span>
          )}
          <span className="ReviewComment-date">{humanTime(c.createdAt())}</span>
        </div>
        <div className="ReviewComment-body">{c.comment()}</div>
      </div>
    );
  }

  deleteProduct(product: any) {
    if (!confirm(app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.confirm_delete_product'))) return;
    product
      .delete()
      .then(() => {
        m.route.set(app.route('reviews'));
      })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_error'));
      });
  }

  postComment(e: any) {
    e.preventDefault();
    if (!this.newComment.trim()) return;
    this.postingComment = true;

    app.store
      .createRecord('tfb-review-comments')
      .save({ productId: parseInt(this.product.id()), comment: this.newComment.trim() })
      .then((c: any) => {
        this.comments.push(c);
        this.newComment = '';
        this.postingComment = false;
        m.redraw();
      })
      .catch((err: any) => {
        this.postingComment = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_error'));
        m.redraw();
      });
  }

  safeUrl(raw: string): string {
    return /^https?:\/\//i.test(raw) ? raw : '';
  }
}
