<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index,
    store
} from '@/routes/admin/event'
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, InfoIcon, Layers, Layers2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@/components/ui/input-group'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Textarea } from '@/components/ui/textarea';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import InputError from '@/components/InputError.vue';

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
    category_name : "",
    category_image : null
})



const submit = () => {
    form.transform((data)=> ({
        ...data,
    })).post(store().url,{

    })
}
</script>

<template>
    <Head title="Add Product" />

    <AppLayout :breadcrumbs="breadcrumbs"
        page-title="Add Product"
        page-description="Quickly add new items to your store by entering product details such as name, description, price, category, and stock levels. Upload images, set variations (like size or color), and publish products to make them available for customers."
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
                            <Label for="product_name">Category Name</Label>
                            <Input id="product_name" v-model="form.category_name"  type="text" placeholder="Enter your category name here..." />
                            <InputError :message="form.errors.category_name" />
                        </div>

                        
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div>
                        <h3 class="text-xl font-semibold mb-5">Category Image</h3>
                        <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                            <Label for="picture">Picture</Label>
                            <Input id="picture"
                                @change="e => form.category_image = e.target.files[0]" 
                                type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"/>
                            <InputError :message="form.errors.category_image" />
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
                        <Layers2 v-else />
                        Create Category
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
