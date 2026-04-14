<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import dtrRoute from '@/routes/admin/dtr';
import { type BreadcrumbItem } from '@/types';
import { ref, watch } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table/';
import { type Paginate } from '@/types/payroll';

interface DtrRecord {
    id: number;
    user: {
        id?: number;
        name?: string;
        employee_id?: string | null;
    };
    event: {
        id?: number;
        name?: string;
        formatted_date?: string;
        formatted_time_range?: string;
    } | null;
    log_date?: string;
    scheduled_time_in?: string;
    scheduled_time_out?: string;
    actual_time_in?: string;
    actual_time_out?: string;
    total_hours?: number;
    late_minutes?: number;
    late_formatted?: string;
    overtime_minutes?: number;
    overtime_formatted?: string;
    undertime_minutes?: number;
    undertime_formatted?: string;
    status?: string;
    remarks?: string;
}

const props = defineProps<{
    dtrs: Paginate & { data: DtrRecord[] };
    filters: {
        start?: string;
        end?: string;
    };
    summary: {
        total_records: number;
        total_hours: number;
        late_count: number;
        absence_count: number;
        completed_count: number;
        overtime_count: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DTR',
        href: dtrRoute.index().url,
    },
];

const filters = ref({
    start: props.filters.start ?? new Date().toISOString().slice(0, 10),
    end: props.filters.end ?? new Date().toISOString().slice(0, 10),
});

const isSearching = ref(false);
let timeout: number | null = null;

watch(
    filters,
    (value) => {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = window.setTimeout(() => {
            isSearching.value = true;
            router.get(
                dtrRoute.index().url,
                {
                    start: value.start,
                    end: value.end,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onFinish: () => {
                        isSearching.value = false;
                    },
                }
            );
        }, 350);
    },
    { deep: true }
);
</script>

<template>
    <Head title="DTR" />

    <AppLayout :breadcrumbs="breadcrumbs" page-title="Daily Time Records">
        <template #action>
            <a
                :href="dtrRoute.export({ query: { start: filters.start, end: filters.end } }).url"
                class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export to Excel
            </a>
        </template>
        <template #content>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 bg-background p-4">
                    <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Records</p>
                    <p class="mt-2 text-3xl font-semibold">{{ props.summary.total_records }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background p-4">
                    <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Hours completed</p>
                    <p class="mt-2 text-3xl font-semibold">{{ props.summary.total_hours }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background p-4">
                    <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Late / Absent</p>
                    <div class="mt-2 flex items-center gap-4">
                        <span class="rounded-full bg-amber-500/10 px-3 py-1 text-sm text-amber-700">Late {{ props.summary.late_count }}</span>
                        <span class="rounded-full bg-destructive/10 px-3 py-1 text-sm text-destructive">Absent {{ props.summary.absence_count }}</span>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block rounded-xl border border-sidebar-border/70 bg-background p-4">
                    <span class="text-sm text-muted-foreground">Start date</span>
                    <input
                        type="date"
                        class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        v-model="filters.start"
                    />
                </label>
                <label class="block rounded-xl border border-sidebar-border/70 bg-background p-4">
                    <span class="text-sm text-muted-foreground">End date</span>
                    <input
                        type="date"
                        class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        v-model="filters.end"
                    />
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background p-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Time logs</h2>
                        <p class="text-sm text-muted-foreground">
                            Showing records from {{ filters.start }} to {{ filters.end }}.
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">
                        {{ isSearching ? 'Refreshing...' : 'Updated' }}
                    </span>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Employee</TableHead>
                            <TableHead>Event</TableHead>
                            <TableHead>Schedule</TableHead>
                            <TableHead>Actual In/Out</TableHead>
                            <TableHead>Total Hours</TableHead>
                            <TableHead>Late</TableHead>
                            <TableHead>OT / UT</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="record in props.dtrs.data" :key="record.id">
                            <TableCell>{{ record.log_date }}</TableCell>
                            <TableCell>{{ record.user.name || 'Unknown' }}</TableCell>
                            <TableCell>
                                <div class="font-medium">{{ record.event?.name || 'No event' }}</div>
                                <div class="text-sm text-muted-foreground">{{ record.event?.formatted_date }}</div>
                            </TableCell>
                            <TableCell>
                                {{ record.scheduled_time_in || '-' }} - {{ record.scheduled_time_out || '-' }}
                            </TableCell>
                            <TableCell>
                                {{ record.actual_time_in || '-' }} / {{ record.actual_time_out || '-' }}
                            </TableCell>
                            <TableCell>{{ record.total_hours ?? 0 }}</TableCell>
                            <TableCell>{{ record.late_formatted || 'On Time' }}</TableCell>
                            <TableCell>
                                <div>{{ record.overtime_formatted || 'No OT' }}</div>
                                <div>{{ record.undertime_formatted || 'Complete' }}</div>
                            </TableCell>
                            <TableCell>{{ record.status || 'Unknown' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div
                    v-if="props.dtrs.links.length > 1"
                    class="mt-6 flex flex-wrap items-center justify-center gap-2"
                >
                    <template v-for="link in props.dtrs.links" :key="link.label">
                        <button
                            v-if="link.url"
                            @click="router.visit(link.url, { preserveState: true, preserveScroll: true })"
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
            </div>
        </template>
    </AppLayout>
</template>
