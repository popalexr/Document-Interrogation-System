<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { ScrollArea } from "@/components/ui/scroll-area";

const props = defineProps<{ url: string }>();
const content = ref("");

async function load() {
  const res = await fetch(props.url);
  content.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
  <ScrollArea class="h-[70vh] rounded-xl border bg-muted/20">
    <pre class="p-4 text-sm leading-relaxed whitespace-pre-wrap break-words">
{{ content }}
    </pre>
  </ScrollArea>
</template>
