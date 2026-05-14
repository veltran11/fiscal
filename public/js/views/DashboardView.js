import { BaseView }         from './BaseView.js';
import { ClientesView }     from './ClientesView.js';
import { MiCuentaView }     from './MiCuentaView.js';
import { CertificadosView } from './CertificadosView.js';

const SUB_VIEWS = {
  'clientes':     ClientesView,
  'mi-cuenta':    MiCuentaView,
  'certificados': CertificadosView,
};

const MD = 768;

export class DashboardView extends BaseView {
  #views      = new Map(); // ViewClass → { instance, el }
  #current    = null;      // ViewClass actualmente visible
  #history    = [];        // stack de ViewClass
  #sidebar    = null;
  #outlet2    = null;
  #backBtn    = null;
  #backHandler = null;
  #mq         = null;
  #mqListener = null;

  template() {
    return `
      <div class="flex h-full">
        <aside id="db-sidebar" class="md:mr-4 w-full md:w-80 md:shrink-0 flex flex-col">
          <h2 class="text-lg font-semibold mb-2">Menú</h2>
          <div class="frame grd-out">
            <nav class="grd grid-cols-1 divide-y">
              <a href="#" data-route="clientes">Clientes</a>
              <a href="#" data-route="mi-cuenta">Mi cuenta</a>
            </nav>
          </div>
        </aside>
        <div id="db-outlet" class="flex-1 flex-col overflow-hidden hidden md:flex"></div>
      </div>`;
  }

  afterRender() {
    this.#sidebar = document.getElementById('db-sidebar');
    this.#outlet2 = document.getElementById('db-outlet');

    this.#mq = window.matchMedia(`(min-width: ${MD}px)`);
    this.#mqListener = ({ matches }) => {
      if (matches) {
        this.#sidebar.classList.remove('hidden');
        this.#outlet2.classList.remove('hidden');
        this.#detachBackBtn();
      }
    };
    this.#mq.addEventListener('change', this.#mqListener);

    this.outlet.addEventListener('click', e => {
      const el = e.target.closest('[data-route]');
      if (!el) return;
      e.preventDefault();
      const ViewClass = SUB_VIEWS[el.dataset.route];
      if (!ViewClass) return;
      if (this.#sidebar.contains(el)) this.#history = [];
      this.#go(ViewClass);
    });
  }

  // ── navegación ────────────────────────────────────────────────────────────

  #go(ViewClass) {
    if (ViewClass === this.#current) return;

    this.#detachBackBtn();

    // Ocultar vista actual
    if (this.#current) {
      this.#views.get(this.#current)?.el.classList.add('hidden');
      this.#history.push(this.#current);
    }

    // Crear o mostrar vista destino
    const entry = this.#getOrCreate(ViewClass);
    entry.el.classList.remove('hidden');
    this.#current = ViewClass;

    if (window.innerWidth < MD) {
      this.#sidebar.classList.add('hidden');
      this.#outlet2.classList.remove('hidden');
      this.#outlet2.classList.add('flex');
    }

    this.#attachBackBtn();
  }

  #goBack() {
    this.#detachBackBtn();

    if (this.#current) {
      this.#views.get(this.#current)?.el.classList.add('hidden');
    }

    const prev = this.#history.pop();
    if (prev) {
      this.#views.get(prev)?.el.classList.remove('hidden');
      this.#current = prev;
      this.#attachBackBtn();
    } else {
      this.#current = null;
      if (window.innerWidth < MD) {
        this.#sidebar.classList.remove('hidden');
        this.#outlet2.classList.add('hidden');
        this.#outlet2.classList.remove('flex');
      }
    }
  }

  // ── keep-alive ────────────────────────────────────────────────────────────

  #getOrCreate(ViewClass) {
    if (this.#views.has(ViewClass)) return this.#views.get(ViewClass);

    const id = `db-view-${this.#views.size}`;
    const el = document.createElement('div');
    el.id        = id;
    el.className = 'h-full w-full overflow-hidden hidden';
    this.#outlet2.appendChild(el);

    const instance = new ViewClass(id);
    instance.render();

    const entry = { instance, el };
    this.#views.set(ViewClass, entry);
    return entry;
  }

  // ── back button ───────────────────────────────────────────────────────────

  #attachBackBtn() {
    const el = this.#views.get(this.#current)?.el;
    this.#backBtn = el?.querySelector('.tit i') ?? null;
    if (this.#backBtn) {
      this.#backHandler = () => this.#goBack();
      this.#backBtn.classList.add('cursor-pointer');
      this.#backBtn.addEventListener('click', this.#backHandler);
    }
  }

  #detachBackBtn() {
    if (this.#backBtn && this.#backHandler) {
      this.#backBtn.removeEventListener('click', this.#backHandler);
      this.#backBtn.classList.remove('cursor-pointer');
    }
    this.#backBtn    = null;
    this.#backHandler = null;
  }

  // ── lifecycle ─────────────────────────────────────────────────────────────

  destroy() {
    this.#mq?.removeEventListener('change', this.#mqListener);
    this.#detachBackBtn();
    this.#views.forEach(({ instance }) => instance?.destroy?.());
    this.#views.clear();
    super.destroy();
  }
}
