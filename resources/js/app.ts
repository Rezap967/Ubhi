import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || '';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});
