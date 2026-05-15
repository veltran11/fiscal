import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { eventBus } from '../utils/EventBus.js';
import { Toast } from '../components/Toast.js';

export class RegisterView extends BaseView {
  template() {
    return `
      <div class="flex items-center justify-center min-h-[80vh]">
        <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-sm">
          <h1 class="text-2xl font-bold text-center text-blue-700 mb-6">Crear cuenta</h1>
          <form id="form-register" novalidate class="flex flex-col gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
              <input id="input-nombre" type="text" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input id="input-email" type="email" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
              <input id="input-password" type="password" required minlength="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <button type="submit" id="btn-register"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition flex items-center justify-center gap-2">
              <span id="btn-register-text">Registrarse</span>
              <span id="btn-register-spinner" class="hidden">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
              </span>
            </button>
            <button type="button" id="btn-volver-login"
              class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg transition">
              Volver
            </button>
          </form>
        </div>
      </div>`;
  }

  afterRender() {
    const form = this.outlet.querySelector('#form-register');
    const nombre = this.outlet.querySelector('#input-nombre');
    const email = this.outlet.querySelector('#input-email');
    const password = this.outlet.querySelector('#input-password');
    const btn = this.outlet.querySelector('#btn-register');
    const btnText = this.outlet.querySelector('#btn-register-text');
    const spinner = this.outlet.querySelector('#btn-register-spinner');

    function setLoading(loading) {
      if (loading) {
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-wait');
        btnText.textContent = 'Registrando...';
        spinner.classList.remove('hidden');
      } else {
        btn.disabled = false;
        btn.classList.remove('opacity-70', 'cursor-wait');
        btnText.textContent = 'Registrarse';
        spinner.classList.add('hidden');
      }
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();
      setLoading(true);
      try {
        await api.post('/auth/register', {
          nombre: nombre.value.trim(),
          email: email.value.trim(),
          password: password.value,
        });
        Toast.success('Cuenta creada. Revisá tu email para activarla.');
        eventBus.emit('navigate', 'login');
      } catch (err) {
        Toast.error(err.message ?? 'Error al registrarse');
        setLoading(false);
      }
    });

    this.outlet.querySelector('#btn-volver-login')
      ?.addEventListener('click', () => eventBus.emit('navigate', 'login'));
  }
}
