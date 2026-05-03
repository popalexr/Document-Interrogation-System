<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ChevronDown,
    FileText,
    Plus,
    SendHorizontal,
    X,
} from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import type { DocumentInfo } from './types';

defineProps<{
    allDocumentsSelected: boolean;
    canSend: boolean;
    selectedDocuments: DocumentInfo[];
}>();

const emit = defineEmits<{
    clearDocuments: [];
    openDocumentPicker: [];
    removeDocument: [documentId: string];
    send: [];
}>();

const input = defineModel<string>({ required: true });

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);
const maxRows = 4;

function autoGrow(): void {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = 'auto';

    const maxHeight = lineHeightPx.value * maxRows;
    const nextHeight = Math.min(el.scrollHeight, maxHeight);

    el.style.height = `${nextHeight}px`;
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

function handleTextareaKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || event.shiftKey) return;

    event.preventDefault();
    emit('send');
}

onMounted(() => {
    const el = textareaRef.value;
    if (!el) return;

    const style = window.getComputedStyle(el);
    lineHeightPx.value = parseFloat(style.lineHeight) || 20;
    autoGrow();
});

watch(input, async () => {
    await nextTick();
    autoGrow();
});
</script>

<template>
    <div class="mx-auto w-full max-w-5xl shrink-0">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <Button
                v-if="selectedDocuments.length === 0"
                variant="outline"
                class="h-10 gap-2 rounded-md bg-background"
                @click="emit('openDocumentPicker')"
            >
                <FileText class="size-4" />
                Add documents to interrogate
                <ChevronDown class="size-4" />
            </Button>

            <Badge
                v-if="allDocumentsSelected"
                variant="secondary"
                class="h-8 max-w-56 gap-1 rounded-md px-2"
            >
                <span class="truncate">All documents included</span>
                <button
                    type="button"
                    class="inline-flex size-5 items-center justify-center rounded hover:bg-background"
                    aria-label="Clear selected documents"
                    @click="emit('clearDocuments')"
                >
                    <X class="size-3" />
                </button>
            </Badge>

            <template v-else>
                <Badge
                    v-for="document in selectedDocuments"
                    :key="document._id"
                    variant="secondary"
                    class="h-8 max-w-56 gap-1 rounded-md px-2"
                >
                    <span class="truncate">
                        {{ document.original_name }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex size-5 items-center justify-center rounded hover:bg-background"
                        :aria-label="`Remove ${document.original_name}`"
                        @click="emit('removeDocument', document._id)"
                    >
                        <X class="size-3" />
                    </button>
                </Badge>
            </template>
        </div>

        <div class="rounded-lg border border-input bg-background">
            <div class="flex items-center gap-3 px-4 pt-3 pb-2">
                <button
                    type="button"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border hover:bg-muted"
                    aria-label="Add documents"
                    @click="emit('openDocumentPicker')"
                >
                    <Plus class="size-4" />
                </button>
                <textarea
                    ref="textareaRef"
                    v-model="input"
                    rows="1"
                    maxlength="500"
                    class="max-h-32 min-h-5 flex-1 resize-none bg-transparent text-sm leading-5 outline-none placeholder:text-muted-foreground"
                    placeholder="Ask a question across the selected documents..."
                    @input="autoGrow"
                    @keydown="handleTextareaKeydown"
                ></textarea>
                <button
                    type="button"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!canSend"
                    aria-label="Send message"
                    @click="emit('send')"
                >
                    <SendHorizontal class="size-5" />
                </button>
            </div>

            <div
                class="flex items-center justify-end px-4 pb-3 text-xs text-muted-foreground"
            >
                <span>{{ input.length }}/500</span>
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-muted-foreground">
            This application can make mistakes. Please verify any information it
            provides.
        </p>
    </div>
</template>
