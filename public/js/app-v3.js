import { Router } from './router-v2/Router.js';
import { LoginView } from './views-v2/LoginView.js';
import { RegisterView } from './views-v2/RegisterView.js';
import { OlvideView } from './views-v2/OlvideView.js';
import { DashboardView } from './views-v2/DashboardView.js';
import { MiCuentaView } from './views-v2/MiCuentaView.js';
import { Navbar } from './components-v2/Navbar.js';
import { auth } from './services-v2/AuthService.js';
import { eventBus } from './utils-v2/EventBus.js';

class App {
  #router;
  #navbar;

  constructor() {
    this.#router = new Router();
    this.#navbar = new Navbar();
  }

  #registerRoutes() {
    this.#router
      .register('login', LoginView)
      .register('register', RegisterView)
      .register('olvide', OlvideView)
      .register('dashboard', DashboardView)
      .register('mi-cuenta', MiCuentaView);
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
