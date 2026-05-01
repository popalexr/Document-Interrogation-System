<script setup lang="ts">
import EditFilePreview from '@/components/documents/edit/EditFilePreview.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import documents from '@/routes/documents';
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    fileId: string;
    fileName: string;
    mimeType?: string;
    isEditedFile?: boolean;
}>();

const ext = computed(() => {
    const parts = props.fileName.toLowerCase().split('.');
    return parts.length > 1 ? (parts.pop() ?? '') : '';
});

const label = computed(() => ext.value.toUpperCase() || 'FILE');

const downloadUrl = computed(() =>
    documents.downloadDocument.url({
        query: {
            id: props.fileId,
            source: props.isEditedFile ? 'edit' : 'upload',
        },
    }),
);

function extColor(extension: string): string {
    switch (extension) {
        case 'PDF':
            return 'bg-red-500 text-white';
        case 'DOC':
        case 'DOCX':
            return 'bg-blue-600 text-white';
        case 'XLS':
        case 'XLSX':
            return 'bg-green-600 text-white';
        case 'PPT':
        case 'PPTX':
            return 'bg-amber-500 text-white';
        case 'TXT':
            return 'bg-muted text-foreground';
        default:
            return 'bg-muted text-foreground';
    }
}
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col bg-background">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 px-3 py-3 sm:px-4 lg:px-6"
        >
            <div class="truncatefont-medium text-foreground">
                <div class="flex items-center gap-2 text-sm">
                    <Badge class="text-xs" :class="extColor(label)">{{
                        label
                    }}</Badge>
                    <span class="truncate font-medium text-foreground">
                        {{ fileName }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    as="a"
                    variant="outline"
                    class="gap-2"
                    :href="downloadUrl"
                >
                    <Download class="size-4" />
                    Download
                </Button>
            </div>
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto bg-muted/20 px-3 py-4 sm:px-5 lg:px-8"
        >
            <EditFilePreview
                :file-id="fileId"
                :file-name="fileName"
                :mime-type="mimeType"
                :is-edited-file="isEditedFile"
            />
        </div>
    </section>
</template>
