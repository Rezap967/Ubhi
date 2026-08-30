<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import { onMounted } from 'vue';
import CashierView from '@/components/views/CashierView.vue';
import CloseSessionModal from '@/components/views/kasir/Components/CloseSessionModal.vue';
import OpenSessionModal from '@/components/views/kasir/Components/OpenSessionModal.vue';
import SessionClosedSummary from '@/components/views/kasir/Components/SessionClosedSummary.vue';
import SessionStatusBar from '@/components/views/kasir/Components/SessionStatusBar.vue';
import { usePosSession } from '@/composables/kasir/usePosSession';
import type { PosSession, PosSummary } from '@/types/pos';

// ── Props from Inertia controller ──────────────────────────────────
// Props session/cart bisa diabaikan karena kita pakai client-side fetch.
const props = defineProps<{
    summary: PosSummary;
}>();

// ── Reactive state ─────────────────────────────────────────────────
const {
    session: activeSession,
    loading,
    fetchCurrentSession,
} = usePosSession();
const currentSummary = ref<PosSummary>(props.summary);
const showCloseModal = ref(false);

// Bentuk lengkap hasil closing yang disimpan di state (untuk summary view)
type ClosedResult = {
    session_id: number;
    status: string;
    opening_cash: number;
    closing_cash: number;
    expected_cash: number;
    cash_difference: number;
    closed_at: string;
};
const closedResult = ref<ClosedResult | null>(null);

// Bentuk payload yang benar-benar dikirim oleh event @session-closed
// dari CloseSessionModal — tidak menyertakan opening_cash, karena
// nilai itu diambil dari activeSession, bukan dari response closing.
type SessionClosedPayload = Omit<ClosedResult, 'opening_cash'>;

// ── Handlers ───────────────────────────────────────────────────────
onMounted(async () => {
    await fetchCurrentSession();
});

const onSessionOpened = (data: PosSession) => {
    activeSession.value = data;
};

const onSessionClosed = (result: SessionClosedPayload) => {
    showCloseModal.value = false;
    // Inject opening_cash from current session for summary display
    closedResult.value = {
        ...result,
        opening_cash: activeSession.value?.opening_cash ?? 0,
    };
    activeSession.value = null;
};

const resetToOpen = () => {
    closedResult.value = null;
    activeSession.value = null;
};
</script>

<template>
    <Head title="Kasir POS" />

    <div class="flex h-screen flex-col overflow-hidden bg-slate-900">
        <div
            v-if="loading"
            class="flex h-full items-center justify-center text-white"
        >
            Memuat Sesi...
        </div>

        <!-- ── No session: Fullscreen open session form ── -->
        <template v-else-if="!activeSession && !closedResult">
            <OpenSessionModal @session-opened="onSessionOpened" />
        </template>

        <!-- ── Session closed: Show summary ── -->
        <template v-else-if="closedResult">
            <div class="flex h-full items-center justify-center">
                <SessionClosedSummary
                    :result="closedResult"
                    @reopen="resetToOpen"
                />
            </div>
        </template>

        <!-- ── Active session: Full POS layout ── -->
        <template v-else-if="activeSession">
            <!-- Session bar on top -->
            <SessionStatusBar
                :session="activeSession"
                :summary="currentSummary"
                @close-session="showCloseModal = true"
            />

            <!-- POS main content area -->
            <div class="flex flex-1 overflow-hidden">
                <CashierView class="w-full" />
            </div>

            <!-- Close session modal overlay -->
            <CloseSessionModal
                v-if="showCloseModal"
                :session="activeSession"
                @session-closed="onSessionClosed"
                @cancel="showCloseModal = false"
            />
        </template>
    </div>
</template>
