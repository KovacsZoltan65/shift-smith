<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import LeaveTypeService from "@/services/LeaveTypeService.js";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Select from "primevue/select";
import Tag from "primevue/tag";

const props = defineProps({
    title: { type: String, default: "Szabadsag tipusok" },
    filter: { type: Object, default: () => ({}) },
});

const rows = ref([]);
const loading = ref(false);
const category = ref(props.filter?.category ?? null);
const active = ref(props.filter?.active ?? null);

const categoryOptions = [
    { label: "Szabadsag", value: "leave" },
    { label: "Betegszabadsag", value: "sick_leave" },
    { label: "Fizetett tavollet", value: "paid_absence" },
    { label: "Fizetes nelkuli tavollet", value: "unpaid_absence" },
];

const activeOptions = [
    { label: "Aktiv", value: true },
    { label: "Inaktiv", value: false },
];

const fetchRows = async () => {
    loading.value = true;
    try {
        const { data } = await LeaveTypeService.fetch({
            perPage: 100,
            category: category.value ?? undefined,
            active: active.value ?? undefined,
        });

        rows.value = Array.isArray(data?.items) ? data.items : [];
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRows);
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <Button icon="pi pi-refresh" label="Frissites" severity="secondary" :loading="loading" @click="fetchRows" />
            </div>

            <div class="flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <div class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Kategoria</label>
                    <Select
                        v-model="category"
                        :options="categoryOptions"
                        optionLabel="label"
                        optionValue="value"
                        showClear
                        class="w-full"
                        @change="fetchRows"
                    />
                </div>

                <div class="min-w-48">
                    <label class="mb-1 block text-xs text-slate-600">Statusz</label>
                    <Select
                        v-model="active"
                        :options="activeOptions"
                        optionLabel="label"
                        optionValue="value"
                        showClear
                        class="w-full"
                        @change="fetchRows"
                    />
                </div>
            </div>

            <DataTable :value="rows" :loading="loading" dataKey="id" responsiveLayout="scroll">
                <Column field="code" header="Kod" />
                <Column field="name" header="Nev" />
                <Column field="category" header="Kategoria">
                    <template #body="{ data }">
                        <Tag :value="data.category" severity="info" />
                    </template>
                </Column>
                <Column header="Keretet csokkenti">
                    <template #body="{ data }">
                        <Tag :value="data.affects_leave_balance ? 'Igen' : 'Nem'" :severity="data.affects_leave_balance ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="Jovahagyas">
                    <template #body="{ data }">
                        <Tag :value="data.requires_approval ? 'Kotelezo' : 'Nem'" :severity="data.requires_approval ? 'warning' : 'secondary'" />
                    </template>
                </Column>
                <Column header="Aktiv">
                    <template #body="{ data }">
                        <Tag :value="data.active ? 'Aktiv' : 'Inaktiv'" :severity="data.active ? 'success' : 'danger'" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
