<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import * as pdfjsLib from "pdfjs-dist";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

const props = defineProps<{ url: string }>();

pdfjsLib.GlobalWorkerOptions.workerSrc =
  new URL("pdfjs-dist/build/pdf.worker.mjs", import.meta.url).toString();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const pageNum = ref(1);
const numPages = ref(1);
let pdfDoc: any = null;

async function render() {
  if (!pdfDoc || !canvasRef.value) return;

  const page = await pdfDoc.getPage(pageNum.value);
  const viewport = page.getViewport({ scale: 1.2 });

  const canvas = canvasRef.value;
  const ctx = canvas.getContext("2d")!;
  canvas.width = viewport.width;
  canvas.height = viewport.height;

  await page.render({ canvasContext: ctx, viewport }).promise;
}

async function load() {
  pdfDoc = await pdfjsLib.getDocument(props.url).promise;
  numPages.value = pdfDoc.numPages;
  pageNum.value = 1;
  await render();
}

onMounted(load);
watch(() => props.url, load);
watch(pageNum, render);
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          @click="pageNum = Math.max(1, pageNum - 1)"
          :disabled="pageNum === 1"
        >
          Prev
        </Button>
        <Button
          variant="outline"
          size="sm"
          @click="pageNum = Math.min(numPages, pageNum + 1)"
          :disabled="pageNum === numPages"
        >
          Next
        </Button>
      </div>

      <Badge variant="secondary" class="text-xs">
        {{ pageNum }} / {{ numPages }}
      </Badge>
    </div>

    <div class="overflow-auto rounded-xl border bg-background p-2">
      <canvas ref="canvasRef" class="mx-auto block" />
    </div>
  </div>
</template>
