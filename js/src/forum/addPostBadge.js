import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';

/**
 * Renders the MyBB-style 3-stat block inside `.Post-side`, directly below the
 * author's avatar: posts count (blue), trade feedback total (green) and likes
 * received (red). Added to Post.sideItems (avatar = priority 100) at a lower
 * priority so it stacks under the avatar — like a point-system profile title.
 * Gated by the legacy badge settings (showBadgeInPosts + tag + onlyFirstPost).
 */
export default function addPostBadge() {
  extend('flarum/forum/components/Post', 'sideItems', function (items) {
    if (!app.forum.attribute('huseyinfiliz.traderfeedback.showBadgeInPosts')) {
      return;
    }

    const post = this.attrs.post;
    if (!post || typeof post.user !== 'function') return;
    const user = post.user();
    if (!user) return;

    const onlyFirstPost = app.forum.attribute('huseyinfiliz.traderfeedback.badgeOnlyFirstPost');
    if (onlyFirstPost && typeof post.number === 'function' && post.number() !== 1) {
      return;
    }

    if (!passesTagFilter(post)) return;

    const posts = Number(user.commentCount?.() ?? user.attribute('commentCount') ?? 0);
    const trade = Number(user.attribute('traderTotalFeedback') || 0);
    const likes = Number(user.attribute('traderLikesReceived') || 0);

    if (posts === 0 && trade === 0 && likes === 0) return;

    const profileHref = route('user', { username: user.slug() });
    const feedbacksHref = route('user.feedbacks', { username: user.slug() });

    const score = Math.round(user.attribute('traderScore') || 0);
    const tradeTitle = app.translator.trans('huseyinfiliz-traderfeedback.forum.user_card.score_tooltip', {
      score,
      positive: user.attribute('traderPositiveCount') || 0,
      neutral: user.attribute('traderNeutralCount') || 0,
      negative: user.attribute('traderNegativeCount') || 0,
    });

    items.add(
      'traderStats',
      <div className="TraderStats">
        <a
          className="TraderStat TraderStat--posts"
          href={profileHref}
          title={app.translator.trans('huseyinfiliz-traderfeedback.forum.post_stats.posts')}
        >
          <i className="fas fa-comment"></i>
          <span className="TraderStat-value">{fmt(posts)}</span>
        </a>
        <a className="TraderStat TraderStat--trade" href={feedbacksHref} title={tradeTitle}>
          <i className="fas fa-shopping-cart"></i>
          <span className="TraderStat-value">{fmt(trade)}</span>
        </a>
        <a
          className="TraderStat TraderStat--likes"
          href={profileHref}
          title={app.translator.trans('huseyinfiliz-traderfeedback.forum.post_stats.likes')}
        >
          <i className="fas fa-thumbs-up"></i>
          <span className="TraderStat-value">{fmt(likes)}</span>
        </a>
      </div>,
      50
    );
  });
}

function fmt(n) {
  try {
    return Number(n).toLocaleString();
  } catch (e) {
    return String(n);
  }
}

function route(name, params) {
  try {
    return app.route(name, params);
  } catch (e) {
    return undefined;
  }
}

function passesTagFilter(post) {
  const tagFilterJson = app.forum.attribute('huseyinfiliz.traderfeedback.badgeTagFilter') || '[]';
  let allowedTags = [];
  try {
    allowedTags = JSON.parse(tagFilterJson);
  } catch (e) {
    allowedTags = [];
  }

  if (allowedTags.length === 0) return true;

  if (typeof post.discussion !== 'function') return true;
  const discussion = post.discussion();
  if (!discussion) return false;

  const discussionTags = discussion.tags ? discussion.tags() : [];
  if (!discussionTags || discussionTags.length === 0) return false;

  return discussionTags.some((tag) => allowedTags.includes(tag.id()));
}
