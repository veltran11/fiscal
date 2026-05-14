import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { eventBus } from '../utils/EventBus.js';
import { clienteState } from './ClienteState.js';

export class ClientesView extends BaseView {
  #clientes = [];
  #onActualizado = null;

  template() {
    return `
      <div class="inner flex flex-col gap-4">
        <h2 class="tit">
          <i class="fas fa-chevron-left pr-4"></i>
          <div>Clientes</div>
        </h2>

        <div class="btns">
          <input id="buscar" type="search" placeholder="Buscar…" class="flex-1">

          <button type="button" id="btn-nuevo" data-route="cliente-form">
            <i class="fas fa-plus"></i>
            <div>Nuevo</div>
          </button>
        </div>

        <div class="frame bg-white flex-1">
          <div id="clientes-grd" class="grd rows grid-cols-1 md:grid-cols-[2fr_repeat(2,1fr)]">
            <div class="header contents">
              <div>Nombre</div>
              <div class="hidden md:block">CUIT</div>
              <div class="hidden md:block">Condición IVA</div>
            </div>

            <div class="contents">
              <div class="col-span-3 text-gray-400">Cargando…</td></tr>
            </div>
          </div>
        </div>
      </div>`;
  }

  // Llamado por DashboardView cada vez que esta vista se hace visible
  onShow() {
    this.#cargarClientes();
  }

  afterRender() {
    this.#cargarClientes();

    this.outlet.querySelector('#btn-nuevo')
      ?.addEventListener('click', () => { clienteState.cliente = null; });

    this.outlet.querySelector('#buscar')
      ?.addEventListener('input', e => this.#filtrar(e.target.value));

    this.outlet.querySelector('#clientes-grd')
      ?.addEventListener('click', e => {
        const fila = e.target.closest('[data-id]');
        if (!fila) return;
        clienteState.cliente = this.#clientes.find(c => c.id === Number(fila.dataset.id)) ?? null;
        if (clienteState.cliente) clienteState.cliente = { ...clienteState.cliente };
      });

    this.#onActualizado = ({ cliente, eliminado }) => {
      if (eliminado) {
        this.#clientes = this.#clientes.filter(c => c.id !== cliente.id);
      } else if (clienteState.cliente === null) {
        this.#clientes.push(cliente);
      } else {
        const idx = this.#clientes.findIndex(c => c.id === cliente.id);
        if (idx !== -1) this.#clientes[idx] = cliente;
      }
      this.#renderLista(this.#clientes);
    };
    eventBus.on('clientes:actualizado', this.#onActualizado);
  }

  async #cargarClientes() {
    try {
      this.#clientes = await api.get('/clientes');
      this.#renderLista(this.#clientes);
    } catch (e) {
      this.outlet.querySelector('#clientes-grd').insertAdjacentHTML('beforeend', `<div class="contents"><div class="col-span-3 text-center text-red-400">${e.message}</div></div>`);
    }
  }

  #renderLista(clientes) {
    const tbody = this.outlet.querySelector('#clientes-grd');

    [...tbody.children].filter(h => !h.classList.contains('header')).forEach(h => h.remove());

    if (!clientes.length) {
      tbody.insertAdjacentHTML('beforeend', `<div class="contents"><div class="col-span-3 text-center text-gray-400">Sin clientes aún.</div></div>`);
      return;
    }

    var row = clientes.map(c => `
      <div class="contents" data-id="${c.id}" data-route="cliente-form">
        <div class="">${c.nombre}</div>
        <div class="hidden md:block text-gray-500">${c.cuit}</div>
        <div class="hidden md:block text-gray-500">${c.cond_iva}</div>
      </div>`).join('');

    tbody.insertAdjacentHTML('beforeend', row);
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

  destroy() {
    if (this.#onActualizado) eventBus.off('clientes:actualizado', this.#onActualizado);
    super.destroy();
  }
}
