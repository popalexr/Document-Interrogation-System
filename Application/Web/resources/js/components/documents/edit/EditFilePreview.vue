<script setup lang="ts">
import api from '@/routes/api';
import { computed } from 'vue';

import DocPreview from '@/components/renderers/DocPreview.vue';
import DocxPreview from '@/components/renderers/DocxPreview.vue';
import HtmlPreview from '@/components/renderers/HtmlPreview.vue';
import JsonPreview from '@/components/renderers/JsonPreview.vue';
import MarkdownPreview from '@/components/renderers/MarkdownPreview.vue';
import PdfPreview from '@/components/renderers/PdfPreview.vue';
import PptxPreview from '@/components/renderers/PptxPreview.vue';
import TextPreview from '@/components/renderers/TextPreview.vue';

type PreviewKind =
    | 'pdf'
    | 'text'
    | 'markdown'
    | 'json'
    | 'html'
    | 'docx'
    | 'doc'
    | 'pptx'
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
    if (e === 'docx') return 'docx';
    if (e === 'doc') return 'doc';
    if (e === 'pptx') return 'pptx';

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

        <DocxPreview
            v-else-if="kind === 'docx'"
            class="h-full"
            :url="fileUrl"
        />

        <DocPreview
            v-else-if="kind === 'doc'"
            class="h-full"
            :doc-url="fileUrl"
        />

        <PptxPreview
            v-else-if="kind === 'pptx'"
            class="h-full"
            :pptx-url="fileUrl"
            :file-name="fileName"
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
