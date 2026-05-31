import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import StarInput from './StarInput';

/**
 * Formulário de novo review de um produto: nota por campo (estrelas) +
 * comentário por campo + preço/URL + fotos (URLs) + texto geral.
 *
 * attrs: { product } (modelo tfb-products com categoryId).
 */
export default class AddReviewModal extends Modal {
  fields: any[] = [];
  loadingFields = true;
  ratings: Record<number, number> = {};
  fieldComments: Record<number, string> = {};
  price = Stream('');
  url = Stream('');
  photos = Stream('');
  comment = Stream('');

  oninit(vnode: any) {
    super.oninit(vnode);
    const product = this.attrs.product;
    app.store
      .find('tfb-review-categories', String(product.categoryId()))
      .then((cat: any) => {
        this.fields = (cat.fields() || []).filter(Boolean);
        this.loadingFields = false;
        m.redraw();
      })
      .catch(() => {
        this.loadingFields = false;
        m.redraw();
      });
  }

  className() {
    return 'Modal--medium AddReviewModal';
  }

  title() {
    return app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.add_review_to', {
      product: this.attrs.product.name(),
    });
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.loadingFields ? (
            <LoadingIndicator />
          ) : (
            this.fields.map((f) => (
              <div className="Form-group AddReview-field" key={f.id()}>
                <label>
                  {f.name()} <StarInput value={this.ratings[f.id()] || 0} onchange={(v) => (this.ratings[f.id()] = v)} />
                </label>
                <input
                  className="FormControl"
                  placeholder={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.field_comment_placeholder')}
                  oninput={(e: any) => (this.fieldComments[f.id()] = e.target.value)}
                />
              </div>
            ))
          )}

          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.price')}</label>
            <input className="FormControl" value={this.price()} oninput={(e: any) => this.price(e.target.value)} maxlength={30} />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.product_url')}</label>
            <input className="FormControl" value={this.url()} oninput={(e: any) => this.url(e.target.value)} placeholder="https://..." />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.photos_label')}</label>
            <textarea
              className="FormControl"
              rows="2"
              value={this.photos()}
              oninput={(e: any) => this.photos(e.target.value)}
              placeholder={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.photos_placeholder')}
            />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.review_comment_label')}</label>
            <textarea className="FormControl" rows="5" value={this.comment()} oninput={(e: any) => this.comment(e.target.value)} />
          </div>
          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.comment().trim()}>
              {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.submit_review')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: any) {
    e.preventDefault();
    this.loading = true;

    const fields = Object.keys(this.ratings)
      .map((k) => parseInt(k))
      .filter((id) => this.ratings[id] > 0)
      .map((id) => ({ fieldId: id, rating: this.ratings[id], comment: this.fieldComments[id] || '' }));

    const photos = this.photos()
      .split(/\s*\n\s*/)
      .map((s: string) => s.trim())
      .filter((s: string) => s !== '');

    app.store
      .createRecord('tfb-product-reviews')
      .save({
        productId: parseInt(this.attrs.product.id()),
        comment: this.comment().trim(),
        price: this.price().trim(),
        url: this.url().trim(),
        fields,
        photos,
      })
      .then(() => {
        this.hide();
        if (this.attrs.oncreated) this.attrs.oncreated();
      })
      .catch((err: any) => {
        this.loading = false;
        this.onerror(err);
      });
  }
}
