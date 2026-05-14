import { BaseView } from './BaseView.js';
import { api } from '../services/ApiService.js';

export class CertificadosView extends BaseView {
  #estado = null;

  template() {
    return `
      <div class="inner flex flex-col gap-4 w-full">
        <h2 class="tit">
          <i class="fas fa-chevron-left pr-4 !block"></i>
          <div>Certificados AFIP</div>
        </h2>
        <div id="cert-body" class="overflow-y-auto flex flex-col gap-4 pr-1">
          <p class="text-sm text-gray-400">Cargando...</p>
        </div>
      </div>`;
  }

  afterRender() {
    this.#cargar();
  }

  async #cargar() {
    try {
      this.#estado = await api.get('/certificados');
      this.#render();
    } catch (e) {
      document.getElementById('cert-body').innerHTML =
        `<p class="text-sm text-red-600">${e.message}</p>`;
    }
  }

  #render() {
    const s = this.#estado;
    document.getElementById('cert-body').innerHTML =
      this.#cardEstado(s) +
      this.#cardGenerar(s) +
      this.#cardArca(s) +
      this.#cardSubir(s);
    this.#bindEvents();
  }

  // ── helpers ──────────────────────────────────────────────────────────────

  #badge(tipo, texto) {
    const bg = { ok: 'bg-green-50 text-green-700', warn: 'bg-yellow-50 text-yellow-700', miss: 'bg-gray-100 text-gray-400' };
    const dot = { ok: 'bg-green-500', warn: 'bg-yellow-400', miss: 'bg-gray-300' };
    return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${bg[tipo]}">
      <span class="w-2 h-2 rounded-full ${dot[tipo]}"></span>${texto}</span>`;
  }

  #certBadge(s) {
    if (!s.tieneCert) return this.#badge('miss', 'Pendiente');
    if (!s.certVence) return this.#badge('ok', 'Instalado');
    if (s.certVence < s.ahora) return this.#badge('warn', 'Vencido');
    if (s.certVence < s.ahora + 30 * 86400) return this.#badge('warn', 'Vence pronto');
    return this.#badge('ok', 'Activo');
  }

  #stepNum(n, estado) {
    const cls = { done: 'bg-green-500 text-white', act: 'bg-blue-500 text-white', pend: 'bg-gray-200 text-gray-400' };
    const lbl = estado === 'done' ? '<i class="fas fa-check text-xs"></i>' : n;
    return `<span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${cls[estado]}">${lbl}</span>`;
  }

  // ── cards ─────────────────────────────────────────────────────────────────

  #cardEstado(s) {
    const vence = s.tieneCert && s.certVence
      ? `<p class="text-xs text-gray-400 mt-3">Vence el <strong>${new Date(s.certVence * 1000).toLocaleDateString('es-AR')}</strong>${s.certCN ? ' · ' + s.certCN : ''}</p>`
      : '';
    return `
      <div class="frame bg-white p-4">
        <h3 class="font-semibold text-gray-600 mb-3">Estado</h3>
        <div class="grid grid-cols-3 gap-2 text-center">
          <div><p class="text-xs text-gray-400 mb-1">Clave privada</p>${this.#badge(s.tieneKey ? 'ok' : 'miss', s.tieneKey ? 'Generada' : 'Pendiente')}</div>
          <div><p class="text-xs text-gray-400 mb-1">CSR</p>           ${this.#badge(s.tieneCsr ? 'ok' : 'miss', s.tieneCsr ? 'Generado' : 'Pendiente')}</div>
          <div><p class="text-xs text-gray-400 mb-1">Certificado</p>   ${this.#certBadge(s)}</div>
        </div>
        ${vence}
      </div>`;
  }

  #cardGenerar(s) {
    const hecho = s.tieneKey && s.tieneCsr;
    const btnCls = hecho ? 'px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-sm font-medium'
      : 'px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium';
    const aviso = hecho ? `<p class="text-xs text-yellow-700 bg-yellow-50 rounded p-2 mb-3">Ya tenés clave y CSR. Podés regenerarlos pero deberás volver a presentarlo en AFIP.</p>` : '';
    const btnCsr = hecho ? `<button id="btn-csr" class="px-3 py-1.5 border rounded text-sm font-medium hover:bg-gray-50">Descargar CSR</button>` : '';
    return `
      <div class="frame bg-white p-4">
        <div class="flex items-center gap-3 mb-3">
          ${this.#stepNum(1, hecho ? 'done' : 'act')}
          <span class="font-semibold text-gray-700">Generá tu clave privada y CSR</span>
        </div>
        <p class="text-sm text-gray-500 mb-3">La app genera una clave privada RSA 2048 y una solicitud firmada con tus datos fiscales. La clave nunca se comparte.</p>
        ${aviso}
        <div class="flex gap-2 flex-wrap items-center">
          <button id="btn-generar" class="${btnCls}">${hecho ? 'Regenerar clave y CSR' : 'Generar clave y CSR'}</button>
          ${btnCsr}
          <span class="text-xs text-gray-400">${s.cuit} · ${s.razonSocial}</span>
        </div>
        <div id="msg-generar" class="mt-2"></div>
      </div>`;
  }

  #cardArca(s) {
    const paso = s.tieneCert ? 'done' : (s.tieneCsr ? 'act' : 'pend');
    return `
      <div class="frame bg-white p-4">
        <div class="flex items-center gap-3 mb-3">
          ${this.#stepNum(2, paso)}
          <span class="font-semibold text-gray-700">Crear y autorizar el certificado en ARCA</span>
        </div>
        <div class="text-sm text-gray-500 space-y-4">
          <div>
            <p class="font-medium text-gray-600 mb-1">2a — Crear el certificado</p>
            <ol class="list-decimal list-inside space-y-1">
              <li>Ingresá a <strong>arca.gob.ar</strong> con CUIT y Clave Fiscal (nivel 3)</li>
              <li>Accedé a <strong>"Administración de Certificados Digitales"</strong></li>
              <li>Clic en <strong>"Agregar alias"</strong> y escribí un nombre (ej: <code>facturacion</code>)</li>
              <li>Subí el <code>solicitud_afip.csr</code> descargado en el paso 1</li>
              <li>Descargá el <code>cert.pem</code> que genera ARCA</li>
            </ol>
          </div>
          <div>
            <p class="font-medium text-gray-600 mb-1">2b — Autorizar servicios web</p>
            <p class="mb-2">Con el alias creado: <strong>Nueva relación → buscá el servicio → confirmá</strong></p>
            <div class="font-mono text-xs bg-gray-50 rounded p-3 space-y-1">
              <div><span class="text-gray-400"># wsfe</span> — Facturación Electrónica</div>
              <div><span class="text-gray-400"># ws_sr_padron_a13</span> — Consulta Padrón A13</div>
            </div>
          </div>
        </div>
      </div>`;
  }

  #cardSubir(s) {
    return `
      <div class="frame bg-white p-4">
        <div class="flex items-center gap-3 mb-3">
          ${this.#stepNum(3, s.tieneCert ? 'done' : 'pend')}
          <span class="font-semibold text-gray-700">Subí el certificado firmado por AFIP</span>
        </div>
        <p class="text-sm text-gray-500 mb-3">
          Una vez que AFIP firmó tu solicitud, descargá el <code>cert.pem</code> y subilo acá.
          Si reutilizás un certificado existente, subí también el <code>key.pem</code>.
        </p>
        <form id="form-subir" class="flex flex-col gap-3">
          <div class="fld">
            <label>Archivo cert.pem</label>
            <input type="file" name="cert" accept=".pem,.crt,.cer" class="w-full">
          </div>
          <div class="fld">
            <label>Archivo key.pem <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
            <input type="file" name="key" accept=".pem,.key" class="w-full">
          </div>
          <div>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium">
              Instalar certificado
            </button>
          </div>
          <div id="msg-subir"></div>
        </form>
      </div>`;
  }

  // ── eventos ───────────────────────────────────────────────────────────────

  #bindEvents() {
    document.getElementById('btn-generar')?.addEventListener('click', () => this.#generar());
    document.getElementById('btn-csr')?.addEventListener('click', () => this.#descargarCsr());
    document.getElementById('form-subir')?.addEventListener('submit', e => { e.preventDefault(); this.#subir(e.target); });
  }

  async #generar() {
    const btn = document.getElementById('btn-generar');
    const msg = document.getElementById('msg-generar');
    btn.disabled = true;
    msg.innerHTML = '<span class="text-gray-400 text-sm">Generando...</span>';
    try {
      const data = await api.post('/certificados/generar');
      this.#descargarTexto(data.csr, 'solicitud_afip.csr');
      this.#estado = { ...this.#estado, tieneKey: true, tieneCsr: true };
      this.#render();
    } catch (e) {
      msg.innerHTML = `<span class="text-red-600 text-sm">${e.message}</span>`;
      btn.disabled = false;
    }
  }

  async #descargarCsr() {
    try {
      const data = await api.get('/certificados/csr');
      this.#descargarTexto(data.csr, 'solicitud_afip.csr');
    } catch (e) {
      alert(e.message);
    }
  }

  async #subir(form) {
    const btn = form.querySelector('[type=submit]');
    const msg = document.getElementById('msg-subir');
    btn.disabled = true;
    msg.innerHTML = '<span class="text-gray-400 text-sm">Subiendo...</span>';
    try {
      const data = await api.upload('/certificados/subir', new FormData(form));
      this.#estado = { ...this.#estado, ...data };
      this.#render();
    } catch (e) {
      msg.innerHTML = `<span class="text-red-600 text-sm">${e.message}</span>`;
      btn.disabled = false;
    }
  }

  #descargarTexto(texto, nombre) {
    const url = URL.createObjectURL(new Blob([texto], { type: 'text/plain' }));
    Object.assign(document.createElement('a'), { href: url, download: nombre }).click();
    URL.revokeObjectURL(url);
  }
}
