import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { eventBus } from '../utils/EventBus.js';
import { facturaState } from './FacturaState.js';

const CAMPOS = ['id', 'cliente_id', 'numero', 'fecha', 'concepto', 'monto'];

export class FacturaFormView extends BaseView {
  #clientes = [];

  template() {
    return `
      <div class="inner flex flex-col gap-4">
        <h2 class="tit">
          <i class="fas fa-chevron-left pr-4"></i>
          <div id="form-titulo">Nueva factura</div>
        </h2>

        <form id="form-factura" class="frame bg-white p-4 overflow-y-auto pb-4 flex flex-col gap-4">
          <div class="fld">
            <label>Cliente *</label>
            <select name="cliente_id" class="w-full" required>
              <option value="">Seleccionar cliente…</option>
            </select>
          </div>
          
          <div class="flex items-center gap-4 *:flex-1">
            <div class="fld">
              <label>Fecha *</label>
              <input name="fecha" type="date" class="w-full" required>
            </div>

            <div class="fld">
              <label>Monto *</label>
              <input name="monto" type="number" step="0.01" min="0" class="w-full" required>
            </div>
          </div>

          <div class="fld">
            <label>Concepto *</label>
            <textarea rows="3" name="concepto" type="text" class="w-full resize-none" required></textarea>
          </div>

          <div class="btns pt-4">
            <div class="flex-1"></div>

            <button type="submit" id="btn-guardar-factura" class="btn-blue">
              <i class="fas fa-floppy-disk"></i>
              <div>Guardar</div>
            </button>

            <button id="btn-cancelar-factura">
              <i class="fas fa-xmark"></i>
              <div>Cancelar</div>
            </button>
          </div>
        </form>
      </div>`;
  }

  afterRender() {
    this.#cargarClientes();

    this.outlet.querySelector('#form-factura')
      ?.addEventListener('submit', e => { e.preventDefault(); this.#guardar(); });

    this.outlet.querySelector('#btn-cancelar-factura')
      ?.addEventListener('click', () => this.#volver());
  }

  async #cargarClientes() {
    try {
      this.#clientes = await api.get('/clientes');
      const sel = this.outlet.querySelector('select[name="cliente_id"]');
      sel.innerHTML = '<option value="">Seleccionar cliente…</option>' +
        this.#clientes.map(c =>
          `<option value="${c.id}">${c.nombre}${c.cuit ? ' (' + c.cuit + ')' : ''}</option>`
        ).join('');
    } catch (e) {
      console.error('Error cargando clientes', e);
    }
  }

  // Llamado por DashboardView cada vez que esta vista se hace visible
  onShow() {
    const form = this.outlet.querySelector('#form-factura');
    form.reset();
    form.querySelector('input[name="fecha"]').value = new Date().toISOString().slice(0, 10);
  }

  #volver() {
    this.outlet.querySelector('.tit i')?.click();
  }

  async #guardar() {
    const form = this.outlet.querySelector('#form-factura');
    const btn = this.outlet.querySelector('#btn-guardar-factura');
    const data = Object.fromEntries(new FormData(form));

    btn.disabled = true;
    try {
      const resultado = await api.post('/facturas', data);
      eventBus.emit('facturas:actualizado', { factura: resultado });
      this.#volver();
    } catch (e) {
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  }
}
