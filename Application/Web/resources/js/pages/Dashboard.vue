<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import Icon from '@/components/Icon.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { computed } from 'vue';

type Folder = {
    id: number;
    name: string;
    meta: string;
};

type FileItem = {
    id: number;
    name: string;
    date: string;
    ext: 'PDF' | 'DOC' | 'XLS' | 'PPT' | 'TXT';
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const user = usePage().props.auth.user;

const folders: Folder[] = [
    { id: 1, name: 'Contract Versions', meta: '(3) Files' },
    { id: 2, name: 'Big Kahuna (1)', meta: '(17) Files' },
    { id: 3, name: 'Biffco Enterprise corp.', meta: '(6) Files' },
    { id: 4, name: 'Abstergo Ltd.', meta: '(21) Files' },
    { id: 5, name: 'Contract Versions', meta: '(3) Files' },
];

const files: FileItem[] = [
    { id: 1, name: 'Contracts No. 442', ext: 'PDF', date: '15 Mar 2024' },
    { id: 2, name: 'Application Terms', ext: 'DOC', date: '3 Hours Ago' },
    { id: 3, name: 'Financial Spread Sheet', ext: 'XLS', date: 'Yesterday' },
    { id: 4, name: 'Hilton Hotel', ext: 'PPT', date: '02 Jun 2024' },
    { id: 5, name: 'Hilton Hotel', ext: 'PPT', date: '02 Jun 2024' },
    { id: 6, name: 'Application Terms', ext: 'DOC', date: '3 Hours Ago' },
    { id: 7, name: 'Contracts No. 442', ext: 'PDF', date: '15 Mar 2024' },
    { id: 8, name: 'Hardware Projects ver.2', ext: 'PDF', date: '15 Mar 2024' },
    { id: 9, name: 'Application Terms', ext: 'DOC', date: '3 Hours Ago' },
    { id: 10, name: 'Financial Spread Sheet', ext: 'XLS', date: 'Yesterday' },
    { id: 11, name: 'Application Terms', ext: 'DOC', date: '3 Hours Ago' },
];

type Item =
    | ({ type: 'folder'; meta: string } & Pick<Folder, 'id' | 'name'>)
    | ({ type: 'file' } & FileItem);

// Merge folders and files and sort by name so they display together.
const items = computed<Item[]>(() => {
    const folderItems: Item[] = folders.map((f) => ({
        type: 'folder',
        id: f.id,
        name: f.name,
        meta: f.meta,
    }));
    const fileItems: Item[] = files.map((f) => ({
        type: 'file',
        ...f,
    }));
    return [...folderItems, ...fileItems].sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});

const uploads = [
    { id: 1, name: 'Salse proposal.doc', size: '27.5MB', progress: 68 },
    { id: 2, name: 'Contracts 742.pdf', size: '4.4MB', progress: 100 },
    { id: 3, name: 'Financial spread sheet.xls', size: '3.7MB', progress: 100 },
];

const sharePeople = [
    { id: 1, initials: 'AK', name: 'Asmaa Kassim', email: 'asmaa-kas@gmail.com', role: 'file owner' },
    { id: 2, initials: 'AA', name: 'Abeer Abdullah', email: 'adeer-abd@gmail.com', role: 'can edit' },
    { id: 3, initials: 'EA', name: 'Ebrahim Ali', email: 'ebrahim-ali@gmail.com', role: 'can view' },
];

const extColor = (ext: FileItem['ext']) => {
    switch (ext) {
        case 'PDF':
            return 'bg-red-500 text-white';
        case 'DOC':
            return 'bg-blue-600 text-white';
        case 'XLS':
            return 'bg-green-600 text-white';
        case 'PPT':
            return 'bg-amber-500 text-white';
        default:
            return 'bg-muted text-foreground';
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="grid gap-4 xl:grid-cols-3">
                <!-- Left: Library -->
                <Card class="xl:col-span-2">
                    <CardHeader class="pb-0">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="text-xl">Root folder</CardTitle>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    64.2 MB • Owner: {{user.name}}
                                </p>
                            </div>
                            <Button variant="outline" class="gap-2">
                                <Icon name="upload" />
                                Upload File
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <!-- Unified grid: folders and files, sorted by name -->
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <div
                                v-for="item in items"
                                :key="`${item.type}-${item.id}`"
                                class="group rounded-lg border p-3 hover:shadow-sm"
                            >
                                <div class="flex items-start gap-3">
                                    <!-- Icon + badge area -->
                                    <div
                                        class="relative flex size-10 items-center justify-center rounded-md border"
                                    >
                                        <Icon :name="item.type === 'folder' ? 'folder' : 'file'" class="h-6 w-6" />
                                        <template v-if="item.type === 'file'">
                                            <span
                                                class="absolute -left-1 -top-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="extColor(item.ext)"
                                            >
                                                {{ item.ext }}
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Details -->
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium group-hover:underline">
                                            {{ item.name }}
                                        </div>
                                        <div class="text-muted-foreground text-xs">
                                            <template v-if="item.type === 'folder'">
                                                {{ item.meta }}
                                            </template>
                                            <template v-else>
                                                {{ item.date }}
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Right: Upload + Share -->
                <div class="flex flex-col gap-4">
                    <!-- Upload File -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle>Upload File</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="rounded-xl border border-dashed p-6 text-center"
                            >
                                <div class="mx-auto mb-2 flex size-10 items-center justify-center rounded-full bg-secondary">
                                    <Icon name="upload" />
                                </div>
                                <div class="text-sm font-medium">
                                    Drag your files here
                                </div>
                                <div class="text-muted-foreground mt-1 text-xs">
                                    DOC, PDF, XLSX, and PPT formats, up to 50 MB
                                </div>
                                <div class="mt-3">
                                    <Button size="sm">Browse Files</Button>
                                </div>
                            </div>

                            <div
                                v-for="item in uploads"
                                :key="item.id"
                                class="rounded-lg border p-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <Icon name="file" class="text-muted-foreground" />
                                        <div class="text-sm font-medium">
                                            {{ item.name }}
                                        </div>
                                    </div>
                                    <div class="text-muted-foreground text-xs">
                                        {{ item.size }}
                                    </div>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary">
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{ width: `${item.progress}%` }"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Share panel -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle>
                                Share "Financial Spread Sheet.xls"
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex gap-2">
                                <Input
                                    placeholder="add a name, group, or an email"
                                />
                                <Button variant="outline" class="whitespace-nowrap"
                                    >can view</Button
                                >
                            </div>

                            <div
                                v-for="person in sharePeople"
                                :key="person.id"
                                class="flex items-center justify-between gap-3 rounded-lg border p-3"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <Avatar>
                                        <AvatarFallback>{{ person.initials }}</AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">
                                            {{ person.name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground truncate text-xs"
                                        >
                                            {{ person.email }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-muted-foreground text-xs">
                                    {{ person.role }}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
