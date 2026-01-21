<script>
import axios from 'axios';

export default {
    props: {
        name: { type: String, default: 'client_id' },
        searchUrl: { type: String, required: true },
        storeUrl: { type: String, required: true },
        initialClientId: { type: [String, Number], default: '' },
        initialClientLabel: { type: String, default: '' },
        invalid: { type: Boolean, default: false },
    },
    data() {
        return {
            selectedClientId: '',
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
                type: 'pf',
                nume: '',
                telefon: '',
                email: '',
                adresa: '',
                cnp: '',
                sex: '',
                reg_com: '',
                cui: '',
                iban: '',
                banca: '',
                reprezentant: '',
                reprezentant_functie: '',
            },
            createErrors: {},
            creating: false,
            fetchError: null,
        };
    },
    watch: {
        'createForm.type'(newType) {
            if (newType === 'pf') {
                this.createForm.reg_com = '';
                this.createForm.cui = '';
                this.createForm.iban = '';
                this.createForm.banca = '';
                this.createForm.reprezentant = '';
                this.createForm.reprezentant_functie = '';
            } else {
                this.createForm.cnp = '';
                this.createForm.sex = '';
            }
        },
    },
    mounted() {
        this.selectedClientId = this.initialClientId ? String(this.initialClientId) : '';
        this.selectedLabel = this.initialClientLabel || '';
        this.query = this.selectedLabel;

        if (this.selectedClientId && !this.selectedLabel) {
            this.fetchById(this.selectedClientId);
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
                type: 'pf',
                nume: '',
                telefon: '',
                email: '',
                adresa: '',
                cnp: '',
                sex: '',
                reg_com: '',
                cui: '',
                iban: '',
                banca: '',
                reprezentant: '',
                reprezentant_functie: '',
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
                    this.selectedClientId = String(first.id);
                    this.selectedLabel = first.label || '';
                    this.query = this.selectedLabel;
                }
            } catch (error) {
                this.fetchError = 'Nu am putut incarca clientul selectat.';
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
            this.selectedClientId = '';
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
        selectClient(client) {
            this.selectedClientId = String(client.id);
            this.selectedLabel = client.label || '';
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
                const client = response?.data?.client;
                if (client?.id) {
                    this.selectClient(client);
                    await this.fetchPage({ search: this.lastSearch, page: 1, append: false });
                    this.closeCreate();
                }
            } catch (error) {
                if (error?.response?.status === 422) {
                    this.createErrors = error.response.data.errors || {};
                } else {
                    this.fetchError = 'Nu am putut salva clientul.';
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
        <input type="hidden" :name="name" :value="selectedClientId" />

        <div class="input-group">
            <input
                type="text"
                class="form-control bg-white rounded-start-3"
                :class="{ 'is-invalid': invalid }"
                v-model="query"
                placeholder="Cauta dupa nume sau telefon..."
                autocomplete="off"
                @focus="handleFocus"
                @input="handleInput"
            />
            <button
                type="button"
                class="btn btn-success rounded-end-3"
                title="Adauga client nou"
                aria-label="Adauga client nou"
                @click="openCreate"
            >
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>

        <div v-if="fetchError" class="form-text text-danger">{{ fetchError }}</div>
        <div v-else-if="selectedClientId && selectedLabel" class="form-text">Selectat: {{ selectedLabel }}</div>

        <div
            v-if="open"
            class="list-group position-absolute w-100 shadow-sm mt-1"
            style="z-index: 1050; max-height: 260px; overflow: auto"
            @scroll.passive="onScroll"
        >
            <div v-if="loading" class="list-group-item text-muted">Cautare...</div>
            <div v-else-if="results.length === 0" class="list-group-item text-muted">Niciun client gasit</div>
            <button
                v-else
                v-for="client in results"
                :key="client.id"
                type="button"
                class="list-group-item list-group-item-action"
                @mousedown.prevent="selectClient(client)"
            >
                {{ client.label }}
            </button>
        </div>

        <div class="modal fade" ref="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form @submit.prevent="submitCreate">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fa-solid fa-user-plus me-2"></i>
                                Adauga client nou
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
                                <div class="col-lg-3">
                                    <label class="form-label">Tip</label>
                                    <select v-model="createForm.type" class="form-select bg-white rounded-3">
                                        <option value="pf">Persoana fizica</option>
                                        <option value="pj">Persoana juridica</option>
                                    </select>
                                </div>
                                <div class="col-lg-9">
                                    <label class="form-label">Nume<span class="text-danger">*</span></label>
                                    <input
                                        v-model="createForm.nume"
                                        type="text"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.nume }"
                                    />
                                    <div v-if="createErrors.nume" class="invalid-feedback">
                                        {{ createErrors.nume?.[0] }}
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label">Telefon</label>
                                    <input
                                        v-model="createForm.telefon"
                                        type="text"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.telefon }"
                                    />
                                    <div v-if="createErrors.telefon" class="invalid-feedback">
                                        {{ createErrors.telefon?.[0] }}
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label">Email</label>
                                    <input
                                        v-model="createForm.email"
                                        type="email"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.email }"
                                    />
                                    <div v-if="createErrors.email" class="invalid-feedback">
                                        {{ createErrors.email?.[0] }}
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <label class="form-label">Adresa</label>
                                    <input
                                        v-model="createForm.adresa"
                                        type="text"
                                        class="form-control bg-white rounded-3"
                                        :class="{ 'is-invalid': createErrors.adresa }"
                                    />
                                    <div v-if="createErrors.adresa" class="invalid-feedback">
                                        {{ createErrors.adresa?.[0] }}
                                    </div>
                                </div>

                                <template v-if="createForm.type === 'pf'">
                                    <div class="col-lg-6">
                                        <label class="form-label">CNP</label>
                                        <input
                                            v-model="createForm.cnp"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.cnp }"
                                        />
                                        <div v-if="createErrors.cnp" class="invalid-feedback">
                                            {{ createErrors.cnp?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Sex</label>
                                        <select
                                            v-model="createForm.sex"
                                            class="form-select bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.sex }"
                                        >
                                            <option value="">-</option>
                                            <option value="M">M</option>
                                            <option value="F">F</option>
                                        </select>
                                        <div v-if="createErrors.sex" class="invalid-feedback">
                                            {{ createErrors.sex?.[0] }}
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="col-lg-6">
                                        <label class="form-label">CUI</label>
                                        <input
                                            v-model="createForm.cui"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.cui }"
                                        />
                                        <div v-if="createErrors.cui" class="invalid-feedback">
                                            {{ createErrors.cui?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Reg. com.</label>
                                        <input
                                            v-model="createForm.reg_com"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.reg_com }"
                                        />
                                        <div v-if="createErrors.reg_com" class="invalid-feedback">
                                            {{ createErrors.reg_com?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">IBAN</label>
                                        <input
                                            v-model="createForm.iban"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.iban }"
                                        />
                                        <div v-if="createErrors.iban" class="invalid-feedback">
                                            {{ createErrors.iban?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Banca</label>
                                        <input
                                            v-model="createForm.banca"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.banca }"
                                        />
                                        <div v-if="createErrors.banca" class="invalid-feedback">
                                            {{ createErrors.banca?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Reprezentant</label>
                                        <input
                                            v-model="createForm.reprezentant"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.reprezentant }"
                                        />
                                        <div v-if="createErrors.reprezentant" class="invalid-feedback">
                                            {{ createErrors.reprezentant?.[0] }}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Functie reprezentant</label>
                                        <input
                                            v-model="createForm.reprezentant_functie"
                                            type="text"
                                            class="form-control bg-white rounded-3"
                                            :class="{ 'is-invalid': createErrors.reprezentant_functie }"
                                        />
                                        <div v-if="createErrors.reprezentant_functie" class="invalid-feedback">
                                            {{ createErrors.reprezentant_functie?.[0] }}
                                        </div>
                                    </div>
                                </template>
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
