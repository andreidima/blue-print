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

let appConfirmModalState = null;

const ensureAppConfirmModal = () => {
    if (appConfirmModalState) {
        return appConfirmModalState;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="modal fade" id="app-confirm-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" data-confirm-title>Confirmare</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                    </div>
                    <div class="modal-body" data-confirm-message></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-confirm-cancel>Renunta</button>
                        <button type="button" class="btn btn-danger" data-confirm-accept>Confirma</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const modalEl = wrapper.firstElementChild;
    if (!modalEl) {
        throw new Error('Nu s-a putut initializa modalul de confirmare.');
    }

    document.body.appendChild(modalEl);

    const modalInstance = new bootstrap.Modal(modalEl);
    const titleEl = modalEl.querySelector('[data-confirm-title]');
    const messageEl = modalEl.querySelector('[data-confirm-message]');
    const cancelBtn = modalEl.querySelector('[data-confirm-cancel]');
    const acceptBtn = modalEl.querySelector('[data-confirm-accept]');

    let resolver = null;
    let accepted = false;

    const resolveResult = (result) => {
        if (!resolver) {
            return;
        }

        const resolve = resolver;
        resolver = null;
        resolve(result);
    };

    acceptBtn?.addEventListener('click', () => {
        accepted = true;
        modalInstance.hide();
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        resolveResult(accepted);
        accepted = false;
    });

    appConfirmModalState = {
        modalInstance,
        titleEl,
        messageEl,
        cancelBtn,
        acceptBtn,
        setResolver(nextResolver) {
            resolver = nextResolver;
        },
    };

    return appConfirmModalState;
};

window.AppConfirm = {
    confirm(options = {}) {
        const {
            title = 'Confirmare',
            message = 'Esti sigur?',
            confirmText = 'Confirma',
            cancelText = 'Renunta',
            confirmClass = 'btn-danger',
        } = options;

        const state = ensureAppConfirmModal();

        if (state.titleEl) {
            state.titleEl.textContent = title;
        }
        if (state.messageEl) {
            state.messageEl.textContent = message;
        }
        if (state.acceptBtn) {
            state.acceptBtn.textContent = confirmText;
            state.acceptBtn.className = `btn ${confirmClass}`.trim();
        }
        if (state.cancelBtn) {
            state.cancelBtn.textContent = cancelText;
        }

        return new Promise((resolve) => {
            state.setResolver(resolve);
            state.modalInstance.show();
        });
    },
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (form.matches('[data-ajax-form]')) {
        return;
    }

    const confirmMessage = form.dataset.confirm;
    if (!confirmMessage) {
        return;
    }
    if (form.dataset.confirmApproved === '1') {
        form.dataset.confirmApproved = '0';
        return;
    }

    event.preventDefault();

    window.AppConfirm.confirm({
        title: form.dataset.confirmTitle || 'Confirmare',
        message: confirmMessage,
        confirmText: form.dataset.confirmButton || 'Confirma',
        cancelText: form.dataset.confirmCancel || 'Renunta',
        confirmClass: form.dataset.confirmClass || 'btn-danger',
    }).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        form.dataset.confirmApproved = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    });
}, true);

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
