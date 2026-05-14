import { BaseView } from './BaseView.js';
import { api }      from '../services/ApiService.js';

const CAMPOS_FORM = [
  'nombre', 'tipo_documento', 'numero_documento', 'condicion_iva',
  'email', 'telefono', 'direccion', 'localidad', 'codigo_postal',
];

export class ClientesView extends BaseView {
  #clientes = [];
  #editId   = null;

  template() {
    return `
      <div class="inner flex flex-col">
        <h2 class="tit text-lg font-semibold mb-2 flex items-center">
          <i class="fas fa-chevron-left pr-4"></i>
          <div>Clientes</div>
        </h2>

        <div id="panel-lista" class="flex flex-col flex-1 overflow-hidden gap-2">
          <div class="btns justify-between">
            <input id="buscar" type="search" placeholder="Buscar…" class="flex-1">
            <button type="button" id="btn-nuevo">
              <i class="fas fa-plus"></i>
              <div>Nuevo</div>
            </button>
          </div>
          <div class="frame grd-out overflow-y-auto flex-1">
            <table class="grd w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500 text-xs uppercase">
                  <th class="px-3 py-2">Nombre / Razón social</th>
                  <th class="px-3 py-2 hidden sm:table-cell">Documento</th>
                  <th class="px-3 py-2 hidden md:table-cell">Condición IVA</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody id="tbody-clientes">
                <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Cargando…</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div id="panel-form" class="hidden flex-col flex-1 overflow-hidden">
          <h3 id="form-titulo" class="font-semibold text-gray-600 mb-3">Nuevo cliente</h3>
          <form id="form-cliente" class="overflow-y-auto pb-4 flex flex-col gap-4">
            <section class="frame p-4 bg-white">
              <h3 class="font-bold text-gray-600 mb-4">Identificación</h3>
              <div class="flex flex-col gap-4">
                <div class="fld">
                  <label>Nombre / Razón social *</label>
                  <input name="nombre" type="text" class="w-full" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="fld">
                    <label>Tipo documento</label>
                    <select name="tipo_documento" class="w-full">
                      <option value="CUIT">CUIT</option>
                      <option value="DNI">DNI</option>
                      <option value="Pasaporte">Pasaporte</option>
                    </select>
                  </div>
                  <div class="fld">
                    <label>Número</label>
                    <input name="numero_documento" type="text" class="w-full">
                  </div>
                </div>
                <div class="fld">
                  <label>Condición IVA</label>
                  <select name="condicion_iva" class="w-full">
                    <option value="Consumidor Final">Consumidor Final</option>
                    <option value="Responsable Inscripto">Responsable Inscripto</option>
                    <option value="Monotributista">Monotributista</option>
                    <option value="Exento">Exento</option>
                  </select>
                </div>
              </div>
            </section>

            <section class="frame p-4 bg-white">
              <h3 class="font-bold text-gray-600 mb-4">Contacto</h3>
              <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                  <div class="fld">
                    <label>Email</label>
                    <input name="email" type="email" class="w-full">
                  </div>
                  <div class="fld">
                    <label>Teléfono</label>
                    <input name="telefono" type="tel" class="w-full">
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

            <div class="btns justify-between">
              <button type="button" id="btn-cancelar">
                <i class="fas fa-arrow-left"></i>
                <div>Cancelar</div>
              </button>
              <div class="flex gap-2">
                <button type="button" id="btn-eliminar" class="hidden">
                  <i class="fas fa-trash"></i>
                  <div>Eliminar</div>
                </button>
                <button type="submit" id="btn-guardar">
                  <i class="fas fa-floppy-disk"></i>
                  <div>Guardar</div>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>`;
  }

  afterRender() {
    this.#cargarClientes();

    this.outlet.querySelector('#btn-nuevo')
      ?.addEventListener('click', () => this.#mostrarForm(null));

    this.outlet.querySelector('#btn-cancelar')
      ?.addEventListener('click', () => this.#mostrarLista());

    this.outlet.querySelector('#buscar')
      ?.addEventListener('input', e => this.#filtrar(e.target.value));

    this.outlet.querySelector('#tbody-clientes')
      ?.addEventListener('click', e => {
        const fila = e.target.closest('[data-id]');
        if (fila) this.#mostrarForm(Number(fila.dataset.id));
      });

    this.outlet.querySelector('#form-cliente')
      ?.addEventListener('submit', e => { e.preventDefault(); this.#guardar(); });

    this.outlet.querySelector('#btn-eliminar')
      ?.addEventListener('click', () => this.#eliminar());
  }

  async #cargarClientes() {
    try {
      this.#clientes = await api.get('/clientes');
      this.#renderLista(this.#clientes);
    } catch (e) {
      this.outlet.querySelector('#tbody-clientes').innerHTML =
        `<tr><td colspan="4" class="px-3 py-4 text-center text-red-400">${e.message}</td></tr>`;
    }
  }

  #renderLista(clientes) {
    const tbody = this.outlet.querySelector('#tbody-clientes');
    if (!clientes.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Sin clientes aún.</td></tr>`;
      return;
    }
    tbody.innerHTML = clientes.map(c => `
      <tr class="hover:bg-gray-50 cursor-pointer" data-id="${c.id}">
        <td class="px-3 py-2 font-medium">${c.nombre}</td>
        <td class="px-3 py-2 hidden sm:table-cell text-gray-500">
          ${c.numero_documento ? `${c.tipo_documento} ${c.numero_documento}` : '—'}
        </td>
        <td class="px-3 py-2 hidden md:table-cell text-gray-500">${c.condicion_iva ?? '—'}</td>
        <td class="px-3 py-2 text-right">
          <i class="fas fa-pencil text-sm text-blue-400"></i>
        </td>
      </tr>`).join('');
  }

  #filtrar(q) {
    const lower = q.toLowerCase();
    this.#renderLista(
      this.#clientes.filter(c =>
        c.nombre.toLowerCase().includes(lower) ||
        (c.numero_documento ?? '').includes(lower) ||
        (c.email ?? '').toLowerCase().includes(lower)
      )
    );
  }

  #mostrarForm(id) {
    this.#editId = id;
    const esNuevo = id === null;

    this.outlet.querySelector('#form-titulo').textContent =
      esNuevo ? 'Nuevo cliente' : 'Editar cliente';
    this.outlet.querySelector('#btn-eliminar').classList.toggle('hidden', esNuevo);

    const form = this.outlet.querySelector('#form-cliente');
    form.reset();

    if (!esNuevo) {
      const c = this.#clientes.find(x => x.id === id);
      if (c) CAMPOS_FORM.forEach(f => {
        const el = form.querySelector(`[name="${f}"]`);
        if (el && c[f] != null) el.value = c[f];
      });
    }

    this.outlet.querySelector('#panel-lista').classList.add('hidden');
    this.outlet.querySelector('#panel-form').classList.remove('hidden');
    this.outlet.querySelector('#panel-form').classList.add('flex');
  }

  #mostrarLista() {
    this.outlet.querySelector('#panel-form').classList.add('hidden');
    this.outlet.querySelector('#panel-form').classList.remove('flex');
    this.outlet.querySelector('#panel-lista').classList.remove('hidden');
  }

  async #guardar() {
    const form = this.outlet.querySelector('#form-cliente');
    const btn  = this.outlet.querySelector('#btn-guardar');
    const data = Object.fromEntries(new FormData(form));

    btn.disabled = true;
    try {
      if (this.#editId === null) {
        const nuevo = await api.post('/clientes', data);
        this.#clientes.push(nuevo);
      } else {
        const actualizado = await api.put(`/clientes/${this.#editId}`, data);
        const idx = this.#clientes.findIndex(c => c.id === this.#editId);
        if (idx !== -1) this.#clientes[idx] = actualizado;
      }
      this.#mostrarLista();
      this.#renderLista(this.#clientes);
    } catch (e) {
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  }

  async #eliminar() {
    if (!confirm('¿Eliminar este cliente?')) return;
    try {
      await api.delete(`/clientes/${this.#editId}`);
      this.#clientes = this.#clientes.filter(c => c.id !== this.#editId);
      this.#mostrarLista();
      this.#renderLista(this.#clientes);
    } catch (e) {
      alert(e.message);
    }
  }
}
