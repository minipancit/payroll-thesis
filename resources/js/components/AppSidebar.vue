<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, BookUser, CalendarClock, ChartArea, Folder, HandCoins, LayoutGrid, MapPin, Users2 } from 'lucide-vue-next';

import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes/admin';
import {
    index as eventIndex
} from '@/routes/admin/event'
import {
    index as dtrIndex
} from '@/routes/admin/dtr'
import {
    index as payrollIndex
} from '@/routes/admin/payroll'
import {
    index as userIndex
} from '@/routes/admin/user'
import { type NavItem } from '@/types';

import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Events',
        href: eventIndex(),
        icon: MapPin,
    },
    {
        title: 'DTR',
        href: dtrIndex(),
        icon: CalendarClock,
    },
    {
        title: 'Users',
        href: userIndex(),
        icon: Users2,
    },
];

const footerNavItems: NavItem[] = [
];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
