export class Toast {
  static #outlet = document.getElementById('toast-outlet');

  static #show(message, colorClass) {
    const el = document.createElement('div');
    el.className = `px-4 py-3 rounded shadow text-white text-sm ${colorClass} transition-opacity duration-300`;
    el.textContent = message;
    this.#outlet.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 300);
    }, 3000);
  }

  static success(message) { this.#show(message, 'bg-green-500'); }
  static error(message)   { this.#show(message, 'bg-red-500'); }
  static info(message)    { this.#show(message, 'bg-blue-500'); }
}
