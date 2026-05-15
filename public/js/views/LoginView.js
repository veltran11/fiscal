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
            <div id="reenviar-section" class="hidden text-center text-sm bg-yellow-50 border border-yellow-200 rounded-lg p-3">
              <p class="text-yellow-800 mb-2">Tu cuenta aún no fue activada.</p>
              <button type="button" id="btn-reenviar" class="text-blue-600 hover:underline font-medium">
                Reenviar email de verificación
              </button>
            </div>
            <p class="text-center text-sm mt-1">
              <a href="#" id="link-olvide" class="text-gray-500 hover:text-blue-600 hover:underline">¿Olvidaste tu contraseña?</a>
            </p>
          </form>
          <p class="text-center text-sm text-gray-500 mt-4">
            ¿No tenés cuenta?
            <a href="#" id="link-registrarse" class="text-blue-600 hover:underline">Registrarse</a>
          </p>
        </div>
      </div>`;
  }

  afterRender() {
    const form = this.outlet.querySelector('#form-login');
    const email = this.outlet.querySelector('#input-email');
    const password = this.outlet.querySelector('#input-password');
    const reenviarSection = this.outlet.querySelector('#reenviar-section');
    const btnReenviar = this.outlet.querySelector('#btn-reenviar');

    form.addEventListener('submit', async e => {
      e.preventDefault();
      reenviarSection.classList.add('hidden');
      try {
        await auth.login(email.value.trim(), password.value);
        eventBus.emit('navigate', 'dashboard');
      } catch (err) {
        if (err.message === 'Usuario inactivo') {
          reenviarSection.classList.remove('hidden');
        } else {
          Toast.error(err.message ?? 'Credenciales incorrectas');
        }
      }
    });

    btnReenviar.addEventListener('click', async () => {
      btnReenviar.disabled = true;
      btnReenviar.textContent = 'Enviando...';
      try {
        await auth.resendVerification(email.value.trim());
        Toast.success('Email de verificación enviado. Revisá tu bandeja.');
        reenviarSection.classList.add('hidden');
      } catch {
        Toast.error('No se pudo enviar el email. Intentá de nuevo.');
      } finally {
        btnReenviar.disabled = false;
        btnReenviar.textContent = 'Reenviar email de verificación';
      }
    });

    this.outlet.querySelector('#link-registrarse')
      ?.addEventListener('click', e => {
        e.preventDefault();
        eventBus.emit('navigate', 'register');
      });

    this.outlet.querySelector('#link-olvide')
      ?.addEventListener('click', e => {
        e.preventDefault();
        eventBus.emit('navigate', 'olvide');
      });
  }
}
