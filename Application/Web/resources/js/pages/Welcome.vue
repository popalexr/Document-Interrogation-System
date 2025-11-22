<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { dashboard, login, register } from '@/routes'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import Icon from '@/components/Icon.vue'
import AppLogoIcon from '@/components/AppLogoIcon.vue'

withDefaults(
  defineProps<{
    canRegister: boolean
  }>(),
  {
    canRegister: true,
  },
)
</script>

<template>
  <Head title="Welcome" />

  <div class="min-h-svh flex flex-col bg-gradient-to-b from-background to-muted/30 text-foreground">
    <!-- Top nav -->
    <header class="border-b border-border/60 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
          <span class="inline-flex size-8 items-center justify-center rounded-md text-primary-foreground">
            <AppLogoIcon className="size-5" />
          </span>
          <span class="text-sm font-semibold">InterroDocs</span>
        </div>
        <nav class="flex items-center gap-2">
          <template v-if="$page.props.auth.user">
            <Button asChild size="sm">
              <Link :href="dashboard()">Dashboard</Link>
            </Button>
          </template>
          <template v-else>
            <Button asChild variant="ghost" size="sm">
              <Link :href="login()">Sign in</Link>
            </Button>
            <Button v-if="canRegister" asChild size="sm">
              <Link :href="register()">Create account</Link>
            </Button>
          </template>
        </nav>
      </div>
    </header>

    <!-- Hero -->
    <section class="mx-auto max-w-6xl px-6 py-12 lg:py-16">
      <div class="grid items-center gap-10 lg:grid-cols-2">
        <div class="space-y-6">
          <Badge class="w-fit" variant="outline">
            <Icon name="sparkles" /> New: Enhanced search and chat
          </Badge>
          <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Ask your documents anything</h1>
          <p class="max-w-prose text-muted-foreground">
            Upload files, search semantically, and get conversational answers with citations — all in one place.
          </p>
          <div class="flex flex-wrap gap-3">
            <template v-if="$page.props.auth.user">
              <Button asChild size="lg">
                <Link :href="dashboard()">Go to Dashboard</Link>
              </Button>
              <Button asChild variant="outline" size="lg">
                <Link :href="dashboard()">Quick Start</Link>
              </Button>
            </template>
            <template v-else>
              <Button v-if="canRegister" asChild size="lg">
                <Link :href="register()">Get started</Link>
              </Button>
              <Button asChild variant="outline" size="lg">
                <Link :href="login()">Sign in</Link>
              </Button>
            </template>
          </div>
        </div>
        <div>
          <!-- Feature summary card -->
          <Card class="border-border/70 shadow-sm">
            <CardHeader>
              <CardTitle class="text-base">What you can do</CardTitle>
              <CardDescription>Three ways to work smarter with your files.</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid gap-4">
                <div class="flex items-start gap-3">
                  <span class="mt-0.5 inline-flex size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon name="fileText" />
                  </span>
                  <div>
                    <div class="font-medium">Upload & organize</div>
                    <p class="text-sm text-muted-foreground">Drag-and-drop PDFs and docs into neat, searchable collections.</p>
                  </div>
                </div>
                <div class="flex items-start gap-3">
                  <span class="mt-0.5 inline-flex size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon name="search" />
                  </span>
                  <div>
                    <div class="font-medium">Semantic search</div>
                    <p class="text-sm text-muted-foreground">Find passages by meaning, not just keywords.</p>
                  </div>
                </div>
                <div class="flex items-start gap-3">
                  <span class="mt-0.5 inline-flex size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon name="messageSquare" />
                  </span>
                  <div>
                    <div class="font-medium">Chat with sources</div>
                    <p class="text-sm text-muted-foreground">Ask questions and get cited answers you can trust.</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </section>

    <!-- Features grid -->
    <section class="mx-auto max-w-6xl px-6 pb-16">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card class="shadow-sm">
          <CardContent class="pt-6">
            <div class="mb-3 inline-flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Icon name="uploadCloud" />
            </div>
            <div class="font-medium">Effortless uploads</div>
            <p class="mt-1 text-sm text-muted-foreground">Drag-and-drop, multi-file support, and tidy collections.</p>
          </CardContent>
        </Card>
        <Card class="shadow-sm">
          <CardContent class="pt-6">
            <div class="mb-3 inline-flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Icon name="sparkles" />
            </div>
            <div class="font-medium">Smart results</div>
            <p class="mt-1 text-sm text-muted-foreground">See the most relevant passages instantly.</p>
          </CardContent>
        </Card>
        <Card class="shadow-sm">
          <CardContent class="pt-6">
            <div class="mb-3 inline-flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Icon name="quote" />
            </div>
            <div class="font-medium">Cited answers</div>
            <p class="mt-1 text-sm text-muted-foreground">Every response links back to original sources.</p>
          </CardContent>
        </Card>
        <Card class="shadow-sm">
          <CardContent class="pt-6">
            <div class="mb-3 inline-flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Icon name="shieldCheck" />
            </div>
            <div class="font-medium">Privacy first</div>
            <p class="mt-1 text-sm text-muted-foreground">Your data stays secure with fine-grained access.</p>
          </CardContent>
        </Card>
      </div>
    </section>

    <Separator />

    <!-- Footer -->
    <footer class="mt-auto px-6 py-8">
      <div class="mx-auto max-w-6xl text-center text-xs text-muted-foreground">
        &copy; {{ new Date().getFullYear() }} - InterroDocs - Bachelor's Thesis Project
      </div>
    </footer>
  </div>
</template>
