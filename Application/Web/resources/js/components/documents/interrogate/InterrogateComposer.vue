<script setup lang="ts">
import { SendHorizontal } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

defineProps<{
    sending: boolean;
}>();

const emit = defineEmits<{
    send: [];
}>();

const input = defineModel<string>({ required: true });

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);
const maxRows = 4;

const autoGrow = () => {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = 'auto';

    const maxHeight = lineHeightPx.value * maxRows;
    const newHeight = Math.min(el.scrollHeight, maxHeight);

    el.style.height = `${newHeight}px`;
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
};

const handleTextareaKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey) return;

    event.preventDefault();
    emit('send');
};

onMounted(() => {
    const el = textareaRef.value;
    if (!el) return;

    const style = window.getComputedStyle(el);
    lineHeightPx.value = parseFloat(style.lineHeight) || 16;
    autoGrow();
});

watch(input, async () => {
    await nextTick();
    autoGrow();
});
</script>

<template>
    <div class="flex h-full w-full flex-col items-center justify-center">
        <div class="mx-auto mt-6 w-3/4">
            <div class="rounded-2xl border border-input">
                <div
                    class="flex flex-col rounded-2xl bg-background text-sm shadow-sm"
                >
                    <div class="flex items-center gap-3 px-4 pt-2 pb-1">
                        <textarea
                            ref="textareaRef"
                            v-model="input"
                            rows="1"
                            class="w-full resize-none border-none bg-transparent text-sm leading-snug text-foreground outline-none placeholder:text-muted-foreground"
                            placeholder="Ask a question about this document..."
                            maxlength="500"
                            @input="autoGrow"
                            @keydown="handleTextareaKeydown"
                        ></textarea>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center text-xs disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="sending || !input.trim()"
                            aria-label="Send message"
                            @click="emit('send')"
                        >
                            <SendHorizontal />
                        </button>
                    </div>
                    <div
                        class="flex items-center justify-end px-4 py-2 text-xs text-muted-foreground"
                    >
                        {{ input.length }}/500
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-2 text-xs text-muted-foreground">
            This application can make mistakes. Please verify any information it
            provides.
        </div>
    </div>
</template>
