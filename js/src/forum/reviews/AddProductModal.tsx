import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

/**
 * Cadastro de um novo produto (nome + categoria). Ao salvar, redireciona para a
 * página do produto para o usuário adicionar o primeiro review.
 */
export default class AddProductModal extends Modal {
  name = Stream('');
  categoryId = Stream('');
  categories: any[] = [];

  oninit(vnode: any) {
    super.oninit(vnode);
    app.store.find('tfb-review-categories').then((res: any) => {
      this.categories = res;
      if (res[0]) this.categoryId(String(res[0].id()));
      m.redraw();
    });
  }

  className() {
    return 'Modal--small AddProductModal';
  }

  title() {
    return app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.add_product');
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
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.name().trim() || !this.categoryId()}>
              {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.create_product_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: any) {
    e.preventDefault();
    this.loading = true;

    app.store
      .createRecord('tfb-products')
      .save({ name: this.name().trim(), categoryId: parseInt(this.categoryId()) })
      .then((product: any) => {
        this.hide();
        m.route.set(app.route('reviews.product', { id: product.id() }));
      })
      .catch((err: any) => {
        this.loading = false;
        this.onerror(err);
      });
  }
}
