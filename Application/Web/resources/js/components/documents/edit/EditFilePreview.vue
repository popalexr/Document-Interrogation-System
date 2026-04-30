<script setup lang="ts">
import api from '@/routes/api';
import { computed } from 'vue';

import CollaboraPreview from '@/components/renderers/CollaboraPreview.vue';
import HtmlPreview from '@/components/renderers/HtmlPreview.vue';
import JsonPreview from '@/components/renderers/JsonPreview.vue';
import MarkdownPreview from '@/components/renderers/MarkdownPreview.vue';
import PdfPreview from '@/components/renderers/PdfPreview.vue';
import TextPreview from '@/components/renderers/TextPreview.vue';

type PreviewKind =
    | 'pdf'
    | 'text'
    | 'markdown'
    | 'json'
    | 'html'
    | 'collabora'
    | 'unknown';

const props = defineProps<{
    fileId: string;
    fileName: string;
    mimeType?: string;
    isEditedFile?: boolean;
}>();

const fileUrl = computed(() => {
    if (!props.fileId || typeof window === 'undefined') {
        return '';
    }

    return new URL(
        (props.isEditedFile ? api.viewEditedFile : api.viewFile).url({
            query: { id: props.fileId },
        }),
        window.location.origin,
    ).toString();
});

const collaboraUrl = computed(() => {
    if (!props.fileId || typeof window === 'undefined') {
        return '';
    }

    const params = new URLSearchParams({
        id: props.fileId,
        source: props.isEditedFile ? 'edit' : 'upload',
    });

    return new URL(
        `/collabora/preview?${params.toString()}`,
        window.location.origin,
    ).toString();
});

const ext = computed(() => {
    const parts = props.fileName.toLowerCase().split('.');
    return parts.length > 1 ? (parts.pop() ?? '') : '';
});

const kind = computed<PreviewKind>(() => {
    const e = ext.value;

    if (e === 'pdf') return 'pdf';
    if (e === 'txt' || e === 'tex') return 'text';
    if (e === 'md') return 'markdown';
    if (e === 'json') return 'json';
    if (e === 'html' || e === 'htm') return 'html';
    if (
        [
            'doc',
            'docx',
            'odt',
            'rtf',
            'ppt',
            'pptx',
            'odp',
            'xls',
            'xlsx',
            'ods',
            'csv',
        ].includes(e)
    )
        return 'collabora';

    if (props.mimeType?.includes('text')) return 'text';
    if (props.mimeType === 'application/json') return 'json';

    return 'unknown';
});
</script>

<template>
    <article
        class="edit-file-preview mx-auto flex h-full min-h-0 max-w-5xl flex-col"
    >
        <div
            v-if="!fileUrl"
            class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
        >
            Nu s-a putut genera URL-ul pentru preview.
        </div>

        <PdfPreview v-else-if="kind === 'pdf'" class="h-full" :url="fileUrl" />

        <TextPreview
            v-else-if="kind === 'text'"
            class="h-full"
            :url="fileUrl"
        />

        <MarkdownPreview
            v-else-if="kind === 'markdown'"
            class="h-full"
            :url="fileUrl"
        />

        <JsonPreview
            v-else-if="kind === 'json'"
            class="h-full"
            :url="fileUrl"
        />

        <HtmlPreview
            v-else-if="kind === 'html'"
            class="h-full"
            :url="fileUrl"
        />

        <CollaboraPreview
            v-else-if="kind === 'collabora'"
            class="h-full"
            :url="collaboraUrl"
        />

        <div
            v-else
            class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
        >
            Tip de fisier neacceptat pentru preview.
        </div>
    </article>
</template>

<style scoped>
.edit-file-preview :deep(.h-\[70vh\]) {
    height: 100% !important;
}
</style>
