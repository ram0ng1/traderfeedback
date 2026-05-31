import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

/**
 * Edita um produto existente (nome + categoria). attrs: { product, oncreated }.
 */
export default class EditProductModal extends Modal {
  name = Stream('');
  categoryId = Stream('');
  categories: any[] = [];

  oninit(vnode: any) {
    super.oninit(vnode);
    const p = this.attrs.product;
    this.name(p.name());
    this.categoryId(String(p.categoryId()));
    app.store.find('tfb-review-categories').then((res: any) => {
      this.categories = res;
      m.redraw();
    });
  }

  className() {
    return 'Modal--small EditProductModal';
  }

  title() {
    return app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.edit_product');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.product_name')}</label>
            <input className="FormControl" value={this.name()} oninput={(e: any) => this.name(e.target.value)} maxlength={255} />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.category')}</label>
            <select className="FormControl" value={this.categoryId()} onchange={(e: any) => this.categoryId(e.target.value)}>
              {this.categories.map((c) => (
                <option value={String(c.id())} key={c.id()}>
                  {c.name()}
                </option>
              ))}
            </select>
          </div>
          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.name().trim()}>
              {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.save')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: any) {
    e.preventDefault();
    this.loading = true;
    this.attrs.product
      .save({ name: this.name().trim(), categoryId: parseInt(this.categoryId()) })
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
