import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';

export default function addUserCardStats() {
  // Estatística de trade feedback no card de usuário (perfil + hovercard),
  // no formato do MyBB: ícone de carrinho + contagem total de feedbacks,
  // clicável para a aba de feedbacks. Lê os atributos computados do
  // UserResource (sempre serializados) — ver src/Api/UserResourceFields.php.
  extend('flarum/forum/components/UserCard', 'infoItems', function (items) {
    const user = this.attrs.user;

    if (!user) return;

    const total = user.attribute('traderTotalFeedback') || 0;
    if (total <= 0) return;

    const score = Math.round(user.attribute('traderScore') || 0);
    const positive = user.attribute('traderPositiveCount') || 0;
    const neutral = user.attribute('traderNeutralCount') || 0;
    const negative = user.attribute('traderNegativeCount') || 0;

    const title = app.translator.trans('huseyinfiliz-traderfeedback.forum.user_card.score_tooltip', {
      score,
      positive,
      neutral,
      negative,
    });

    let href;
    try {
      href = app.route('user.feedbacks', { username: user.slug() });
    } catch (e) {
      href = undefined;
    }

    items.add(
      'traderFeedback',
      <a className="TraderScore" href={href} title={title}>
        <i className="fas fa-shopping-cart TraderScore-icon"></i>
        <span className="TraderScore-value"> {total}</span>
      </a>,
      10
    );
  });
}
