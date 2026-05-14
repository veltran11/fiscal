import { BaseView } from './BaseView.js';
import { auth } from '../services/AuthService.js';
import { Toast } from '../components/Toast.js';
import { eventBus } from '../utils/EventBus.js';

export class LoginView extends BaseView {
  template() {
    return `
      <div class="flex items-center justify-center min-h-[80vh]">
        <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-sm">
          <h1 class="text-2xl font-bold text-center text-blue-700 mb-6">Iniciar sesión</h1>
          <form id="form-login" novalidate class="flex flex-col gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input id="input-email" type="email" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
              <input id="input-password" type="password" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
              Entrar
            </button>
          </form>
        </div>
      </div>`;
  }

  afterRender() {
    const form = this.outlet.querySelector('#form-login');
    const email = this.outlet.querySelector('#input-email');
    const password = this.outlet.querySelector('#input-password');

    form.addEventListener('submit', async e => {
      e.preventDefault();
      try {
        await auth.login(email.value.trim(), password.value);
        eventBus.emit('navigate', 'dashboard');
      } catch (err) {
        Toast.error(err.message ?? 'Credenciales incorrectas');
      }
    });
  }
}
