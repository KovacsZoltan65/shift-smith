<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { Button, ConfirmDialog, Toast, Toolbar } from "primevue";

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();

const props = defineProps({
    title: String,
    filter: Object,
});
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <AuthenticatedLayout>
        <div class="card">
            <Toolbar class="md-6">
                <template #start>
                    <Button
                        v-if="has('companies.create')"
                        label="Create"
                        class="mr-2"
                        icon="pi pi-plus"
                    />
                </template>
            </Toolbar>
        </div>
    </AuthenticatedLayout>
</template>
