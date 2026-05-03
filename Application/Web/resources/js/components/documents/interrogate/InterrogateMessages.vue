<script setup lang="ts">
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { nextTick, ref, watch } from 'vue';
import type { ChatMessage } from './types';

const props = defineProps<{
    messages: ChatMessage[];
}>();

const chatContainer = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    const el = chatContainer.value;
    if (!el) return;

    requestAnimationFrame(() => {
        el.scrollTop = el.scrollHeight;
    });
};

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
    <div class="flex h-full w-full justify-center pt-6">
        <div
            ref="chatContainer"
            class="flex h-full w-3/4 flex-col gap-4 overflow-y-auto pr-2 pb-2"
        >
            <div
                v-for="(message, index) in messages"
                :key="index"
                :class="
                    message.role === 'user'
                        ? 'self-end bg-primary text-primary-foreground'
                        : 'self-start bg-muted text-foreground'
                "
                class="max-w-3/4 rounded-md px-4 py-3 text-sm whitespace-pre-wrap"
            >
                <span class="leading-relaxed whitespace-pre-wrap">
                    <span>{{ message.content }}</span>
                    <Spinner
                        v-if="message.role === 'assistant' && message.loading"
                        size="sm"
                        class="ml-2 inline-block shrink-0 align-middle"
                    />
                    <span
                        v-else-if="
                            message.role === 'assistant' && message.typing
                        "
                        class="typing-cursor"
                        aria-hidden="true"
                    ></span>
                </span>
                <div
                    class="mt-1 text-[10px] opacity-70"
                    :class="{ 'flex justify-end': message.role === 'user' }"
                >
                    {{ new Date(message.at).toLocaleTimeString() }}
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
