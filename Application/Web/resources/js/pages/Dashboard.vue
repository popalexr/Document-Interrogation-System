<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { home as dashboard } from '@/routes/dashboard';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/components/Icon.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { computed, ref } from 'vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { MoreHorizontal, TrashIcon } from 'lucide-vue-next';
import documents from '@/routes/documents';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

type UploadItem = {
  _id: string;
  original_name: string;
  mime_type: string;
  size: number;
  status: 'quarantine' | string;
  r2_key: string;
  created_at?: string | Date;
}

const uploads = computed<UploadItem[]>(() => ((page.props as any).uploads ?? []) as UploadItem[])
const deleteDialogOpen = ref(false)
const deletingUpload = ref<UploadItem | null>(null)
const isDeleting = ref(false)

function formatSize(bytes: number | undefined): string {
  if (!bytes && bytes !== 0) return ''
  const units = ['B','KB','MB','GB']
  let b = bytes
  let i = 0
  while (b >= 1024 && i < units.length - 1) { b /= 1024; i++ }
  return `${b.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

function formatDate(d: string | Date | undefined): string {
  if (!d) return ''
  const date = typeof d === 'string' ? new Date(d) : d
  return date.toLocaleString()
}

function fileExt(name: string | undefined): string {
  if (!name) return ''
  const idx = name.lastIndexOf('.')
  if (idx === -1) return ''
  return name.slice(idx + 1).toUpperCase()
}

function extColor(ext: string): string {
  switch (ext) {
    case 'PDF':
      return 'bg-red-500 text-white'
    case 'DOC':
    case 'DOCX':
      return 'bg-blue-600 text-white'
    case 'XLS':
    case 'XLSX':
      return 'bg-green-600 text-white'
    case 'PPT':
    case 'PPTX':
      return 'bg-amber-500 text-white'
    case 'TXT':
      return 'bg-muted text-foreground'
    default:
      return 'bg-muted text-foreground'
  }
}

function openDeleteDialog(upload: UploadItem) {
  deletingUpload.value = upload
  deleteDialogOpen.value = true
}

function handleDialogOpen(open: boolean) {
  deleteDialogOpen.value = open

  if (!open) {
    deletingUpload.value = null
  }
}

function confirmDelete() {
  if (!deletingUpload.value) return

  isDeleting.value = true

  router.post(
    documents.delete.url({ query: { id: deletingUpload.value._id } }),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        isDeleting.value = false
      },
      onSuccess: () => {
        handleDialogOpen(false)
      },
    },
  )
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <Card>
                <CardHeader class="pb-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle class="text-xl">My uploads</CardTitle>
                            <p class="text-muted-foreground mt-1 text-xs">Owner: {{ user.name }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-4">
                    <div v-if="!uploads.length" class="text-sm text-muted-foreground">No uploads yet.</div>
                    <ul v-else class="divide-y rounded-md border">
                        <li v-for="u in uploads" :key="u._id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1 flex items-start gap-3">
                                <div class="relative flex size-10 items-center justify-center rounded-md border">
                                    <Icon name="file" class="h-6 w-6" />
                                    <span
                                        v-if="fileExt(u.original_name)"
                                        class="absolute -left-1 -top-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="extColor(fileExt(u.original_name))"
                                    >
                                        {{ fileExt(u.original_name) }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ u.original_name }}</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ formatSize(u.size) }} • {{ u.mime_type }} • {{ formatDate(u.created_at as any) }}</p>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <div v-if="u.status === 'uploading'">
                                    <TooltipProvider :delay-duration="0">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <div>
                                                    <Badge variant="secondary">
                                                        <Spinner size="sm" />
                                                    </Badge>
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>Uploading</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <div v-else>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40">
                                            <DropdownMenuItem :as-child="true">
                                                <Link
                                                    as="button"
                                                    class="block w-full cursor-pointer text-left"
                                                    :href="documents.view.url({ query: { id: u._id } })"
                                                    prefetch
                                                >
                                                    View
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>Download</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem :as-child="true">
                                                <Link
                                                    as="button"
                                                    class="block w-full cursor-pointer text-left"
                                                    :href="documents.interrogate.url({ query: { id: u._id } })"
                                                    prefetch
                                                >
                                                    Interrogate
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>Edit</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-destructive" :as-child="true">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center"
                                                    @click="openDeleteDialog(u)"
                                                >
                                                    <TrashIcon class="mr-2 h-4 w-4" />
                                                    Delete
                                                </button>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

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
                            <Button variant="secondary" @click="handleDialogOpen(false)">
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
        </div>
    </AppLayout>
  </template>
