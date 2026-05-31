import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

/**
 * Aba admin do Community Reviews: gerência de produtos (listar/buscar/editar/
 * excluir) e de categorias + campos de nota.
 */
export default class ReviewsManageTab extends Component {
  categories: any[] = [];
  products: any[] = [];
  loading = true;
  loadingProducts = true;
  moreProducts = false;
  offset = 0;
  search = '';
  searchTimer: any = null;
  newCategory = '';
  newField: Record<number, string> = {};
  editCat: Record<number, string> = {};
  editField: Record<number, string> = {};

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadCategories();
    this.loadProducts(true);
  }

  loadCategories() {
    app.store.find('tfb-review-categories').then((res: any) => {
      this.categories = Array.from(res);
      this.loading = false;
      m.redraw();
    });
  }

  loadProducts(reset = false) {
    if (reset) {
      this.offset = 0;
      this.products = [];
    }
    this.loadingProducts = true;
    const params: any = { sortBy: 'newest', page: { offset: this.offset, limit: 20 } };
    if (this.search.trim()) params.searchQuery = this.search.trim();

    app.store.find('tfb-products', params).then((res: any) => {
      this.moreProducts = !!res.payload?.links?.next;
      this.products = reset ? Array.from(res) : this.products.concat(Array.from(res));
      this.loadingProducts = false;
      m.redraw();
    });
  }

  view() {
    if (this.loading) return <LoadingIndicator />;

    return (
      <div className="ReviewsManageTab">
        <h3>{app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.products_heading')}</h3>
        <div className="Form-group">
          <input
            className="FormControl"
            placeholder={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.search_products')}
            value={this.search}
            oninput={(e: any) => {
              this.search = e.target.value;
              clearTimeout(this.searchTimer);
              this.searchTimer = setTimeout(() => this.loadProducts(true), 350);
            }}
          />
        </div>

        {this.loadingProducts && this.products.length === 0 ? (
          <LoadingIndicator />
        ) : (
          <table className="ReviewsManage-products">
            <thead>
              <tr>
                <th>{app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.col_name')}</th>
                <th>{app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.col_category')}</th>
                <th>★</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {this.products.map((p: any) => (
                <tr key={p.id()}>
                  <td>
                    <input className="FormControl FormControl--small" value={p.data.attributes.name} oninput={(e: any) => (p.data.attributes.name = e.target.value)} />
                  </td>
                  <td>
                    <select
                      className="FormControl FormControl--small"
                      value={String(p.categoryId())}
                      onchange={(e: any) => (p.data.attributes.categoryId = parseInt(e.target.value))}
                    >
                      {this.categories.map((c) => (
                        <option value={String(c.id())} key={c.id()}>
                          {c.name()}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td>
                    {Number(p.cachedRating()).toFixed(2)} ({p.reviewCount()})
                  </td>
                  <td className="ReviewsManage-rowActions">
                    <Button className="Button Button--small" onclick={() => this.saveProduct(p)}>
                      {app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.save')}
                    </Button>
                    <Button className="Button Button--icon Button--danger" icon="fas fa-trash" onclick={() => this.deleteProduct(p)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {this.moreProducts && (
          <Button className="Button" loading={this.loadingProducts} onclick={() => { this.offset += 20; this.loadProducts(false); }}>
            {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_more')}
          </Button>
        )}

        <div className="ReviewsManage-catsHeader">
          <h3>
            <i className="fas fa-layer-group"></i> {app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.categories_heading')}
          </h3>
          <div className="ReviewsManage-addCat">
            <input
              className="FormControl"
              placeholder={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.new_category')}
              value={this.newCategory}
              oninput={(e: any) => (this.newCategory = e.target.value)}
              onkeydown={(e: any) => { if (e.key === 'Enter' && this.newCategory.trim()) this.addCategory(); }}
            />
            <Button className="Button Button--primary" icon="fas fa-plus" disabled={!this.newCategory.trim()} onclick={() => this.addCategory()}>
              {app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.add_category')}
            </Button>
          </div>
        </div>

        <div className="ReviewsManage-catGrid">
        {this.categories.map((cat: any) => (
          <div className="ReviewsManage-cat" key={cat.id()}>
            <div className="ReviewsManage-catHead">
              <input
                className="ReviewsManage-catNameInput"
                value={this.editCat[cat.id()] ?? cat.name()}
                oninput={(e: any) => (this.editCat[cat.id()] = e.target.value)}
                onblur={() => this.saveCategory(cat)}
                onkeydown={(e: any) => { if (e.key === 'Enter') e.target.blur(); }}
                title={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.edit_name')}
              />
              <span className="ReviewsManage-count" title={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.products_heading')}>
                <i className="fas fa-box"></i> {cat.productCount()}
              </span>
              <Button className="Button Button--icon Button--flat ReviewsManage-catDel" icon="fas fa-trash" onclick={() => this.deleteCategory(cat)} />
            </div>
            <div className="ReviewsManage-fields">
              {(cat.fields() || []).filter(Boolean).length === 0 && (
                <span className="ReviewsManage-noFields">{app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.no_fields')}</span>
              )}
              {(cat.fields() || []).filter(Boolean).map((f: any) => (
                <span className="ReviewsManage-field" key={f.id()}>
                  <input
                    className="ReviewsManage-fieldNameInput"
                    value={this.editField[f.id()] ?? f.name()}
                    oninput={(e: any) => (this.editField[f.id()] = e.target.value)}
                    onblur={() => this.saveField(f)}
                    onkeydown={(e: any) => { if (e.key === 'Enter') e.target.blur(); }}
                  />
                  <a className="ReviewsManage-fieldDel" onclick={() => this.deleteField(f)} title={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.delete')}>
                    <i className="fas fa-times"></i>
                  </a>
                </span>
              ))}
            </div>
            <div className="ReviewsManage-addField">
              <input
                className="FormControl FormControl--small"
                placeholder={app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.new_field')}
                value={this.newField[cat.id()] || ''}
                oninput={(e: any) => (this.newField[cat.id()] = e.target.value)}
                onkeydown={(e: any) => { if (e.key === 'Enter' && (this.newField[cat.id()] || '').trim()) this.addField(cat); }}
              />
              <Button className="Button Button--small" icon="fas fa-plus" disabled={!(this.newField[cat.id()] || '').trim()} onclick={() => this.addField(cat)}>
                {app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.add_field')}
              </Button>
            </div>
          </div>
        ))}
        </div>
      </div>
    );
  }

  saveProduct(p: any) {
    p.save({ name: p.data.attributes.name, categoryId: p.categoryId() })
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.saved')))
      .catch((e: any) => app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || 'Error'));
  }

  deleteProduct(p: any) {
    if (!confirm(app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.confirm_delete_product'))) return;
    p.delete().then(() => {
      this.products = this.products.filter((x) => x.id() !== p.id());
      m.redraw();
    });
  }

  addCategory() {
    app.store.createRecord('tfb-review-categories').save({ name: this.newCategory.trim(), position: 0 }).then(() => {
      this.newCategory = '';
      this.loadCategories();
    }).catch((e: any) => app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || 'Error'));
  }

  deleteCategory(cat: any) {
    if (!confirm(app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.confirm_delete_category'))) return;
    cat.delete().then(() => this.loadCategories());
  }

  addField(cat: any) {
    app.store.createRecord('tfb-review-fields').save({ name: (this.newField[cat.id()] || '').trim(), categoryId: parseInt(cat.id()), position: 0 }).then(() => {
      this.newField[cat.id()] = '';
      this.loadCategories();
    }).catch((e: any) => app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || 'Error'));
  }

  saveCategory(cat: any) {
    const id = cat.id();
    const name = (this.editCat[id] ?? '').trim();
    if (this.editCat[id] === undefined || name === '' || name === cat.name()) {
      delete this.editCat[id];
      return;
    }
    cat
      .save({ name })
      .then(() => {
        delete this.editCat[id];
        app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-traderfeedback.admin.reviews.saved'));
        m.redraw();
      })
      .catch((e: any) => {
        delete this.editCat[id];
        app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || 'Error');
        m.redraw();
      });
  }

  saveField(field: any) {
    const id = field.id();
    const name = (this.editField[id] ?? '').trim();
    if (this.editField[id] === undefined || name === '' || name === field.name()) {
      delete this.editField[id];
      return;
    }
    field
      .save({ name })
      .then(() => {
        delete this.editField[id];
        m.redraw();
      })
      .catch((e: any) => {
        delete this.editField[id];
        app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || 'Error');
        m.redraw();
      });
  }

  deleteField(field: any) {
    field.delete().then(() => this.loadCategories());
  }
}
