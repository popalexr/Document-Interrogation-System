<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger, DialogFooter } from '@/components/ui/dialog'
import { Upload } from 'lucide-vue-next'

const open = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const selected = ref<File[]>([])

const emit = defineEmits<{
  (e: 'files-selected', files: FileList | File[]): void
}>()

function openPicker() {
  fileInput.value?.click()
}

function handleFiles(files: FileList | File[]) {
  const arr = Array.from(files as FileList)
  selected.value = arr
  emit('files-selected', files)
}

function onChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    handleFiles(target.files)
  }
  if (target) target.value = ''
}

const isDragging = ref(false)

function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  if (e.dataTransfer?.files && e.dataTransfer.files.length) {
    handleFiles(e.dataTransfer.files)
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button variant="outline" size="default" class="rounded-lg">
        <Upload class="size-4" />
        Upload File
      </Button>
    </DialogTrigger>

    <DialogContent class="sm:max-w-xl">
      <DialogHeader>
        <DialogTitle>Upload File</DialogTitle>
        <DialogDescription>
          Drag files here or browse from your computer.
        </DialogDescription>
      </DialogHeader>

      <div
        class="mt-2 rounded-lg border border-dashed p-6 text-center"
        :class="isDragging ? 'border-primary bg-primary/5' : 'border-input'"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <div class="flex flex-col items-center gap-2">
          <Upload class="size-6 opacity-70" />
          <p class="text-sm font-medium">Drag your files here</p>
          <p class="text-xs text-muted-foreground">
            DOC, PDF, XLEX, PPT formats, up to 50 MB
          </p>
          <input ref="fileInput" type="file" class="sr-only" multiple @change="onChange" />
          <Button class="mt-2" @click="openPicker">Browse Files</Button>
        </div>
      </div>

      <div v-if="selected.length" class="mt-4 space-y-2">
        <p class="text-sm font-medium">Selected files</p>
        <ul class="max-h-40 overflow-auto space-y-1">
          <li v-for="f in selected" :key="f.name + f.size" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
            <span class="truncate mr-2">{{ f.name }}</span>
            <span class="text-xs text-muted-foreground">{{ (f.size/1024/1024).toFixed(1) }} MB</span>
          </li>
        </ul>
      </div>

      <DialogFooter class="mt-2">
        <Button variant="secondary" @click="open = false">Close</Button>
        <Button :disabled="!selected.length" @click="open = false">Done</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
