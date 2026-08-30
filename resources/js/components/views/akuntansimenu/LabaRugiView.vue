<script setup lang="ts">
import { RotateCw, Receipt, TrendingUp, Clock, Info } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';

// Interface Item Akun
interface AccountItem {
    account_id?: string;
    code: string;
    name: string;
    amount: number;
}

interface ProfitLossProps extends Record<string, unknown> {
    revenues?: { data?: AccountItem[] };
    expenses?: { data?: AccountItem[] };
    summary?: {
        total_revenue?: number;
        total_revenue_label?: string;
        total_expense?: number;
        total_expense_label?: string;
        net_profit?: number;
        net_profit_label?: string;
        total_data?: number;
    };
    total?: number;
    filters?: { date_start?: string; date_end?: string };
}

const page = usePage<ProfitLossProps>();
const api = useApi();
const startDate = ref(page.props.filters?.date_start || getDefaultDateStart());
const endDate = ref(page.props.filters?.date_end || getDefaultDateEnd());
const revenues = ref<AccountItem[]>(page.props.revenues?.data || []);
const expenses = ref<AccountItem[]>(page.props.expenses?.data || []);
const summary = ref(page.props.summary || {});
const reportTotal = ref(page.props.total || summary.value.total_data || 0);

const fetchReport = async () => {
    const response = await api.getProfitAndLoss({
        date_start: startDate.value,
        date_end: endDate.value,
    });
    const data = response.data as {
        revenues?: AccountItem[];
        expenses?: AccountItem[];
        summary?: ProfitLossProps['summary'];
    };

    revenues.value = data.revenues || [];
    expenses.value = data.expenses || [];
    summary.value = data.summary || {};
    reportTotal.value = summary.value.total_data || 0;
};

const totalRevenue = computed(() => {
    return Number(summary.value.total_revenue || 0);
});

const totalExpense = computed(() => {
    return Number(summary.value.total_expense || 0);
});

const netProfitLoss = computed(() => {
    return Number(summary.value.net_profit || 0);
});

const totalDataCount = computed(() => {
    return Number(reportTotal.value);
});

const currentTime = computed(() =>
    new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);

function getDefaultDateStart(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
}

function getDefaultDateEnd(): string {
    return new Date().toISOString().slice(0, 10);
}

// Currency Formatter
const formatCurrency = (val: number) => {
    return val < 0
        ? '-Rp ' + Math.abs(val).toLocaleString('id-ID')
        : 'Rp ' + val.toLocaleString('id-ID');
};

// Refresh Action
const handleRefresh = () => {
    router.reload({
        only: ['revenues', 'expenses', 'summary', 'total', 'filters', 'period'],
    });
    fetchReport();
};

watch([startDate, endDate], ([dateStart, dateEnd]) => {
    router.get(
        '/akuntansi/laba-rugi',
        {
            date_start: dateStart,
            date_end: dateEnd,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
    fetchReport();
});

onMounted(() => {
    fetchReport();
});
</script>

<template>
    <div
        class="flex min-h-screen w-full flex-col justify-between space-y-6 bg-[#f8fafc] p-6 text-slate-700"
    >
        <div class="space-y-5">
            <!-- Judul Halaman -->
            <h1 class="text-xl font-bold text-slate-800">Laba Rugi</h1>

            <!-- Filter Tanggal -->
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-slate-600"
                        >Dari :</label
                    >

                    <input
                        type="date"
                        v-model="startDate"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-indigo-500 focus:outline-none"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-slate-600"
                        >Sampai :</label
                    >

                    <input
                        type="date"
                        v-model="endDate"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-indigo-500 focus:outline-none"
                    />
                </div>
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

            <!-- KELOMPOK PENDAPATAN -->
            <div class="space-y-3 pt-2">
                <h2
                    class="text-xs font-bold tracking-wider text-emerald-600 uppercase"
                >
                    PENDAPATAN
                </h2>

                <div class="space-y-1">
                    <div
                        v-for="item in revenues"
                        :key="item.code"
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
                            formatCurrency(item.amount)
                        }}</span>
                    </div>
                </div>

                <!-- Box Total Pendapatan -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-emerald-600 uppercase"
                    >
                        TOTAL PENDAPATAN
                    </span>
                    <span class="text-xs font-bold text-emerald-600">
                        {{
                            summary.total_revenue_label ||
                            formatCurrency(totalRevenue)
                        }}
                    </span>
                </div>
            </div>

            <!-- KELOMPOK BEBAN -->
            <div class="space-y-3 pt-4">
                <h2
                    class="text-xs font-bold tracking-wider text-amber-500 uppercase"
                >
                    BEBAN
                </h2>

                <div class="space-y-1">
                    <div
                        v-for="item in expenses"
                        :key="item.code"
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
                            formatCurrency(item.amount)
                        }}</span>
                    </div>
                </div>

                <!-- Box Total Beban -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-amber-500 uppercase"
                    >
                        TOTAL BEBAN
                    </span>
                    <span class="text-xs font-bold text-amber-500">
                        {{
                            summary.total_expense_label ||
                            formatCurrency(totalExpense)
                        }}
                    </span>
                </div>
            </div>

            <!-- KELOMPOK LABA (RUGI) BERSIH -->
            <div class="pt-4">
                <div
                    class="flex items-center justify-between rounded-2xl border border-sky-300/80 bg-sky-100/70 p-5 shadow-2xs"
                >
                    <span
                        class="text-sm font-bold tracking-wider text-sky-600 uppercase"
                    >
                        LABA (RUGI) BERSIH
                    </span>
                    <span class="text-sm font-extrabold text-sky-600">
                        {{
                            summary.net_profit_label ||
                            formatCurrency(netProfitLoss)
                        }}
                    </span>
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
                            <strong class="font-semibold text-slate-700">{{
                                summary.total_revenue_label ||
                                formatCurrency(totalRevenue)
                            }}</strong>
                            · Bersih:
                            <strong class="font-semibold text-slate-700">{{
                                summary.net_profit_label ||
                                formatCurrency(netProfitLoss)
                            }}</strong></span
                        >
                    </div>
                    <span class="text-slate-300">|</span>
                    <div class="flex items-center gap-1.5">
                        <Clock class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            >Waktu:
                            <strong class="font-semibold text-slate-700">{{
                                currentTime
                            }}</strong></span
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
