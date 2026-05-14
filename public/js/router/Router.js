export class Router {
  #routes = new Map();
  #current = null;

  register(name, viewClass) {
    this.#routes.set(name, viewClass);
    return this;
  }

  async navigate(name) {
    const ViewClass = this.#routes.get(name);
    if (!ViewClass) {
      console.warn(`Router: vista "${name}" no registrada`);
      return;
    }

    this.#current?.destroy?.();
    this.#current = new ViewClass();
    this.#current.render();
  }
}
