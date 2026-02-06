<script setup lang="ts">
import { onMounted, ref, watch, computed } from "vue";
import DOMPurify from "dompurify";

const props = defineProps<{ url: string }>();
const raw = ref("");

const safeHtml = computed(() =>
  DOMPurify.sanitize(raw.value, { USE_PROFILES: { html: true } })
);

async function load() {
  const res = await fetch(props.url);
  raw.value = await res.text();
}
onMounted(load);
watch(() => props.url, load);
</script>

<template>
  <div class="overflow-hidden rounded-xl border">
    <iframe
      class="h-[70vh] w-full bg-white"
      :srcdoc="safeHtml"
      sandbox="allow-same-origin"
    />
  </div>
</template>
