import 'bootstrap/dist/css/bootstrap.min.css'; // ✅ Ensure Bootstrap CSS is included
import * as bootstrap from 'bootstrap'; // ✅ Ensure Bootstrap JavaScript is imported globally

window.bootstrap = bootstrap; // ✅ Make Bootstrap available globally

import '../css/andrei.css';

import axios from 'axios';
import flatpickr from 'flatpickr';
import { Romanian } from 'flatpickr/dist/l10n/ro.js';
import 'flatpickr/dist/flatpickr.min.css';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}
window.axios = axios;

const DATE_INPUT_SELECTOR = 'input[type="date"], input[type="datetime-local"]';

const normalizeInitialDateValue = (input) => {
    const initialValue = (input.value || '').trim();
    if (initialValue !== '') {
        return;
    }

    const attributeValue = (input.getAttribute('value') || '').trim();
    if (attributeValue === '') {
        return;
    }

    if (input.type === 'date') {
        const match = attributeValue.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
        if (match) {
            const [, day, month, year] = match;
            input.value = `${year}-${month}-${day}`;
        }
        return;
    }

    if (input.type === 'datetime-local') {
        const match = attributeValue.match(/^(\d{2})\.(\d{2})\.(\d{4})[ T](\d{2}):(\d{2})$/);
        if (match) {
            const [, day, month, year, hours, minutes] = match;
            input.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    }
};

const getDatePickerConfig = (input) => {
    const isDateTime = input.type === 'datetime-local';

    const config = {
        altInput: true,
        altInputClass: input.className,
        allowInput: true,
        clickOpens: true,
        disableMobile: true,
        locale: Romanian,
        dateFormat: isDateTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
        altFormat: isDateTime ? 'd.m.Y H:i' : 'd.m.Y',
    };

    if (isDateTime) {
        config.enableTime = true;
        config.time_24hr = true;
        config.minuteIncrement = 1;
    }

    return config;
};

const bindManualInputValidation = (instance) => {
    const visibleInput = instance.altInput;
    if (!visibleInput) {
        return;
    }

    visibleInput.addEventListener('blur', () => {
        const rawValue = visibleInput.value.trim();
        if (rawValue === '') {
            visibleInput.setCustomValidity('');
            instance.clear();
            return;
        }

        const parsedDate = instance.parseDate(rawValue, instance.config.altFormat);
        if (!parsedDate) {
            visibleInput.setCustomValidity('Format invalid. Foloseste formatul corect.');
            visibleInput.reportValidity();
            return;
        }

        visibleInput.setCustomValidity('');
        instance.setDate(parsedDate, true);
    });
};

const initializeGlobalDateInputs = (root = document) => {
    if (!(root instanceof Element) && root !== document) {
        return;
    }

    const inputs = root === document
        ? Array.from(document.querySelectorAll(DATE_INPUT_SELECTOR))
        : [
            ...(root.matches?.(DATE_INPUT_SELECTOR) ? [root] : []),
            ...Array.from(root.querySelectorAll?.(DATE_INPUT_SELECTOR) || []),
        ];

    inputs.forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        if (input.dataset.datepickerReady === '1') {
            return;
        }

        normalizeInitialDateValue(input);
        const pickerInstance = flatpickr(input, getDatePickerConfig(input));
        bindManualInputValidation(pickerInstance);
        input.dataset.datepickerReady = '1';
    });
};

const observeDynamicDateInputs = () => {
    if (!document.body) {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    initializeGlobalDateInputs(node);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
};

initializeGlobalDateInputs();
observeDynamicDateInputs();

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

const createUnsavedChangesGuard = () => {
    const trackedForms = new Set();
    const initialStateByForm = new WeakMap();
    const dirtyForms = new Set();
    const optionsByForm = new WeakMap();
    let lastDirtyForm = null;
    let bypass = false;

    const defaultOptions = {
        title: 'Modificari nesalvate',
        message: 'Doriti salvarea modificarilor facute?',
        confirmText: 'Salveaza',
        cancelText: 'Paraseste fara salvare',
        confirmClass: 'btn-primary',
    };

    const serializeForm = (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return '';
        }

        const formData = new FormData(form);
        const entries = [];
        for (const [key, value] of formData.entries()) {
            if (value instanceof File) {
                entries.push(`${key}=[file:${value.name}:${value.size}]`);
            } else {
                entries.push(`${key}=${String(value)}`);
            }
        }

        return entries.join('&');
    };

    const ensureInitialState = (form) => {
        if (!(form instanceof HTMLFormElement) || !trackedForms.has(form)) {
            return;
        }
        if (!initialStateByForm.has(form)) {
            initialStateByForm.set(form, serializeForm(form));
        }
    };

    const recalculateForm = (form) => {
        if (!(form instanceof HTMLFormElement) || !trackedForms.has(form)) {
            return false;
        }

        ensureInitialState(form);
        const initialState = initialStateByForm.get(form) ?? '';
        const currentState = serializeForm(form);
        const isDirty = currentState !== initialState;

        if (isDirty) {
            dirtyForms.add(form);
            lastDirtyForm = form;
        } else {
            dirtyForms.delete(form);
            if (lastDirtyForm === form) {
                lastDirtyForm = null;
            }
        }

        return isDirty;
    };

    const markSaved = (form) => {
        if (!(form instanceof HTMLFormElement) || !trackedForms.has(form)) {
            return;
        }

        initialStateByForm.set(form, serializeForm(form));
        dirtyForms.delete(form);
        if (lastDirtyForm === form) {
            lastDirtyForm = null;
        }
    };

    const hasUnsavedChanges = () => {
        dirtyForms.forEach((form) => {
            if (!document.body.contains(form)) {
                dirtyForms.delete(form);
                if (lastDirtyForm === form) {
                    lastDirtyForm = null;
                }
            }
        });
        return dirtyForms.size > 0;
    };

    const getDirtyFormToSave = () => {
        const connectedDirtyForms = Array.from(dirtyForms).filter((form) => document.body.contains(form));
        if (lastDirtyForm && connectedDirtyForms.includes(lastDirtyForm)) {
            return lastDirtyForm;
        }
        return connectedDirtyForms[0] || null;
    };

    const attach = (form, options = {}) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        trackedForms.add(form);
        optionsByForm.set(form, { ...defaultOptions, ...options });
        ensureInitialState(form);
    };

    const attachFromDom = () => {
        document.querySelectorAll('form[data-unsaved-guard]').forEach((form) => {
            attach(form, {
                title: form.dataset.unsavedTitle || defaultOptions.title,
                message: form.dataset.unsavedMessage || defaultOptions.message,
                confirmText: form.dataset.unsavedConfirmText || defaultOptions.confirmText,
                cancelText: form.dataset.unsavedCancelText || defaultOptions.cancelText,
                confirmClass: form.dataset.unsavedConfirmClass || defaultOptions.confirmClass,
            });
        });
    };

    document.addEventListener('input', (event) => {
        const form = event.target?.closest?.('form[data-unsaved-guard]');
        if (form) {
            recalculateForm(form);
        }
    }, true);

    document.addEventListener('change', (event) => {
        const form = event.target?.closest?.('form[data-unsaved-guard]');
        if (form) {
            recalculateForm(form);
        }
    }, true);

    document.addEventListener('trix-change', (event) => {
        const form = event.target?.closest?.('form[data-unsaved-guard]');
        if (form) {
            recalculateForm(form);
        }
    }, true);

    document.addEventListener('click', (event) => {
        const form = event.target?.closest?.('form[data-unsaved-guard]');
        if (!form) {
            return;
        }

        // Structural changes (add/remove rows, toggle elements) might not fire input/change.
        setTimeout(() => recalculateForm(form), 0);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !trackedForms.has(form)) {
            return;
        }

        bypass = true;
        markSaved(form);
    }, true);

    window.addEventListener('beforeunload', (event) => {
        if (bypass || !hasUnsavedChanges()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    document.addEventListener('click', async (event) => {
        if (event.defaultPrevented || bypass || !hasUnsavedChanges()) {
            return;
        }

        const link = event.target?.closest?.('a[href]');
        if (!link) {
            return;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const rawHref = (link.getAttribute('href') || '').trim();
        if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) {
            return;
        }

        if (link.hasAttribute('download')) {
            return;
        }

        if ((link.getAttribute('target') || '').toLowerCase() === '_blank') {
            return;
        }

        const destination = link.href;
        if (!destination) {
            return;
        }

        event.preventDefault();

        const formToSave = getDirtyFormToSave();
        const promptOptions = optionsByForm.get(formToSave) || defaultOptions;
        const shouldSave = await window.AppConfirm.confirm(promptOptions);

        if (!shouldSave) {
            bypass = true;
            window.location.href = destination;
            return;
        }

        if (!formToSave) {
            return;
        }

        if (typeof formToSave.requestSubmit === 'function') {
            formToSave.requestSubmit();
            return;
        }

        bypass = true;
        formToSave.submit();
    }, true);

    return {
        attach,
        attachFromDom,
        recalculate: recalculateForm,
        markSaved,
        hasUnsavedChanges,
    };
};

window.AppUnsavedChanges = createUnsavedChangesGuard();
window.AppUnsavedChanges.attachFromDom();

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
