const API_BASE_URL = '/';

class RetinboxAPI {
  async request(endpoint, method = 'GET', data = null) {
    if (!endpoint.endsWith('.php') && !endpoint.includes('?')) {
      endpoint += '.php';
    }
    const url = `${API_BASE_URL}${endpoint}`;

    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'include'
    };

    if (data) {
      options.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(url, options);
      const text = await response.text();
      let result;

      try {
        result = JSON.parse(text);
      } catch (e) {
        throw new Error(`Server Error (${response.status})`);
      }

      if (result.success === false || result.error) {
        throw new Error(result.error || 'Unknown Error');
      }
      return result;
    } catch (error) {
      console.error(error);
      throw error;
    }
  }

  async upload(endpoint, formData) {
    if (!endpoint.endsWith('.php')) endpoint += '.php';
    const url = `${API_BASE_URL}${endpoint}`;

    try {
      const response = await fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });
      const text = await response.text();
      let result;
      try {
        result = JSON.parse(text);
      } catch (e) {
        throw new Error(`Server Error (${response.status})`);
      }

      if (result.success === false || result.error) {
        throw new Error(result.error || 'Upload Failed');
      }
      return result;
    } catch (error) {
      throw error;
    }
  }

  async register(email, password, username) {
    return this.request('auth/register', 'POST', { email, password, username });
  }
  async login(email, password) {
    const res = await this.request('auth/login', 'POST', { email, password });
    if (res.user) {
      sessionStorage.setItem('currentUser', JSON.stringify(res.user));
      this._notifyListeners(res.user);
    }
    return res;
  }
  async logout() {
    await this.request('auth/logout', 'POST');
    sessionStorage.removeItem('currentUser');
    this._notifyListeners(null);
  }
  async createBankAccount() { return this.request('bank/createAccount', 'POST'); }
  async getBankAccount() { return this.request('bank/getAccount').then(r => r.account); }
  async addTransaction(amount, type, description) { return this.request('bank/addTransaction', 'POST', { amount, type, description }); }
  async addLibraryRecord(bookId, bookTitle) { return this.request('library/addRecord', 'POST', { bookId, bookTitle }); }
  async getLibraryRecords() { return this.request('library/getRecords').then(r => r.records || []); }
  async updateUserSettings(data) { return this.request('settings/updateSettings', 'POST', data); }
  async getUserSettings() { return this.request('settings/getSettings').then(r => r.settings); }
  async uploadDocument(formData) { return this.upload('library/upload-document', formData); }

  _listeners = [];
  onAuthStateChanged(callback) {
    this._listeners.push(callback);
    const saved = sessionStorage.getItem('currentUser');
    if (saved) callback(JSON.parse(saved));
    else callback(null);
  }
  _notifyListeners(user) { this._listeners.forEach(cb => cb(user)); }
}

window.retinbox = new RetinboxAPI();
window.userDataManager = window.retinbox;

(async () => {
  try {
    const res = await window.retinbox.request('auth/getCurrentUser');
    if (res.user) {
      sessionStorage.setItem('currentUser', JSON.stringify(res.user));
      window.retinbox._notifyListeners(res.user);
    } else {
      sessionStorage.removeItem('currentUser');
      window.retinbox._notifyListeners(null);
    }
  } catch (e) {}
})();