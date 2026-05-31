import Component from 'flarum/common/Component';

export interface StarInputAttrs {
  value: number;
  onchange: (v: number) => void;
  max?: number;
}

/**
 * Seletor de nota 1–max por estrelas clicáveis (usado no formulário de review).
 */
export default class StarInput extends Component<StarInputAttrs> {
  view() {
    const max = this.attrs.max || 5;
    const value = Number(this.attrs.value) || 0;
    const stars = [];

    for (let i = 1; i <= max; i++) {
      stars.push(
        <i
          className={(i <= value ? 'fas' : 'far') + ' fa-star StarInput-star'}
          key={i}
          onclick={() => this.attrs.onchange(i)}
          role="button"
          aria-label={`${i}`}
        />
      );
    }

    return <span className="StarInput">{stars}</span>;
  }
}
