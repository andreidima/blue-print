import 'bootstrap/dist/css/bootstrap.min.css'; // ✅ Ensure Bootstrap CSS is included
import * as bootstrap from 'bootstrap'; // ✅ Ensure Bootstrap JavaScript is imported globally

window.bootstrap = bootstrap; // ✅ Make Bootstrap available globally

import '../css/andrei.css';

import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}
window.axios = axios;

import { createApp } from 'vue';

// Import other components
import VueDatepickerNext from './components/DatePicker.vue';
import ClientSelector from './components/ClientSelector.vue';
import ProductSelector from './components/ProductSelector.vue';

// App pentru DatePicker
const datePicker = createApp({});
datePicker.component('vue-datepicker-next', VueDatepickerNext);
if (document.getElementById('datePicker') != null) {
    datePicker.mount('#datePicker');
}

document.querySelectorAll('.js-client-selector').forEach((el) => {
    const app = createApp(ClientSelector, {
        name: el.dataset.name || 'client_id',
        searchUrl: el.dataset.searchUrl,
        storeUrl: el.dataset.storeUrl,
        initialClientId: el.dataset.initialClientId || '',
        initialClientLabel: el.dataset.initialClientLabel || '',
        invalid: el.dataset.invalid === '1',
    });

    app.mount(el);
});

document.querySelectorAll('.js-product-selector').forEach((el) => {
    const app = createApp(ProductSelector, {
        name: el.dataset.name || 'produs_id',
        searchUrl: el.dataset.searchUrl,
        storeUrl: el.dataset.storeUrl,
        initialProductId: el.dataset.initialProductId || '',
        initialProductLabel: el.dataset.initialProductLabel || '',
        invalid: el.dataset.invalid === '1',
    });

    app.mount(el);
});
