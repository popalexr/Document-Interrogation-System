<script setup lang="ts">
import DeleteDocumentDialog from '@/components/documents/dashboard/DeleteDocumentDialog.vue';
import DocumentDetailsPanel from '@/components/documents/dashboard/DocumentDetailsPanel.vue';
import DocumentStatusBadge from '@/components/documents/dashboard/DocumentStatusBadge.vue';
import {
    canAskAi,
    documentIcon,
    extClasses,
    fileExt,
    formatDate,
    formatSize,
    statusDescription,
    statusKind,
} from '@/components/documents/dashboard/documentUtils';
import type { UploadItem } from '@/components/documents/dashboard/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { home as dashboard } from '@/routes/dashboard';
import documents from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock3,
    Download,
    Edit3,
    FileClock,
    FileText,
    MessageSquareText,
    MoreVertical,
    Play,
    RefreshCcw,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type RecentAction = 'uploaded' | 'updated' | 'edited' | 'interrogated';

type RecentDocument = UploadItem & {
    recent_action: RecentAction;
    recent_action_at: string | Date;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Recent files', href: '/recent-files' },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

const recentDocuments = computed<RecentDocument[]>(
    () => ((page.props as any).recentDocuments ?? []) as RecentDocument[],
);

const selectedDocumentId = ref<string | null>(null);
const deleteDialogOpen = ref(false);
const deletingUpload = ref<RecentDocument | null>(null);
const isDeleting = ref(false);

const selectedDocument = computed(() => {
    return (
        recentDocuments.value.find(
            (upload) => upload._id === selectedDocumentId.value,
        ) ?? null
    );
});

watch(
    recentDocuments,
    (nextDocuments) => {
        if (
            selectedDocumentId.value &&
            nextDocuments.some(
                (upload) => upload._id === selectedDocumentId.value,
            )
        ) {
            return;
        }

        selectedDocumentId.value = null;
    },
    { immediate: true },
);

function selectDocument(upload: RecentDocument) {
    selectedDocumentId.value = upload._id;
}

function viewDocument(upload: RecentDocument) {
    router.visit(documents.view.url({ query: { id: upload._id } }));
}

function closeDetails() {
    selectedDocumentId.value = null;
}

function openDeleteDialog(upload: RecentDocument) {
    deletingUpload.value = upload;
    deleteDialogOpen.value = true;
}

function handleDeleteDialogOpen(open: boolean) {
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
                handleDeleteDialogOpen(false);
            },
        },
    );
}

function actionLabel(action: RecentAction): string {
    switch (action) {
        case 'interrogated':
            return 'Interrogated';
        case 'edited':
            return 'Edited';
        case 'updated':
            return 'Updated';
        default:
            return 'Uploaded';
    }
}

function relativeTime(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value) : value;
    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const absSeconds = Math.abs(diffSeconds);

    const units: { unit: Intl.RelativeTimeFormatUnit; seconds: number }[] = [
        { unit: 'year', seconds: 31536000 },
        { unit: 'month', seconds: 2592000 },
        { unit: 'week', seconds: 604800 },
        { unit: 'day', seconds: 86400 },
        { unit: 'hour', seconds: 3600 },
        { unit: 'minute', seconds: 60 },
    ];

    if (absSeconds < 45) {
        return 'just now';
    }

    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });
    const match = units.find((item) => absSeconds >= item.seconds);

    if (!match) {
        return formatter.format(diffSeconds, 'second');
    }

    return formatter.format(Math.round(diffSeconds / match.seconds), match.unit);
}

function recentMessage(upload: RecentDocument): string {
    return `${actionLabel(upload.recent_action)} ${relativeTime(upload.recent_action_at)}`;
}
</script>

<template>
    <Head title="Recent files" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex h-full min-h-0 flex-col overflow-hidden bg-background px-6 py-6"
        >
            <div class="flex min-h-0 flex-1 gap-6">
                <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl font-semibold">
                                    Recent files
                                </h1>
                                <FileClock class="size-5 text-muted-foreground" />
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Owner:
                                <span class="text-foreground">{{
                                    user.name
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex max-h-[42rem] flex-1 flex-col overflow-hidden rounded-lg border bg-background shadow-xs"
                    >
                        <div
                            class="grid h-10 shrink-0 grid-cols-[minmax(18rem,1.6fr)_14rem_7rem_12rem_17rem] items-center border-b bg-muted/30 px-4 text-xs text-muted-foreground"
                        >
                            <div>Document</div>
                            <div>Recent activity</div>
                            <div>Type</div>
                            <div>Status</div>
                            <div class="text-center">Actions</div>
                        </div>

                        <div
                            v-if="!recentDocuments.length"
                            class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
                        >
                            No recent files found.
                        </div>

                        <div v-else class="min-h-0 flex-1 overflow-auto">
                            <div
                                v-for="upload in recentDocuments"
                                :key="upload._id"
                                class="grid min-h-16 cursor-pointer grid-cols-[minmax(18rem,1.6fr)_14rem_7rem_12rem_17rem] items-center border-b px-4 transition-colors hover:bg-muted/30"
                                :class="{
                                    'bg-blue-50/70 hover:bg-blue-50':
                                        selectedDocumentId === upload._id,
                                }"
                                @click="selectDocument(upload)"
                                @dblclick="viewDocument(upload)"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <DocumentIcon
                                        :icon="documentIcon(upload)"
                                        class="size-9 shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">
                                            {{ upload.original_name }}
                                        </p>
                                        <p
                                            class="mt-0.5 truncate text-xs text-muted-foreground"
                                        >
                                            {{ formatSize(upload.size) }}
                                            <span class="px-1">&middot;</span>
                                            Uploaded
                                            {{ formatDate(upload.created_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <Clock3
                                            class="size-4 shrink-0 text-blue-600"
                                        />
                                        <p class="truncate text-sm font-medium">
                                            {{ recentMessage(upload) }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-1 truncate text-xs text-muted-foreground"
                                    >
                                        {{ formatDate(upload.recent_action_at) }}
                                    </p>
                                </div>

                                <div>
                                    <Badge
                                        variant="outline"
                                        :class="extClasses(fileExt(upload))"
                                    >
                                        {{ fileExt(upload) || 'FILE' }}
                                    </Badge>
                                </div>

                                <div>
                                    <DocumentStatusBadge
                                        :upload="upload"
                                        show-spinner
                                    />
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
                                                documents.interrogate.url({
                                                    query: { id: upload._id },
                                                })
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
                                            statusKind(upload) === 'failed'
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
                                            statusKind(upload) === 'not_indexed'
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
                                                    query: { id: upload._id },
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
                                                <MoreVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-44"
                                        >
                                            <DropdownMenuItem :as-child="true">
                                                <Link
                                                    as="button"
                                                    class="block w-full cursor-pointer text-left"
                                                    :href="
                                                        documents.view.url({
                                                            query: {
                                                                id: upload._id,
                                                            },
                                                        })
                                                    "
                                                    prefetch
                                                >
                                                    <FileText
                                                        class="mr-2 size-4"
                                                    />
                                                    View
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem :as-child="true">
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
                                            <DropdownMenuItem :as-child="true">
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
                                            <DropdownMenuItem :as-child="true">
                                                <Link
                                                    as="button"
                                                    class="block w-full cursor-pointer text-left"
                                                    :href="
                                                        documents.edit.url({
                                                            query: {
                                                                id: upload._id,
                                                            },
                                                        })
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
                                                        openDeleteDialog(upload)
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
                        </div>
                    </div>
                </section>

                <DocumentDetailsPanel
                    v-if="selectedDocument"
                    :upload="selectedDocument"
                    :owner-name="user.name"
                    @close="closeDetails"
                    @delete="openDeleteDialog"
                />
            </div>

            <DeleteDocumentDialog
                :open="deleteDialogOpen"
                :upload="deletingUpload"
                :is-deleting="isDeleting"
                @update:open="handleDeleteDialogOpen"
                @confirm="confirmDelete"
            />
        </main>
    </AppLayout>
</template>
