<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home as dashboard } from '@/routes/dashboard';
import { index as favorites_index } from '@/routes/favorites-documents';
import { index as interrogations_index } from '@/routes/interrogations';
import { index as recent_files_index } from '@/routes/recent-documents';
import { index as trash_index } from '@/routes/trash';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, File, LayoutGrid, Star, Trash } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'AI Interrogations',
        href: interrogations_index(),
        icon: BookOpen,
    },
    {
        title: 'Recent files',
        href: recent_files_index(),
        icon: File,
    },
    {
        title: 'Favorites',
        href: favorites_index(),
        icon: Star,
    },
    {
        title: 'Trash',
        href: trash_index(),
        icon: Trash,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
