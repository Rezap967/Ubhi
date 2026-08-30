<script setup lang="ts">
import { computed } from 'vue';
import type { PosReceipt } from '@/composables/kasir/usePosReceipt';

const props = defineProps<{
    receipt: PosReceipt | null;
}>();

const payload = computed(() => props.receipt?.receipt_payload);

function formatRupiah(value: number | string): string {
    const numericValue = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(numericValue || 0);
}

function formatDate(dateString?: string): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="print-template">
        <div v-if="receipt" class="receipt-container">
            <div class="header">
                <h2>{{ payload?.store.name }}</h2>
                <p v-if="payload?.store.address">
                    {{ payload?.store.address }}
                </p>
                <p v-if="payload?.store.phone">{{ payload?.store.phone }}</p>
            </div>

            <div class="divider"></div>

            <div class="info">
                <p>No: {{ receipt.receipt_number }}</p>
                <p>
                    Tgl: {{ formatDate(payload?.transaction.transaction_at) }}
                </p>
                <p>Kasir: {{ payload?.cashier.name }}</p>
            </div>

            <div class="divider"></div>

            <div class="items">
                <div v-for="item in payload?.items" :key="item.id" class="item">
                    <div class="item-name">{{ item.product_name }}</div>
                    <div class="item-details">
                        <span
                            >{{ item.quantity }} x
                            {{ formatRupiah(item.unit_price) }}</span
                        >
                        <span>{{ formatRupiah(item.total_price) }}</span>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>{{
                        formatRupiah(payload?.transaction.subtotal || 0)
                    }}</span>
                </div>
                <div
                    v-if="
                        parseFloat(payload?.transaction.discount_total || '0') >
                        0
                    "
                    class="total-row"
                >
                    <span>Diskon</span>
                    <span
                        >-{{
                            formatRupiah(
                                payload?.transaction.discount_total || 0,
                            )
                        }}</span
                    >
                </div>
                <div
                    v-if="parseFloat(payload?.transaction.tax_total || '0') > 0"
                    class="total-row"
                >
                    <span>Pajak</span>
                    <span>{{
                        formatRupiah(payload?.transaction.tax_total || 0)
                    }}</span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL</span>
                    <span>{{
                        formatRupiah(payload?.transaction.grand_total || 0)
                    }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="payments">
                <div
                    v-for="payment in payload?.payments"
                    :key="payment.id"
                    class="payment-row"
                >
                    <span>Bayar ({{ payment.payment_method }})</span>
                    <span>{{ formatRupiah(payment.amount) }}</span>
                </div>
                <div class="payment-row">
                    <span>Kembali</span>
                    <span>{{
                        formatRupiah(payload?.transaction.change_amount || 0)
                    }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="footer">
                <p>Terima Kasih</p>
                <p>
                    Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.print-template {
    display: none;
}

@media print {
    body * {
        visibility: hidden;
    }

    .print-template,
    .print-template * {
        visibility: visible;
    }
}
</style>
