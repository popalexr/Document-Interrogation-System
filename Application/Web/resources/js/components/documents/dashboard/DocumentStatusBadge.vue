<script setup lang="ts">
import Spinner from '@/components/ui/spinner/Spinner.vue';
import {
    statusClasses,
    statusIcon,
    statusKind,
    statusLabel,
} from './documentUtils';
import type { UploadItem } from './types';

defineProps<{
    upload: UploadItem;
    showSpinner?: boolean;
}>();
</script>

<template>
    <div
        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
        :class="statusClasses(upload)"
    >
        <Spinner
            v-if="showSpinner && statusKind(upload) === 'processing'"
            size="sm"
        />
        <component :is="statusIcon(upload)" v-else class="size-3.5" />
        {{ statusLabel(upload) }}
    </div>
</template>
