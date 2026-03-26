<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Bot, Paperclip, SendHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';

type EditorTab = 'chat' | 'history';

const activeTab = ref<EditorTab>('chat');
const prompt = ref('');

const promptChips = ['Scrie mai formal', 'Rezumat', 'Corecteaza gramatical'];
</script>

<template>
    <aside
        class="flex min-h-[26rem] w-full min-w-0 flex-col border-t border-border/80 bg-muted/20 lg:min-h-0 lg:w-[35rem] lg:max-w-[44vw] lg:border-t-0 lg:border-l"
    >
        <div class="border-b border-border/80 bg-background px-4 sm:px-6">
            <div class="flex items-center gap-7">
                <button
                    type="button"
                    class="border-b-[3px] px-1 py-4"
                    :class="
                        activeTab === 'chat'
                            ? 'border-primary font-semibold text-foreground'
                            : 'border-transparent text-muted-foreground'
                    "
                    @click="activeTab = 'chat'"
                >
                    Chat
                </button>
                <button
                    type="button"
                    class="border-b-[3px] px-1 py-4"
                    :class="
                        activeTab === 'history'
                            ? 'border-primary font-semibold text-foreground'
                            : 'border-transparent text-muted-foreground'
                    "
                    @click="activeTab = 'history'"
                >
                    History
                </button>
            </div>
        </div>

        <template v-if="activeTab === 'chat'">
            <div
                class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5"
            >

                <div class="flex items-start gap-3">
                    <Avatar
                        class="size-10 border border-primary/20 bg-primary/10"
                    >
                        <AvatarFallback class="bg-transparent text-primary">
                            <Bot class="size-5" />
                        </AvatarFallback>
                    </Avatar>
                    <div
                        class="max-w-[82%] rounded-2xl bg-muted px-4 py-3 text-foreground"
                    >
                        Rescrie introducerea intr-un stil mai clar.
                    </div>
                </div>

                <div class="flex items-start justify-end gap-3">
                    <div
                        class="max-w-[82%] rounded-2xl bg-blue-50 px-4 py-3 leading-8 text-foreground"
                    >
                        Am rescris introducerea intr-un stil mai clar si concis.
                        Acesta este noul continut propus pentru sectiunea
                        <strong>Introducere.</strong>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <Avatar
                        class="size-10 border border-primary/20 bg-primary/10"
                    >
                        <AvatarFallback class="bg-transparent text-primary">
                            <Bot class="size-5" />
                        </AvatarFallback>
                    </Avatar>

                    <div
                        class="w-full overflow-hidden rounded-xl border border-border bg-background"
                    >
                        <div
                            class="border-b border-border/70 bg-muted/70 px-4 py-2"
                        >
                            <p class="font-medium text-foreground">
                                AI Edited
                            </p>
                        </div>

                        <div
                            class="space-y-3 px-4 py-4 leading-[1.65] text-foreground"
                        >
                            <p>
                                In recent years, blockchain technology has
                                emerged as a promising solution to enhance the
                                security, transparency, and verifiability of
                                electronic voting systems.
                            </p>
                            <p>
                                By utilizing the decentralized nature of
                                blockchain, voting processes can be made
                                significantly more secure and transparent
                                compared to traditional methods.
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap gap-2 border-t border-border/70 px-4 py-3"
                        >
                            <Button size="sm" class="shadow-none">
                                Apply Change
                            </Button>
                            <Button size="sm" variant="outline">
                                Preview Change
                            </Button>
                            <Button size="sm" variant="outline">
                                Discard
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-border/80 bg-background p-4">
                <div
                    class="flex items-center gap-2 rounded-lg border border-input bg-background px-2"
                >
                    <Button variant="ghost" size="icon" class="size-8">
                        <Paperclip class="size-4 text-muted-foreground" />
                    </Button>
                    <Input
                        v-model="prompt"
                        placeholder="Send a message..."
                        class="h-11 border-0 px-0 text-base shadow-none focus-visible:border-transparent focus-visible:ring-0"
                    />
                    <Button size="sm" class="h-9 px-3">
                        <SendHorizontal class="size-4" />
                        Send
                    </Button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        v-for="chip in promptChips"
                        :key="chip"
                        type="button"
                        class="rounded-md bg-secondary px-3 py-2 text-sm font-medium text-secondary-foreground transition-colors hover:bg-secondary/70"
                    >
                        {{ chip }}
                    </button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="flex flex-1 items-center justify-center p-6">
                <div
                    class="max-w-sm rounded-xl border border-dashed border-border bg-background p-6 text-center"
                >
                    <p class="font-medium text-foreground">
                        No history available yet
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Once you apply or discard edits, activity will appear
                        here.
                    </p>
                </div>
            </div>
        </template>
    </aside>
</template>
