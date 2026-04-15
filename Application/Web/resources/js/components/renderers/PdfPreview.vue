<script setup lang="ts">
defineProps<{ url: string }>();
</script>

<template>
  <div class="overflow-hidden rounded-xl border bg-background">
    <embed
      :src="url"
      type="application/pdf"
      class="h-[70vh] w-full bg-background"
    />
  </div>
</template>
