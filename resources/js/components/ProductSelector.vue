<script>
import axios from 'axios';

export default {
    props: {
        name: { type: String, default: 'produs_id' },
        searchUrl: { type: String, required: true },
        storeUrl: { type: String, required: true },
        initialProductId: { type: [String, Number], default: '' },
        initialProductLabel: { type: String, default: '' },
        invalid: { type: Boolean, default: false },
    },
    data() {
        return {
            selectedProductId: '',
            selectedLabel: '',
            query: '',
            results: [],
            open: false,
            loading: false,
            searchTimer: null,
            page: 1,
            hasMore: false,
            lastSearch: '',
            createModal: null,
            createForm: {
                denumire: '',
                pret: '',
                activ: true,
            },
            createErrors: {},
            creating: false,
            fetchError: null,
        };
    },
    mounted() {
        this.selectedProductId = this.initialProductId ? String(this.initialProductId) : '';
        this.selectedLabel = this.initialProductLabel || '';
        this.query = this.selectedLabel;

        if (this.selectedProductId && !this.selectedLabel) {
            this.fetchById(this.selectedProductId);
        }

        this.fetchPage({ search: '', page: 1, append: false });
        document.addEventListener('click', this.onDocumentClick, true);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.onDocumentClick, true);
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
    },
    methods: {
        getEmptyCreateForm(overrides = {}) {
            return {
                denumire: '',
                pret: '',
                activ: true,
                ...overrides,
            };
        },
        async fetchById(id) {
            this.loading = true;
            this.fetchError = null;
            try {
                const response = await axios.get(this.searchUrl, { params: { id } });
                const first = response?.data?.results?.[0];
                if (first?.id) {
                    this.selectedProductId = String(first.id);
                    this.selectedLabel = first.label || '';
                    this.query = this.selectedLabel;
                }
            } catch (error) {
                this.fetchError = 'Nu am putut incarca produsul selectat.';
            } finally {
                this.loading = false;
            }
        },
        async fetchPage({ search, page, append }) {
            this.loading = true;
            this.fetchError = null;
            try {
                const response = await axios.get(this.searchUrl, { params: { search, page } });
                const results = response?.data?.results || [];
                const pagination = response?.data?.pagination;

                this.results = append ? [...this.results, ...results] : results;

                this.page = pagination?.current_page || page;
                this.hasMore = Boolean(pagination?.has_more);
                this.lastSearch = search || '';
            } catch (error) {
                if (!append) {
                    this.results = [];
                }
                this.fetchError = 'Cautarea a esuat.';
            } finally {
                this.loading = false;
            }
        },
        handleFocus() {
            this.open = true;
            if (this.results.length === 0 && !this.loading) {
                this.fetchPage({ search: '', page: 1, append: false });
            }
        },
        handleInput() {
            this.fetchError = null;
            this.selectedProductId = '';
            this.selectedLabel = '';
            this.open = true;

            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }

            this.searchTimer = setTimeout(() => {
                this.search();
            }, 250);
        },
        async search() {
            const search = (this.query || '').trim();
            await this.fetchPage({ search, page: 1, append: false });
        },
        onScroll(event) {
            const el = event.target;
            if (!this.hasMore || this.loading) {
                return;
            }

            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 30) {
                this.fetchPage({ search: this.lastSearch, page: this.page + 1, append: true });
            }
        },
        selectProduct(product) {
            this.selectedProductId = String(product.id);
            this.selectedLabel = product.label || '';
            this.query = this.selectedLabel;
            this.open = false;
        },
        onDocumentClick(event) {
            const root = this.$refs.root;
            if (!root) {
                return;
            }

            if (!root.contains(event.target)) {
                this.open = false;
            }
        },
        openCreate() {
            this.createErrors = {};
            this.fetchError = null;
            this.createForm = this.getEmptyCreateForm();

            const modalEl = this.$refs.createModal;
            if (!modalEl) {
                return;
            }

            if (!this.createModal) {
                this.createModal = new window.bootstrap.Modal(modalEl);
            }

            this.createModal.show();
        },
        closeCreate() {
            this.createModal?.hide();
        },
        async submitCreate() {
            this.creating = true;
            this.createErrors = {};
            this.fetchError = null;
            try {
                const response = await axios.post(this.storeUrl, this.createForm);
                const produs = response?.data?.produs;
                if (produs?.id) {
                    this.selectProduct(produs);
                    await this.fetchPage({ search: this.lastSearch, page: 1, append: false });
                    this.closeCreate();
                }
            } catch (error) {
                if (error?.response?.status === 422) {
                    this.createErrors = error.response.data.errors || {};
                } else {
                    this.fetchError = 'Nu am putut salva produsul.';
                }
            } finally {
                this.creating = false;
            }
        },
    },
};
</script>

<template>
    <div ref="root" class="position-relative">
        <input type="hidden" :name="name" :value="selectedProductId" />

        <div class="input-group">
            <input
                type="text"
                class="form-control bg-white rounded-start-3"
                :class="{ 'is-invalid': invalid }"
                v-model="query"
                placeholder="Cauta dupa denumire..."
                autocomplete="off"
                @focus="handleFocus"
                @input="handleInput"
            />
            <button
                type="button"
                class="btn btn-success rounded-end-3"
                title="Adauga produs nou"
                aria-label="Adauga produs nou"
                @click="openCreate"
            >
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>

        <div v-if="fetchError" class="form-text text-danger">{{ fetchError }}</div>
        <div v-else-if="selectedProductId && selectedLabel" class="form-text">Selectat: {{ selectedLabel }}</div>

        <div
            v-if="open"
            class="list-group position-absolute w-100 shadow-sm mt-1"
            style="z-index: 1050; max-height: 260px; overflow: auto"
            @scroll.passive="onScroll"
        >
            <div v-if="loading" class="list-group-item text-muted">Cautare...</div>
            <div v-else-if="results.length === 0" class="list-group-item text-muted">Niciun produs gasit</div>
            <button
                v-else
                v-for="product in results"
                :key="product.id"
                type="button"
                class="list-group-item list-group-item-action"
                @mousedown.prevent="selectProduct(product)"
            >
                {{ product.label }}
            </button>
        </div>

        <div class="modal fade" ref="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form @submit.prevent="submitCreate">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fa-solid fa-boxes-stacked me-2"></i>
                                Adauga produs nou
                            </h5>
                            <button
                                type="button"
                                class="btn-close btn-close-white"
                                @click="closeCreate"
                                aria-label="Close"
                            ></button>
                        </div>
                        <div class="modal-body bg-light">
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <label class="form-label">Denumire<span class="text-danger">*</span></label>
                                    <input
                                        v-model="createForm.denumire"
                                        type="text"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.denumire }"
                                    />
                                    <div v-if="createErrors.denumire" class="invalid-feedback">
                                        {{ createErrors.denumire?.[0] }}
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label">Pret<span class="text-danger">*</span></label>
                                    <input
                                        v-model="createForm.pret"
                                        type="number"
                                        step="0.01"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.pret }"
                                    />
                                    <div v-if="createErrors.pret" class="invalid-feedback">
                                        {{ createErrors.pret?.[0] }}
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            :id="`produs-activ-${_uid}`"
                                            v-model="createForm.activ"
                                        />
                                        <label class="form-check-label" :for="`produs-activ-${_uid}`">Activ</label>
                                    </div>
                                    <div v-if="createErrors.activ" class="invalid-feedback d-block">
                                        {{ createErrors.activ?.[0] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" @click="closeCreate">Renunta</button>
                            <button type="submit" class="btn btn-success" :disabled="creating">
                                <span v-if="creating">Se salveaza...</span>
                                <span v-else>Salveaza</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
