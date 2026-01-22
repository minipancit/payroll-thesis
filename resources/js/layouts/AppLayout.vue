<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

import 'vue-sonner/style.css'
import { Toaster } from '@/components/ui/sonner'
interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    pageTitle?: string;
    pageDescription?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster />
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div v-if="pageTitle" class="flex w-full flex-col justify-start gap-6">
                <div class="flex flex-col gap-2">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-start justify-between">
                            <h1 class="scroll-m-20 text-3xl font-semibold tracking-tight sm:text-2xl xl:text-3xl">{{ pageTitle }}</h1>
                            <slot name="action"></slot>
                        </div>
                        <p class="text-muted-foreground text-balance text-sm sm:text-base">{{ pageDescription }}</p>
                    </div>
                </div>
                <hr>
            </div>
            <slot name="content" />
        </div>
    </AppLayout>
</template>
