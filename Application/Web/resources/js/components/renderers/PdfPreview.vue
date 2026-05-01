<script setup lang="ts">
defineProps<{ url: string }>();
</script>

<template>
    <div
        class="h-full min-h-[40rem] overflow-hidden rounded-xl border bg-background"
    >
        <embed
            :src="url"
            type="application/pdf"
            class="h-full w-full bg-background"
        />
    </div>
</template>
