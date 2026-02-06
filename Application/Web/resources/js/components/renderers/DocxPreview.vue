<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import * as mammoth from "mammoth";
import { ScrollArea } from "@/components/ui/scroll-area";

const props = defineProps<{ url: string }>();
const html = ref("");
const warnings = ref<string[]>([]);

async function load() {
  warnings.value = [];
  const res = await fetch(props.url);
  const arrayBuffer = await res.arrayBuffer();

  const result = await mammoth.convertToHtml({ arrayBuffer });
  html.value = result.value;
  warnings.value = (result.messages || []).map((m: any) => m.message || String(m));
}

onMounted(load);
watch(() => props.url, load);
</script>

<template>
  <div class="space-y-3">
    <div
      v-if="warnings.length"
      class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200"
    >
      <p class="font-medium">Note</p>
      <ul class="mt-1 list-disc pl-4">
        <li v-for="(w, i) in warnings" :key="i">{{ w }}</li>
      </ul>
    </div>

    <ScrollArea class="h-[70vh] rounded-xl border bg-background">
      <div class="prose prose-sm max-w-none p-4 dark:prose-invert" v-html="html" />
    </ScrollArea>
  </div>
</template>
