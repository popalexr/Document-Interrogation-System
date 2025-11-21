<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url
  },
  {
    title: 'My Documents',
    href: '#',
  },
  {
    title: page.props.document.original_name,
    href: '#',
  },
  {
    title: 'Interrogate Document',
    href: '',
  },
];

type DocumentInfo = {
  _id: string;
  original_name: string;
  mime_type: string;
  size: number;
  status: string;
  r2_key: string;
  created_at?: string | Date;
}

const documentInfo = computed<DocumentInfo | null>(() => (page.props as any).document ?? null);

function formatSize(bytes?: number): string {
  if (bytes === undefined || bytes === null) return ''
  const units = ['B','KB','MB','GB']
  let b = bytes
  let i = 0
  while (b >= 1024 && i < units.length - 1) { b /= 1024; i++ }
  return `${b.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

type ChatMessage = { role: 'user' | 'assistant'; content: string; at: Date };
const messages = ref<ChatMessage[]>([]);
const input = ref('');
const sending = ref(false);
const chatContainer = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
  const el = chatContainer.value;
  if (!el) return;
  requestAnimationFrame(() => {
    el.scrollTop = el.scrollHeight;
  });
};

watch(
  () => messages.value.length,
  async () => {
    await nextTick();
    scrollToBottom();
  }
);

async function sendMessage() {
  const text = input.value.trim();
  if (!text) return;
  messages.value.push({ role: 'user', content: text, at: new Date() });
  input.value = '';
  sending.value = true;

  try {
    const tokenEl = document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    const token = tokenEl?.content ?? '';
    const did = documentInfo.value?._id;
    const res = await fetch(`/documents/interrogate`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      },
      body: JSON.stringify({
        document_id: did,
        query: text,
      }),
      credentials: 'same-origin',
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({} as any));
      throw new Error(data?.message || `Request failed (${res.status})`);
    }
    const data = await res.json();
    const answer = (data && typeof data.answer === 'string') ? data.answer : 'No answer returned.';
    messages.value.push({ role: 'assistant', content: answer, at: new Date() });
  } catch (e: any) {
    messages.value.push({ role: 'assistant', content: e?.message ?? 'Error performing interrogation.', at: new Date() });
  } finally {
    sending.value = false;
  }
}

onMounted(() => {
  messages.value = page.props.chats || [];
  nextTick(scrollToBottom);
});
</script>

<template>
  <Head title="Interrogate Document" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
      <Card>
        <CardHeader class="pb-0">
          <CardTitle class="text-xl">Interrogate Document</CardTitle>
        </CardHeader>
        <CardContent class="pt-4 space-y-4">
          <template v-if="documentInfo">
            <div class="text-sm">
              <div><span class="text-muted-foreground">Name:</span> {{ documentInfo.original_name }}</div>
              <div><span class="text-muted-foreground">MIME:</span> {{ documentInfo.mime_type }}</div>
              <div><span class="text-muted-foreground">Size:</span> {{ formatSize(documentInfo.size) }}</div>
              <div><span class="text-muted-foreground">Status:</span> {{ documentInfo.status }}</div>
            </div>
            <Separator />
            <div class="space-y-3">
              <div class="text-sm font-medium">Chat</div>
              <div class="flex flex-col gap-2 max-h-[50vh] overflow-y-auto pr-1" ref="chatContainer">
                <div v-if="!messages.length" class="text-xs text-muted-foreground">
                  Start by asking a question about this document.
                </div>
                <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                  <div :class="[
                      'rounded-md px-3 py-2 text-sm max-w-[80%] whitespace-pre-wrap',
                      m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'
                    ]">
                    {{ m.content }}
                    <div class="mt-1 text-[10px] opacity-70">{{ new Date(m.at).toLocaleTimeString() }}</div>
                  </div>
                </div>
                <div v-if="sending" class="flex justify-start">
                  <div class="rounded-md bg-muted px-3 py-2 text-sm flex items-center gap-2">
                    <Spinner size="sm" />
                    Thinking...
                  </div>
                </div>
              </div>

              <form class="flex items-end gap-2" @submit.prevent="sendMessage">
                <textarea
                  v-model="input"
                  rows="2"
                  class="w-full resize-none rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  placeholder="Ask a question about this document..."
                ></textarea>
                <Button type="submit" :disabled="sending || !input.trim()">Send</Button>
              </form>
            </div>
          </template>
          <template v-else>
            <p class="text-sm text-destructive">Document not found.</p>
          </template>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
  </template>
