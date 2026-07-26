const API = {
    baseUrl: '/api',
    token: localStorage.getItem('token'),

    setToken(token) {
        this.token = token;
        if (token) localStorage.setItem('token', token);
        else localStorage.removeItem('token');
    },

    async request(method, path, data = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (this.token) opts.headers['Authorization'] = `Bearer ${this.token}`;
        if (data && method !== 'GET') opts.body = JSON.stringify(data);

        try {
            const res = await fetch(`${this.baseUrl}${path}`, opts);
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || 'Request failed');
            return json;
        } catch (err) {
            if (err.message.includes('Unauthorized')) {
                this.setToken(null);
                router.navigate('/login');
            }
            throw err;
        }
    },

    async upload(method, path, formData) {
        const opts = { method };
        if (this.token) opts.headers = { 'Authorization': `Bearer ${this.token}` };
        try {
            const res = await fetch(`${this.baseUrl}${path}`, opts);
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || 'Upload failed');
            return json;
        } catch (err) {
            if (err.message.includes('Unauthorized')) {
                this.setToken(null);
                router.navigate('/login');
            }
            throw err;
        }
    },

    get(path) { return this.request('GET', path); },
    post(path, data) { return this.request('POST', path, data); },
    patch(path, data) { return this.request('PATCH', path, data); },
    delete(path) { return this.request('DELETE', path); },
};
