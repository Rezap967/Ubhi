<script setup lang="ts">
import {
    RotateCw,
    CheckCircle2,
    AlertCircle,
    Receipt,
    TrendingUp,
    Clock,
    Info,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface BalanceSheetProps extends Record<string, unknown> {
    assets?: { data: any[] };
    liabilities?: { data: any[] };
    equities?: { data: any[] };
    summary?: any;
    filters?: any;
    total?: number;
}

const page = usePage<BalanceSheetProps>();

// State Tanggal Filter
const asOfDate = computed({
    get: () =>
        page.props.filters?.as_of_date ||
        new Date().toISOString().split('T')[0],
    set: (value: string) => {
        router.get(
            '/akuntansi/neraca',
            { as_of_date: value },
            { preserveState: true, preserveScroll: true },
        );
    },
});

// Calculations
const totalAssets = computed(() => {
    return page.props.summary?.total_assets || 0;
});

const totalLiabilities = computed(() => {
    return page.props.summary?.total_liabilities || 0;
});

const totalEquity = computed(() => {
    return page.props.summary?.total_equity || 0;
});

const totalPasiva = computed(() => {
    return page.props.summary?.total_pasiva || 0;
});

const isBalanced = computed(() => {
    return page.props.summary?.is_balanced || false;
});

const totalDataCount = computed(() => {
    return page.props.total || 0;
});

// Currency Formatter (matches backend format)
const formatCurrency = (val: number) => {
    if (val < 0) {
        return '-Rp ' + Math.abs(val).toLocaleString('id-ID');
    }
    return 'Rp ' + val.toLocaleString('id-ID');
};

// Refresh Action
const handleRefresh = () => {
    router.reload({
        only: ['assets', 'liabilities', 'equities', 'summary', 'total'],
    });
};
</script>

<template>
    <div
        class="flex min-h-screen w-full flex-col justify-between space-y-6 bg-[#f8fafc] p-6 text-slate-700"
    >
        <div class="space-y-5">
            <!-- Judul Halaman -->
            <h1 class="text-xl font-bold text-slate-800">Neraca</h1>

            <!-- Filter Tanggal -->
            <div class="flex w-fit flex-col gap-1">
                <label class="text-xs font-medium text-slate-600"
                    >Per Tanggal :</label
                >
                <input
                    type="date"
                    v-model="asOfDate"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-indigo-500 focus:outline-none"
                />
            </div>

            <!-- Action Toolbar & Total Info -->
            <div
                class="flex items-center justify-between gap-4 border-t border-slate-200/60 pt-1"
            >
                <div class="flex items-center gap-2 pt-2">
                    <button
                        @click="handleRefresh"
                        class="cursor-pointer rounded-lg bg-slate-600 p-2 text-white shadow-2xs transition-colors hover:bg-slate-700"
                        title="Muat Ulang"
                    >
                        <RotateCw class="h-4 w-4" />
                    </button>
                </div>

                <span class="pt-2 text-xs text-slate-500">
                    Total data yang ditemukan :
                    <strong class="font-semibold text-slate-700">{{
                        totalDataCount
                    }}</strong
                    >.
                </span>
            </div>

            <!-- KELOMPOK AKTIVA (ASET) -->
            <div class="space-y-3 pt-2">
                <h2
                    class="text-xs font-bold tracking-wider text-sky-500 uppercase"
                >
                    AKTIVA (ASET)
                </h2>

                <div class="space-y-1">
                    <div
                        v-for="item in page.props.assets?.data || []"
                        :key="item.account_id"
                        class="flex items-center justify-between rounded-lg px-1 py-2 transition-colors hover:bg-slate-100/50"
                    >
                        <div>
                            <p class="text-xs font-semibold text-slate-800">
                                {{ item.name }}
                            </p>
                            <p class="text-[11px] font-medium text-slate-400">
                                {{ item.code }}
                            </p>
                        </div>
                        <span class="text-xs font-bold text-slate-800">{{
                            item.amount_label
                        }}</span>
                    </div>
                </div>

                <!-- Box Total Aktiva -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-sky-200/60 bg-sky-50/70 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-sky-500 uppercase"
                    >
                        TOTAL AKTIVA (ASET)
                    </span>
                    <span class="text-xs font-bold text-sky-500">
                        {{ page.props.summary?.total_assets_label || 'Rp 0' }}
                    </span>
                </div>
            </div>

            <!-- KELOMPOK KEWAJIBAN (LIABILITAS) -->
            <div class="space-y-3 pt-4">
                <h2
                    class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                >
                    KEWAJIBAN (LIABILITAS)
                </h2>

                <div class="space-y-1">
                    <div
                        v-for="item in page.props.liabilities?.data || []"
                        :key="item.account_id"
                        class="flex items-center justify-between rounded-lg px-1 py-2 transition-colors hover:bg-slate-100/50"
                    >
                        <div>
                            <p class="text-xs font-semibold text-slate-800">
                                {{ item.name }}
                            </p>
                            <p class="text-[11px] font-medium text-slate-400">
                                {{ item.code }}
                            </p>
                        </div>
                        <span class="text-xs font-bold text-slate-800">{{
                            item.amount_label
                        }}</span>
                    </div>
                </div>

                <!-- Box Total Kewajiban -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-rose-200/70 bg-rose-50/60 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                    >
                        TOTAL KEWAJIBAN (LIABILITAS)
                    </span>
                    <span class="text-xs font-bold text-rose-500">
                        {{
                            page.props.summary?.total_liabilities_label ||
                            'Rp 0'
                        }}
                    </span>
                </div>
            </div>

            <!-- KELOMPOK MODAL (EKUITAS) -->
            <div class="space-y-3 pt-4">
                <h2
                    class="text-xs font-bold tracking-wider text-purple-600 uppercase"
                >
                    MODAL (EKUITAS)
                </h2>

                <div class="space-y-1">
                    <div
                        v-for="item in page.props.equities?.data || []"
                        :key="item.account_id"
                        class="flex items-center justify-between rounded-lg px-1 py-2 transition-colors hover:bg-slate-100/50"
                    >
                        <div>
                            <p class="text-xs font-semibold text-slate-800">
                                {{ item.name }}
                            </p>
                            <p class="text-[11px] font-medium text-slate-400">
                                {{ item.code }}
                            </p>
                        </div>
                        <span class="text-xs font-bold text-slate-800">{{
                            item.amount_label
                        }}</span>
                    </div>
                </div>

                <!-- Box Total Modal -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-purple-200/70 bg-purple-50/60 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-purple-600 uppercase"
                    >
                        TOTAL MODAL (EKUITAS)
                    </span>
                    <span class="text-xs font-bold text-purple-600">
                        {{ page.props.summary?.total_equity_label || 'Rp 0' }}
                    </span>
                </div>
            </div>

            <!-- BOX RINGKASAN NERACA (TOTAL AKTIVA VS PASIVA & STATUS BALANCE) -->
            <div class="pt-4">
                <div
                    class="space-y-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-5 shadow-2xs"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-sky-500 uppercase"
                        >
                            TOTAL AKTIVA
                        </span>
                        <span class="text-sm font-bold text-sky-500">
                            {{
                                page.props.summary?.total_assets_label || 'Rp 0'
                            }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >
                            TOTAL PASIVA
                        </span>
                        <span class="text-sm font-bold text-rose-500">
                            {{
                                page.props.summary?.total_pasiva_label || 'Rp 0'
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-center gap-2 pt-2 text-xs font-bold text-emerald-600"
                    >
                        <template v-if="isBalanced">
                            <CheckCircle2 class="h-4 w-4" />
                            <span>Neraca Seimbang</span>
                        </template>
                        <template v-else>
                            <AlertCircle class="h-4 w-4 text-rose-600" />
                            <span class="text-rose-600"
                                >Neraca Tidak Seimbang</span
                            >
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Bar / Footer Sistem Bottom -->
        <div class="pt-6">
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/60 bg-slate-100/80 px-4 py-2.5 text-[11px] text-slate-500"
            >
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <Receipt class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            >Penjualan:
                            <strong class="font-semibold text-slate-700"
                                >0 transaksi</strong
                            ></span
                        >
                    </div>
                    <span class="text-slate-300">|</span>
                    <div class="flex items-center gap-1.5">
                        <TrendingUp class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            >Pendapatan: Kotor:
                            <strong class="font-semibold text-slate-700"
                                >Rp 0</strong
                            >
                            · Bersih:
                            <strong class="font-semibold text-slate-700"
                                >Rp 0</strong
                            ></span
                        >
                    </div>
                    <span class="text-slate-300">|</span>
                    <div class="flex items-center gap-1.5">
                        <Clock class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            >Waktu:
                            <strong class="font-semibold text-slate-700"
                                >15:18</strong
                            ></span
                        >
                    </div>
                    <span class="text-slate-300">|</span>
                    <div class="flex items-center gap-1.5">
                        <Info class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            >Versi:
                            <strong class="font-semibold text-slate-700"
                                >v1.2.37</strong
                            ></span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
