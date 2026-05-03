<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Info } from 'lucide-vue-next';

defineProps<{
    ownerName: string;
}>();
</script>

<template>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Deleted Documents
                </h1>
                <TooltipProvider :delay-duration="150">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                                aria-label="Trash information"
                            >
                                <Info class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            Review deleted documents and restore files back to
                            your document library.
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Owner:
                <span class="text-foreground">{{ ownerName }}</span>
            </p>
        </div>
    </div>
</template>
