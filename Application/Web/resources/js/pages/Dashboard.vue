<script setup lang="ts">
import UploadButton from '@/components/UploadButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { home as dashboard } from '@/routes/dashboard';
import documents from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownUp,
    Check,
    ChevronDown,
    CircleAlert,
    CircleDashed,
    Download,
    Edit3,
    FileText,
    Info,
    MessageSquareText,
    MoreVertical,
    Play,
    RefreshCcw,
    Search,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

type UploadStatus = 'uploading' | 'uploaded' | 'failed' | 'quarantine' | string;

type UploadItem = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: UploadStatus;
    r2_key: string;
    created_at?: string | Date;
    updated_at?: string | Date;
};

type StatusFilter = 'all' | 'indexed' | 'processing' | 'failed' | 'not_indexed';
type SortKey = 'newest' | 'oldest' | 'name';

const uploads = computed<UploadItem[]>(
    () => ((page.props as any).uploads ?? []) as UploadItem[],
);

const selectedDocumentId = ref<string | null>(null);
const searchQuery = ref('');
const statusFilter = ref<StatusFilter>('all');
const typeFilter = ref('all');
const sortKey = ref<SortKey>('newest');
const deleteDialogOpen = ref(false);
const deletingUpload = ref<UploadItem | null>(null);
const isDeleting = ref(false);

const documentTypes = computed(() => {
    return Array.from(
        new Set(uploads.value.map((upload) => fileExt(upload))),
    ).filter(Boolean);
});

const filteredUploads = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...uploads.value]
        .filter((upload) => {
            const matchesSearch =
                !query || upload.original_name.toLowerCase().includes(query);
            const matchesStatus =
                statusFilter.value === 'all' ||
                statusKind(upload) === statusFilter.value;
            const matchesType =
                typeFilter.value === 'all' ||
                fileExt(upload) === typeFilter.value;

            return matchesSearch && matchesStatus && matchesType;
        })
        .sort((a, b) => {
            if (sortKey.value === 'name') {
                return a.original_name.localeCompare(b.original_name);
            }

            const aTime = new Date(a.created_at ?? 0).getTime();
            const bTime = new Date(b.created_at ?? 0).getTime();

            return sortKey.value === 'oldest' ? aTime - bTime : bTime - aTime;
        });
});

const selectedDocument = computed(() => {
    return (
        uploads.value.find(
            (upload) => upload._id === selectedDocumentId.value,
        ) ?? null
    );
});

watch(
    uploads,
    (nextUploads) => {
        if (
            selectedDocumentId.value &&
            nextUploads.some(
                (upload) => upload._id === selectedDocumentId.value,
            )
        ) {
            return;
        }

        selectedDocumentId.value = null;
    },
    { immediate: true },
);

function formatSize(bytes: number | undefined): string {
    if (!bytes && bytes !== 0) return '';

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatDate(value: string | Date | undefined): string {
    if (!value) return '';

    const date = typeof value === 'string' ? new Date(value) : value;

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function fileExt(upload: UploadItem | null | undefined): string {
    const name = upload?.original_name;
    if (!name) return '';

    const index = name.lastIndexOf('.');
    if (index === -1) return '';

    return name.slice(index + 1).toUpperCase();
}

function statusKind(upload: UploadItem): StatusFilter {
    if (upload.status === 'uploaded') return 'indexed';
    if (upload.status === 'uploading') return 'processing';
    if (['failed', 'error'].includes(upload.status)) return 'failed';

    return 'not_indexed';
}

function canAskAi(upload: UploadItem): boolean {
    return statusKind(upload) === 'indexed';
}

function statusLabel(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'Indexed';
        case 'processing':
            return 'Processing';
        case 'failed':
            return 'Failed';
        default:
            return 'Not indexed';
    }
}

function statusDescription(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'Searchable';
        case 'processing':
            return 'Indexing';
        case 'failed':
            return 'Extraction error';
        default:
            return 'Queued';
    }
}

function statusClasses(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'border-green-200 bg-green-50 text-green-700';
        case 'processing':
            return 'border-orange-200 bg-orange-50 text-orange-700';
        case 'failed':
            return 'border-red-200 bg-red-50 text-red-700';
        default:
            return 'border-slate-200 bg-slate-100 text-slate-700';
    }
}

function statusIcon(upload: UploadItem) {
    switch (statusKind(upload)) {
        case 'indexed':
            return Check;
        case 'processing':
            return CircleDashed;
        case 'failed':
            return CircleAlert;
        default:
            return CircleDashed;
    }
}

function extClasses(extension: string): string {
    switch (extension) {
        case 'PDF':
            return 'border-red-200 bg-red-50 text-red-600';
        case 'DOC':
        case 'DOCX':
            return 'border-blue-200 bg-blue-50 text-blue-600';
        case 'XLS':
        case 'XLSX':
            return 'border-green-200 bg-green-50 text-green-600';
        case 'PPT':
        case 'PPTX':
            return 'border-orange-200 bg-orange-50 text-orange-600';
        default:
            return 'border-slate-200 bg-white text-slate-700';
    }
}

function documentIcon(upload: UploadItem): string {
    switch (fileExt(upload)) {
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
}

function selectDocument(upload: UploadItem) {
    selectedDocumentId.value = upload._id;
}

function viewDocument(upload: UploadItem) {
    router.visit(documents.view.url({ query: { id: upload._id } }));
}

function closeDetails() {
    selectedDocumentId.value = null;
}

function openDeleteDialog(upload: UploadItem) {
    deletingUpload.value = upload;
    deleteDialogOpen.value = true;
}

function handleDialogOpen(open: boolean) {
    deleteDialogOpen.value = open;

    if (!open) {
        deletingUpload.value = null;
    }
}

function confirmDelete() {
    if (!deletingUpload.value) return;

    isDeleting.value = true;

    router.post(
        documents.delete.url({ query: { id: deletingUpload.value._id } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isDeleting.value = false;
            },
            onSuccess: () => {
                handleDialogOpen(false);
            },
        },
    );
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex h-[calc(100dvh-3rem)] min-h-0 flex-col overflow-hidden bg-background px-6 py-6"
        >
            <div class="flex min-h-0 flex-1 gap-6">
                <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight"
                                >
                                    My Documents
                                </h1>
                                <TooltipProvider :delay-duration="150">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                                                aria-label="Dashboard information"
                                            >
                                                <Info class="size-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent class="max-w-xs">
                                            View, search, filter, and manage
                                            your uploaded documents. Select a
                                            row to inspect indexing status,
                                            metadata, and available actions.
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Owner:
                                <span class="text-foreground">{{
                                    user.name
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mb-6 flex flex-wrap items-center gap-3">
                        <label
                            class="flex h-10 min-w-[18rem] items-center gap-2 rounded-md border bg-background px-3 text-sm shadow-xs"
                        >
                            <Search class="size-4 text-muted-foreground" />
                            <input
                                v-model="searchQuery"
                                type="search"
                                class="min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                                placeholder="Search documents..."
                            />
                        </label>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <SlidersHorizontal class="size-4" />
                                    Status:
                                    <span class="font-medium capitalize">
                                        {{ statusFilter.replace('_', ' ') }}
                                    </span>
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-44">
                                <DropdownMenuItem @click="statusFilter = 'all'">
                                    All
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="statusFilter = 'indexed'"
                                >
                                    Indexed
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="statusFilter = 'processing'"
                                >
                                    Processing
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="statusFilter = 'failed'"
                                >
                                    Failed
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="statusFilter = 'not_indexed'"
                                >
                                    Not indexed
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <FileText class="size-4" />
                                    Type:
                                    <span class="font-medium">
                                        {{
                                            typeFilter === 'all'
                                                ? 'All'
                                                : typeFilter
                                        }}
                                    </span>
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-36">
                                <DropdownMenuItem @click="typeFilter = 'all'">
                                    All
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="type in documentTypes"
                                    :key="type"
                                    @click="typeFilter = type"
                                >
                                    {{ type }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <ArrowDownUp class="size-4" />
                                    Sort:
                                    <span class="font-medium">
                                        {{
                                            sortKey === 'newest'
                                                ? 'Newest first'
                                                : sortKey === 'oldest'
                                                  ? 'Oldest first'
                                                  : 'Name'
                                        }}
                                    </span>
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-44">
                                <DropdownMenuItem @click="sortKey = 'newest'">
                                    Newest first
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortKey = 'oldest'">
                                    Oldest first
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortKey = 'name'">
                                    Name
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div class="ml-auto">
                            <UploadButton
                                label="Upload Documents"
                                variant="default"
                                trigger-class="gap-2"
                            />
                        </div>
                    </div>

                    <div
                        class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border bg-background shadow-xs"
                    >
                        <div
                            class="grid h-10 shrink-0 grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_17rem] items-center border-b bg-muted/30 px-4 text-xs text-muted-foreground"
                        >
                            <div>
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-input"
                                    aria-label="Select all documents"
                                />
                            </div>
                            <div>Document</div>
                            <div>Type</div>
                            <div>Status</div>
                            <div class="text-center">Actions</div>
                        </div>

                        <div
                            v-if="!filteredUploads.length"
                            class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
                        >
                            No documents found.
                        </div>

                        <div v-else class="min-h-0 flex-1 overflow-auto">
                            <ContextMenu
                                v-for="upload in filteredUploads"
                                :key="upload._id"
                            >
                                <ContextMenuTrigger as-child>
                                    <div
                                        class="grid min-h-16 cursor-pointer grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_17rem] items-center border-b px-4 transition-colors hover:bg-muted/30"
                                        :class="{
                                            'bg-blue-50/70 hover:bg-blue-50':
                                                selectedDocumentId ===
                                                upload._id,
                                        }"
                                        @click="selectDocument(upload)"
                                        @dblclick="viewDocument(upload)"
                                    >
                                        <div>
                                            <input
                                                type="checkbox"
                                                class="size-4 rounded border-input accent-blue-600"
                                                :checked="
                                                    selectedDocumentId ===
                                                    upload._id
                                                "
                                                aria-label="Select document"
                                                @click.stop="
                                                    selectDocument(upload)
                                                "
                                            />
                                        </div>

                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <DocumentIcon
                                                :icon="documentIcon(upload)"
                                                class="size-9 shrink-0"
                                            />
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-medium"
                                                >
                                                    {{ upload.original_name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        formatSize(upload.size)
                                                    }}
                                                    <span class="px-1">·</span>
                                                    Uploaded
                                                    {{
                                                        formatDate(
                                                            upload.created_at,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <Badge
                                                variant="outline"
                                                :class="
                                                    extClasses(fileExt(upload))
                                                "
                                            >
                                                {{ fileExt(upload) || 'FILE' }}
                                            </Badge>
                                        </div>

                                        <div>
                                            <div
                                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                                :class="statusClasses(upload)"
                                            >
                                                <Spinner
                                                    v-if="
                                                        statusKind(upload) ===
                                                        'processing'
                                                    "
                                                    size="sm"
                                                />
                                                <component
                                                    :is="statusIcon(upload)"
                                                    v-else
                                                    class="size-3.5"
                                                />
                                                {{ statusLabel(upload) }}
                                            </div>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ statusDescription(upload) }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex items-center justify-end gap-2"
                                            @click.stop
                                        >
                                            <Button
                                                v-if="canAskAi(upload)"
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        documents.interrogate.url(
                                                            {
                                                                query: {
                                                                    id: upload._id,
                                                                },
                                                            },
                                                        )
                                                    "
                                                    prefetch
                                                >
                                                    <Sparkles
                                                        class="size-4 text-blue-600"
                                                    />
                                                    Ask AI
                                                </Link>
                                            </Button>
                                            <Button
                                                v-else-if="
                                                    statusKind(upload) ===
                                                    'failed'
                                                "
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                            >
                                                <RefreshCcw class="size-4" />
                                                Retry
                                            </Button>
                                            <Button
                                                v-else-if="
                                                    statusKind(upload) ===
                                                    'not_indexed'
                                                "
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                            >
                                                <Play class="size-4" />
                                                Index
                                            </Button>
                                            <Button
                                                v-else
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                                disabled
                                            >
                                                <Sparkles class="size-4" />
                                                Ask AI
                                            </Button>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        documents.view.url({
                                                            query: {
                                                                id: upload._id,
                                                            },
                                                        })
                                                    "
                                                    prefetch
                                                >
                                                    <FileText class="size-4" />
                                                    View
                                                </Link>
                                            </Button>

                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8"
                                                    >
                                                        <MoreVertical
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                    class="w-44"
                                                >
                                                    <DropdownMenuItem
                                                        :as-child="true"
                                                    >
                                                        <Link
                                                            as="button"
                                                            class="block w-full cursor-pointer text-left"
                                                            :href="
                                                                documents.view.url(
                                                                    {
                                                                        query: {
                                                                            id: upload._id,
                                                                        },
                                                                    },
                                                                )
                                                            "
                                                            prefetch
                                                        >
                                                            <FileText
                                                                class="mr-2 size-4"
                                                            />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        :as-child="true"
                                                    >
                                                        <a
                                                            class="block w-full cursor-pointer text-left"
                                                            :href="
                                                                documents.downloadDocument.url(
                                                                    {
                                                                        query: {
                                                                            id: upload._id,
                                                                        },
                                                                    },
                                                                )
                                                            "
                                                            target="_blank"
                                                            rel="noopener"
                                                        >
                                                            <Download
                                                                class="mr-2 size-4"
                                                            />
                                                            Download
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        :as-child="true"
                                                    >
                                                        <Link
                                                            as="button"
                                                            class="block w-full cursor-pointer text-left"
                                                            :href="
                                                                documents.interrogate.url(
                                                                    {
                                                                        query: {
                                                                            id: upload._id,
                                                                        },
                                                                    },
                                                                )
                                                            "
                                                            prefetch
                                                        >
                                                            <MessageSquareText
                                                                class="mr-2 size-4"
                                                            />
                                                            Interrogate
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        :as-child="true"
                                                    >
                                                        <Link
                                                            as="button"
                                                            class="block w-full cursor-pointer text-left"
                                                            :href="
                                                                documents.edit.url(
                                                                    {
                                                                        query: {
                                                                            id: upload._id,
                                                                        },
                                                                    },
                                                                )
                                                            "
                                                            prefetch
                                                        >
                                                            <Edit3
                                                                class="mr-2 size-4"
                                                            />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        class="text-destructive"
                                                        :as-child="true"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center"
                                                            @click="
                                                                openDeleteDialog(
                                                                    upload,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="mr-2 size-4"
                                                            />
                                                            Delete
                                                        </button>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                </ContextMenuTrigger>

                                <ContextMenuContent class="w-44">
                                    <ContextMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.view.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <FileText class="mr-2 size-4" />
                                            View
                                        </Link>
                                    </ContextMenuItem>
                                    <ContextMenuItem :as-child="true">
                                        <a
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.downloadDocument.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <Download class="mr-2 size-4" />
                                            Download
                                        </a>
                                    </ContextMenuItem>
                                    <ContextMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.interrogate.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <MessageSquareText
                                                class="mr-2 size-4"
                                            />
                                            Interrogate
                                        </Link>
                                    </ContextMenuItem>
                                    <ContextMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.edit.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <Edit3 class="mr-2 size-4" />
                                            Edit
                                        </Link>
                                    </ContextMenuItem>
                                    <ContextMenuSeparator />
                                    <ContextMenuItem
                                        class="text-destructive"
                                        :as-child="true"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center"
                                            @click="openDeleteDialog(upload)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </button>
                                    </ContextMenuItem>
                                </ContextMenuContent>
                            </ContextMenu>
                        </div>
                    </div>
                </section>

                <aside
                    v-if="selectedDocument"
                    class="flex w-[26rem] shrink-0 flex-col rounded-lg border bg-background shadow-xs"
                >
                    <div class="flex items-center justify-between p-5">
                        <h2 class="font-semibold">Document details</h2>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            @click="closeDetails"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="space-y-5 px-5 pb-6">
                        <div class="flex items-center gap-3">
                            <DocumentIcon
                                :icon="documentIcon(selectedDocument)"
                                class="size-12 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ selectedDocument.original_name }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatSize(selectedDocument.size) }}
                                    <span class="px-1">·</span>
                                    {{ fileExt(selectedDocument) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="statusClasses(selectedDocument)"
                            >
                                <component
                                    :is="statusIcon(selectedDocument)"
                                    class="size-3.5"
                                />
                                {{ statusLabel(selectedDocument) }}
                            </div>
                            <span class="text-sm text-muted-foreground">
                                {{ statusDescription(selectedDocument) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-if="canAskAi(selectedDocument)"
                                class="gap-2"
                                as-child
                            >
                                <Link
                                    :href="
                                        documents.interrogate.url({
                                            query: { id: selectedDocument._id },
                                        })
                                    "
                                    prefetch
                                >
                                    <Sparkles class="size-4" />
                                    Ask AI
                                </Link>
                            </Button>
                            <Button
                                v-else
                                class="gap-2"
                                disabled
                            >
                                <Sparkles class="size-4" />
                                Ask AI
                            </Button>

                            <Button
                                v-if="canAskAi(selectedDocument)"
                                class="gap-2"
                                as-child
                            >
                                <Link
                                    :href="
                                        documents.edit.url({
                                            query: { id: selectedDocument._id },
                                        })
                                    "
                                    prefetch
                                >
                                    <FileText class="size-4" />
                                    Edit using AI
                                </Link>
                            </Button>
                            <Button
                                v-else
                                class="gap-2"
                                disabled
                            >
                                <FileText class="size-4" />
                                Edit using AI
                            </Button>
                        </div>

                        <dl class="space-y-4 text-sm">
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">
                                    Upload date
                                </dt>
                                <dd class="text-right">
                                    {{
                                        formatDate(selectedDocument.created_at)
                                    }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">MIME type</dt>
                                <dd class="text-right break-words">
                                    {{ selectedDocument.mime_type }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">Size</dt>
                                <dd class="text-right">
                                    {{ formatSize(selectedDocument.size) }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">Owner</dt>
                                <dd class="text-right">{{ user.name }}</dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">
                                    Last modify
                                </dt>
                                <dd class="text-right">
                                    {{
                                        formatDate(
                                            selectedDocument.updated_at ??
                                                selectedDocument.created_at,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">
                                    Searchable
                                </dt>
                                <dd
                                    class="flex items-center justify-end gap-1 text-green-600"
                                >
                                    <Check class="size-4" />
                                    {{
                                        canAskAi(selectedDocument)
                                            ? 'Yes'
                                            : 'No'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-auto border-t p-5">
                        <h3 class="mb-3 text-sm font-semibold">Actions</h3>
                        <Button
                            variant="outline"
                            class="w-full justify-start gap-2"
                            as-child
                        >
                            <a
                                :href="
                                    documents.downloadDocument.url({
                                        query: { id: selectedDocument._id },
                                    })
                                "
                                target="_blank"
                                rel="noopener"
                            >
                                <Download class="size-4" />
                                Download original file
                            </a>
                        </Button>
                    </div>

                    <div class="border-t p-5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 text-sm font-medium text-red-600"
                            @click="openDeleteDialog(selectedDocument)"
                        >
                            <Trash2 class="size-4" />
                            Delete document
                        </button>
                    </div>
                </aside>
            </div>

            <Dialog :open="deleteDialogOpen" @update:open="handleDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Delete document</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            <span class="font-medium text-foreground">
                                {{ deletingUpload?.original_name }}
                            </span>
                            ? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="handleDialogOpen(false)"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="!deletingUpload || isDeleting"
                            @click="confirmDelete"
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AppLayout>
</template>
