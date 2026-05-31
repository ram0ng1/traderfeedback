import Model from 'flarum/common/Model';

export class ReviewCategory extends Model {
  name = Model.attribute<string>('name');
  position = Model.attribute<number>('position');
  productCount = Model.attribute<number>('productCount');
  fields = Model.hasMany('fields');
}

export class ReviewField extends Model {
  name = Model.attribute<string>('name');
  categoryId = Model.attribute<number>('categoryId');
  position = Model.attribute<number>('position');
}

export class Product extends Model {
  name = Model.attribute<string>('name');
  categoryId = Model.attribute<number>('categoryId');
  views = Model.attribute<number>('views');
  cachedRating = Model.attribute<number>('cachedRating');
  reviewCount = Model.attribute<number>('reviewCount');
  canManage = Model.attribute<boolean>('canManage');
  thumbnailUrl = Model.attribute<string | null>('thumbnailUrl');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  category = Model.hasOne('category');
  user = Model.hasOne('user');
  reviews = Model.hasMany('reviews');
}

export class ProductReview extends Model {
  productId = Model.attribute<number>('productId');
  userId = Model.attribute<number | null>('userId');
  merchantUserId = Model.attribute<number | null>('merchantUserId');
  price = Model.attribute<string | null>('price');
  url = Model.attribute<string | null>('url');
  comment = Model.attribute<string>('comment');
  rating = Model.attribute<number>('rating');
  canDelete = Model.attribute<boolean>('canDelete');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  product = Model.hasOne('product');
  user = Model.hasOne('user');
  merchant = Model.hasOne('merchant');
  photos = Model.hasMany('photos');
  fieldRatings = Model.hasMany('fieldRatings');
}

export class ReviewFieldRating extends Model {
  rating = Model.attribute<number>('rating');
  comment = Model.attribute<string | null>('comment');
  fieldId = Model.attribute<number>('fieldId');
  field = Model.hasOne('field');
}

export class ReviewPhoto extends Model {
  url = Model.attribute<string>('url');
  thumbnailUrl = Model.attribute<string | null>('thumbnailUrl');
  position = Model.attribute<number>('position');
}

export class ReviewComment extends Model {
  comment = Model.attribute<string>('comment');
  productId = Model.attribute<number>('productId');
  reviewId = Model.attribute<number | null>('reviewId');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  user = Model.hasOne('user');
}

/** Registers all review models in the store. Call once on init. */
export function registerReviewModels(store: any): void {
  store.models['tfb-review-categories'] = ReviewCategory;
  store.models['tfb-review-fields'] = ReviewField;
  store.models['tfb-products'] = Product;
  store.models['tfb-product-reviews'] = ProductReview;
  store.models['tfb-review-field-ratings'] = ReviewFieldRating;
  store.models['tfb-review-photos'] = ReviewPhoto;
  store.models['tfb-review-comments'] = ReviewComment;
}
