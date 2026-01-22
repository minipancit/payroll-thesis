<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import {  ChevronDownIcon, Circle, CircleCheck, Loader2, PenBox, Plus, Search, Shield, ShieldAlertIcon, Trash, Trash2, View, Warehouse } from 'lucide-vue-next';
import moduleRoute from '@/routes/admin/payroll'
import { Ref, ref, watch } from 'vue';
import { Paginate } from '@/types/payroll';
import { toast } from 'vue-sonner';

import { RangeCalendar } from '@/components/ui/range-calendar'

import type { DateValue } from '@internationalized/date'
import { getLocalTimeZone, today } from '@internationalized/date'
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
import Label from '@/components/ui/label/Label.vue';
import { Checkbox } from '@/components/ui/checkbox'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Calendar } from '@/components/ui/calendar'
import { DateRange } from 'reka-ui';
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

const start = today(getLocalTimeZone())
const end = start.add({ days: 7 })
const dateRange = ref({
  start,
  end,
}) as Ref<DateRange>
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

watch(dateRange, (value) => {
    let startDate = value.start 
    let endDate = value.end 

    if (timeout) clearTimeout(timeout)
    timeout = window.setTimeout(() => {
        isSearching.value = true
        router.get(
            moduleRoute.index().url,
            { 
                search: '',
                start: startDate?.toString(),
                endDate: endDate?.toString()
            },
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
        page-title="Process Payroll"
        >
        <template #action>
            <Link class="text-sm underline">
                Payroll Register
            </Link>
        </template>
        <template #content>
            
            <!-- <InputGroup class="w-100">
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
            </InputGroup> -->
        <div class="flex flex-col gap-3">
            <Label for="date" class="px-1">
                Payroll Date Range
            </Label>
            <Popover v-slot="{ close }">
            <PopoverTrigger as-child>
                <Button
                id="date"
                variant="outline"
                class="w-60 justify-between font-normal"
                >
                {{ dateRange ? `${dateRange.start} - ${dateRange?.end ?? ''}` : "Select date" }}
                <ChevronDownIcon />
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto overflow-hidden p-0" align="start">
                <!-- <Calendar
                    :model-value="date"
                    layout="month-and-year"
                    @update:model-value="(value) => {
                        if (value) {
                        date = value
                        close()
                        }
                    }"
                /> -->
                <RangeCalendar
                    v-model="dateRange"
                    class="rounded-md border shadow-sm"
                    :number-of-months="2"
                    disable-days-outside-current-view
                />
            </PopoverContent>
            </Popover>
        </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead rowspan="2" class="w-[60px]">
                            <Checkbox />
                        </TableHead>
                        <TableHead rowspan="2" class="w-[150px]">Employee ID</TableHead>
                        <TableHead rowspan="2">Employee</TableHead>
                        <TableHead rowspan="2" class="w-[200px]">Type</TableHead>
                        <TableHead rowspan="2" class="w-[100px]">Basic</TableHead>
                        <TableHead rowspan="2" class="w-[100px]">Daily Rate</TableHead>
                        <TableHead colspan="3" class="text-center bg-sky-500/30">Attendance</TableHead>
                        <TableHead colspan="9" class="text-center bg-emerald-700/20">Payroll (Amount)</TableHead>
                    </TableRow>
                    <TableRow>
                        <TableHead class="w-[50px] bg-sky-500/30">Days</TableHead>
                        <TableHead class="w-[50px] bg-sky-500/30 text-balance">Late</TableHead>
                        <TableHead class="w-[50px] bg-sky-500/30 whitespace-normal break-words text-center">Overtime (x1.3)</TableHead>

                        
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">Basic</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">Late</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">Overtime</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/60 font-bold">Gross</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">SSS</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">PhilHealth</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">Pagibig</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/20">W-Tax</TableHead>
                        <TableHead class="w-[80px] text-center bg-emerald-700/80 font-bold">Net Pay</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="mod in modules.data" :key="mod.id">
                        <TableCell >
                            <Checkbox />
                        </TableCell>
                        <TableCell >
                            {{ mod.employee_id }}
                        </TableCell>
                        <TableCell >
                            {{ mod.name }}
                        </TableCell>
                        <TableCell class="capitalize">
                            {{ mod.pay_frequency}}
                        </TableCell>
                        <TableCell>
                            {{ mod.basic_salary}}
                        </TableCell>
                        <TableCell>
                            {{ mod.daily_rate}}
                        </TableCell>
                        <TableCell class="text-center bg-sky-500/30">8</TableCell>
                        <TableCell class="text-center bg-sky-500/30">4</TableCell>
                        <TableCell class="text-center bg-sky-500/30">30</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">3,2000</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">6.68</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">65.13</TableCell>
                        <TableCell class="text-center bg-emerald-700/60 font-bold">3,258.45</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">300</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">163</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">0</TableCell>
                        <TableCell class="text-center bg-emerald-700/20">0</TableCell>
                        <TableCell class="text-center bg-emerald-700/80 font-bold">2,795.45</TableCell>
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
