<script setup lang="ts">
import { MessageSquareText } from 'lucide-vue-next';
</script>

<template>
    <div class="flex h-full items-center justify-center">
        <div
            class="w-full max-w-4xl rounded-lg border border-dashed bg-muted/20 p-10 text-center"
        >
            <MessageSquareText
                class="mx-auto mb-4 size-10 text-muted-foreground"
            />
            <h1 class="text-2xl font-semibold">Interrogate documents</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Select one or more documents, then ask a question across the
                chosen context.
            </p>
        </div>
    </div>
</template>
