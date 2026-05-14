import { Router }        from './router/Router.js';
import { LoginView }     from './views/LoginView.js';
import { DashboardView } from './views/DashboardView.js';
import { MiCuentaView }  from './views/MiCuentaView.js';
import { Navbar }        from './components/Navbar.js';
import { auth }          from './services/AuthService.js';
import { eventBus }      from './utils/EventBus.js';

class App {
  #router;
  #navbar;

  constructor() {
    this.#router = new Router();
    this.#navbar = new Navbar();
  }

  #registerRoutes() {
    this.#router
      .register('login',      LoginView)
      .register('dashboard',  DashboardView)
      .register('mi-cuenta',  MiCuentaView);
  }

  #registerEvents() {
    eventBus.on('navigate', name => {
      this.#navbar.render();
      this.#router.navigate(name);
    });

    eventBus.on('auth:logout', () => {
      eventBus.emit('navigate', 'login');
    });
  }

  async init() {
    this.#registerRoutes();
    this.#registerEvents();

    const user = await auth.fetchUser();
    const startView = user ? 'dashboard' : 'login';

    this.#navbar.render();
    this.#router.navigate(startView);
  }
}

const app = new App();
app.init();
