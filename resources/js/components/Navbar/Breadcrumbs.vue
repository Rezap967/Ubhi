<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types/navbar';

withDefaults(
    defineProps<{
        items: BreadcrumbItem[];
    }>(),
    {
        items: () => [],
    },
);
</script>

<template>
    <nav aria-label="Breadcrumb" class="flex items-center">
        <ol class="flex items-center space-x-1 sm:space-x-2">
            <li
                v-for="(item, index) in items"
                :key="index"
                class="flex items-center"
            >
                <Link
                    v-if="index < items.length - 1"
                    :href="item.href"
                    class="text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    {{ item.title }}
                </Link>
                <span
                    v-else
                    class="text-sm font-semibold text-slate-900"
                    aria-current="page"
                >
                    {{ item.title }}
                </span>

                <ChevronRight
                    v-if="index < items.length - 1"
                    class="mx-1 h-4 w-4 text-slate-400"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </nav>
</template>
