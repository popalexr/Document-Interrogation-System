<script setup lang="ts">
import type { FlashMessages } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, X, XCircle } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';

type ToastType = 'success' | 'error' | 'warning';

type Toast = {
    id: number;
    type: ToastType;
    message: string;
};

const page = usePage();
const toasts = reactive<Toast[]>([]);
const timeouts = new Map<number, ReturnType<typeof window.setTimeout>>();
let counter = 0;

const flash = computed<FlashMessages | undefined>(() => {
    return (page.props as any).flash as FlashMessages | undefined;
});

watch(
    flash,
    (flashMessages) => {
        if (!flashMessages) return;

        (['success', 'error', 'warning'] as ToastType[]).forEach((type) => {
            const message = flashMessages[type];
            if (typeof message === 'string' && message.trim().length) {
                addToast(type, message);
            }
        });
    },
    { immediate: true },
);

function addToast(type: ToastType, message: string) {
    const id = ++counter;
    toasts.push({ id, type, message });

    const timeoutId = window.setTimeout(() => dismiss(id), 4500);
    timeouts.set(id, timeoutId);
}

function dismiss(id: number) {
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toasts.splice(index, 1);
    }

    const timeoutId = timeouts.get(id);
    if (timeoutId) {
        clearTimeout(timeoutId);
        timeouts.delete(id);
    }
}

onBeforeUnmount(() => {
    timeouts.forEach((timeoutId) => clearTimeout(timeoutId));
    timeouts.clear();
});

const variantClasses: Record<ToastType, string> = {
    success: 'bg-emerald-600 text-white ring-emerald-800/40',
    error: 'bg-destructive text-destructive-foreground ring-destructive/40',
    warning: 'bg-amber-500 text-amber-950 ring-amber-700/40',
};

const variantIcons: Record<ToastType, Component> = {
    success: CheckCircle2,
    error: XCircle,
    warning: AlertTriangle,
};
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-3 px-4 sm:left-auto sm:right-4 sm:items-end"
        >
            <TransitionGroup name="flash-toast">
                <article
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-xl shadow-black/10 ring-1 backdrop-blur"
                    :class="variantClasses[toast.type]"
                >
                    <component :is="variantIcons[toast.type]" class="mt-0.5 h-5 w-5 shrink-0" />
                    <p class="leading-5">
                        {{ toast.message }}
                    </p>
                    <button
                        type="button"
                        class="ml-auto rounded-full p-1 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent sm:-mr-1 sm:mt-0.5"
                        aria-label="Dismiss notification"
                        @click="dismiss(toast.id)"
                    >
                        <X class="h-4 w-4 opacity-80" />
                    </button>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.flash-toast-enter-active,
.flash-toast-leave-active {
    transition: all 0.2s ease, opacity 0.18s ease;
}

.flash-toast-enter-from,
.flash-toast-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.98);
}
</style>
