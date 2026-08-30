<template>
    <main class="relative min-h-screen overflow-hidden bg-slate-50">
        <!-- Background decorations -->
        <div
            class="pointer-events-none absolute -top-28 -left-24 h-80 w-80 rounded-full bg-sky-200/60 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -right-24 -bottom-28 h-80 w-80 rounded-full bg-indigo-200/60 blur-3xl"
        />

        <div
            class="relative flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8"
        >
            <slot />
        </div>
    </main>
</template>
