<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { home as dashboard } from '@/routes/dashboard';
import FilePreview from '@/components/FilePreview.vue';
import api from '@/routes/api';

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
  }
];

let selected = {
  url: new URL(api.viewFile.url({ query: { id: page.props.document._id } }), window.location.origin).toString(),
  name: page.props.document.original_name,
};
</script>

<template>
    <Head :title="page.props.document.original_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FilePreview
            :file-url="selected.url"
            :file-name="selected.name"
            :file-id="page.props.document._id"
        />
    </AppLayout>
</template>
