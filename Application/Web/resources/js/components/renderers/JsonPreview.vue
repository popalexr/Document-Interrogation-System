<script setup lang="ts">
import { onMounted, ref, watch, computed } from "vue";
import { ScrollArea } from "@/components/ui/scroll-area";

const props = defineProps<{ url: string }>();
const raw = ref("");

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
  <ScrollArea class="h-[70vh] rounded-xl border bg-muted/20">
    <pre class="p-4 text-sm whitespace-pre overflow-auto">{{ pretty }}</pre>
  </ScrollArea>
</template>
