<script setup lang="ts">
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

import CollaboraPreview from '@/components/renderers/CollaboraPreview.vue';
import HtmlPreview from '@/components/renderers/HtmlPreview.vue';
import JsonPreview from '@/components/renderers/JsonPreview.vue';
import MarkdownPreview from '@/components/renderers/MarkdownPreview.vue';
import PdfPreview from '@/components/renderers/PdfPreview.vue';
import TextPreview from '@/components/renderers/TextPreview.vue';
import documents from '@/routes/documents';
import { Link } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import Button from './ui/button/Button.vue';
import DropdownMenu from './ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuContent from './ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from './ui/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuSeparator from './ui/dropdown-menu/DropdownMenuSeparator.vue';
import DropdownMenuTrigger from './ui/dropdown-menu/DropdownMenuTrigger.vue';

type PreviewKind =
    | 'pdf'
    | 'text'
    | 'markdown'
    | 'json'
    | 'html'
    | 'collabora'
    | 'unknown';

const props = defineProps<{
    fileUrl: string;
    fileName: string;
    fileId: string;
    mimeType?: string;
}>();

const ext = computed(() => {
    const parts = props.fileName.toLowerCase().split('.');
    return parts.length > 1 ? parts.pop()! : '';
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

    return 'unknown';
});

const label = computed(() => ext.value.toUpperCase() || 'FILE');

const collaboraUrl = computed(() => {
    if (!props.fileId || typeof window === 'undefined') {
        return '';
    }

    return new URL(
        `/collabora/preview?id=${encodeURIComponent(props.fileId)}`,
        window.location.origin,
    ).toString();
});

function extColor(ext: string): string {
    switch (ext) {
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
    <section
        class="flex min-h-[42rem] flex-col gap-4 md:h-[calc(100vh-6.5rem)]"
    >
        <header class="shrink-0 space-y-2 px-6 pt-6">
            <div class="flex items-start justify-between gap-3">
                <div class="w-full min-w-0">
                    <div class="leading-none font-semibold sm:text-lg">
                        <div class="flex justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Badge
                                    class="text-xs"
                                    :class="extColor(label)"
                                    >{{ label }}</Badge
                                >
                                {{ fileName }}
                            </div>
                            <div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8"
                                        >
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-40"
                                    >
                                        <DropdownMenuItem>
                                            <a
                                                class="block w-full cursor-pointer text-left"
                                                :href="
                                                    documents.downloadDocument.url(
                                                        {
                                                            query: {
                                                                id: fileId,
                                                            },
                                                        },
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Download
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem :as-child="true">
                                            <Link
                                                as="button"
                                                class="block w-full cursor-pointer text-left"
                                                :href="
                                                    documents.interrogate.url({
                                                        query: { id: fileId },
                                                    })
                                                "
                                                prefetch
                                            >
                                                Interrogate
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem>
                                            <Link
                                                as="button"
                                                class="block w-full cursor-pointer text-left"
                                                :href="
                                                    documents.edit.url({
                                                        query: { id: fileId },
                                                    })
                                                "
                                                prefetch
                                            >
                                                Edit
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Separator />
        </header>

        <div class="min-h-0 flex-1 px-6">
            <PdfPreview v-if="kind === 'pdf'" :url="fileUrl" />

            <TextPreview v-else-if="kind === 'text'" :url="fileUrl" />

            <MarkdownPreview v-else-if="kind === 'markdown'" :url="fileUrl" />

            <JsonPreview v-else-if="kind === 'json'" :url="fileUrl" />

            <HtmlPreview v-else-if="kind === 'html'" :url="fileUrl" />

            <CollaboraPreview
                v-else-if="kind === 'collabora'"
                :url="collaboraUrl"
            />

            <div
                v-else
                class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            >
                Tip de fișier neacceptat pentru preview.
            </div>
        </div>
    </section>
</template>
