<script setup lang="ts">
import EditFilePreview from '@/components/documents/edit/EditFilePreview.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import documents from '@/routes/documents';
import { Icon as DocumentIcon } from '@iconify/vue';
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

const documentIcon = computed(() => {
    switch (label.value) {
        case 'DOC':
        case 'DOCX':
            return 'vscode-icons:file-type-word';
        case 'PDF':
            return 'vscode-icons:file-type-pdf2';
        case 'PPT':
        case 'PPTX':
            return 'vscode-icons:file-type-powerpoint';
        case 'XLS':
        case 'XLSX':
        case 'CSV':
            return 'vscode-icons:file-type-excel';
        case 'MD':
        case 'TXT':
        case 'TEX':
            return 'vscode-icons:file-type-text';
        case 'JSON':
            return 'vscode-icons:file-type-json';
        default:
            return 'vscode-icons:default-file';
    }
});

const downloadUrl = computed(() =>
    documents.downloadDocument.url({
        query: {
            id: props.fileId,
            source: props.isEditedFile ? 'edit' : 'upload',
        },
    }),
);
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col bg-background">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 px-3 py-3 sm:px-4 lg:px-6"
        >
            <div class="truncatefont-medium text-foreground">
                <div class="flex items-center gap-2 text-sm">
                    <TooltipProvider :delay-duration="150">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <DocumentIcon
                                    :icon="documentIcon"
                                    class="h-6 w-6 shrink-0"
                                    :aria-label="label"
                                />
                            </TooltipTrigger>
                            <TooltipContent>
                                {{ label }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
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
