const router = {
    routes: [],
    currentRoute: null,

    register(path, handler) {
        this.routes.push({ path, handler });
    },

    async navigate(path) {
        history.pushState(null, '', path);
        await this.handleRoute();
    },

    async handleRoute() {
        const path = location.pathname;
        showLoading(true);

        try {
            let handler = null;
            let params = null;

            for (const route of this.routes) {
                if (typeof route.path === 'string' && route.path === path) {
                    handler = route.handler;
                    break;
                }
                if (route.path instanceof RegExp) {
                    const match = path.match(route.path);
                    if (match) {
                        handler = route.handler;
                        params = match.slice(1);
                        break;
                    }
                }
            }

            if (!handler) {
                for (const route of this.routes) {
                    if (route.path === '*') {
                        handler = route.handler;
                        break;
                    }
                }
            }

            if (handler) {
                if (params) {
                    await handler(...params);
                } else {
                    await handler();
                }
            } else {
                document.getElementById('pageContent').innerHTML =
                    '<div class="empty-state"><h3>Page Not Found</h3><p>The page you are looking for does not exist.</p></div>';
            }
        } catch (err) {
            document.getElementById('pageContent').innerHTML =
                '<div class="empty-state"><h3>Something went wrong</h3><p>' + esc(err.message) + '</p></div>';
        } finally {
            showLoading(false);
            updateNavbar();
            window.scrollTo(0, 0);
        }
    },
};

window.addEventListener('popstate', () => router.handleRoute());
