import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';
import { eventBus } from '../utils/EventBus.js';
import { facturaState } from './FacturaState.js';

export class FacturasView extends BaseView {
  #facturas = [];
  #onActualizado = null;

  template() {
    return `
      <div class="inner flex flex-col gap-4">
        <h2 class="tit">
          <i class="fas fa-chevron-left pr-4"></i>
          <div>Facturas</div>
        </h2>

        <div class="btns">
          <input id="buscar-factura" type="search" placeholder="Buscar…" class="flex-1">

          <button type="button" id="btn-nueva-factura" data-route="factura-form">
            <i class="fas fa-plus"></i>
            <div>Nueva</div>
          </button>
        </div>

        <div class="frame bg-white flex-1">
          <div id="facturas-grd" class="grd items-stretch rows grid-cols-2 md:grid-cols-[2fr_1fr_1fr_min-content]">
            <div class="header contents">
              <div class="pt-2 md:pt-0">Cliente</div>
              <div class="text-right hidden md:block">Número</div>
              <div class="text-center hidden md:block">Fecha</div>
              <div class="text-right">Importe</div>
            </div>

            <div class="contents">
              <div class="col-span-3 text-gray-400">Cargando…</div>
            </div>
          </div>
        </div>
      </div>`;
  }

  afterRender() {
    this.#cargarFacturas();

    this.outlet.querySelector('#btn-nueva-factura')
      ?.addEventListener('click', () => { facturaState.factura = null; });

    this.outlet.querySelector('#buscar-factura')
      ?.addEventListener('input', e => this.#filtrar(e.target.value));

    this.#onActualizado = ({ factura }) => {
      this.#facturas.push(factura);
      this.#renderLista(this.#facturas);
    };
    eventBus.on('facturas:actualizado', this.#onActualizado);
  }

  async #cargarFacturas() {
    try {
      this.#facturas = await api.get('/facturas');
      this.#renderLista(this.#facturas);
    } catch (e) {
      this.outlet.querySelector('#facturas-grd').insertAdjacentHTML('beforeend',
        `<div class="contents"><div class="col-span-3 text-center text-red-400">${e.message}</div></div>`);
    }
  }

  #renderLista(facturas) {
    const grd = this.outlet.querySelector('#facturas-grd');

    [...grd.children].filter(h => !h.classList.contains('header')).forEach(h => h.remove());

    if (!facturas.length) {
      grd.insertAdjacentHTML('beforeend',
        `<div class="contents"><div class="col-span-3 text-center text-gray-400">Sin facturas aún.</div></div>`);
      return;
    }

    const rows = facturas.map(f => `
      <div class="contents">
        <div>${f.cliente_nombre}</div>
        <div class="text-right hidden md:block text-gray-500">${String(f.numero).padStart(5, '0')}</div>
        <div class="text-center whitespace-nowrap hidden md:block text-gray-500">${f.fecha ? f.aux = new Date(f.fecha).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''}</div>
        <div class="text-right font-bold md:font-normal">
          <div class="text-xs md:hidden text-gray-400">${f.aux}</div>
          <div>$ ${f.monto}</div>
        </div>
      </div>`).join('');

    grd.insertAdjacentHTML('beforeend', rows);
  }

  #filtrar(q) {
    const lower = q.toLowerCase();
    this.#renderLista(
      this.#facturas.filter(f =>
        (f.cliente_nombre ?? '').toLowerCase().includes(lower) ||
        String(f.numero).includes(lower) ||
        (f.fecha ?? '').includes(lower)
      )
    );
  }

  destroy() {
    if (this.#onActualizado) eventBus.off('facturas:actualizado', this.#onActualizado);
    super.destroy();
  }
}
