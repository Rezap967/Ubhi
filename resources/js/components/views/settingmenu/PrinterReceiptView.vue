<script setup lang="ts">
import {
    Printer as PrinterIcon,
    Check,
    Save,
    Loader2,
    Plus,
    Trash2,
    Edit3,
    X,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface PrinterItem {
    id: number;
    name: string;
    type: 'thermal' | 'inkjet' | 'laser' | 'bluetooth' | 'network';
    paper_size: string;
    copies: number;
    is_default: boolean;
    status: 'active' | 'inactive';
}

interface ReceiptSetting {
    header_text?: string;
    footer_text?: string;
    show_logo?: boolean;
    show_qr_code?: boolean;
    show_tax?: boolean;
    show_discount?: boolean;
    show_cashier_name?: boolean;
    tax_label?: string;
    tax_percent?: number;
}

// Printers
const printers = ref<PrinterItem[]>([]);
const isLoadingPrinters = ref(true);

// Receipt
const receiptSetting = ref<ReceiptSetting>({
    header_text: '',
    footer_text: 'Terima Kasih Telah Berbelanja',
    show_logo: true,
    show_qr_code: false,
    show_tax: false,
    show_discount: true,
    show_cashier_name: true,
    tax_label: 'PPN',
    tax_percent: 11,
});
const isLoadingReceipt = ref(true);
const isSavingReceipt = ref(false);

// Toast
const toastMessage = ref('');
const isToastVisible = ref(false);
const toastType = ref<'success' | 'error'>('success');

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    isToastVisible.value = true;
    setTimeout(() => {
        isToastVisible.value = false;
    }, 3000);
};

// Load Data
const loadPrinters = async () => {
    isLoadingPrinters.value = true;

    try {
        const res = await api.getPrinters();

        if (res.success && res.data) {
            printers.value = (res.data as PrinterItem[]) ?? [];
        }
    } catch {
        // Fallback / silently ignore
    } finally {
        isLoadingPrinters.value = false;
    }
};

const loadReceipt = async () => {
    isLoadingReceipt.value = true;

    try {
        const res = await api.getReceipts();

        if (res.success && res.data) {
            receiptSetting.value = {
                ...receiptSetting.value,
                ...(res.data as ReceiptSetting),
            };
        }
    } catch {
        // Fallback
    } finally {
        isLoadingReceipt.value = false;
    }
};

onMounted(() => {
    loadPrinters();
    loadReceipt();
});

// Save Receipt
const handleSaveReceipt = async () => {
    isSavingReceipt.value = true;

    try {
        const res = await api.updateReceipts(receiptSetting.value);

        if (res.success) {
            showToast('Pengaturan struk berhasil disimpan!');
        }
    } catch {
        showToast('Gagal menyimpan pengaturan struk.', 'error');
    } finally {
        isSavingReceipt.value = false;
    }
};

// Printer CRUD Modal
const isPrinterModalOpen = ref(false);
const isEditingPrinter = ref(false);
const editingPrinterId = ref<number | null>(null);
const isSavingPrinter = ref(false);
const printerForm = ref({
    name: '',
    type: 'thermal' as 'thermal' | 'inkjet' | 'laser' | 'bluetooth' | 'network',
    paper_size: '58mm',
    copies: 1,
    is_default: false,
    status: 'active' as 'active' | 'inactive',
});

const openAddPrinterModal = () => {
    isEditingPrinter.value = false;
    editingPrinterId.value = null;
    printerForm.value = {
        name: '',
        type: 'thermal',
        paper_size: '58mm',
        copies: 1,
        is_default: printers.value.length === 0,
        status: 'active',
    };
    isPrinterModalOpen.value = true;
};

const openEditPrinterModal = (printer: PrinterItem) => {
    isEditingPrinter.value = true;
    editingPrinterId.value = printer.id;
    printerForm.value = {
        name: printer.name,
        type: printer.type,
        paper_size: printer.paper_size,
        copies: printer.copies,
        is_default: printer.is_default,
        status: printer.status,
    };
    isPrinterModalOpen.value = true;
};

const handleSavePrinter = async () => {
    if (!printerForm.value.name) {
        return;
    }

    isSavingPrinter.value = true;

    try {
        if (isEditingPrinter.value && editingPrinterId.value) {
            await api.updatePrinter(editingPrinterId.value, printerForm.value);
            showToast('Printer berhasil diperbarui!');
        } else {
            await api.createPrinter(printerForm.value);
            showToast('Printer baru berhasil ditambahkan!');
        }

        isPrinterModalOpen.value = false;
        loadPrinters();
    } catch {
        showToast('Gagal menyimpan printer.', 'error');
    } finally {
        isSavingPrinter.value = false;
    }
};

const handleDeletePrinter = async (printer: PrinterItem) => {
    if (!confirm(`Hapus printer "${printer.name}"?`)) {
        return;
    }

    try {
        await api.deletePrinter(printer.id);
        showToast('Printer berhasil dihapus.');
        loadPrinters();
    } catch {
        showToast('Gagal menghapus printer.', 'error');
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Toast -->
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isToastVisible"
                :class="[
                    'fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-2xl px-5 py-3.5 text-white shadow-xl',
                    toastType === 'error' ? 'bg-red-600' : 'bg-slate-900',
                ]"
            >
                <div
                    :class="[
                        'flex h-7 w-7 items-center justify-center rounded-full text-white',
                        toastType === 'error' ? 'bg-red-400' : 'bg-emerald-500',
                    ]"
                >
                    <Check class="h-4 w-4" />
                </div>
                <span class="text-sm font-medium">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- HEADER -->
        <div
            class="mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-indigo-600 to-indigo-700 p-7 shadow-xl shadow-indigo-500/20"
        >
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm"
                    >
                        <PrinterIcon class="h-7 w-7 text-white" />
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-white md:text-3xl"
                        >
                            Printer & Struk
                        </h1>
                        <p class="mt-0.5 text-sm text-indigo-200">
                            Atur printer default dan kustomisasi cetak struk
                            transaksi.
                        </p>
                    </div>
                </div>
                <button
                    @click="openAddPrinterModal"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-indigo-600 shadow-md transition-all hover:bg-indigo-50"
                >
                    <Plus class="h-4 w-4" />
                    <span>Tambah Printer</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <!-- PRINTER LIST CARD -->
            <div
                class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm md:p-8"
            >
                <div
                    class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h2 class="text-lg font-bold text-slate-900">
                        Daftar Printer Perangkat
                    </h2>
                    <span class="text-xs font-semibold text-slate-400"
                        >{{ printers.length }} Terdaftar</span
                    >
                </div>

                <div
                    v-if="isLoadingPrinters"
                    class="flex h-32 items-center justify-center"
                >
                    <Loader2 class="h-6 w-6 animate-spin text-indigo-600" />
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="printer in printers"
                        :key="printer.id"
                        class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition-colors hover:bg-slate-50"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                            >
                                <PrinterIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-slate-900">
                                        {{ printer.name }}
                                    </h3>
                                    <span
                                        v-if="printer.is_default"
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                                        >Default</span
                                    >
                                </div>
                                <p class="text-xs text-slate-500 capitalize">
                                    {{ printer.type }} •
                                    {{ printer.paper_size }} •
                                    {{ printer.copies }} Rangkap
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                @click="openEditPrinterModal(printer)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                            >
                                <Edit3 class="h-4 w-4" />
                            </button>
                            <button
                                @click="handleDeletePrinter(printer)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="printers.length === 0"
                        class="py-8 text-center text-slate-400"
                    >
                        <PrinterIcon
                            class="mx-auto mb-2 h-8 w-8 text-slate-300"
                        />
                        <p class="text-sm">Belum ada printer terdaftar.</p>
                    </div>
                </div>
            </div>

            <!-- RECEIPT SETTINGS CARD -->
            <div
                class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm md:p-8"
            >
                <div
                    class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h2 class="text-lg font-bold text-slate-900">
                        Kustomisasi Struk
                    </h2>
                </div>

                <div
                    v-if="isLoadingReceipt"
                    class="flex h-32 items-center justify-center"
                >
                    <Loader2 class="h-6 w-6 animate-spin text-indigo-600" />
                </div>

                <form
                    v-else
                    @submit.prevent="handleSaveReceipt"
                    class="space-y-4"
                >
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                            >Teks Header Struk</label
                        >
                        <input
                            v-model="receiptSetting.header_text"
                            type="text"
                            placeholder="Contoh: Selamat Datang di Toko Kami"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                            >Teks Footer Struk</label
                        >
                        <textarea
                            v-model="receiptSetting.footer_text"
                            rows="2"
                            placeholder="Contoh: Terima Kasih Telah Berbelanja!"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        ></textarea>
                    </div>

                    <div class="space-y-3 pt-2">
                        <label
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <span class="text-sm font-medium text-slate-700"
                                >Tampilkan Logo Toko</span
                            >
                            <input
                                v-model="receiptSetting.show_logo"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <span class="text-sm font-medium text-slate-700"
                                >Tampilkan QR Code</span
                            >
                            <input
                                v-model="receiptSetting.show_qr_code"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <span class="text-sm font-medium text-slate-700"
                                >Tampilkan Diskon</span
                            >
                            <input
                                v-model="receiptSetting.show_discount"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <span class="text-sm font-medium text-slate-700"
                                >Tampilkan Nama Kasir</span
                            >
                            <input
                                v-model="receiptSetting.show_cashier_name"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <span class="text-sm font-medium text-slate-700"
                                >Tampilkan Pajak</span
                            >
                            <input
                                v-model="receiptSetting.show_tax"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            :disabled="isSavingReceipt"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 disabled:opacity-60"
                        >
                            <Loader2
                                v-if="isSavingReceipt"
                                class="h-4 w-4 animate-spin"
                            />
                            <Save v-else class="h-4 w-4" />
                            <span>{{
                                isSavingReceipt
                                    ? 'Menyimpan...'
                                    : 'Simpan Pengaturan Struk'
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PRINTER MODAL -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isPrinterModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                @click.self="isPrinterModalOpen = false"
            >
                <div
                    class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-6 shadow-2xl md:p-8"
                >
                    <div
                        class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                    >
                        <h3 class="text-lg font-bold text-slate-900">
                            {{
                                isEditingPrinter
                                    ? 'Edit Printer'
                                    : 'Tambah Printer Baru'
                            }}
                        </h3>
                        <button
                            @click="isPrinterModalOpen = false"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <form @submit.prevent="handleSavePrinter" class="space-y-4">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Nama Printer *</label
                            >
                            <input
                                v-model="printerForm.name"
                                type="text"
                                required
                                placeholder="Contoh: Thermal Kasir Utam"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                    >Tipe Printer</label
                                >
                                <select
                                    v-model="printerForm.type"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="thermal">Thermal</option>
                                    <option value="bluetooth">Bluetooth</option>
                                    <option value="network">Network</option>
                                    <option value="inkjet">Inkjet</option>
                                    <option value="laser">Laser</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                    >Ukuran Kertas</label
                                >
                                <select
                                    v-model="printerForm.paper_size"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="58mm">58 mm</option>
                                    <option value="80mm">80 mm</option>
                                    <option value="A4">A4</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                    >Jumlah Cetak</label
                                >
                                <input
                                    v-model.number="printerForm.copies"
                                    type="number"
                                    min="1"
                                    max="5"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="printerForm.is_default"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span
                                        class="text-xs font-semibold text-slate-700"
                                        >Set Default</span
                                    >
                                </label>
                            </div>
                        </div>
                        <div
                            class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4"
                        >
                            <button
                                @click="isPrinterModalOpen = false"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSavingPrinter"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isSavingPrinter"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Save v-else class="h-4 w-4" />
                                <span>{{
                                    isSavingPrinter ? 'Menyimpan...' : 'Simpan'
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
