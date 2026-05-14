import { BaseView } from './BaseView.js';
import { eventBus } from '../utils/EventBus.js';
import { api } from '../services/ApiService.js';

export class MiCuentaView extends BaseView {
  template() {
    return `
      <div class="inner flex flex-col">
        <h2 class="tit text-lg font-semibold mb-2 flex items-center">
          <i class="fas fa-chevron-left pr-4"></i>
          <div>Mi cuenta</div>
        </h2>
        
        <form class="overflow-y-auto pb-4 flex flex-col gap-4">
          <section class="frame p-4 bg-white">
            <h3 class="font-bold text-gray-600 mb-4">Identificación</h3>
          
            <div class="grid grid-cols-1 gap-4">
              <div class="grid grid-cols-[150px_1fr] gap-4">
                <div class="fld">
                  <label>CUIT</label>
                  <input id="cuit" type="text" name="cuit" maxlength="13" class="w-full">
                </div>
            
                <div class="fld">
                  <label>Razón social</label>
                  <input id="razon_social" type="text" name="razon_social" class="w-full">
                </div>
              </div>
            </div>

            <div class="btns justify-end">
              <button type="button" data-route="certificados">
                <div>Certificados</div>
                <i class="fas fa-toggle-off"></i>
              </button>
              
              <button type="button" id="btn-padron">
                <div>Traer datos fiscales</div>
                <i id="icon-padron" class="fas fa-rotate-right"></i>
              </button>
            </div>
          </section>

          <section class="frame p-4 bg-white">
            <h3 class="font-bold text-gray-600 mb-4">Datos fiscales</h3>

            <div class="flex flex-col gap-y-4">
              <div class="fld">
                <label>Nombre de fantasía</label>
                <input id="nombre_fantasia" type="text" name="nombre_fantasia" class="w-full">
              </div>
            
              <div class="grid grid-cols-2 gap-4">
                <div class="fld">
                  <label>Inicio de actividades</label>
                  <input type="text" name="inicio_actividades" placeholder="MM/AAAA" class="w-full" readonly>
                </div>
                <div class="fld">
                  <label>Punto de venta</label>
                  <select name="punto_venta" class="w-full"></select>
                </div>
              </div>

              <div class="fld">
                <label>Dirección</label>
                <input type="text" name="domicilio_fiscal" class="w-full" readonly>
              </div>

              <div class="grid grid-cols-[2fr_1fr] gap-4">
                <div class="fld">
                  <label>Localidad</label>
                  <input type="text" name="localidad" class="w-full" readonly>
                </div>

                <div class="fld">
                  <label>Código postal</label>
                  <input type="text" name="codigo_postal" maxlength="10" class="w-full" readonly>
                </div>
              </div>
            </div>
          </section>
        </form>
      </div>`;
  }

  afterRender() {
    api.get('/cuenta').then(d => this.#poblar(d)).catch(() => { });

    document.getElementById('cuit')?.addEventListener('input', function () {
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

    document.getElementById('btn-cerrar-sesion')?.addEventListener('click', () => {
      eventBus.emit('auth:logout');
    });

    document.getElementById('btn-padron')?.addEventListener('click', () => this.#traerPadron());
  }

  async #traerPadron() {
    const btn = document.getElementById('btn-padron');
    const icon = document.getElementById('icon-padron');
    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';
    try {
      const d = await api.post('/cuenta/padron');
      this.#poblar(d);
      icon.className = 'fas fa-check text-green-600';
    } catch (e) {
      icon.className = 'fas fa-triangle-exclamation text-red-500';
      alert(e.message);
    } finally {
      btn.disabled = false;
    }
  }

  #poblar(d) {
    const set = (name, val) => {
      const el = this.outlet.querySelector(`[name="${name}"]`);
      if (el && val != null) el.value = val;
    };
    set('cuit', d.cuit);
    set('razon_social', d.razon_social);
    set('nombre_fantasia', d.nombre_fantasia);
    set('inicio_actividades', d.inicio_actividades);
    set('domicilio_fiscal', d.domicilio_fiscal);
    set('localidad', d.localidad);
    set('codigo_postal', d.codigo_postal);

    const sel = this.outlet.querySelector('[name="punto_venta"]');
    if (sel) {
      if (d.puntos_venta?.length) {
        sel.innerHTML = d.puntos_venta
          .map(n => `<option value="${n}">${String(n).padStart(5, '0')}</option>`)
          .join('');
      }
      if (d.punto_venta) sel.value = d.punto_venta;
    }

    const iconCert = this.outlet.querySelector('[data-route="certificados"] i');
    if (iconCert && d.tiene_cert !== undefined) {
      const activo = d.tiene_cert && d.tiene_key;
      iconCert.className = activo ? 'fas fa-toggle-on text-green-600' : 'fas fa-toggle-off';
    }
  }
}
