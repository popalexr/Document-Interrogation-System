<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Check, History, Redo2, Undo2 } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-end gap-2 border-b border-border/80 bg-background px-3 py-3 sm:px-4 lg:px-6"
    >
        <Button variant="outline" class="gap-2">
            <Check class="size-4 text-primary" />
            Save
        </Button>
        <Button variant="outline" class="gap-2">
            <Undo2 class="size-4" />
            Undo
        </Button>
        <Button variant="outline" class="gap-2">
            <Redo2 class="size-4" />
            Redo
        </Button>
        <Button variant="outline" class="gap-2">
            <History class="size-4" />
            Chat History
        </Button>
    </div>
</template>
