import Component from 'flarum/common/Component';

export interface StarRatingAttrs {
  rating: number; // 0..5
  max?: number;
}

/**
 * Renders a 0–5 star rating with full / half / empty stars (Font Awesome),
 * matching the MyBB Community Reviews display.
 */
export default class StarRating extends Component<StarRatingAttrs> {
  view() {
    const max = this.attrs.max || 5;
    const rating = Math.max(0, Math.min(max, Number(this.attrs.rating) || 0));
    const stars = [];

    for (let i = 1; i <= max; i++) {
      let cls;
      if (rating >= i) {
        cls = 'fas fa-star';
      } else if (rating >= i - 0.5) {
        cls = 'fas fa-star-half-alt';
      } else {
        cls = 'far fa-star';
      }
      stars.push(<i className={`${cls} StarRating-star`} key={i} />);
    }

    return (
      <span className="StarRating" title={`${rating.toFixed(2)} / ${max}`}>
        {stars}
      </span>
    );
  }
}
