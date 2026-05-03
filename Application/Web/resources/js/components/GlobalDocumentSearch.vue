<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { csrfToken } from '@/lib/utils';
import documents from '@/routes/documents';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    FileText,
    Loader2,
    Search,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface SearchResult {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: string;
    score: number | null;
    snippet: string;
    created_at: string | null;
}

const open = ref(false);
const query = ref('');
const searchedQuery = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const error = ref('');
let debounceTimer: ReturnType<typeof window.setTimeout> | null = null;
let abortController: AbortController | null = null;

const canSearch = computed(() => query.value.trim().length >= 2);
const hasSearched = computed(() => searchedQuery.value.length > 0);

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

watch(query, () => {
    error.value = '';

    if (debounceTimer !== null) {
        window.clearTimeout(debounceTimer);
    }

    if (!canSearch.value) {
        results.value = [];
        searchedQuery.value = '';
        loading.value = false;
        abortController?.abort();
        return;
    }

    debounceTimer = window.setTimeout(() => {
        void searchFiles();
    }, 350);
});

watch(open, (isOpen) => {
    if (!isOpen) {
        abortController?.abort();
        loading.value = false;
    }
});

function handleGlobalKeydown(event: KeyboardEvent): void {
    if (event.key.toLowerCase() !== 'z' || (!event.ctrlKey && !event.metaKey)) {
        return;
    }

    if (isEditableTarget(event.target)) {
        return;
    }

    event.preventDefault();
    open.value = true;
}

function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
    );
}

async function searchFiles(): Promise<void> {
    const text = query.value.trim();
    if (text.length < 2) return;

    abortController?.abort();
    const currentController = new AbortController();
    abortController = currentController;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/documents/retrieve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken() || '',
            },
            body: JSON.stringify({ query: text, limit: 10 }),
            credentials: 'same-origin',
            signal: currentController.signal,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload?.message || 'Search failed.');
        }

        const payload = await response.json();
        results.value = Array.isArray(payload.files) ? payload.files : [];
        searchedQuery.value = payload.query || text;
    } catch (searchError) {
        if (searchError instanceof DOMException && searchError.name === 'AbortError') {
            return;
        }

        error.value =
            searchError instanceof Error
                ? searchError.message
                : 'Search failed.';
        results.value = [];
    } finally {
        if (abortController === currentController) {
            loading.value = false;
        }
    }
}

function openResult(result: SearchResult): void {
    open.value = false;
    router.visit(documents.view.url({ query: { id: result._id } }));
}

function formatDate(value: string | null): string {
    if (!value) return '';

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}
</script>

<template>
    <Dialog v-model:open="open">
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="h-8 shrink-0 gap-2 rounded-md"
            @click="open = true"
        >
            <Search class="size-4" />
            <span class="hidden sm:inline">Search files</span>
        </Button>

        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-2xl">
            <DialogHeader class="sr-only">
                <DialogTitle>Search files</DialogTitle>
                <DialogDescription>
                    Search uploaded files by semantic context.
                </DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-3 border-b px-4 py-3">
                <Search class="size-5 shrink-0 text-muted-foreground" />
                <Input
                    v-model="query"
                    class="h-10 border-0 px-0 shadow-none focus-visible:ring-0 focus-visible:ring-offset-0"
                    placeholder="Search by context..."
                    @keydown.enter.prevent="searchFiles"
                />
            </div>

            <div class="max-h-[min(68vh,34rem)] overflow-y-auto p-2">
                <div
                    v-if="error"
                    class="flex items-center gap-2 rounded-md px-3 py-6 text-sm text-destructive"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <div
                    v-else-if="loading"
                    class="flex items-center gap-3 rounded-md px-3 py-8 text-sm text-muted-foreground"
                >
                    <Loader2 class="size-4 shrink-0 animate-spin" />
                    <span>Searching files...</span>
                </div>

                <div
                    v-else-if="!canSearch"
                    class="flex items-center gap-3 rounded-md px-3 py-8 text-sm text-muted-foreground"
                >
                    <Sparkles class="size-4 shrink-0" />
                    <span>Type a phrase from the document context.</span>
                </div>

                <div
                    v-else-if="!loading && hasSearched && results.length === 0"
                    class="flex items-center gap-3 rounded-md px-3 py-8 text-sm text-muted-foreground"
                >
                    <FileText class="size-4 shrink-0" />
                    <span>No matching files found.</span>
                </div>

                <div v-else class="space-y-1">
                    <button
                        v-for="result in results"
                        :key="result._id"
                        type="button"
                        class="grid w-full grid-cols-[auto_1fr_auto] gap-3 rounded-md px-3 py-3 text-left outline-none transition-colors hover:bg-accent focus-visible:bg-accent"
                        @click="openResult(result)"
                    >
                        <FileText
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">
                                {{ result.original_name }}
                            </span>
                            <span
                                class="mt-1 line-clamp-2 block text-xs leading-5 text-muted-foreground"
                            >
                                {{ result.snippet || result.mime_type }}
                            </span>
                        </span>
                        <span
                            class="mt-0.5 hidden shrink-0 text-xs text-muted-foreground sm:block"
                        >
                            {{ formatDate(result.created_at) }}
                        </span>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
