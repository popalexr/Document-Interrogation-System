<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { ClipboardCheck, Ellipsis, FileText, Paperclip, SendHorizontal } from 'lucide-vue-next';

const page = usePage();

let breadcrumbs: BreadcrumbItem[] = [
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

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);

const maxRows = 4;

const documentInfo = computed<DocumentInfo | null>(() => (page.props as any).document ?? null);

type ChatMessage = { role: 'user' | 'assistant'; content: string; at: Date | string; loading?: boolean };
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
  if (!text || sending.value) return;
  const userMessage: ChatMessage = { role: 'user', content: text, at: new Date() };
  messages.value.push(userMessage);
  // First assistant reply shows thinking text and owns the spinner until chunks arrive.
  messages.value.push({ role: 'assistant', content: 'Thinking...', at: new Date(), loading: true });
  // Use the reactive entry from the array so stream mutations repaint the UI.
  let assistantMessage: ChatMessage | null = messages.value[messages.value.length - 1] ?? null;
  let hasStarted = false;
  input.value = '';
  sending.value = true;

  try {
    if (!assistantMessage) {
      throw new Error('Unable to create assistant message entry.');
    }
    const tokenEl = document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    const token = tokenEl?.content ?? '';
    const did = documentInfo.value?._id;
    const res = await fetch(`/documents/interrogate`, {
      method: 'POST',
      headers: {
        'Accept': 'text/event-stream',
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
    const reader = res.body?.getReader();
    if (!reader) {
      throw new Error('No response body.');
    }

    const decoder = new TextDecoder();
    let buffer = '';

    const handleEvent = (raw: string) => {
      const line = raw.trim();
      if (!line.startsWith('data:')) return;

      const jsonPayload = line.replace(/^data:\s*/, '');
      let payload: any = null;

      try {
        payload = JSON.parse(jsonPayload);
      } catch (err) {
        return;
      }

      if (payload?.type === 'chunk' && typeof payload.delta === 'string') {
        if (!hasStarted) {
          assistantMessage.content = '';
          hasStarted = true;
        }
        assistantMessage.content += payload.delta;
        assistantMessage.at = new Date();
        scrollToBottom();
      } else if (payload?.type === 'done') {
        if (typeof payload.answer === 'string') {
          assistantMessage.content = payload.answer;
        }
        assistantMessage.loading = false;
        assistantMessage.at = new Date();
        scrollToBottom();
      } else if (payload?.type === 'error') {
        assistantMessage.content = payload.message ?? 'Error performing interrogation.';
        assistantMessage.loading = false;
        assistantMessage.at = new Date();
      }
    };

    const processBuffer = (flush = false) => {
      let working = buffer;
      const events = working.split(/\r?\n\r?\n/);
      working = events.pop() ?? '';

      for (const rawEvent of events) {
        handleEvent(rawEvent);
      }

      if (flush && working.trim()) {
        handleEvent(working);
        working = '';
      }

      buffer = working;
    };

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      processBuffer();
    }

    buffer += decoder.decode();
    processBuffer(true);

    if (!assistantMessage.content.trim()) {
      assistantMessage.content = 'No answer returned.';
      assistantMessage.at = new Date();
    }
  } catch (e: any) {
    if (assistantMessage) {
      assistantMessage.content = e?.message ?? 'Error performing interrogation.';
      assistantMessage.loading = false;
      assistantMessage.at = new Date();
    }
    scrollToBottom();
  } finally {
    sending.value = false;
  }
}

const insertSuggestion = (suggestion : number) => {
  const suggestions = [
    "What's this document about?",
    'Summarize this document.',
  ];

  input.value = suggestions[suggestion];
  nextTick(() => {
    sendMessage();
  });
};

const autoGrow = () => {
  const el = textareaRef.value
  if (!el) return

  // Reset height to recalculate
  el.style.height = 'auto'

  const maxHeight = lineHeightPx.value * maxRows
  const newHeight = Math.min(el.scrollHeight, maxHeight)

  el.style.height = newHeight + 'px'

  // Optional: allow scroll once we hit max rows
  el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden'
}

const handleTextareaKeydown = (event: KeyboardEvent) => {
  if (event.key !== 'Enter' || event.shiftKey) return;
  event.preventDefault();
  sendMessage();
};

const textAreaInitialSizing = () => {
    const el = textareaRef.value
    if (!el) return

    // Get the computed line-height (in px)
    const style = window.getComputedStyle(el)
    lineHeightPx.value = parseFloat(style.lineHeight) || 16 // fallback

    // Initial sizing
    autoGrow()
}

onMounted(() => {
  messages.value = page.props.chats || [];
  nextTick(scrollToBottom);

  textAreaInitialSizing();
});
</script>

<template>
    <Head title="Interrogate Document" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex layout-content-height w-full">
            <div class="w-6/7 px-10 border-r border-gray-200">
                <div class="flex flex-col h-full w-full">
                    <div class="h-4/5 w-full">
                        <template v-if="messages.length === 0">
                            <div class="flex h-full items-center justify-center">
                                <div class="flex flex-col">
                                    <div class="text-center text-5xl font-bold mb-3">
                                        Interrogate this document
                                    </div>
                                    <div class="text-center text-gray-500 mb-10">
                                        Get started by asking a question about this document. Not sure what to ask?
                                    </div>

                                    <div class="flex flex-wrap gap-6">
                                        <div class="flex items-center justify-between flex-1 min-w-[220px] max-w-xs rounded-2xl border border-gray-200 bg-white px-5 py-3 shadow-sm hover:shadow-lg transition-shadow">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-200">
                                                    <span class="text-lg"><FileText /> </span>
                                                </div>
                                                <span class="font-medium text-gray-800 text-center">What's this document about?</span>
                                            </div>
                                            <button
                                                class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 text-gray-600 cursor-pointer"
                                                @click="insertSuggestion(0)"
                                            >
                                                +
                                            </button>
                                        </div>
                                        
                                        <div class="flex items-center justify-between flex-1 min-w-[220px] max-w-xs rounded-2xl border border-gray-200 bg-white px-5 py-3 shadow-sm hover:shadow-lg transition-shadow">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-lime-200">
                                                    <span class="text-lg"><ClipboardCheck /> </span>
                                                </div>
                                                <span class="font-medium text-gray-800 text-center">Summarize this document.</span>
                                            </div>
                                            <button
                                                class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 text-gray-600 cursor-pointer"
                                                @click="insertSuggestion(1)"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex w-full h-full justify-center pt-6">
                                <div
                                    ref="chatContainer"
                                    class="flex h-full w-3/4 flex-col gap-4 overflow-y-auto pr-2 pb-2"
                                >
                                    <div
                                        v-for="(m, i) in messages"
                                        :key="i"
                                        :class="m.role === 'user' ? 'self-end bg-primary text-primary-foreground' : 'self-start bg-muted text-foreground'"
                                        class="rounded-md px-4 py-3 text-sm max-w-3/4 whitespace-pre-wrap"
                                    >
                                        <span class="inline-flex items-end gap-2 leading-relaxed">
                                            <span class="whitespace-pre-wrap">{{ m.content }}</span>
                                            <Spinner
                                                v-if="m.role === 'assistant' && m.loading"
                                                size="sm"
                                                class="shrink-0 inline-block align-middle"
                                            />
                                        </span>
                                        <div class="mt-1 text-[10px] opacity-70" :class="{'flex justify-end': (m.role === 'user')}">{{ new Date(m.at).toLocaleTimeString() }}</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="h-1/5">
                        <div class="flex flex-col h-full w-full items-center justify-center">
                            <div class="w-3/4 mx-auto mt-6">
                                <div class="border border-gray-400 rounded-2xl">
                                    <div class="bg-background rounded-2xl flex flex-col text-sm shadow-sm">
                                        <div class="flex items-center gap-3 px-4 pt-2 pb-1">
                                            <textarea
                                                ref="textareaRef"
                                                v-model="input"
                                                rows="1"
                                                class="w-full border-none outline-none resize-none text-sm placeholder:text-gray-400 leading-snug"
                                                placeholder="Ask a question about this document..."
                                                maxlength="500"
                                                @input="autoGrow"
                                                @keydown="handleTextareaKeydown"
                                            ></textarea>
                                            <button
                                                class="flex h-8 w-8 items-center justify-center text-xs shrink-0 cursor-pointer"
                                                :disabled="sending || !input.trim()"
                                                @click="sendMessage"
                                            >
                                                <SendHorizontal />
                                            </button>
                                        </div>
                                        <div class="flex items-center justify-end px-4 py-2 text-xs text-gray-500">
                                            {{ input.length }}/500
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                This application can make mistakes. Please verify any information it provides.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-1/7 p-4">
                <div class="flex items-center justify-between border-b border-gray-200">
                    <div class="text-md font-bold">Your chats</div>
                    <button class="p-1 rounded-full hover:bg-gray-100 cursor-pointer">
                        <Ellipsis />
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
