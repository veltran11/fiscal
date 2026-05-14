export class EventBus {
  #listeners = {};

  on(event, callback) {
    if (!this.#listeners[event]) this.#listeners[event] = [];
    this.#listeners[event].push(callback);
  }

  off(event, callback) {
    if (!this.#listeners[event]) return;
    this.#listeners[event] = this.#listeners[event].filter(cb => cb !== callback);
  }

  emit(event, data = null) {
    (this.#listeners[event] ?? []).forEach(cb => cb(data));
  }
}

export const eventBus = new EventBus();
