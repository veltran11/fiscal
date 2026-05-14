export class Component {
  #outlet;
  #rendered = false;

  constructor(outletId) {
    this.#outlet = document.getElementById(outletId);
    if (!this.#outlet) throw new Error(`Outlet #${outletId} no encontrado`);
  }

  get outlet() { return this.#outlet; }
  get rendered() { return this.#rendered; }

  /** Subclases deben implementar este método y retornar HTML string */
  template() { return ''; }

  /** Se ejecuta una vez luego del primer render, para bindear eventos */
  afterRender() {}

  render() {
    this.#outlet.innerHTML = this.template();
    if (!this.#rendered) {
      this.afterRender();
      this.#rendered = true;
    }
  }

  destroy() {
    this.#outlet.innerHTML = '';
    this.#rendered = false;
  }
}
