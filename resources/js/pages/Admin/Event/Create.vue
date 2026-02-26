<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index,
    store
} from '@/routes/admin/event'
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, InfoIcon, Layers, Layers2, CalendarIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@/components/ui/input-group'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Textarea } from '@/components/ui/textarea';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import InputError from '@/components/InputError.vue';
import MapPicker from '@/components/MapPicker.vue';
import Calendar from '@/components/ui/calendar/Calendar.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Event',
        href: index().url,
    },
    {
        title: 'Add Event',
        href: index().url,
    },
];

const successState = ref(false)
const handleState = () => {
    successState.value = true
}


const form = useForm({
    name : "",
    address: "",
    event_date : "",
    start_time : "",
    end_time : "",
    description : "",
    event_image : null,
    lat : 14.5995,
    lng : 120.9842
})



const submit = () => {
    form.transform((data)=> ({
        ...data,
    })).post(store().url,{

    })
}
</script>

<template>
    <Head title="Add Event" />

    <AppLayout :breadcrumbs="breadcrumbs"
        page-title="Add Event"
        page-description=""
    >
        <template #action>
        </template>
        <template #content>
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-8"
            >
                    <!-- LEFT COLUMN -->
                    <div>
                        <h3 class="text-xl font-semibold mb-5">Basic Information</h3>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="product_name">Event Name</Label>
                            <Input id="product_name" v-model="form.name"  type="text" placeholder="Enter your event name here..." />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="address">Address</Label>
                            <Input id="address" v-model="form.address"  type="text" placeholder="Enter address here..." />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="event_date">Event Date</Label>
                            <Input id="event_date" v-model="form.event_date"  type="date" />
                            <InputError :message="form.errors.event_date" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="start_time">Start Time</Label>
                            <Input id="start_time" v-model="form.start_time"  type="time" />
                            <InputError :message="form.errors.start_time" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="end_time">End Time</Label>
                            <Input id="end_time" v-model="form.end_time"  type="time" />
                            <InputError :message="form.errors.end_time" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model.trim.lazy="form.description"/>
                            <InputError :message="form.errors.description" />
                        </div>


                        
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div>
                        <h3 class="text-xl font-semibold mb-5">Event Image</h3>
                        <MapPicker
                            v-model:lat="form.lat"
                            v-model:lng="form.lng"
                            />
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="picture">Picture</Label>
                            <Input id="picture"
                                @change="e => form.event_image = e.target.files[0]" 
                                type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"/>
                            <InputError :message="form.errors.event_image" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <img :src="''" />
                        </div>
                    </div>
                </div>
                <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                    <Button
                        type="submit"
                        class="mt-4 w-full"
                        :tabindex="4"
                        :disabled="form.processing"
                        data-test="login-button"
                        @click="submit"
                    >
                        <Spinner v-if="form.processing" />
                        <CalendarIcon v-else />
                        Create Event
                    </Button>
                    <div 
                        v-show="successState">
                        <p
                            class="text-md text-green-600"
                        >
                            Saved. 
                        </p>
                    </div>
                </div>
        </template>
    </AppLayout>
</template>
