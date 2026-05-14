const API_BASE = new URL('../../api', import.meta.url).pathname;

export class ApiService {
  #tokenKey = 'auth_token';

  getToken() {
    return localStorage.getItem(this.#tokenKey);
  }

  setToken(token) {
    localStorage.setItem(this.#tokenKey, token);
  }

  removeToken() {
    localStorage.removeItem(this.#tokenKey);
  }

  #buildHeaders(extra = {}) {
    const headers = { 'Content-Type': 'application/json', ...extra };
    const token = this.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
    return headers;
  }

  async #request(method, endpoint, body = null) {
    const options = { method, headers: this.#buildHeaders() };
    if (body) options.body = JSON.stringify(body);

    const res = await fetch(`${API_BASE}${endpoint}`, options);
    const data = await res.json().catch(() => ({}));

    if (!res.ok) throw { status: res.status, message: data.message ?? 'Error desconocido' };
    return data.data ?? data;
  }

  get(endpoint)             { return this.#request('GET',    endpoint); }
  post(endpoint, body)      { return this.#request('POST',   endpoint, body); }
  put(endpoint, body)       { return this.#request('PUT',    endpoint, body); }
  delete(endpoint)          { return this.#request('DELETE', endpoint); }

  async upload(endpoint, formData) {
    const headers = {};
    const token = this.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res  = await fetch(`${API_BASE}${endpoint}`, { method: 'POST', headers, body: formData });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw { status: res.status, message: data.message ?? 'Error desconocido' };
    return data.data ?? data;
  }
}

export const api = new ApiService();
