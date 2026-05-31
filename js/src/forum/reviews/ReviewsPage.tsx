import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import StarRating from './StarRating';
import AddProductModal from './AddProductModal';

/**
 * Índice do Community Reviews: sidebar com busca + categorias (com contagem) e
 * grade de produtos (thumb, nome, estrelas, nº de reviews). Espelha o layout do
 * MyBB.
 */
export default class ReviewsPage extends Page {
  categories: any[] = [];
  products: any[] = [];
  loading = true;
  loadingMore = false;
  moreResults = false;
  offset = 0;
  limit = 24;
  category: number | null = null;
  sortBy = 'newest';
  search = '';
  searchTimer: any = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.category = this.attrs.routeName === 'reviews.category' && m.route.param('id') ? parseInt(m.route.param('id')) : null;
    this.loadCategories();
    this.loadProducts(true);
  }

  loadCategories() {
    app.store
      .find('tfb-review-categories')
      .then((res: any) => {
        this.categories = res;
        m.redraw();
      })
      .catch(() => {});
  }

  loadProducts(reset = false) {
    if (reset) {
      this.offset = 0;
      this.products = [];
      this.loading = true;
    } else {
      this.loadingMore = true;
    }

    const params: any = { sortBy: this.sortBy, page: { offset: this.offset, limit: this.limit } };
    if (this.category) params.byCategory = this.category;
    if (this.search.trim()) params.searchQuery = this.search.trim();

    app.store
      .find('tfb-products', params)
      .then((res: any) => {
        this.moreResults = !!res.payload?.links?.next;
        this.products = reset ? Array.from(res) : this.products.concat(Array.from(res));
        this.loading = false;
        this.loadingMore = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        this.loadingMore = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_error'));
        m.redraw();
      });
  }

  selectCategory(id: number | null) {
    this.category = id;
    this.loadProducts(true);
  }

  onSearchInput(value: string) {
    this.search = value;
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.loadProducts(true), 350);
  }

  view() {
    return (
      <div className="ReviewsPage">
        <div className="container">
          <h2 className="ReviewsPage-title">
            <i className="fas fa-star"></i> {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.title')}
            {app.session.user && (
              <Button
                className="Button Button--primary ReviewsPage-addProduct"
                icon="fas fa-plus"
                onclick={() => app.modal.show(AddProductModal)}
              >
                {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.add_product')}
              </Button>
            )}
          </h2>
          <div className="ReviewsPage-layout">
            <aside className="ReviewsPage-sidebar">
              <div className="ReviewsPage-search">
                <input
                  className="FormControl"
                  placeholder={app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.search_placeholder')}
                  value={this.search}
                  oninput={(e: any) => this.onSearchInput(e.target.value)}
                />
              </div>
              <ul className="ReviewsPage-categories">
                <li>
                  <a className={'ReviewsCat' + (this.category === null ? ' active' : '')} onclick={() => this.selectCategory(null)}>
                    {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.all_categories')}
                  </a>
                </li>
                {this.categories.map((c) => (
                  <li key={c.id()}>
                    <a
                      className={'ReviewsCat' + (this.category === parseInt(c.id()) ? ' active' : '')}
                      onclick={() => this.selectCategory(parseInt(c.id()))}
                    >
                      <span className="ReviewsCat-name">{c.name()}</span>
                      <span className="ReviewsCat-count">{c.productCount()}</span>
                    </a>
                  </li>
                ))}
              </ul>
              <div className="ReviewsPage-sort">
                <label>{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_label')}</label>
                <select
                  className="FormControl"
                  value={this.sortBy}
                  onchange={(e: any) => {
                    this.sortBy = e.target.value;
                    this.loadProducts(true);
                  }}
                >
                  <option value="newest">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_newest')}</option>
                  <option value="oldest">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_oldest')}</option>
                  <option value="rating">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_rating')}</option>
                  <option value="reviews">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_reviews')}</option>
                  <option value="views">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.sort_views')}</option>
                </select>
              </div>
            </aside>

            <main className="ReviewsPage-main">
              {this.loading ? (
                <LoadingIndicator />
              ) : this.products.length === 0 ? (
                <p className="ReviewsPage-empty">{app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.no_products')}</p>
              ) : (
                <div className="ReviewsGrid">
                  {this.products.map((p) => this.productCard(p))}
                </div>
              )}
              {this.moreResults && !this.loading && (
                <div className="ReviewsPage-more">
                  <Button
                    className="Button"
                    loading={this.loadingMore}
                    onclick={() => {
                      this.offset += this.limit;
                      this.loadProducts(false);
                    }}
                  >
                    {app.translator.trans('huseyinfiliz-traderfeedback.forum.reviews.load_more')}
                  </Button>
                </div>
              )}
            </main>
          </div>
        </div>
      </div>
    );
  }

  productCard(p: any) {
    const thumb = p.thumbnailUrl();
    return (
      <Link className="ReviewCard" href={app.route('reviews.product', { id: p.id() })} key={p.id()}>
        <div className="ReviewCard-meta">
          <span>
            <i className="fas fa-comment"></i> {p.reviewCount()}
          </span>
          <span>
            <i className="fas fa-eye"></i> {p.views()}
          </span>
        </div>
        <div className="ReviewCard-thumb">
          {thumb ? <img src={thumb} alt={p.name()} loading="lazy" /> : <i className="fas fa-image ReviewCard-noimg"></i>}
        </div>
        <div className="ReviewCard-rating">
          <StarRating rating={p.cachedRating()} />
        </div>
        <div className="ReviewCard-name">{p.name()}</div>
      </Link>
    );
  }
}
