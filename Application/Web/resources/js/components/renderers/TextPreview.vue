<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ url: string }>();
const content = ref('');

async function load() {
    const res = await fetch(props.url);
    content.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
    <ScrollArea class="h-full min-h-[40rem] rounded-xl border bg-muted/20">
        <pre class="p-4 text-sm leading-relaxed break-words whitespace-pre-wrap"
            >{{ content }}
    </pre
        >
    </ScrollArea>
</template>
