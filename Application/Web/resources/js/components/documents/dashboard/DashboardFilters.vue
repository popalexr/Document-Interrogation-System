<script setup lang="ts">
import UploadButton from '@/components/UploadButton.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowDownUp,
    ChevronDown,
    FileText,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { SortKey, StatusFilter } from './types';

withDefaults(defineProps<{
    documentTypes: string[];
    showUploadButton?: boolean;
}>(), {
    showUploadButton: true,
});

const searchQuery = defineModel<string>('searchQuery', { required: true });
const statusFilter = defineModel<StatusFilter>('statusFilter', {
    required: true,
});
const typeFilter = defineModel<string>('typeFilter', { required: true });
const sortKey = defineModel<SortKey>('sortKey', { required: true });

const statusLabel = computed(() => statusFilter.value.replace('_', ' '));
const typeLabel = computed(() =>
    typeFilter.value === 'all' ? 'All' : typeFilter.value,
);
const sortLabel = computed(() => {
    if (sortKey.value === 'newest') return 'Newest first';
    if (sortKey.value === 'oldest') return 'Oldest first';

    return 'Name';
});
</script>

<template>
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
                        {{ statusLabel }}
                    </span>
                    <ChevronDown class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-44">
                <DropdownMenuItem @click="statusFilter = 'all'">
                    All
                </DropdownMenuItem>
                <DropdownMenuItem @click="statusFilter = 'indexed'">
                    Indexed
                </DropdownMenuItem>
                <DropdownMenuItem @click="statusFilter = 'processing'">
                    Processing
                </DropdownMenuItem>
                <DropdownMenuItem @click="statusFilter = 'failed'">
                    Failed
                </DropdownMenuItem>
                <DropdownMenuItem @click="statusFilter = 'not_indexed'">
                    Not indexed
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline" class="gap-2">
                    <FileText class="size-4" />
                    Type:
                    <span class="font-medium">{{ typeLabel }}</span>
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
                    <span class="font-medium">{{ sortLabel }}</span>
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

        <div v-if="showUploadButton" class="ml-auto">
            <UploadButton
                label="Upload Documents"
                variant="default"
                trigger-class="gap-2"
            />
        </div>
    </div>
</template>
