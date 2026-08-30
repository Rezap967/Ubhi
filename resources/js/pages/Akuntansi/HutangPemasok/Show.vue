<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage<any>();
</script>

<template>
    <div class="min-h-screen bg-[#F4F6F9] p-6 text-sm text-gray-800">
        <Link
            href="/akuntansi/hutang-pemasok"
            class="text-blue-700 hover:underline"
            >Kembali ke Hutang Usaha</Link
        >
        <div
            class="mt-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
        >
            <h1 class="text-xl font-bold">
                {{ page.props.payable?.supplier_name }}
            </h1>
            <p class="mt-1 text-gray-500">
                {{ page.props.payable?.payable_no }}
            </p>
            <div class="mt-6 space-y-2">
                <div class="flex justify-between">
                    <span>Sisa hutang</span
                    ><strong>{{
                        page.props.payable?.outstanding_amount_label
                    }}</strong>
                </div>
                <div class="flex justify-between">
                    <span>Status</span
                    ><strong>{{ page.props.payable?.status_label }}</strong>
                </div>
                <div class="flex justify-between">
                    <span>Jatuh tempo</span
                    ><strong>{{ page.props.payable?.due_date_label }}</strong>
                </div>
            </div>
        </div>
    </div>
</template>
