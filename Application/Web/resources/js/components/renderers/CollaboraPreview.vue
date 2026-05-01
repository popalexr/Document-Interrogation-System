<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineProps<{
    url: string;
}>();

const iframe = ref<HTMLIFrameElement | null>(null);
const collaboraOrigin = ref<string | null>(null);
const timers: number[] = [];

const hideUiMessages = [
    { MessageId: 'Hide_Menubar' },
    { MessageId: 'Hide_StatusBar' },
    { MessageId: 'Hide_Ruler' },
    { MessageId: 'Collapse_Notebookbar' },
] as const;

function postMessage(message: (typeof hideUiMessages)[number]) {
    const target = iframe.value?.contentWindow;

    if (!target) {
        return;
    }

    target.postMessage(
        {
            ...message,
            SendTime: Date.now(),
        },
        collaboraOrigin.value ?? '*',
    );
}

function hideCollaboraChrome() {
    hideUiMessages.forEach(postMessage);
}

function scheduleChromeHide() {
    timers.splice(0).forEach(window.clearTimeout);

    [250, 750, 1500, 3000].forEach((delay) => {
        timers.push(window.setTimeout(hideCollaboraChrome, delay));
    });
}

function handleMessage(event: MessageEvent) {
    if (event.source !== iframe.value?.contentWindow) {
        return;
    }

    collaboraOrigin.value = event.origin;

    const messageId = event.data?.MessageId;
    const status = event.data?.Values?.Status;

    if (
        messageId === 'App_LoadingStatus' ||
        status === 'Initialized' ||
        status === 'Document_Loaded'
    ) {
        hideCollaboraChrome();
    }
}

onMounted(() => {
    window.addEventListener('message', handleMessage);
});

onBeforeUnmount(() => {
    window.removeEventListener('message', handleMessage);
    timers.splice(0).forEach(window.clearTimeout);
});

watch(() => iframe.value?.src, scheduleChromeHide);
</script>

<template>
    <div
        class="h-full min-h-[40rem] overflow-hidden rounded-xl border bg-background"
    >
        <iframe
            ref="iframe"
            :src="url"
            class="h-full w-full"
            frameborder="0"
            allow="fullscreen"
            title="Document preview"
            @load="scheduleChromeHide"
        />
    </div>
</template>
