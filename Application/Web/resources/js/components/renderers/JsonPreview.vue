<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ url: string }>();
const raw = ref('');

const pretty = computed(() => {
    try {
        return JSON.stringify(JSON.parse(raw.value), null, 2);
    } catch {
        return raw.value;
    }
});

async function load() {
    const res = await fetch(props.url);
    raw.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
    <ScrollArea class="h-full min-h-[40rem] rounded-xl border bg-muted/20">
        <pre class="overflow-auto p-4 text-sm whitespace-pre">{{ pretty }}</pre>
    </ScrollArea>
</template>
