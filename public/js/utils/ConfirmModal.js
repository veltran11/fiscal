/**
 * Modal de confirmación global.
 * Uso:
 *   import { confirm } from '../utils/ConfirmModal.js';
 *   if (await confirm('¿Estás seguro?')) { ... }
 */
export function confirm(mensaje) {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
    overlay.innerHTML = `
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <p class="text-gray-700 mb-6 text-center">${mensaje}</p>
        <div class="flex gap-3 justify-end">
          <button id="modal-confirm-no" class="btn px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Cancelar</button>
          <button id="modal-confirm-yes" class="btn px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600">Eliminar</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    const cerrar = (resultado) => {
      overlay.remove();
      resolve(resultado);
    };

    overlay.querySelector('#modal-confirm-yes').addEventListener('click', () => cerrar(true));
    overlay.querySelector('#modal-confirm-no').addEventListener('click', () => cerrar(false));
    overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(false); });
  });
}
