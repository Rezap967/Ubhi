<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useJournal } from '@/composables/accounting/useJournal';
import type {
    JournalStatus,
    JournalListItem,
    JournalDetail,
    JournalLine,
    JournalPayload,
    JournalPayloadLine,
    JournalFilters,
    JournalSummary,
    PaginationMeta,
    AccountRef,
    JournalFilterOptions,
    ReverseJournalPayload,
    JournalPreviewResult,
} from '@/composables/accounting/useJournal';

const {
    loading,
    detailLoading,
    saving,
    posting,
    reversing,
    accountsLoading,
    optionsLoading,
    periodChecking,
    previewLoading,
    error,
    validationErrors,
    getJournals,
    getJournalDetail,
    createJournal,
    updateJournal,
    postJournal,
    reverseJournal,
    searchAccounts,
    getJournalFilterOptions,
    getReferenceNumber,
    checkAccountingPeriod,
    previewJournal,
    validateJournalPayload,
    calculateTotals,
    generateRequestUuid,
} = useJournal();

// State Data Utama
const journals = ref<JournalListItem[]>([]);
const pagination = ref<PaginationMeta | undefined>(undefined);
const summary = ref<JournalSummary | undefined>(undefined);
const filterOptions = ref<JournalFilterOptions | null>(null);
const accountOptions = ref<AccountRef[]>([]);

// Filter Form State
const filters = reactive<JournalFilters>({
    search: '',
    status: 'semua',
    source_type: 'semua',
    account_id: null,
    date_start: null,
    date_end: null,
    sort_by: 'journal_date',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
});

// UI & Modal States
const selectedJournal = ref<JournalDetail | null>(null);
const targetActionJournal = ref<JournalListItem | null>(null);
const showDetailModal = ref(false);
const showFormModal = ref(false);
const showConfirmModal = ref(false);
const showReverseModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');

// Messages & Warnings
const localError = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const periodWarning = ref<string | null>(null);

// Form Input State
const previewNo = ref<string>('');
const form = reactive<JournalPayload>({
    journal_date: new Date().toISOString().slice(0, 10),
    description: '',
    source_type: 'manual',
    source_id: null,
    request_uuid: '',
    lines: [],
});

const reverseForm = reactive<ReverseJournalPayload>({
    reversal_date: new Date().toISOString().slice(0, 10),
    reason: '',
});

const previewResultState = ref<JournalPreviewResult | null>(null);

// Formatters
const formatCurrency = (val: number | undefined | null): string => {
    if (val === undefined || val === null) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val);
};

const formatDate = (val: string | undefined | null): string => {
    if (!val) return '-';
    try {
        return new Date(val).toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return val;
    }
};

const formatDateTime = (val: string | undefined | null): string => {
    if (!val) return '-';
    try {
        return new Date(val).toLocaleString('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return val;
    }
};

const getStatusLabel = (status: JournalStatus | string): string => {
    switch (status) {
        case 'draft':
            return 'Draft';
        case 'posted':
            return 'Posted';
        case 'reversed':
            return 'Reversed';
        case 'void':
            return 'Void';
        default:
            return status;
    }
};

const getStatusBadgeClass = (status: JournalStatus | string): string => {
    switch (status) {
        case 'draft':
            return 'bg-amber-100 text-amber-800 border-amber-300';
        case 'posted':
            return 'bg-emerald-100 text-emerald-800 border-emerald-300';
        case 'reversed':
            return 'bg-purple-100 text-purple-800 border-purple-300';
        case 'void':
            return 'bg-rose-100 text-rose-800 border-rose-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-300';
    }
};

const getSourceTypeLabel = (source: string | null | undefined): string => {
    if (!source) return 'Manual';
    switch (source) {
        case 'manual':
            return 'Manual';
        case 'expense':
            return 'Pengeluaran';
        case 'recurring_expense':
            return 'Pengeluaran Berulang';
        case 'payable':
            return 'Hutang (AP)';
        case 'receivable':
            return 'Piutang (AR)';
        case 'tax':
            return 'Pajak';
        case 'depreciation':
            return 'Penyusutan';
        case 'disposal':
            return 'Pelepasan Aset';
        default:
            return source;
    }
};

// Checkers
const isDraft = (status: string) => status === 'draft';
const isPosted = (status: string) => status === 'posted';
const canEdit = (j: JournalListItem) => isDraft(j.status);
const canPost = (j: JournalListItem) => isDraft(j.status);
const canReverse = (j: JournalListItem) => isPosted(j.status);

// Computed Fallback Summary
const computedSummary = computed(() => {
    if (summary.value) return summary.value;

    let total_debit = 0;
    let total_credit = 0;
    let draft_count = 0;
    let posted_count = 0;
    let reversed_count = 0;
    let void_count = 0;

    journals.value.forEach((item) => {
        total_debit += Number(item.total_debit) || 0;
        total_credit += Number(item.total_credit) || 0;
        if (item.status === 'draft') draft_count++;
        if (item.status === 'posted') posted_count++;
        if (item.status === 'reversed') reversed_count++;
        if (item.status === 'void') void_count++;
    });

    return {
        total_journals: journals.value.length,
        draft_count,
        posted_count,
        reversed_count,
        void_count,
        total_debit,
        total_credit,
    };
});

// Calculated Form Totals
const formTotals = computed(() => {
    return calculateTotals(form.lines);
});

// Line Helpers
const createEmptyLine = (): JournalPayloadLine => ({
    account_id: null,
    description: '',
    debit: 0,
    credit: 0,
});

const addLine = () => {
    form.lines.push(createEmptyLine());
};

const removeLine = (index: number) => {
    if (form.lines.length <= 2) {
        localError.value = 'Jurnal manual minimal harus memiliki 2 baris.';
        return;
    }
    form.lines.splice(index, 1);
};

const handleDebitInput = (index: number) => {
    const line = form.lines[index];
    if (line.debit && Number(line.debit) > 0) {
        line.credit = 0;
    }
};

const handleCreditInput = (index: number) => {
    const line = form.lines[index];
    if (line.credit && Number(line.credit) > 0) {
        line.debit = 0;
    }
};

// Fetch Actions
const fetchJournals = async () => {
    localError.value = null;
    try {
        const res = await getJournals(filters);
        journals.value = res.data.items;
        pagination.value = res.data.pagination;
        summary.value = res.data.summary;
    } catch (err) {
        // Handled by composable error state
    }
};

const fetchFilterOptions = async () => {
    try {
        const res = await getJournalFilterOptions();
        filterOptions.value = res.data;
        if (res.data.accounts) {
            accountOptions.value = res.data.accounts;
        }
    } catch {
        // Option endpoint fallback quiet
    }
};

const loadAccountOptions = async (searchStr = '') => {
    try {
        const res = await searchAccounts({
            q: searchStr,
            is_active: true,
            limit: 50,
        });
        accountOptions.value = res.data;
    } catch {
        // Quiet fallback
    }
};

const applyFilters = () => {
    filters.page = 1;
    fetchJournals();
};

const resetFilters = () => {
    filters.search = '';
    filters.status = 'semua';
    filters.source_type = 'semua';
    filters.account_id = null;
    filters.date_start = null;
    filters.date_end = null;
    filters.sort_by = 'journal_date';
    filters.sort_dir = 'desc';
    filters.page = 1;
    filters.per_page = 20;
    fetchJournals();
};

const changePage = (pageNum: number) => {
    filters.page = pageNum;
    fetchJournals();
};

// Period Check Event
const handleDateChange = async () => {
    periodWarning.value = null;
    if (!form.journal_date) return;
    try {
        const res = await checkAccountingPeriod({ date: form.journal_date });
        if (res.data && res.data.status !== 'open') {
            periodWarning.value =
                res.data.message ||
                'Periode akuntansi pada tanggal ini sedang terkunci/ditutup.';
        }
    } catch {
        // Ignore optional check fail
    }
};

// Modals Trigger
const openCreateModal = async () => {
    localError.value = null;
    successMessage.value = null;
    periodWarning.value = null;
    previewResultState.value = null;
    formMode.value = 'create';

    form.journal_date = new Date().toISOString().slice(0, 10);
    form.description = '';
    form.source_type = 'manual';
    form.source_id = null;
    form.request_uuid = generateRequestUuid();
    form.lines = [createEmptyLine(), createEmptyLine()];

    previewNo.value = 'Draft Baru';

    try {
        const resRef = await getReferenceNumber();
        if (resRef.data) {
            previewNo.value =
                resRef.data.reference_no ||
                resRef.data.journal_no ||
                'Draft Baru';
        }
    } catch {
        // Ignore reference number fail
    }

    await loadAccountOptions();
    showFormModal.value = true;
};

const openEditModal = async (journal: JournalListItem) => {
    if (!canEdit(journal)) return;
    localError.value = null;
    successMessage.value = null;
    periodWarning.value = null;
    previewResultState.value = null;
    formMode.value = 'edit';

    try {
        const detailRes = await getJournalDetail(journal.id);
        const detail = detailRes.data;
        selectedJournal.value = detail;

        form.journal_date = detail.journal_date;
        form.description = detail.description;
        form.source_type = detail.source_type || 'manual';
        form.source_id = detail.source_id || null;
        form.request_uuid = generateRequestUuid();
        form.lines = detail.lines.map((l) => ({
            account_id: l.account_id,
            description: l.description || '',
            debit: Number(l.debit) || 0,
            credit: Number(l.credit) || 0,
        }));

        previewNo.value = detail.journal_no;
        await loadAccountOptions();
        showFormModal.value = true;
    } catch (err) {
        localError.value = 'Gagal memuat detail jurnal untuk diedit.';
    }
};

const openDetail = async (journal: JournalListItem) => {
    localError.value = null;
    try {
        const detailRes = await getJournalDetail(journal.id);
        selectedJournal.value = detailRes.data;
        showDetailModal.value = true;
    } catch (err) {
        localError.value = 'Gagal mengambil detail jurnal.';
    }
};

const submitForm = async () => {
    localError.value = null;
    successMessage.value = null;

    const validationMsgs = validateJournalPayload(form, true);
    if (validationMsgs.length > 0) {
        localError.value = validationMsgs[0];
        return;
    }

    try {
        if (formMode.value === 'create') {
            await createJournal(form);
            successMessage.value =
                'Jurnal umum berhasil disimpan sebagai draft.';
        } else {
            if (!selectedJournal.value) return;
            await updateJournal(selectedJournal.value.id, form);
            successMessage.value = 'Jurnal umum draft berhasil diperbarui.';
        }
        showFormModal.value = false;
        fetchJournals();
    } catch (err) {
        // Composable handles setting the error state
    }
};

const openConfirmPost = (journal: JournalListItem) => {
    if (!canPost(journal)) return;
    targetActionJournal.value = journal;
    localError.value = null;
    showConfirmModal.value = true;
};

const submitPost = async () => {
    if (!targetActionJournal.value) return;
    localError.value = null;
    successMessage.value = null;
    try {
        await postJournal(targetActionJournal.value.id);
        successMessage.value = `Jurnal ${targetActionJournal.value.journal_no} berhasil diposting.`;
        showConfirmModal.value = false;
        if (showDetailModal.value) showDetailModal.value = false;
        fetchJournals();
    } catch (err) {
        // Composable handles setting error
    }
};

const openReverseModal = (journal: JournalListItem) => {
    if (!canReverse(journal)) return;
    targetActionJournal.value = journal;
    reverseForm.reversal_date = new Date().toISOString().slice(0, 10);
    reverseForm.reason = '';
    localError.value = null;
    showReverseModal.value = true;
};

const submitReverseAction = async () => {
    if (!targetActionJournal.value) return;
    localError.value = null;
    successMessage.value = null;
    try {
        await reverseJournal(targetActionJournal.value.id, reverseForm);
        successMessage.value = `Jurnal ${targetActionJournal.value.journal_no} berhasil direverse.`;
        showReverseModal.value = false;
        if (showDetailModal.value) showDetailModal.value = false;
        fetchJournals();
    } catch (err) {
        // Handled by composable
    }
};

const handlePreview = async () => {
    previewResultState.value = null;
    try {
        const res = await previewJournal(form);
        previewResultState.value = res.data;
    } catch {
        // Fallback to local calculateTotals
        const totals = calculateTotals(form.lines);
        previewResultState.value = {
            total_debit: totals.totalDebit,
            total_credit: totals.totalCredit,
            difference: totals.difference,
            is_balanced: totals.isBalanced,
        };
    }
};

onMounted(() => {
    fetchJournals();
    fetchFilterOptions();
    loadAccountOptions();
});
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 p-4 font-sans text-gray-800 sm:p-6 lg:p-8"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- HEADER SECTION -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
                    >
                        Jurnal Umum
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Catat, tinjau, posting, dan lakukan reversal jurnal
                        akuntansi toko.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="fetchJournals"
                        :disabled="loading"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        Refresh
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        Tambah Jurnal Manual
                    </button>
                </div>
            </div>

            <!-- CALLOUT INFO -->
            <div
                class="flex items-start gap-3 rounded-r-lg border-l-4 border-blue-600 bg-blue-50 p-4 text-sm text-blue-900"
            >
                <svg
                    class="mt-0.5 h-5 w-5 shrink-0 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <div>
                    <p class="font-medium">
                        Prinsip dasar Immutability Jurnal Akuntansi
                    </p>
                    <p class="mt-0.5 text-xs text-blue-800 sm:text-sm">
                        Jurnal yang sudah diposting bersifat final dan
                        immutable. Koreksi terhadap jurnal yang telah diposting
                        hanya dapat dilakukan melalui pembentukan jurnal
                        pembalik (reversal).
                    </p>
                </div>
            </div>

            <!-- ERROR & SUCCESS ALERTS -->
            <div
                v-if="error || localError"
                class="flex items-start justify-between rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
            >
                <div class="flex items-start gap-2">
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-rose-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <div>
                        <p class="font-semibold">{{ error || localError }}</p>
                        <ul
                            v-if="validationErrors"
                            class="mt-1 list-inside list-disc space-y-0.5 text-xs"
                        >
                            <template
                                v-for="(errArr, field) in validationErrors"
                                :key="field"
                            >
                                <li v-for="(msg, i) in errArr" :key="i">
                                    {{ msg }}
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                v-if="successMessage"
                class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                <div class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5 shrink-0 text-emerald-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <p class="font-medium">{{ successMessage }}</p>
                </div>
                <button
                    type="button"
                    @click="successMessage = null"
                    class="text-emerald-600 hover:text-emerald-800"
                >
                    &times;
                </button>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-gray-500">
                        Total Jurnal
                    </p>
                    <p class="mt-1 text-lg font-bold text-gray-900">
                        {{ computedSummary.total_journals ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-amber-600">Draft</p>
                    <p class="mt-1 text-lg font-bold text-amber-900">
                        {{ computedSummary.draft_count ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-emerald-600">Posted</p>
                    <p class="mt-1 text-lg font-bold text-emerald-900">
                        {{ computedSummary.posted_count ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-purple-600">Reversed</p>
                    <p class="mt-1 text-lg font-bold text-purple-900">
                        {{ computedSummary.reversed_count ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-rose-600">Void</p>
                    <p class="mt-1 text-lg font-bold text-rose-900">
                        {{ computedSummary.void_count ?? 0 }}
                    </p>
                </div>
                <div
                    class="col-span-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:col-span-2"
                >
                    <p class="text-xs font-medium text-gray-500">
                        Total Debit / Kredit
                    </p>
                    <p class="mt-1 text-sm font-bold text-gray-900">
                        {{ formatCurrency(computedSummary.total_debit) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm"
                >
                    <p class="text-xs font-medium text-gray-500">
                        Selisih Total
                    </p>
                    <p
                        class="mt-1 text-sm font-bold"
                        :class="
                            (computedSummary.total_debit ?? 0) -
                                (computedSummary.total_credit ?? 0) ===
                            0
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                    >
                        {{
                            formatCurrency(
                                (computedSummary.total_debit ?? 0) -
                                    (computedSummary.total_credit ?? 0),
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div
                class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Cari Jurnal</label
                        >
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="No Jurnal / Deskripsi..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Status</label
                        >
                        <select
                            v-model="filters.status"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="semua">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="posted">Posted</option>
                            <option value="reversed">Reversed</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Sumber (Source Type)</label
                        >
                        <select
                            v-model="filters.source_type"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="semua">Semua Sumber</option>
                            <option value="manual">Manual</option>
                            <option value="expense">Pengeluaran</option>
                            <option value="recurring_expense">
                                Pengeluaran Berulang
                            </option>
                            <option value="payable">Hutang (AP)</option>
                            <option value="receivable">Piutang (AR)</option>
                            <option value="tax">Pajak</option>
                            <option value="depreciation">Penyusutan</option>
                            <option value="disposal">Pelepasan Aset</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Filter Akun</label
                        >
                        <select
                            v-model="filters.account_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option :value="null">Semua Akun</option>
                            <option
                                v-for="acc in accountOptions"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.account_code }} - {{ acc.account_name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Mulai Tanggal</label
                        >
                        <input
                            v-model="filters.date_start"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Sampai Tanggal</label
                        >
                        <input
                            v-model="filters.date_end"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-700"
                            >Urutkan</label
                        >
                        <div class="flex gap-2">
                            <select
                                v-model="filters.sort_by"
                                class="w-full rounded-lg border border-gray-300 px-2 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="journal_date">
                                    Tanggal Jurnal
                                </option>
                                <option value="journal_no">No Jurnal</option>
                                <option value="total_debit">Total Debit</option>
                                <option value="status">Status</option>
                                <option value="created_at">Waktu Buat</option>
                            </select>
                            <select
                                v-model="filters.sort_dir"
                                class="w-20 rounded-lg border border-gray-300 px-2 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="desc">DESC</option>
                                <option value="asc">ASC</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-end gap-2">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 focus:outline-none"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 focus:outline-none"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- TABLE SECTION -->
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="border-b border-gray-200 bg-gray-50 text-xs font-semibold tracking-wider text-gray-600 uppercase"
                            >
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">No Jurnal</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Deskripsi</th>
                                <th class="px-4 py-3">Sumber</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Kredit</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Dibuat Oleh</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-xs">
                            <template v-if="loading">
                                <tr
                                    v-for="i in 5"
                                    :key="i"
                                    class="animate-pulse"
                                >
                                    <td
                                        colspan="10"
                                        class="bg-gray-50/50 px-4 py-4"
                                    >
                                        <div
                                            class="h-4 w-full rounded bg-gray-200"
                                        ></div>
                                    </td>
                                </tr>
                            </template>

                            <template v-else-if="journals.length === 0">
                                <tr>
                                    <td
                                        colspan="10"
                                        class="py-12 text-center text-gray-500"
                                    >
                                        <svg
                                            class="mx-auto mb-2 h-12 w-12 text-gray-300"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        Data jurnal umum tidak ditemukan.
                                    </td>
                                </tr>
                            </template>

                            <template v-else>
                                <tr
                                    v-for="(item, index) in journals"
                                    :key="item.id"
                                    class="transition-colors hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3 text-gray-500">
                                        {{
                                            ((pagination?.current_page ?? 1) -
                                                1) *
                                                (pagination?.per_page ?? 20) +
                                            index +
                                            1
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-mono font-medium text-gray-900"
                                    >
                                        {{ item.journal_no }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(item.journal_date) }}
                                    </td>
                                    <td
                                        class="max-w-xs truncate px-4 py-3"
                                        :title="item.description"
                                    >
                                        {{ item.description }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                            class="rounded bg-gray-100 px-2 py-0.5 font-medium text-gray-700"
                                        >
                                            {{
                                                getSourceTypeLabel(
                                                    item.source_type,
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.total_debit) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.total_credit) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center whitespace-nowrap"
                                    >
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                getStatusBadgeClass(
                                                    item.status,
                                                ),
                                            ]"
                                        >
                                            {{ getStatusLabel(item.status) }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 whitespace-nowrap text-gray-600"
                                    >
                                        {{ item.created_by_name || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center whitespace-nowrap"
                                    >
                                        <div
                                            class="inline-flex items-center gap-1"
                                        >
                                            <button
                                                type="button"
                                                @click="openDetail(item)"
                                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                            >
                                                Detail
                                            </button>
                                            <button
                                                v-if="canEdit(item)"
                                                type="button"
                                                @click="openEditModal(item)"
                                                :disabled="saving"
                                                class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="canPost(item)"
                                                type="button"
                                                @click="openConfirmPost(item)"
                                                :disabled="posting"
                                                class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                                            >
                                                Posting
                                            </button>
                                            <button
                                                v-if="canReverse(item)"
                                                type="button"
                                                @click="openReverseModal(item)"
                                                :disabled="reversing"
                                                class="rounded border border-purple-200 bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 hover:bg-purple-100 disabled:opacity-50"
                                            >
                                                Reversal
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION FOOTER -->
                <div
                    v-if="pagination && (pagination.last_page ?? 1) > 1"
                    class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"
                >
                    <div>
                        Menampilkan halaman
                        <span class="font-semibold text-gray-900">{{
                            pagination.current_page
                        }}</span>
                        dari
                        <span class="font-semibold text-gray-900">{{
                            pagination.last_page
                        }}</span>
                        (Total {{ pagination.total }} data)
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="
                                (pagination.current_page ?? 1) <= 1 || loading
                            "
                            @click="
                                changePage((pagination.current_page ?? 1) - 1)
                            "
                            class="rounded border border-gray-300 bg-white px-3 py-1.5 hover:bg-gray-50 disabled:opacity-50"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            :disabled="
                                (pagination.current_page ?? 1) >=
                                    (pagination.last_page ?? 1) || loading
                            "
                            @click="
                                changePage((pagination.current_page ?? 1) + 1)
                            "
                            class="rounded border border-gray-300 bg-white px-3 py-1.5 hover:bg-gray-50 disabled:opacity-50"
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DETAIL -->
        <div
            v-if="showDetailModal && selectedJournal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            Detail Jurnal Umum
                        </h3>
                        <p class="font-mono text-xs text-gray-500">
                            {{ selectedJournal.journal_no }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showDetailModal = false"
                        class="text-xl font-bold text-gray-400 hover:text-gray-600"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-6 overflow-y-auto p-6 text-xs">
                    <!-- Metadata Grid -->
                    <div
                        class="grid grid-cols-2 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-4"
                    >
                        <div>
                            <p class="text-gray-500">Tanggal Jurnal</p>
                            <p class="mt-0.5 font-semibold text-gray-900">
                                {{ formatDate(selectedJournal.journal_date) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Status</p>
                            <span
                                :class="[
                                    'mt-0.5 inline-block rounded border px-2 py-0.5 text-xs font-semibold',
                                    getStatusBadgeClass(selectedJournal.status),
                                ]"
                            >
                                {{ getStatusLabel(selectedJournal.status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-500">Sumber</p>
                            <p class="mt-0.5 font-semibold text-gray-900">
                                {{
                                    getSourceTypeLabel(
                                        selectedJournal.source_type,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Dibuat Oleh</p>
                            <p class="mt-0.5 font-semibold text-gray-900">
                                {{ selectedJournal.created_by_name || '-' }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-500">Deskripsi Utama</p>
                            <p class="mt-0.5 font-medium text-gray-800">
                                {{ selectedJournal.description }}
                            </p>
                        </div>
                        <div
                            v-if="selectedJournal.posted_by_name"
                            class="col-span-2"
                        >
                            <p class="text-gray-500">Diposting Oleh & Waktu</p>
                            <p class="mt-0.5 font-medium text-gray-800">
                                {{ selectedJournal.posted_by_name }} ({{
                                    formatDateTime(selectedJournal.posted_at)
                                }})
                            </p>
                        </div>
                    </div>

                    <!-- Lines Table -->
                    <div>
                        <h4 class="mb-2 font-bold text-gray-800">
                            Rincian Baris Jurnal (Lines)
                        </h4>
                        <div
                            class="overflow-x-auto rounded-lg border border-gray-200"
                        >
                            <table class="w-full text-left">
                                <thead
                                    class="border-b border-gray-200 bg-gray-100 text-gray-600"
                                >
                                    <tr>
                                        <th class="px-3 py-2">#</th>
                                        <th class="px-3 py-2">Kode Akun</th>
                                        <th class="px-3 py-2">Nama Akun</th>
                                        <th class="px-3 py-2">
                                            Deskripsi Baris
                                        </th>
                                        <th class="px-3 py-2 text-right">
                                            Debit
                                        </th>
                                        <th class="px-3 py-2 text-right">
                                            Kredit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr
                                        v-for="(
                                            line, idx
                                        ) in selectedJournal.lines"
                                        :key="line.id || idx"
                                    >
                                        <td class="px-3 py-2 text-gray-500">
                                            {{ idx + 1 }}
                                        </td>
                                        <td
                                            class="px-3 py-2 font-mono font-medium text-gray-900"
                                        >
                                            {{
                                                line.account?.account_code ||
                                                '-'
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 font-medium text-gray-800"
                                        >
                                            {{
                                                line.account?.account_name ||
                                                '-'
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">
                                            {{ line.description || '-' }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-medium text-gray-900"
                                        >
                                            {{ formatCurrency(line.debit) }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-medium text-gray-900"
                                        >
                                            {{ formatCurrency(line.credit) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot
                                    class="border-t border-gray-200 bg-gray-50 font-bold"
                                >
                                    <tr>
                                        <td
                                            colspan="4"
                                            class="px-3 py-2 text-right"
                                        >
                                            Total:
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-gray-900"
                                        >
                                            {{
                                                formatCurrency(
                                                    selectedJournal.total_debit,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-gray-900"
                                        >
                                            {{
                                                formatCurrency(
                                                    selectedJournal.total_credit,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Balance Validation Badge -->
                    <div
                        class="flex items-center justify-between rounded-lg border p-3"
                        :class="
                            Math.abs(
                                selectedJournal.total_debit -
                                    selectedJournal.total_credit,
                            ) < 0.01
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-rose-200 bg-rose-50 text-rose-800'
                        "
                    >
                        <span class="font-medium">Status Balance Jurnal:</span>
                        <span
                            class="text-xs font-bold tracking-wider uppercase"
                        >
                            {{
                                Math.abs(
                                    selectedJournal.total_debit -
                                        selectedJournal.total_credit,
                                ) < 0.01
                                    ? '✓ Balanced'
                                    : '✕ Tidak Balance'
                            }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <button
                        v-if="canEdit(selectedJournal)"
                        type="button"
                        @click="
                            openEditModal(selectedJournal);
                            showDetailModal = false;
                        "
                        class="rounded-lg bg-amber-100 px-4 py-2 text-xs font-medium text-amber-700 hover:bg-amber-200"
                    >
                        Edit Jurnal Draft
                    </button>
                    <button
                        v-if="canPost(selectedJournal)"
                        type="button"
                        @click="openConfirmPost(selectedJournal)"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-medium text-white hover:bg-emerald-700"
                    >
                        Posting Jurnal
                    </button>
                    <button
                        v-if="canReverse(selectedJournal)"
                        type="button"
                        @click="openReverseModal(selectedJournal)"
                        class="rounded-lg bg-purple-600 px-4 py-2 text-xs font-medium text-white hover:bg-purple-700"
                    >
                        Reversal Jurnal
                    </button>
                    <button
                        type="button"
                        @click="showDetailModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL FORM TAMBAH / EDIT -->
        <div
            v-if="showFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        >
            <div
                class="my-8 flex w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            {{
                                formMode === 'create'
                                    ? 'Tambah Jurnal Manual Baru'
                                    : 'Edit Jurnal Draft'
                            }}
                        </h3>
                        <p class="font-mono text-xs text-gray-500">
                            No. Ref: {{ previewNo }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showFormModal = false"
                        class="text-xl font-bold text-gray-400 hover:text-gray-600"
                    >
                        &times;
                    </button>
                </div>

                <form
                    @submit.prevent="submitForm"
                    class="space-y-6 p-6 text-xs"
                >
                    <!-- Period Warning Alert -->
                    <div
                        v-if="periodWarning"
                        class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-900"
                    >
                        <svg
                            class="h-5 w-5 shrink-0 text-amber-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                        <span>{{ periodWarning }}</span>
                    </div>

                    <!-- Header Inputs -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-700"
                                >Tanggal Jurnal *</label
                            >
                            <input
                                v-model="form.journal_date"
                                type="date"
                                required
                                @change="handleDateChange"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-700"
                                >Tipe Sumber</label
                            >
                            <input
                                type="text"
                                value="manual"
                                disabled
                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-500"
                            />
                        </div>
                        <div class="sm:col-span-3">
                            <label
                                class="mb-1 block text-xs font-medium text-gray-700"
                                >Deskripsi Transaksi *</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="2"
                                required
                                placeholder="Tuliskan keterangan detail transaksi jurnal..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Lines Editor Table -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-gray-800">
                                Baris Jurnal (Minimal 2 Baris)
                            </h4>
                            <button
                                type="button"
                                @click="addLine"
                                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100"
                            >
                                + Tambah Baris
                            </button>
                        </div>

                        <div
                            class="overflow-x-auto rounded-lg border border-gray-200"
                        >
                            <table class="w-full text-left">
                                <thead
                                    class="border-b border-gray-200 bg-gray-100 text-gray-600"
                                >
                                    <tr>
                                        <th class="w-10 px-3 py-2">#</th>
                                        <th class="w-64 px-3 py-2">
                                            Akun (COA) *
                                        </th>
                                        <th class="px-3 py-2">
                                            Deskripsi Baris
                                        </th>
                                        <th class="w-36 px-3 py-2 text-right">
                                            Debit
                                        </th>
                                        <th class="w-36 px-3 py-2 text-right">
                                            Kredit
                                        </th>
                                        <th class="w-12 px-3 py-2 text-center">
                                            Hapus
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr
                                        v-for="(line, idx) in form.lines"
                                        :key="idx"
                                        class="hover:bg-gray-50/50"
                                    >
                                        <td
                                            class="px-3 py-2 text-center text-gray-500"
                                        >
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <select
                                                v-model="line.account_id"
                                                required
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:ring-1 focus:ring-blue-500"
                                            >
                                                <option :value="null" disabled>
                                                    -- Pilih Akun --
                                                </option>
                                                <option
                                                    v-for="acc in accountOptions"
                                                    :key="acc.id"
                                                    :value="acc.id"
                                                >
                                                    {{ acc.label }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="line.description"
                                                type="text"
                                                placeholder="Keterangan opsional"
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:ring-1 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model.number="line.debit"
                                                type="number"
                                                min="0"
                                                step="any"
                                                @input="handleDebitInput(idx)"
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-right font-mono text-xs focus:ring-1 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model.number="line.credit"
                                                type="number"
                                                min="0"
                                                step="any"
                                                @input="handleCreditInput(idx)"
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-right font-mono text-xs focus:ring-1 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeLine(idx)"
                                                :disabled="
                                                    form.lines.length <= 2
                                                "
                                                class="font-bold text-rose-500 hover:text-rose-700 disabled:opacity-30"
                                            >
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Real-Time Calculation & Balance Status -->
                    <div
                        class="flex flex-col items-center justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:flex-row"
                    >
                        <div class="flex items-center gap-4">
                            <div>
                                <p class="text-gray-500">Total Debit</p>
                                <p class="text-sm font-bold text-gray-900">
                                    {{ formatCurrency(formTotals.totalDebit) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500">Total Kredit</p>
                                <p class="text-sm font-bold text-gray-900">
                                    {{ formatCurrency(formTotals.totalCredit) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500">Selisih</p>
                                <p
                                    :class="[
                                        'text-sm font-bold',
                                        formTotals.isBalanced
                                            ? 'text-emerald-600'
                                            : 'text-rose-600',
                                    ]"
                                >
                                    {{ formatCurrency(formTotals.difference) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="handlePreview"
                                :disabled="previewLoading"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100"
                            >
                                {{
                                    previewLoading
                                        ? 'Memeriksa...'
                                        : 'Preview Backend'
                                }}
                            </button>
                            <span
                                :class="[
                                    'rounded-lg border px-3 py-1.5 text-xs font-bold uppercase',
                                    formTotals.isBalanced
                                        ? 'border-emerald-300 bg-emerald-100 text-emerald-800'
                                        : 'border-rose-300 bg-rose-100 text-rose-800',
                                ]"
                            >
                                {{
                                    formTotals.isBalanced
                                        ? '✓ Balance'
                                        : '✕ Belum Balance'
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="previewResultState"
                        class="flex justify-between rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900"
                    >
                        <span
                            >Hasil Verifikasi Server: Debit
                            {{
                                formatCurrency(previewResultState.total_debit)
                            }}
                            | Kredit
                            {{
                                formatCurrency(previewResultState.total_credit)
                            }}</span
                        >
                        <span class="font-bold">{{
                            previewResultState.is_balanced
                                ? 'Balanced'
                                : 'Tidak Balanced'
                        }}</span>
                    </div>

                    <!-- Form Buttons -->
                    <div
                        class="flex justify-end gap-2 border-t border-gray-200 pt-4"
                    >
                        <button
                            type="button"
                            @click="showFormModal = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{
                                saving
                                    ? 'Menyimpan...'
                                    : formMode === 'create'
                                      ? 'Simpan Sebagai Draft'
                                      : 'Perbarui Draft'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL CONFIRM POSTING -->
        <div
            v-if="showConfirmModal && targetActionJournal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md space-y-4 overflow-hidden rounded-xl bg-white p-6 shadow-xl"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="rounded-full bg-emerald-100 p-2 text-emerald-600"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">
                            Posting Jurnal Umum
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Setelah diposting, jurnal
                            <strong class="font-mono text-gray-800">{{
                                targetActionJournal.journal_no
                            }}</strong>
                            bersifat <strong>immutable</strong> dan tidak dapat
                            diedit secara langsung.
                        </p>
                    </div>
                </div>

                <div
                    class="space-y-1 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs"
                >
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Debit:</span>
                        <span class="font-medium text-gray-900">{{
                            formatCurrency(targetActionJournal.total_debit)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Kredit:</span>
                        <span class="font-medium text-gray-900">{{
                            formatCurrency(targetActionJournal.total_credit)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Selisih:</span>
                        <span class="font-bold text-emerald-600"
                            >Rp 0 (Balanced)</span
                        >
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="showConfirmModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitPost"
                        :disabled="posting"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ posting ? 'Memposting...' : 'Ya, Posting Jurnal' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL REVERSAL -->
        <div
            v-if="showReverseModal && targetActionJournal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md space-y-4 overflow-hidden rounded-xl bg-white p-6 shadow-xl"
            >
                <div class="flex items-start gap-3">
                    <div class="rounded-full bg-purple-100 p-2 text-purple-600">
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">
                            Reversal Jurnal Umum
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Reversal akan membuat jurnal baru dengan posisi
                            debit/kredit dibalik dan mengubah status jurnal
                            <strong class="font-mono text-gray-800">{{
                                targetActionJournal.journal_no
                            }}</strong>
                            menjadi <strong>reversed</strong>.
                        </p>
                    </div>
                </div>

                <form
                    @submit.prevent="submitReverseAction"
                    class="space-y-3 text-xs"
                >
                    <div>
                        <label class="mb-1 block font-medium text-gray-700"
                            >Tanggal Reversal</label
                        >
                        <input
                            v-model="reverseForm.reversal_date"
                            type="date"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block font-medium text-gray-700"
                            >Alasan Reversal (Opsional)</label
                        >
                        <textarea
                            v-model="reverseForm.reason"
                            rows="2"
                            placeholder="Contoh: Koreksi salah catat akun kas..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showReverseModal = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="reversing"
                            class="rounded-lg bg-purple-600 px-4 py-2 text-xs font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                        >
                            {{ reversing ? 'Memproses...' : 'Proses Reversal' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
