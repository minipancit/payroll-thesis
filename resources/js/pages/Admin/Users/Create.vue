<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index,
    store
} from '@/routes/admin/user'
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, InfoIcon, Layers, Layers2, Save } from 'lucide-vue-next';
import { ref } from 'vue';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@/components/ui/input-group'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Textarea } from '@/components/ui/textarea';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import InputError from '@/components/InputError.vue';
import { email } from '@/routes/password';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Category',
        href: index().url,
    },
    {
        title: 'Add Category',
        href: index().url,
    },
];

const successState = ref(false)
const handleState = () => {
    successState.value = true
}


const form = useForm({
    first_name : "",
    last_name : "",
    middle_name : "",
    email : "",
    phone : ""
})



const submit = () => {
    form.transform((data)=> ({
        ...data,
    })).post(store().url,{

    })
}
</script>

<template>
    <Head title="Add User" />

    <AppLayout :breadcrumbs="breadcrumbs"
        page-title="Add User"
        page-description="Quickly add new users to your system by entering their details such as name, email, role, and permissions."
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
                            <Label for="product_name">First Name</Label>
                            <Input id="product_name" v-model="form.first_name"  type="text" placeholder="Enter your first name here..." />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="product_name">Last Name</Label>
                            <Input id="product_name" v-model="form.last_name"  type="text" placeholder="Enter your last name here..." />
                            <InputError :message="form.errors.last_name" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="product_name">Middle Name</Label>
                            <Input id="product_name" v-model="form.middle_name"  type="text" placeholder="Enter your middle name here..." />
                            <InputError :message="form.errors.middle_name" />
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="product_name">Email Address</Label>
                            <Input id="product_name" v-model="form.email"  type="email" placeholder="Enter your email address here..." />
                            <InputError :message="form.errors.email" /> 
                        </div>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="product_name">Contact Number</Label>
                            <Input id="product_name" v-model="form.phone"  type="text" placeholder="Enter your contact number here..." />
                            <InputError :message="form.errors.phone" />
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
                        <Save v-else />
                        Save User
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
