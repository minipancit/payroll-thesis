<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import {  Circle, CircleCheck, Loader2, PenBox, Plus, Search, Shield, ShieldAlertIcon, Trash, Trash2, View, Warehouse } from 'lucide-vue-next';
import moduleRoute from '@/routes/admin/user'
import { ref, watch } from 'vue';
import { Paginate } from '@/types/payroll';
import { toast } from 'vue-sonner';


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
        title: 'Users',
        href: moduleRoute.index().url,
    },
];
interface PropsInterface {
    modules: Paginate
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
            moduleRoute.index().url,
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
        page-title="Manage Users"
        >
        <template #action>
                <Link :href="moduleRoute.create().url" as-child >
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
                    placeholder="Search user..." />
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
                        <TableHead>Employee</TableHead>
                        <TableHead>Details</TableHead>
                        <TableHead>Date Hired</TableHead>
                        <TableHead class="w-[80px]">Status</TableHead>
                        <TableHead class="w-[100px]">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="mod in modules.data" :key="mod.id">
                        <TableCell >
                            <div class="flex items-center mb-0"  v-if="mod.is_admin">
                                <div class="flex me-0 items-center font-bold">
                                    <Shield :size="15" color="red"  />
                                    Administrator
                                </div>
                                <br>
                            </div>
                            <span class="font-bold text-orange-500">{{ mod.employee_id }}<br></span>
                            {{ mod.name }}<br>
                            <span class="text-muted-foreground">
                                {{ mod.email }}<br>
                                {{ mod.phone }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span class="capitalize">{{ mod.employee_type }}<br></span>
                            <span class="text-muted-foreground">
                                {{ mod.position }}<br>
                                {{ mod.department }}
                            </span>
                        </TableCell>
                        <TableCell>{{ mod.formatted_hire_date }}</TableCell>
                        <TableCell>
                            <CircleCheck color="green" v-if="mod.is_active" :size="20" />
                            <Circle color="red" v-else :size="20" />
                        </TableCell>
                        <!-- <TableCell class="">
                            {{ event.address }}<br />
                            <span class="text-muted-foreground">
                                Latitude: {{ event.latitude }}<br>
                                Longitude: {{ event.longitude }}
                            </span>
                        </TableCell> -->
                        <!-- <TableCell class="">
                            {{ event.formatted_date }}<br />
                            <span class="text-muted-foreground">
                                {{ event.formatted_time_range }}
                            </span>
                        </TableCell> -->
                        <TableCell class="justify-center ">
                            <div class="flex items-center   gap-x-3">
                                
                            <Link :href="moduleRoute.edit(mod.id)">
                                <PenBox :size="16"  />
                            </Link>
                            <Link method="delete" :href="moduleRoute.destroy(mod.id)"  
                                v-on:success="()=>{
                                    toast.info(page.flash.header, {
                                        description: page.flash.message,
                                    })
                                }">
                                <Trash2 :size="16"  />
                            </Link>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div
                v-if="modules.links.length > 1"
                class="mt-6 flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="link in modules.links" :key="link.label">
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
