<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    collapsed: boolean;
}>();
</script>

<template>
    <div
        class="flex h-16 shrink-0 items-center border-b border-slate-100 px-4 transition-all duration-300"
    >
        <Link
            href="/UbhiSir"
            class="group flex w-full items-center gap-3 focus:outline-none"
        >
            <!-- Logo Image -->
            <div
                class="flex h-15 w-15 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm transition-transform group-hover:scale-105"
                :class="collapsed ? 'mx-auto' : ''"
            >
                <img
                    src="/images/welcome/Logo_UbhiSir.png"
                    alt="UbhiSir Logo"
                    class="h-full w-full object-contain"
                />
            </div>

            <!-- Branding Text (Hidden when collapsed) -->
            <div v-show="!collapsed" class="flex min-w-0 flex-col">
                <span
                    class="truncate text-lg leading-none font-bold tracking-tight text-slate-900"
                    >UbhiSir</span
                >
                <span
                    class="mt-1 truncate text-[10px] font-medium tracking-widest text-slate-500 uppercase"
                    >Kasir & Akuntansi</span
                >
            </div>
        </Link>
    </div>
</template>
