import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { eventBus } from '../utils/EventBus.js';
import { confirm } from '../utils/ConfirmModal.js';
import { clienteState } from './ClienteState.js';

const CAMPOS = ['id', 'nombre', 'cuit', 'cond_iva_id', 'email', 'cod_area', 'telefono', 'direccion', 'localidad', 'codigo_postal'];

export class ClienteFormView extends BaseView {
  template() {
    return `
      <div class="inner flex flex-col gap-4">
        <h2 class="tit">
          <i class="fas fa-chevron-left pr-4"></i>
          <div id="form-titulo">Cliente</div>
        </h2>

        <form id="form-cliente" class="frame bg-white p-4 overflow-y-auto pb-4 flex flex-col gap-4">
            <div class="btns items-end">
              <div class="fld w-80">
                <label>CUIT</label>
                <div class="flex gap-2">
                  <input name="cuit" type="text" class="w-full">
                </div>
              </div>

              <button type="button" id="btn-padron-cliente" class="btn">
                <div class="whitespace-nowrap">Traer datos fiscales</div>
                <i id="icon-padron-cliente" class="fas fa-rotate-right"></i>
              </button>
            </div>

            <div class="fld">
              <label>Nombre / Razón social *</label>
              <input name="nombre" type="text" class="w-full" required>
            </div>

            <div class="fld">
              <label>Condición IVA</label>
              <select name="cond_iva_id" class="w-full">
                <option value="1">Consumidor Final</option>
                <option value="2">Responsable Inscripto</option>
                <option value="3">Monotributista</option>
                <option value="4">Exento</option>
              </select>
            </div>

            <div class="flex flex-col gap-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="fld">
                  <label>Email</label>
                  <input name="email" type="email" class="w-full">
                </div>
                <div class="grid grid-cols-[100px_1fr] gap-2">
                  <div class="fld">
                    <label>Cód. área</label>
                    <input name="cod_area" type="tel" class="w-full" maxlength="5">
                  </div>
                  <div class="fld">
                    <label>Teléfono</label>
                    <input name="telefono" type="tel" class="w-full">
                  </div>
                </div>
              </div>
              <div class="fld">
                <label>Dirección</label>
                <input name="direccion" type="text" class="w-full">
              </div>
              <div class="grid grid-cols-[2fr_1fr] gap-4">
                <div class="fld">
                  <label>Localidad</label>
                  <input name="localidad" type="text" class="w-full">
                </div>
                <div class="fld">
                  <label>Código postal</label>
                  <input name="codigo_postal" type="text" maxlength="10" class="w-full">
                </div>
              </div>
            </div>
          </section>

          <div class="btns pt-4">
            <button type="button" id="btn-eliminar" class="hidden btn-red">
              <i class="fas fa-trash"></i>
              <div>Eliminar</div>
            </button>

            <div class="flex-1"></div>

            <button type="submit" id="btn-guardar" class="btn-blue">
              <i class="fas fa-floppy-disk"></i>
              <div>Guardar</div>
            </button>

            <button id="btn-cancelar">
              <i class="fas fa-xmark"></i>
              <div>Cancelar</div>
            </button>
          </div>
        </form>
      </div>`;
  }

  afterRender() {
    this.outlet.querySelector('input[name="cuit"]')?.addEventListener('input', function () {
      const pos = this.selectionStart;
      const digitsAntes = this.value.substring(0, pos).replace(/\D/g, '').length;
      let d = this.value.replace(/\D/g, '').slice(0, 11);
      let f = d;
      if (d.length > 2) f = d.slice(0, 2) + '-' + d.slice(2);
      if (d.length > 10) f = f.slice(0, 11) + '-' + d.slice(10);
      this.value = f;
      let nuevaPos = 0, contado = 0;
      while (nuevaPos < f.length && contado < digitsAntes) {
        if (f[nuevaPos] !== '-') contado++;
        nuevaPos++;
      }
      while (nuevaPos < f.length && f[nuevaPos] === '-') nuevaPos++;
      this.setSelectionRange(nuevaPos, nuevaPos);
    });

    this.outlet.querySelector('#form-cliente')
      ?.addEventListener('submit', e => { e.preventDefault(); this.#guardar(); });

    this.outlet.querySelector('#btn-eliminar')
      ?.addEventListener('click', () => this.#eliminar());

    this.outlet.querySelector('#btn-cancelar')
      ?.addEventListener('click', () => this.#volver());

    this.outlet.querySelector('#btn-padron-cliente')
      ?.addEventListener('click', () => this.#traerPadron());
  }

  // Llamado por DashboardView cada vez que esta vista se hace visible
  onShow() {
    const c = clienteState.cliente;
    const form = this.outlet.querySelector('#form-cliente');

    form.reset();
    this.outlet.querySelector('#form-titulo').textContent =
      c ? 'Editar cliente' : 'Nuevo cliente';
    this.outlet.querySelector('#btn-eliminar').classList.toggle('hidden', !c);

    if (c) CAMPOS.forEach(f => {
      const el = form.querySelector(`[name="${f}"]`);
      if (el && c[f] != null) {
        el.value = f === 'cuit' ? this.#formatearCuit(c[f]) : c[f];
      }
    });
  }

  #volver() {
    this.outlet.querySelector('.tit i')?.click();
  }

  #formatearCuit(valor) {
    const d = ((valor + '') ?? '').replace(/\D/g, '').slice(0, 11);
    let f = d;
    if (d.length > 2) f = d.slice(0, 2) + '-' + d.slice(2);
    if (d.length > 10) f = f.slice(0, 11) + '-' + d.slice(10);
    return f;
  }

  async #traerPadron() {
    const cuit = this.outlet.querySelector('input[name="cuit"]')?.value?.replace(/\D/g, '');
    if (!cuit || cuit.length !== 11) {
      alert('Ingrese un CUIT válido de 11 dígitos');
      return;
    }
    const btn = this.outlet.querySelector('#btn-padron-cliente');
    const icon = this.outlet.querySelector('#icon-padron-cliente');
    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';
    try {
      const d = await api.post('/cuenta/padron', { cuit });
      const form = this.outlet.querySelector('#form-cliente');
      const set = (name, val) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el && val != null) el.value = val;
      };
      set('cuit', this.#formatearCuit(d.cuit || cuit));
      set('nombre', d.razon_social);
      set('cond_iva_id', d.cond_iva_id);
      set('direccion', d.domicilio_fiscal);
      set('localidad', d.localidad);
      set('codigo_postal', d.codigo_postal);
      icon.className = 'fas fa-check text-green-600';
    } catch (e) {
      icon.className = 'fas fa-triangle-exclamation text-red-500';
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  }

  async #guardar() {
    const form = this.outlet.querySelector('#form-cliente');
    const btn = this.outlet.querySelector('#btn-guardar');
    const data = Object.fromEntries(new FormData(form));
    // Limpiar el CUIT: guardar solo dígitos
    if (data.cuit) data.cuit = data.cuit.replace(/\D/g, '');
    const c = clienteState.cliente;

    btn.disabled = true;
    try {
      const resultado = c
        ? await api.put(`/clientes/${c.id}`, data)
        : await api.post('/clientes', data);
      eventBus.emit('clientes:actualizado', { cliente: resultado, eliminado: false });
      this.#volver();
    } catch (e) {
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  }

  async #eliminar() {
    const c = clienteState.cliente;
    if (!c || !await confirm('¿Eliminar este cliente?')) return;
    try {
      await api.delete(`/clientes/${c.id}`);
      eventBus.emit('clientes:actualizado', { cliente: c, eliminado: true });
      this.#volver();
    } catch (e) {
      alert(e.message);
    }
  }
}
