<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import MarkdownIt from 'markdown-it';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ url: string }>();
const raw = ref('');

const md = new MarkdownIt({ html: false, linkify: true, breaks: true });
const html = computed(() => md.render(raw.value));

async function load() {
    const res = await fetch(props.url);
    raw.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
    <ScrollArea class="h-full min-h-[40rem] rounded-xl border bg-background">
        <div
            class="prose prose-sm max-w-none p-4 dark:prose-invert"
            v-html="html"
        />
    </ScrollArea>
</template>
