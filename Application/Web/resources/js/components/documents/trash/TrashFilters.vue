<script setup lang="ts">
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
import type { TrashSortKey } from './types';

defineProps<{
    documentTypes: string[];
}>();

const searchQuery = defineModel<string>('searchQuery', { required: true });
const typeFilter = defineModel<string>('typeFilter', { required: true });
const sortKey = defineModel<TrashSortKey>('sortKey', { required: true });

const typeLabel = computed(() =>
    typeFilter.value === 'all' ? 'All' : typeFilter.value,
);
const sortLabel = computed(() => {
    if (sortKey.value === 'newest_deleted') return 'Newest deleted';
    if (sortKey.value === 'oldest_deleted') return 'Oldest deleted';

    return 'Name';
});

function clearFilters() {
    typeFilter.value = 'all';
}
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
                placeholder="Search deleted documents..."
            />
        </label>

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
                <DropdownMenuItem @click="sortKey = 'newest_deleted'">
                    Newest deleted
                </DropdownMenuItem>
                <DropdownMenuItem @click="sortKey = 'oldest_deleted'">
                    Oldest deleted
                </DropdownMenuItem>
                <DropdownMenuItem @click="sortKey = 'name'">
                    Name
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Button variant="outline" class="gap-2" @click="clearFilters">
            <SlidersHorizontal class="size-4" />
            Clear filters
        </Button>
    </div>
</template>
