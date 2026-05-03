<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { formatMessageTime } from './interrogationHelpers';
import type { ChatMessage } from './types';

const props = defineProps<{
    messages: ChatMessage[];
}>();

const chatContainer = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    const el = chatContainer.value;
    if (!el) return;

    requestAnimationFrame(() => {
        el.scrollTop = el.scrollHeight;
    });
}

watch(
    () => props.messages.length,
    async () => {
        await nextTick();
        scrollToBottom();
    },
);

defineExpose({
    scrollToBottom,
});
</script>

<template>
    <div
        ref="chatContainer"
        class="h-0 min-h-0 flex-1 overflow-y-auto overscroll-contain px-4"
    >
        <div class="mx-auto flex max-w-4xl flex-col gap-4 py-6">
            <div
                v-for="(message, index) in messages"
                :key="index"
                class="max-w-[78%] rounded-lg px-4 py-3 text-sm shadow-xs"
                :class="
                    message.role === 'user'
                        ? 'self-end bg-primary text-primary-foreground'
                        : 'self-start bg-muted text-foreground'
                "
            >
                <span class="leading-relaxed whitespace-pre-wrap">
                    <span>{{ message.content }}</span>
                    <span
                        v-if="message.role === 'assistant' && message.typing"
                        class="typing-cursor"
                        aria-hidden="true"
                    ></span>
                </span>
                <div class="mt-2 text-[11px] opacity-70">
                    {{ formatMessageTime(message.at) }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.typing-cursor {
    display: inline-block;
    width: 2px;
    height: 1rem;
    margin-left: 2px;
    background: currentColor;
    animation: typing-cursor-blink 0.8s infinite;
    vertical-align: -0.125em;
}

@keyframes typing-cursor-blink {
    0%,
    50% {
        opacity: 1;
    }

    51%,
    100% {
        opacity: 0;
    }
}
</style>
