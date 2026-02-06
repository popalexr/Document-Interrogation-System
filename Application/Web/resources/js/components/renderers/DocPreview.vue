<script setup lang="ts">
import { computed } from "vue";
import { Card, CardContent } from "@/components/ui/card";

const props = defineProps<{
  docUrl: string;
  fileName?: string;
}>();

// Microsoft viewer endpoint
const embedUrl = computed(() => {
  const src = encodeURIComponent(props.docUrl);
  return `https://view.officeapps.live.com/op/embed.aspx?src=${src}`;
});
</script>

<template>
  <Card class="border-muted/60">
    <CardContent class="pt-0">
      <div class="overflow-hidden rounded-xl border bg-background">
        <iframe
          :src="embedUrl"
          class="h-[70vh] w-full"
          frameborder="0"
          allowfullscreen
        />
      </div>
    </CardContent>
  </Card>
</template>
