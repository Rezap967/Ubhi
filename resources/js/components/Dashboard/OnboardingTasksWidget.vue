<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    ShoppingBag,
    UserPlus,
    ClipboardCheck,
    ChevronRight,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { DashboardTaskItem } from '@/types/dashboard';

const props = withDefaults(
    defineProps<{
        tasks?: DashboardTaskItem[];
    }>(),
    {
        tasks: () => [],
    },
);

const localTasks = ref<DashboardTaskItem[]>([...props.tasks]);

const completedCount = computed(
    () => localTasks.value.filter((t) => t.is_completed).length,
);

const progressPercent = computed(() => {
    if (localTasks.value.length === 0) {
        return 0;
    }

    return Math.round((completedCount.value / localTasks.value.length) * 100);
});

const toggleTask = (task: DashboardTaskItem) => {
    task.is_completed = !task.is_completed;
    task.completed_at = task.is_completed ? new Date().toISOString() : null;
};

const handleNavigate = (actionUrl?: string) => {
    if (!actionUrl) {
        return;
    }

    router.visit(actionUrl);
};

const getIcon = (iconName?: string) => {
    switch (iconName) {
        case 'shopping-bag':
            return ShoppingBag;
        case 'user-plus':
            return UserPlus;
        case 'clipboard-check':
            return ClipboardCheck;
        default:
            return Sparkles;
    }
};
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm"
    >
        <!-- Header Widget -->
        <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 shadow-sm"
                >
                    <Sparkles class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Checklist Persiapan Toko
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ completedCount }} dari
                        {{ localTasks.length }} langkah selesai
                    </p>
                </div>
            </div>
            <span
                class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-600"
            >
                {{ progressPercent }}%
            </span>
        </div>

        <!-- Progress Bar -->
        <div class="mb-6 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full bg-indigo-600 transition-all duration-500"
                :style="{ width: `${progressPercent}%` }"
            ></div>
        </div>

        <!-- Task Items List -->
        <div class="space-y-3">
            <div
                v-for="task in localTasks"
                :key="task.id"
                :class="[
                    'group flex items-start justify-between gap-4 rounded-xl border p-4 transition-all duration-200',
                    task.is_completed
                        ? 'border-slate-100 bg-slate-50/60 opacity-80'
                        : 'border-slate-200/80 bg-white hover:border-indigo-200 hover:bg-indigo-50/20',
                ]"
            >
                <!-- Toggle Check Checkbox & Text -->
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <button
                        type="button"
                        @click="toggleTask(task)"
                        class="mt-0.5 shrink-0 text-slate-400 transition-colors hover:text-indigo-600 focus:outline-none"
                    >
                        <CheckCircle2
                            v-if="task.is_completed"
                            class="h-5 w-5 fill-emerald-500 text-white"
                        />
                        <Circle
                            v-else
                            class="h-5 w-5 text-slate-300 group-hover:text-indigo-500"
                        />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <component
                                :is="getIcon(task.icon)"
                                class="h-4 w-4 shrink-0 text-slate-500"
                            />
                            <h4
                                :class="[
                                    'text-sm font-semibold transition-colors',
                                    task.is_completed
                                        ? 'text-slate-400 line-through'
                                        : 'text-slate-900 group-hover:text-indigo-600',
                                ]"
                            >
                                {{ task.title }}
                            </h4>
                        </div>
                        <p
                            :class="[
                                'mt-1 text-xs leading-relaxed',
                                task.is_completed
                                    ? 'text-slate-400'
                                    : 'text-slate-500',
                            ]"
                        >
                            {{ task.description }}
                        </p>
                    </div>
                </div>

                <!-- Action Link Button -->
                <button
                    v-if="task.action_url && !task.is_completed"
                    type="button"
                    @click="handleNavigate(task.action_url)"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-indigo-600 transition-colors hover:text-indigo-800"
                >
                    <span>Buka</span>
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
