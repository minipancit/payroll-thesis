<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import { Cog, Edit, Edit2, Info, Loader2, PenBox, Plus, Search, Trash, Trash2, View, Warehouse } from 'lucide-vue-next';
import eventRoute from '@/routes/admin/event'
import { ref, watch } from 'vue';
import { Paginate } from '@/types/payroll';
import { toast } from 'vue-sonner';

import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

import InputGroup from '@/components/ui/input-group/InputGroup.vue';
import InputGroupInput from '@/components/ui/input-group/InputGroupInput.vue';
import InputGroupAddon from '@/components/ui/input-group/InputGroupAddon.vue';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table/'
import { Button } from '@/components/ui/button';
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Events',
        href: eventRoute.index().url,
    },
];
interface PropsInterface {
    events: Paginate
    filters: {
        search?: string
    }
}

const page = usePage()
const props = defineProps<PropsInterface>()
const search = ref(props.filters.search || '')
const isSearching = ref(false)
let timeout: number | null = null

watch(search, (value) => {
    if (timeout) clearTimeout(timeout)

    timeout = window.setTimeout(() => {
        isSearching.value = true
        router.get(
            eventRoute.index().url,
            { search: value },
            {
                preserveState: true,
                replace: true,
                onFinish: () => {
                    isSearching.value = false
                },
            }
        )
    }, 400)
})

</script>

<template>
    <Head title="Events" />

    <AppLayout :breadcrumbs="breadcrumbs"
        page-title="Manage Events"
        >
        <template #action>
                <Link :href="eventRoute.create().url" as-child >
                    <Button variant="outline" size="sm">
                            <Plus />
                            Create new Event
                    </Button>
                </Link>
        </template>
        <template #content>
            
            <InputGroup class="w-100">
                <InputGroupInput 
                    v-model="search"
                    placeholder="Search event..." />
                <InputGroupAddon>
                    <Loader2
                        v-if="isSearching"
                        class="h-4 w-4 animate-spin"
                    />
                    <Search v-else />
                </InputGroupAddon>
            </InputGroup>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Address</TableHead>
                        <TableHead>Schedule</TableHead>
                        <TableHead class="text-right">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="event in events.data" :key="event.id">
                        <TableCell>{{ event.name }}</TableCell>
                        <TableCell class="">
                            {{ event.address }}<br />
                            <span class="text-muted-foreground">
                                Latitude: {{ event.latitude }}<br>
                                Longitude: {{ event.longitude }}
                            </span>
                        </TableCell>
                        <TableCell class="">
                            {{ event.formatted_date }}<br />
                            <span class="text-muted-foreground">
                                {{ event.formatted_time_range }}
                            </span>
                        </TableCell>
                        <TableCell class="flex items-center text-right justify-center gap-x-3">
                            <Link :href="eventRoute.edit(event.id)">
                                <PenBox :size="16"  />
                            </Link>
                            <Link method="delete" :href="eventRoute.destroy(event.id)"  
                                v-on:success="()=>{
                                    toast.info(page.flash.header, {
                                        description: page.flash.message,
                                    })
                                }">
                                <Trash2 :size="16"  />
                            </Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div
                v-if="events.links.length > 1"
                class="mt-6 flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="link in events.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.visit(link.url, {
                            preserveState: true,
                            preserveScroll: true
                        })"
                        v-html="link.label"
                        class="px-3 py-1 rounded-md text-sm transition"
                        :class="[
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/70'
                        ]"
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 text-sm text-muted-foreground"
                    />
                </template>
            </div>
        </template>
    </AppLayout>
</template>
