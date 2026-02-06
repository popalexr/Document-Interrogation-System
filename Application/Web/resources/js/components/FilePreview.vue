<script setup lang="ts">
import { computed } from "vue";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";

import PdfPreview from "@/components/renderers/PdfPreview.vue";
import TextPreview from "@/components/renderers/TextPreview.vue";
import MarkdownPreview from "@/components/renderers/MarkdownPreview.vue";
import JsonPreview from "@/components/renderers/JsonPreview.vue";
import HtmlPreview from "@/components/renderers/HtmlPreview.vue";
import DocxPreview from "@/components/renderers/DocxPreview.vue";
import { MoreHorizontal } from "lucide-vue-next";
import DropdownMenu from "./ui/dropdown-menu/DropdownMenu.vue";
import DropdownMenuTrigger from "./ui/dropdown-menu/DropdownMenuTrigger.vue";
import Button from "./ui/button/Button.vue";
import DropdownMenuContent from "./ui/dropdown-menu/DropdownMenuContent.vue";
import DropdownMenuItem from "./ui/dropdown-menu/DropdownMenuItem.vue";
import DropdownMenuSeparator from "./ui/dropdown-menu/DropdownMenuSeparator.vue";
import documents from "@/routes/documents";
import { Link } from "@inertiajs/vue3";
import PptxPreview from "./renderers/PptxPreview.vue";

type PreviewKind =
  | "pdf"
  | "text"
  | "markdown"
  | "json"
  | "html"
  | "docx"
  | "doc"
  | "pptx"
  | "unknown";

const props = defineProps<{
  fileUrl: string;
  fileName: string;
  fileId: string;
  mimeType?: string;
}>();

const ext = computed(() => {
  const parts = props.fileName.toLowerCase().split(".");
  return parts.length > 1 ? parts.pop()! : "";
});

const kind = computed<PreviewKind>(() => {
  const e = ext.value;

  if (e === "pdf") return "pdf";
  if (e === "txt" || e === "tex") return "text";
  if (e === "md") return "markdown";
  if (e === "json") return "json";
  if (e === "html" || e === "htm") return "html";
  if (e === "docx") return "docx";

  if (e === "doc") return "doc";
  if (e === "pptx") return "pptx";

  return "unknown";
});

const label = computed(() => ext.value.toUpperCase() || "FILE");

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
  <Card class="border-muted/60">
    <CardHeader class="space-y-2">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 w-full">
          <div class="leading-none font-semibold sm:text-lg">
            <div class="flex justify-between gap-3">
              <div class="flex gap-2 items-center">
                <Badge class="text-xs" :class="extColor(label)">{{ label }}</Badge>
                {{ fileName }}
              </div>
              <div>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon" class="h-8 w-8">
                          <MoreHorizontal class="h-4 w-4" />
                      </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-40">
                      <DropdownMenuItem>
                        <a
                          class="block w-full cursor-pointer text-left"
                          :href="documents.downloadDocument.url({ query: { id: fileId } })"
                          target="_blank"
                          rel="noopener"
                        >
                          Download
                        </a>
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem :as-child="true">
                          <Link
                              as="button"
                              class="block w-full cursor-pointer text-left"
                              href="#"
                              prefetch
                          >
                              Interrogate
                          </Link>
                      </DropdownMenuItem>
                      <DropdownMenuItem>Edit</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Separator />
    </CardHeader>

    <CardContent class="pt-0">
      <PdfPreview v-if="kind === 'pdf'" :url="fileUrl" />

      <TextPreview v-else-if="kind === 'text'" :url="fileUrl" />

      <MarkdownPreview v-else-if="kind === 'markdown'" :url="fileUrl" />

      <JsonPreview v-else-if="kind === 'json'" :url="fileUrl" />

      <HtmlPreview v-else-if="kind === 'html'" :url="fileUrl" />

      <DocxPreview v-else-if="kind === 'docx'" :url="fileUrl" />

      <PptxPreview v-else-if="kind === 'pptx'" :pptx-url="fileUrl" :file-name="fileName" />

      <div
        v-else
        class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
      >
        Tip de fișier neacceptat pentru preview.
      </div>
    </CardContent>
  </Card>
</template>
