<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger, DialogFooter } from '@/components/ui/dialog'
import { Upload } from 'lucide-vue-next'

const open = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const selected = ref<File[]>([])

type UploadState = {
  file: File
  progress: number
  status: 'idle' | 'uploading' | 'done' | 'error'
  response?: any
  error?: string
}

const uploads = ref<UploadState[]>([])
const shouldReload = ref(false)

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
  // kickoff upload immediately
  startUploads(arr)
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

function getCsrfToken(): string | null {
  const el = document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
  return el?.content ?? null
}

function startUploads(files: File[]) {
  shouldReload.value = false
  uploads.value = files.map((f) => ({ file: f, progress: 0, status: 'idle' }))
  // upload sequentially to simplify UI/state
  void uploadNext(0)
}

async function uploadNext(index: number): Promise<void> {
  if (index >= uploads.value.length) {
    if (shouldReload.value) {
      router.reload({ preserveScroll: true })
    }
    return
  }
  const item = uploads.value[index]
  item.status = 'uploading'

  try {
    const form = new FormData()
    form.append('file', item.file)

    const token = getCsrfToken()

    // Use XMLHttpRequest to report progress
    const xhr = new XMLHttpRequest()
    const url = '/uploads'

    const progressHandler = (e: ProgressEvent<EventTarget>) => {
      if (e.lengthComputable) {
        item.progress = Math.round((e.loaded / e.total) * 100)
      }
    }

    const result: any = await new Promise((resolve, reject) => {
      xhr.upload.addEventListener('progress', progressHandler)
      xhr.onreadystatechange = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          try {
            const resp = xhr.responseText ? JSON.parse(xhr.responseText) : null
            if (xhr.status >= 200 && xhr.status < 300) resolve(resp)
            else reject(new Error(resp?.message || `Upload failed (${xhr.status})`))
          } catch (err) {
            if (xhr.status >= 200 && xhr.status < 300) resolve(null)
            else reject(new Error(`Upload failed (${xhr.status})`))
          }
        }
      }
      xhr.open('POST', url, true)
      xhr.setRequestHeader('Accept', 'application/json')
      if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token)
      xhr.send(form)
    })

    item.response = result
    item.status = 'done'
    item.progress = 100
    shouldReload.value = true
  } catch (e: any) {
    item.status = 'error'
    item.error = e?.message ?? 'Upload failed'
  } finally {
    // move to next
    void uploadNext(index + 1)
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

      <div v-if="uploads.length" class="mt-4 space-y-2">
        <p class="text-sm font-medium">Uploads</p>
        <ul class="max-h-56 overflow-auto space-y-1">
          <li
            v-for="u in uploads"
            :key="u.file.name + u.file.size"
            class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
          >
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between">
                <span class="truncate mr-2">{{ u.file.name }}</span>
                <span class="text-xs text-muted-foreground ml-2">{{ (u.file.size/1024/1024).toFixed(1) }} MB</span>
              </div>
              <div class="mt-1 h-1.5 w-full rounded bg-muted">
                <div class="h-1.5 rounded bg-primary transition-all" :style="{ width: `${u.progress}%` }"></div>
              </div>
              <div class="mt-1 text-xs" :class="u.status === 'error' ? 'text-red-600' : 'text-muted-foreground'">
                <template v-if="u.status === 'uploading'">Uploading... {{ u.progress }}%</template>
                <template v-else-if="u.status === 'done'">Uploaded</template>
                <template v-else-if="u.status === 'error'">{{ u.error }}</template>
                <template v-else>Queued</template>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <DialogFooter class="mt-2">
        <Button variant="secondary" @click="open = false">Close</Button>
        <Button :disabled="uploads.some(u => u.status === 'uploading')" @click="open = false">Done</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
