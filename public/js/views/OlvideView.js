import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { Toast } from '../components/Toast.js';
import { eventBus } from '../utils/EventBus.js';

export class OlvideView extends BaseView {
  template() {
    return `
      <div class="flex items-center justify-center min-h-[80vh]">
        <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-sm">
          <h1 class="text-2xl font-bold text-center text-blue-700 mb-2">¿Olvidaste tu contraseña?</h1>
          <p class="text-sm text-gray-500 text-center mb-6">
            Ingresá tu email y te enviaremos un link para restablecerla.
          </p>
          <form id="form-olvide" novalidate class="flex flex-col gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input id="input-email" type="email" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <button type="submit" id="btn-enviar"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition flex items-center justify-center gap-2">
              <span id="btn-text">Enviar link</span>
              <span id="btn-spinner" class="hidden">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
              </span>
            </button>
          </form>
          <p class="text-center text-sm text-gray-500 mt-4">
            <a href="#" id="link-volver" class="text-blue-600 hover:underline">Volver al inicio de sesión</a>
          </p>
        </div>
      </div>`;
  }

  afterRender() {
    const form = this.outlet.querySelector('#form-olvide');
    const email = this.outlet.querySelector('#input-email');
    const btn = this.outlet.querySelector('#btn-enviar');
    const btnText = this.outlet.querySelector('#btn-text');
    const spinner = this.outlet.querySelector('#btn-spinner');

    function setLoading(loading) {
      if (loading) {
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-wait');
        btnText.textContent = 'Enviando...';
        spinner.classList.remove('hidden');
      } else {
        btn.disabled = false;
        btn.classList.remove('opacity-70', 'cursor-wait');
        btnText.textContent = 'Enviar link';
        spinner.classList.add('hidden');
      }
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();
      setLoading(true);
      try {
        await api.post('/auth/olvide', {
          email: email.value.trim(),
        });
        Toast.success('Si el email existe, recibirás un link para restablecer tu contraseña.');
        eventBus.emit('navigate', 'login');
      } catch (err) {
        Toast.error(err.message ?? 'Error al enviar el mail');
        setLoading(false);
      }
    });

    this.outlet.querySelector('#link-volver')
      ?.addEventListener('click', e => {
        e.preventDefault();
        eventBus.emit('navigate', 'login');
      });
  }
}
