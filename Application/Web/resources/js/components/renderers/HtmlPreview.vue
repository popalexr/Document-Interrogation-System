<script setup lang="ts">
import DOMPurify from 'dompurify';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ url: string }>();
const raw = ref('');

const safeHtml = computed(() =>
    DOMPurify.sanitize(raw.value, { USE_PROFILES: { html: true } }),
);

async function load() {
    const res = await fetch(props.url);
    raw.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
    <div class="h-full min-h-[40rem] overflow-hidden rounded-xl border">
        <iframe
            class="h-full w-full bg-white"
            :srcdoc="safeHtml"
            sandbox="allow-same-origin"
        />
    </div>
</template>
