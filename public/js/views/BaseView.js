export class BaseView {
  #outlet;

  constructor(outletId = 'view-outlet') {
    this.#outlet = document.getElementById(outletId);
  }

  get outlet() { return this.#outlet; }

  /** Retorna HTML string — cada vista lo implementa */
  template() { return ''; }

  /** Se ejecuta después del render para bindear eventos */
  afterRender() {}

  render() {
    this.#outlet.innerHTML = this.template();
    this.afterRender();
  }

  destroy() {
    this.#outlet.innerHTML = '';
  }
}
