<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {  login, logout, timeIn, timeOut } from '@/routes';
import {
    dashboard
} from '@/routes/admin'
interface Event {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    formatted_date: string;
    formatted_time_range: string;
    status: string;
    is_active: boolean;
    is_past: boolean;
    is_future: boolean;
    is_today?: boolean;
    days_until?: number;
    start_time?: string;
    end_time?: string;
}

interface Props {
    canRegister: boolean;
    events?: Array<Event>;
    upcomingEvents?: Array<Event>;
    activeLog?: {
        id: number;
        event_id: number;
        time_in: string;
        event: {
            id: number;
            name: string;
            start_time?: string;
            end_time?: string;
        };
    } | null;
}

const props = defineProps<Props>();

const loading = ref(false);
const message = ref<{type: 'success' | 'error' | 'info', text: string} | null>(null);
const currentTime = ref(new Date());
let timerInterval: number | null = null;

// Update current time every second for real-time updates
onMounted(() => {
    timerInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
});

// Clean up interval on component unmount
onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});

const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
});

const formattedDate = computed(() => {
    return currentTime.value.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
});

const getCurrentLocation = (): Promise<GeolocationPosition> => {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by your browser'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            resolve,
            (error) => {
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        reject(new Error('Please enable location services to time in/out'));
                        break;
                    case error.POSITION_UNAVAILABLE:
                        reject(new Error('Location information is unavailable'));
                        break;
                    case error.TIMEOUT:
                        reject(new Error('Location request timed out'));
                        break;
                    default:
                        reject(new Error('An unknown error occurred'));
                        break;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });
};

const handleTimeIn = async (eventId: number) => {
    if (loading.value) return;
    
    loading.value = true;
    message.value = null;
    
    try {
        const position = await getCurrentLocation();
        
        router.post(timeIn(eventId), {
            event_id: eventId,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude
        }, {
            preserveScroll: true,
            onSuccess: () => {
                message.value = {
                    type: 'success',
                    text: 'Time in recorded successfully!'
                };
            },
            onError: (errors) => {
                message.value = {
                    type: 'error',
                    text: errors.message || 'Failed to record time in'
                };
            },
            onFinish: () => {
                loading.value = false;
            }
        });
    } catch (error: any) {
        message.value = {
            type: 'error',
            text: error.message || 'Failed to get location'
        };
        loading.value = false;
    }
};

const handleTimeOut = async () => {
    if (loading.value || !props.activeLog) return;
    
    loading.value = true;
    message.value = null;
    
    try {
        const position = await getCurrentLocation();
        
        router.post(timeOut(props.activeLog.event_id), {
            event_id: props.activeLog.event_id,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude
        }, {
            preserveScroll: true,
            onSuccess: () => {
                message.value = {
                    type: 'success',
                    text: 'Time out recorded successfully!'
                };
            },
            onError: (errors) => {
                message.value = {
                    type: 'error',
                    text: errors.message || 'Failed to record time out'
                };
            },
            onFinish: () => {
                loading.value = false;
            }
        });
    } catch (error: any) {
        message.value = {
            type: 'error',
            text: error.message || 'Failed to get location'
        };
        loading.value = false;
    }
};

const handleLogout = () => {
    router.post(logout().url);
};

// Helper function to calculate duration
const calculateDuration = (timeIn: string) => {
    const start = new Date(timeIn);
    const now = currentTime.value;
    const diffMs = now.getTime() - start.getTime();
    const diffHrs = Math.floor(diffMs / 3600000);
    const diffMins = Math.floor((diffMs % 3600000) / 60000);
    const diffSecs = Math.floor((diffMs % 60000) / 1000);
    
    if (diffHrs > 0) {
        return `${diffHrs}h ${diffMins}m ${diffSecs}s`;
    }
    return `${diffMins}m ${diffSecs}s`;
};

// Calculate time remaining for active event
const calculateTimeRemaining = computed(() => {
    if (!props.activeLog?.event?.end_time) return null;
    
    const end = new Date(props.activeLog.event.end_time);
    const now = currentTime.value;
    const diffMs = end.getTime() - now.getTime();
    
    if (diffMs <= 0) return 'Event ended';
    
    const diffHrs = Math.floor(diffMs / 3600000);
    const diffMins = Math.floor((diffMs % 3600000) / 60000);
    const diffSecs = Math.floor((diffMs % 60000) / 1000);
    
    return `${diffHrs}h ${diffMins}m ${diffSecs}s`;
});

// Get current active event from today's events
const currentActiveEvent = computed(() => {
    if (!props.events) return null;
    return props.events.find(event => event.is_active);
});

// Categorize today's events
const categorizedEvents = computed(() => {
    if (!props.events) return { active: [], upcoming: [] };
    
    return {
        active: props.events.filter(event => event.is_active),
        upcoming: props.events.filter(event => !event.is_active && !event.is_past)
    };
});

// Get status badge class
const getStatusBadgeClass = (event: Event) => {
    if (event.is_active) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    } else if (event.is_past) {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    } else if (event.is_future) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    }
    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
};

// Get status text
const getStatusText = (event: Event) => {
    if (event.is_active) {
        return 'Active Now';
    } else if (event.is_past) {
        return 'Completed';
    } else if (event.is_future) {
        return 'Upcoming';
    }
    return 'Scheduled';
};

// Get days until text
const getDaysUntilText = (event: Event) => {
    if (!event.days_until && event.days_until !== 0) return '';
    
    if (event.days_until === 0) {
        return 'Today';
    } else if (event.days_until === 1) {
        return 'Tomorrow';
    } else {
        return `In ${event.days_until} days`;
    }
};

// Format time nicely
const formatTime = (timeString: string) => {
    return new Date(timeString).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
};

// Show location status
const getLocationStatus = (event: Event) => {
    if (event.latitude && event.longitude) {
        return {
            text: event.address || 'Location set',
            class: 'text-green-600 dark:text-green-400',
            icon: 'text-green-500'
        };
    }
    return {
        text: 'Location not set',
        class: 'text-yellow-600 dark:text-yellow-400',
        icon: 'text-yellow-500'
    };
};
</script>

<template>
    <Head title="Time Clock">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                Time Clock System
                            </h1>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Current Time Display -->
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ formattedTime }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formattedDate }}
                            </div>
                        </div>

                        <template v-if="$page.props.auth.user">
                            <!-- Admin Dashboard Link -->
                            <Link
                                v-if="$page.props.auth.user.is_admin"
                                :href="dashboard()"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Admin Dashboard
                            </Link>
                            
                            <!-- Logout Button -->
                            <button
                                @click="handleLogout"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Logout
                            </button>
                        </template>
                        
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Log in
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div v-if="$page.props.auth.user" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Time Clock & Events -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Welcome Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 flex items-center justify-center">
                                        <span class="text-white font-bold text-lg">
                                            {{ $page.props.auth.user.name.charAt(0) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                        Welcome back, {{ $page.props.auth.user.name }}!
                                    </h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Ready to clock in for your shift?
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="mt-6 grid grid-cols-3 gap-4">
                                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ $page.props.auth.user.employee_id || 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Employee ID
                                    </div>
                                </div>
                                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ $page.props.auth.user.department || 'General' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Department
                                    </div>
                                </div>
                                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ $page.props.auth.user.is_admin ? 'Admin' : 'Employee' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Role
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Event Status Card -->
                    <div v-if="currentActiveEvent" class="bg-gradient-to-r from-green-500 to-emerald-600 dark:from-green-600 dark:to-emerald-700 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-white flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-white mr-2 animate-pulse"></span>
                                        Currently Active Event
                                    </h3>
                                    <div class="mt-4 space-y-2">
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-100 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <span class="text-green-50 font-medium">Event:</span>
                                            <span class="ml-2 text-lg font-bold text-white">{{ currentActiveEvent.name }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-100 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-green-50 font-medium">Time:</span>
                                            <span class="ml-2 text-white">{{ currentActiveEvent.formatted_time_range }}</span>
                                        </div>
                                        <div v-if="calculateTimeRemaining" class="flex items-center">
                                            <svg class="h-5 w-5 text-green-100 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-green-50 font-medium">Time Remaining:</span>
                                            <span class="ml-2 font-bold text-white">{{ calculateTimeRemaining }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-4xl font-bold text-white mb-2">
                                        {{ calculateDuration(props.activeLog!.time_in) }}
                                    </div>
                                    <div class="text-green-100">
                                        Active Duration
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Time Log Card -->
                    <div v-if="props.activeLog" class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-white">
                                        ⏰ Your Current Time Log
                                    </h3>
                                    <p class="mt-1 text-blue-100">
                                        You're currently clocked in at <strong>{{ props.activeLog.event.name }}</strong>
                                    </p>
                                    <p class="mt-2 text-blue-100">
                                        Started at {{ formatTime(props.activeLog.time_in) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-white mb-2">
                                        {{ calculateDuration(props.activeLog.time_in) }}
                                    </div>
                                    <div class="text-blue-100">
                                        Current Duration
                                    </div>
                                </div>
                            </div>
                            
                            <button
                                @click="handleTimeOut"
                                :disabled="loading"
                                class="mt-4 w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-50 transition-colors"
                            >
                                <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                {{ loading ? 'Processing...' : 'Time Out Now' }}
                            </button>
                        </div>
                    </div>

                    <!-- Today's Events Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        📅 Today's Events
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Clock in for today's scheduled events
                                    </p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                        {{ props.events?.filter(e => e.latitude && e.longitude).length || 0 }} with location
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Active Events -->
                            <div v-if="categorizedEvents.active.length > 0" class="mb-6">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                                    <span class="h-2 w-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                                    Currently Active Events
                                </h4>
                                <div class="space-y-4">
                                    <div
                                        v-for="event in categorizedEvents.active"
                                        :key="'active-' + event.id"
                                        class="group relative rounded-lg border-2 border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/20 p-4 transition-all duration-200 hover:shadow-md"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900 flex items-center justify-center">
                                                            <svg class="h-5 w-5 text-green-600 dark:text-green-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between">
                                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                                {{ event.name }}
                                                            </h4>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                                Active Now
                                                            </span>
                                                        </div>
                                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                                            <div class="flex items-center">
                                                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                <span>{{ event.formatted_time_range }}</span>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                </svg>
                                                                <span :class="getLocationStatus(event).class">
                                                                    {{ getLocationStatus(event).text }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-shrink-0">
                                                <button
                                                    @click="handleTimeIn(event.id)"
                                                    :disabled="loading || !event.latitude || !event.longitude"
                                                    :class="[
                                                        'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors',
                                                        event.latitude && event.longitude
                                                            ? 'text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500' 
                                                            : 'text-gray-400 bg-gray-100 dark:bg-gray-700 cursor-not-allowed'
                                                    ]"
                                                >
                                                    <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    Time In
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Upcoming Events Today -->
                            <div v-if="categorizedEvents.upcoming.length > 0" class="mb-6">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                                    Upcoming Today
                                </h4>
                                <div class="space-y-4">
                                    <div
                                        v-for="event in categorizedEvents.upcoming"
                                        :key="'upcoming-' + event.id"
                                        class="group relative rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-gray-800 p-4 transition-all duration-200 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between">
                                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                                {{ event.name }}
                                                            </h4>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                                {{ event.status }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                                            <div class="flex items-center">
                                                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                <span>{{ event.formatted_time_range }}</span>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                </svg>
                                                                <span :class="getLocationStatus(event).class">
                                                                    {{ getLocationStatus(event).text }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-shrink-0">
                                                <button
                                                    @click="handleTimeIn(event.id)"
                                                    :disabled="loading || !event.latitude || !event.longitude"
                                                    :class="[
                                                        'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors',
                                                        event.latitude && event.longitude
                                                            ? 'text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500' 
                                                            : 'text-gray-400 bg-gray-100 dark:bg-gray-700 cursor-not-allowed'
                                                    ]"
                                                >
                                                    <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    Time In
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- No Events Message -->
                            <div v-if="props.events?.length === 0" class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                    No events today
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    There are no events scheduled for today.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Display -->
                    <div
                        v-if="message"
                        :class="[
                            'rounded-lg shadow p-4 transition-all duration-300',
                            message.type === 'success' 
                                ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' 
                                : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
                        ]"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg v-if="message.type === 'success'" class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p :class="[
                                    'text-sm font-medium',
                                    message.type === 'success' 
                                        ? 'text-green-800 dark:text-green-300' 
                                        : 'text-red-800 dark:text-red-300'
                                ]">
                                    {{ message.text }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Status & Quick Links -->
                <div class="space-y-6">
                    <!-- Today's Status Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    📊 Today's Status
                                </h3>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    Today
                                </span>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Status</span>
                                    <span v-if="props.activeLog" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                        Clocked In
                                    </span>
                                    <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                        Clocked Out
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Current Time</span>
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ formattedTime }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Date</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ formattedDate }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Today's Events</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ props.events?.length || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Events Card -->
                    <div v-if="props.upcomingEvents && props.upcomingEvents.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    📅 Upcoming Events
                                </h3>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                    Next 3 days
                                </span>
                            </div>
                            
                            <div class="space-y-4">
                                <div
                                    v-for="event in props.upcomingEvents"
                                    :key="'upcoming-' + event.id"
                                    :class="[
                                        'group relative rounded-lg border p-4 transition-all duration-200 hover:shadow-md',
                                        event.is_today ? 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20' :
                                        'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700'
                                    ]"
                                >
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <div :class="[
                                                'h-10 w-10 rounded-lg flex items-center justify-center',
                                                event.is_today ? 'bg-blue-100 dark:bg-blue-900' :
                                                event.days_until === 1 ? 'bg-purple-100 dark:bg-purple-900' :
                                                'bg-gray-100 dark:bg-gray-700'
                                            ]">
                                                <svg :class="[
                                                    'h-5 w-5',
                                                    event.is_today ? 'text-blue-600 dark:text-blue-400' :
                                                    event.days_until === 1 ? 'text-purple-600 dark:text-purple-400' :
                                                    'text-gray-600 dark:text-gray-400'
                                                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4 flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {{ event.name }}
                                                </h4>
                                                <span :class="[
                                                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                    getStatusBadgeClass(event)
                                                ]">
                                                    {{ getDaysUntilText(event) || getStatusText(event) }}
                                                </span>
                                            </div>
                                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                                <div class="flex items-center">
                                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <span>{{ event.formatted_date }}</span>
                                                </div>
                                                <div class="flex items-center">
                                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>{{ event.formatted_time_range }}</span>
                                                </div>
                                                <div v-if="event.address" class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                                    <svg class="flex-shrink-0 mr-1.5 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    </svg>
                                                    <span class="truncate">{{ event.address }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                🔗 Quick Links
                            </h3>
                            <div class="space-y-3">
                                <Link
                                    href="/dtr"
                                    class="flex items-center p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            Daily Time Records
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            View your attendance history
                                        </p>
                                    </div>
                                </Link>
                                
                                <Link
                                    href="/payslips"
                                    class="flex items-center p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            Payslips
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Access your salary information
                                        </p>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Public Content for Non-Logged in Users -->
            <div v-else class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="text-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Welcome to Time Clock System
                        </h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                            <p>Please log in to access the time tracking system.</p>
                        </div>
                        <div class="mt-5">
                            <Link
                                :href="login()"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Log in to continue
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>