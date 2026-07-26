const router = {
    routes: {},
    currentRoute: null,

    register(path, handler) {
        this.routes[path] = handler;
    },

    async navigate(path) {
        history.pushState(null, '', path);
        await this.handleRoute();
    },

    async handleRoute() {
        const path = location.pathname;
        showLoading(true);

        try {
            const handler = this.routes[path] || this.routes['*'];
            if (handler) await handler();
            else {
                document.getElementById('pageContent').innerHTML =
                    '<div class="empty-state"><h3>Page Not Found</h3><p>The page you are looking for does not exist.</p></div>';
            }
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            showLoading(false);
            updateNavbar();
            window.scrollTo(0, 0);
        }
    },
};

window.addEventListener('popstate', () => router.handleRoute());
