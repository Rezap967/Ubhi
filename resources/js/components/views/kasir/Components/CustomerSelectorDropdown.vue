<template>
    <div class="relative" ref="containerRef">
        <!-- ─── Trigger ─── -->
        <button
            type="button"
            id="customer-selector-trigger"
            @click="toggleOpen"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm transition hover:border-slate-400 focus:ring-2 focus:ring-blue-500/30 focus:outline-none"
            :class="{ 'border-blue-400 ring-2 ring-blue-500/20': isOpen }"
        >
            <div class="flex min-w-0 items-center gap-2">
                <!-- UMUM icon: person-off -->
                <svg
                    v-if="
                        selectedCustomer?.code === 'MBR-000000' ||
                        !selectedCustomer
                    "
                    class="h-4 w-4 shrink-0 text-slate-400"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                    />
                    <line
                        x1="3"
                        y1="3"
                        x2="21"
                        y2="21"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                    />
                </svg>
                <!-- Normal person icon -->
                <svg
                    v-else
                    class="h-4 w-4 shrink-0 text-slate-500"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                    />
                </svg>
                <span class="truncate font-medium text-slate-800">
                    {{ selectedCustomer?.name ?? 'UMUM' }}
                </span>
            </div>
            <!-- Chevron -->
            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <!-- ─── Dropdown panel (Teleported to body to prevent clipping) ─── -->
        <Teleport to="body">
            <Transition name="dropdown">
                <div
                    v-if="isOpen"
                    ref="dropdownRef"
                    :style="dropdownStyle"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
                >
                    <!-- Search -->
                    <div class="border-b border-slate-100 p-3">
                        <div
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
                            :class="
                                searchFocused
                                    ? 'border-blue-400 ring-2 ring-blue-500/20'
                                    : 'border-slate-200 bg-slate-50'
                            "
                        >
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                                type="text"
                                placeholder="Cari nama pelanggan..."
                                class="min-w-0 flex-1 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                                id="customer-search-input"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="clearSearch"
                                class="shrink-0 rounded p-0.5 text-slate-400 hover:text-slate-700"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Customer list -->
                    <div class="max-h-56 overflow-y-auto">
                        <!-- Loading -->
                        <div v-if="loading" class="space-y-1 p-2">
                            <div
                                v-for="i in 4"
                                :key="i"
                                class="h-9 animate-pulse rounded-lg bg-slate-100"
                            ></div>
                        </div>

                        <template v-else>
                            <!-- Empty -->
                            <p
                                v-if="customers.length === 0"
                                class="px-4 py-6 text-center text-sm text-slate-400"
                            >
                                Pelanggan tidak ditemukan.
                            </p>

                            <!-- Items -->
                            <button
                                v-for="customer in customers"
                                :key="customer.id"
                                type="button"
                                @click="selectCustomer(customer)"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-slate-50"
                                :class="{
                                    'bg-slate-50':
                                        selectedCustomer?.id === customer.id,
                                    'font-medium':
                                        selectedCustomer?.id === customer.id,
                                }"
                            >
                                <!-- UMUM icon -->
                                <svg
                                    v-if="customer.code === 'MBR-000000'"
                                    class="h-4 w-4 shrink-0 text-slate-400"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                    />
                                    <line
                                        x1="3"
                                        y1="3"
                                        x2="21"
                                        y2="21"
                                        stroke="currentColor"
                                        stroke-width="1.75"
                                        stroke-linecap="round"
                                    />
                                </svg>
                                <!-- Normal icon -->
                                <svg
                                    v-else
                                    class="h-4 w-4 shrink-0 text-slate-400"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                    />
                                </svg>
                                <span class="truncate text-slate-800">{{
                                    customer.name
                                }}</span>
                            </button>
                        </template>
                    </div>

                    <!-- ─── Quick Create toggle ─── -->
                    <div class="border-t border-slate-100">
                        <button
                            type="button"
                            @click="openTambahPelanggan"
                            class="flex w-full items-center gap-2.5 px-4 py-3 text-sm font-semibold text-blue-600 transition hover:bg-blue-50"
                        >
                            <svg
                                class="h-4 w-4 shrink-0"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            Buat pelanggan baru
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Tambah Pelanggan Modal -->
        <TambahPelanggan
            v-if="showTambahPelanggan"
            @close="showTambahPelanggan = false"
            @saved="handleCustomerSaved"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import TambahPelanggan from '@/components/views/pelangganmenu/Components/TambahPelanggan.vue';
import { usePosCustomers } from '@/composables/kasir/usePosCustomers';
import type { PosCustomer, PosCart } from '@/types/pos';

defineProps<{
    selectedCustomer: PosCustomer | null;
}>();

const emit = defineEmits<{
    (e: 'customer-changed', cart: PosCart): void;
}>();

const { customers, loading, fetchCustomers, setCartCustomer } =
    usePosCustomers();

// ── State ──────────────────────────────────
const isOpen = ref(false);
const searchQuery = ref('');
const searchFocused = ref(false);
const showTambahPelanggan = ref(false);

const containerRef = ref<HTMLElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const dropdownStyle = ref<Record<string, string>>({});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

// ── Position calculation ───────────────────
const updateDropdownPosition = () => {
    if (!containerRef.value) return;

    const rect = containerRef.value.getBoundingClientRect();
    const spaceAbove = rect.top;
    const spaceBelow = window.innerHeight - rect.bottom;

    // Prioritaskan buka ke atas jika ruang di atas cukup (>= 240px) atau lebih besar dari ruang bawah
    const openUpward = spaceAbove >= 240 || spaceAbove > spaceBelow;

    if (openUpward) {
        dropdownStyle.value = {
            position: 'fixed',
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            bottom: `${window.innerHeight - rect.top + 6}px`,
            top: 'auto',
            maxHeight: `${Math.min(spaceAbove - 16, 360)}px`,
            zIndex: '99999',
        };
    } else {
        dropdownStyle.value = {
            position: 'fixed',
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            top: `${rect.bottom + 6}px`,
            bottom: 'auto',
            maxHeight: `${Math.min(spaceBelow - 16, 360)}px`,
            zIndex: '99999',
        };
    }
};

const handleScrollOrResize = () => {
    if (isOpen.value) {
        updateDropdownPosition();
    }
};

// ── Open / close ───────────────────────────
const toggleOpen = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        updateDropdownPosition();
        await fetchCustomers();
        await nextTick();
        updateDropdownPosition();
        searchInputRef.value?.focus();
    } else {
        resetDropdown();
    }
};

const close = () => {
    isOpen.value = false;
    resetDropdown();
};

const resetDropdown = () => {
    searchQuery.value = '';
};

const openTambahPelanggan = () => {
    showTambahPelanggan.value = true;
    close(); // close dropdown while modal is open
};

// ── Click outside to close ─────────────────
const handleClickOutside = (e: MouseEvent) => {
    const target = e.target as Node;
    const clickedInsideTrigger = containerRef.value?.contains(target);
    const clickedInsideDropdown = dropdownRef.value?.contains(target);

    if (!clickedInsideTrigger && !clickedInsideDropdown) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
    window.addEventListener('resize', handleScrollOrResize, { passive: true });
    window.addEventListener('scroll', handleScrollOrResize, {
        capture: true,
        passive: true,
    });
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    window.removeEventListener('resize', handleScrollOrResize);
    window.removeEventListener('scroll', handleScrollOrResize, true);
});

// ── Search ─────────────────────────────────
const onSearchInput = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchCustomers(searchQuery.value);
    }, 300);
};

const clearSearch = () => {
    searchQuery.value = '';
    fetchCustomers();
};

// ── Select customer ────────────────────────
const selectCustomer = async (customer: PosCustomer) => {
    try {
        const customerId = customer.code === 'MBR-000000' ? null : customer.id;
        const updatedCart = await setCartCustomer(customerId);
        emit('customer-changed', updatedCart);
        close();
    } catch {
        // Error sudah di-handle di composable
    }
};

// ── Customer Saved ──────────────────────────
const handleCustomerSaved = async (customerData: PosCustomer | undefined) => {
    showTambahPelanggan.value = false;

    if (customerData) {
        // Attempt to set it as active customer
        await selectCustomer(customerData);

        // Refresh the list in the background
        fetchCustomers();
    }
};
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
</style>
