<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import Icon from '@/components/Icon.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trash', href: '' },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

type DeletedDocument = {
  _id: string;
  original_name: string;
  mime_type: string;
  size: number;
  status?: string;
  r2_key?: string;
  created_at?: string | Date;
  deleted_at?: string | Date;
}

const deletedDocuments = computed<DeletedDocument[]>(() => ((page.props as any).deletedDocuments ?? []) as DeletedDocument[])

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
</script>

<template>
    <Head title="Deleted documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <Card>
                <CardHeader class="pb-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle class="text-xl">Deleted documents</CardTitle>
                            <p class="text-muted-foreground mt-1 text-xs">Owner: {{ user.name }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-4">
                    <div v-if="!deletedDocuments.length" class="text-sm text-muted-foreground">No deleted documents.</div>
                    <ul v-else class="divide-y rounded-md border">
                        <li v-for="doc in deletedDocuments" :key="doc._id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1 flex items-start gap-3">
                                <div class="relative flex size-10 items-center justify-center rounded-md border">
                                    <Icon name="file" class="h-6 w-6" />
                                    <span
                                        v-if="fileExt(doc.original_name)"
                                        class="absolute -left-1 -top-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="extColor(fileExt(doc.original_name))"
                                    >
                                        {{ fileExt(doc.original_name) }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.original_name }}</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ formatSize(doc.size) }} • {{ doc.mime_type }}
                                        <span v-if="doc.deleted_at"> • Deleted {{ formatDate(doc.deleted_at) }}</span>
                                    </p>
                                </div>
                            </div>
                            <Badge variant="secondary" class="shrink-0">Deleted</Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
