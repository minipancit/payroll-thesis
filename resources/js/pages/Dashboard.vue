<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';

interface EventSummary {
    id: number;
    name: string;
    formatted_date: string;
    formatted_time_range: string;
    status: string;
}

interface TimeLogSummary {
    id: number;
    user_name?: string;
    event_name?: string;
    type: string;
    time?: string;
    date?: string;
}

const props = defineProps<{
    eventCounts: {
        total: number;
        today: number;
        upcoming: number;
        active: number;
    };
    eventsToday: EventSummary[];
    upcomingEvents: EventSummary[];
    activeLogs: Array<{
        id: number;
        user_name?: string;
        event_name?: string;
        time_in?: string;
        created_at?: string;
    }>;
    recentLogs: TimeLogSummary[];
    dtrSummary: {
        total_records: number;
        completed: number;
        late: number;
        absent: number;
        today: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" page-title="Admin Dashboard">
        <template #content>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Events</p>
                        <h3 class="mt-2 text-3xl font-semibold">{{ props.eventCounts.total }}</h3>
                    </div>
                    <span class="rounded-full bg-sky-500/10 px-3 py-1 text-sm text-sky-700">Today {{ props.eventCounts.today }}</span>
                </div>
                <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span>Upcoming</span>
                        <span>{{ props.eventCounts.upcoming }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Active</span>
                        <span>{{ props.eventCounts.active }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Daily time records</p>
                        <h3 class="mt-2 text-3xl font-semibold">{{ props.dtrSummary.total_records }}</h3>
                    </div>
                    <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-sm text-emerald-700">Completed {{ props.dtrSummary.completed }}</span>
                </div>
                <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span>Late</span>
                        <span>{{ props.dtrSummary.late }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Absent</span>
                        <span>{{ props.dtrSummary.absent }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm uppercase tracking-[0.16em] text-muted-foreground">Active time logs</p>
                        <h3 class="mt-2 text-3xl font-semibold">{{ props.activeLogs.length }}</h3>
                    </div>
                    <span class="rounded-full bg-amber-500/10 px-3 py-1 text-sm text-amber-700">Live</span>
                </div>
                <div class="mt-4 space-y-2 text-sm text-muted-foreground">
                    <template v-if="props.activeLogs.length">
                        <div v-for="log in props.activeLogs.slice(0, 3)" :key="log.id" class="rounded-lg bg-muted p-3">
                            <p class="font-medium">{{ log.user_name || 'Unknown user' }}</p>
                            <p>{{ log.event_name || 'No event' }} · {{ log.time_in || '-' }}</p>
                        </div>
                    </template>
                    <p v-else>No active time-ins at the moment.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Today's events</h2>
                    <span class="text-sm text-muted-foreground">{{ props.eventsToday.length }} items</span>
                </div>
                <div class="space-y-3">
                    <template v-if="props.eventsToday.length">
                        <div v-for="event in props.eventsToday" :key="event.id" class="rounded-xl border border-border p-4">
                            <p class="font-semibold">{{ event.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ event.formatted_date }} · {{ event.formatted_time_range }}</p>
                            <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ event.status }}</span>
                        </div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">No scheduled events today.</p>
                </div>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Recent time logs</h2>
                    <span class="text-sm text-muted-foreground">Last 8</span>
                </div>
                <div class="space-y-2 text-sm text-muted-foreground">
                    <template v-if="props.recentLogs.length">
                        <div v-for="log in props.recentLogs" :key="log.id" class="rounded-xl border border-border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium">{{ log.user_name || 'Unknown' }}</p>
                                <span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ log.type }}</span>
                            </div>
                            <p>{{ log.event_name || 'No event' }} · {{ log.date }} {{ log.time }}</p>
                        </div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">No recent logs yet.</p>
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Upcoming events</h2>
                <span class="text-sm text-muted-foreground">{{ props.upcomingEvents.length }} entries</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <template v-if="props.upcomingEvents.length">
                    <div v-for="event in props.upcomingEvents" :key="event.id" class="rounded-xl border border-border p-4">
                        <p class="font-semibold">{{ event.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ event.formatted_date }}</p>
                        <p class="text-sm text-muted-foreground">{{ event.formatted_time_range }}</p>
                        <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ event.status }}</span>
                    </div>
                </template>
                <p v-else class="text-sm text-muted-foreground">No upcoming events scheduled.</p>
            </div>
        </section>
        </template>
    </AppLayout>
</template>
