import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { AppLockProvider } from './components/AppLock/AppLockProvider';
import { initTheme } from './lib/theme';

const appName = import.meta.env.VITE_APP_NAME || 'Savo';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const theme = props.initialPage?.props?.app?.theme;
        const isGuest = !props.initialPage?.props?.auth?.user;
        initTheme(theme, isGuest);
        createRoot(el).render(
            <AppLockProvider>
                <App {...props} />
            </AppLockProvider>,
        );

        router.on('navigate', (event) => {
            const app = event?.detail?.page?.props?.app;
            if (app) {
                const html = document.documentElement;
                if (app.locale) html.setAttribute('lang', app.locale.replace('_', '-'));
                if (app.dir) html.setAttribute('dir', app.dir);
            }
        });
    },
    progress: {
        color: '#00b89b',
    },
});
