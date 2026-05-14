import { Component } from './Component.js';
import { auth } from '../services/AuthService.js';
import { eventBus } from '../utils/EventBus.js';

export class Navbar extends Component {
  constructor() {
    super('navbar-outlet');
  }

  template() {
    const user = auth.getUser();
    return `
      <nav class="bg-white shadow-md">
        <div class="px-4 flex items-center gap-4 h-14">
          <div class="font-bold text-blue-700 text-lg flex-1">FACTURación</div>

          ${user ? `
            <div class="class="text-sm text-gray-600">${user.name ?? user.email}</div>

            <button id="btn-logout"
              class="text-sm bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded transition">
              Salir
            </button>
          ` : ''}
        </div>
      </nav>`;
  }

  afterRender() {
    this.outlet.addEventListener('click', e => {
      if (e.target.id === 'btn-logout') {
        auth.logout();
        eventBus.emit('navigate', 'login');
      }
    });
  }
}
