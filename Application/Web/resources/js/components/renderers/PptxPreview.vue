<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { computed } from 'vue';

const props = defineProps<{
    pptxUrl: string;
    fileName?: string;
}>();

// Microsoft viewer endpoint
const embedUrl = computed(() => {
    const src = encodeURIComponent(props.pptxUrl);
    return `https://view.officeapps.live.com/op/embed.aspx?src=${src}`;
});
</script>

<template>
    <Card class="border-muted/60">
        <CardContent class="pt-0">
            <div
                class="h-full min-h-[40rem] overflow-hidden rounded-xl border bg-background"
            >
                <iframe
                    :src="embedUrl"
                    class="h-full w-full"
                    frameborder="0"
                    allowfullscreen
                />
            </div>
        </CardContent>
    </Card>
</template>
