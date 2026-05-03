<script setup lang="ts">
import { ClipboardCheck, FileText, Plus } from 'lucide-vue-next';

const emit = defineEmits<{
    selectSuggestion: [suggestion: string];
}>();

const suggestions = [
    {
        label: "What's this document about?",
        icon: FileText,
        iconClass: 'bg-amber-200 text-amber-950',
    },
    {
        label: 'Summarize this document.',
        icon: ClipboardCheck,
        iconClass: 'bg-lime-200 text-lime-950',
    },
];
</script>

<template>
    <div class="flex h-full items-center justify-center">
        <div class="flex flex-col">
            <div class="mb-3 text-center text-5xl font-bold">
                Interrogate this document
            </div>
            <div class="mb-10 text-center text-muted-foreground">
                Get started by asking a question about this document. Not sure
                what to ask?
            </div>

            <div class="flex flex-wrap gap-6">
                <div
                    v-for="suggestion in suggestions"
                    :key="suggestion.label"
                    class="flex max-w-xs min-w-[220px] flex-1 items-center justify-between rounded-2xl border border-border bg-card px-5 py-3 shadow-sm transition hover:bg-muted/40 hover:shadow-lg"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl"
                            :class="suggestion.iconClass"
                        >
                            <component :is="suggestion.icon" />
                        </div>
                        <span
                            class="text-center font-medium text-card-foreground"
                        >
                            {{ suggestion.label }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-input text-muted-foreground hover:bg-muted hover:text-foreground"
                        :aria-label="`Ask: ${suggestion.label}`"
                        @click="emit('selectSuggestion', suggestion.label)"
                    >
                        <Plus class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
