import { api } from './ApiService.js';
import { eventBus } from '../utils/EventBus.js';

export class AuthService {
  #user = null;

  async login(email, password) {
    const data = await api.post('/auth/login', { email, password });
    api.setToken(data.token);
    this.#user = data.user;
    eventBus.emit('auth:login', this.#user);
    return this.#user;
  }

  logout() {
    api.removeToken();
    this.#user = null;
    eventBus.emit('auth:logout');
  }

  async fetchUser() {
    if (!api.getToken()) return null;
    try {
      this.#user = await api.get('/auth/me');
      return this.#user;
    } catch {
      this.logout();
      return null;
    }
  }

  getUser()        { return this.#user; }
  isLoggedIn()     { return !!api.getToken(); }
}

export const auth = new AuthService();
