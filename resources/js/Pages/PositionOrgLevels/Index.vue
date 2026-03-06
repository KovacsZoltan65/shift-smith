<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Checkbox from "primevue/checkbox";
import Dialog from "primevue/dialog";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import PositionOrgLevelService from "@/services/PositionOrgLevelService.js";

const props = defineProps({
    title: { type: String, default: "Position szint mapping" },
    filter: { type: Object, default: () => ({}) },
    org_levels: { type: Array, default: () => [] },
});

const toast = useToast();
const loading = ref(false);
const rows = ref([]);
const editOpen = ref(false);
const editing = ref(null);
const form = ref({
    position_label: "",
    org_level: "staff",
    active: true,
});
const q = ref(props.filter?.q || "");
const orgLevelFilter = ref(props.filter?.org_level || null);

const load = async () => {
    loading.value = true;
    try {
        const { data } = await PositionOrgLevelService.getMappings({
            q: q.value || undefined,
            org_level: orgLevelFilter.value || undefined,
            per_page: 100,
            page: 1,
        });
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editing.value = null;
    form.value = { position_label: "", org_level: "staff", active: true };
    editOpen.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    form.value = {
        position_label: row.position_label,
        org_level: row.org_level,
        active: !!row.active,
    };
    editOpen.value = true;
};

const save = async () => {
    const payload = {
        position_label: form.value.position_label,
        org_level: form.value.org_level,
        active: !!form.value.active,
    };

    if (editing.value?.id) {
        await PositionOrgLevelService.updateMapping(editing.value.id, payload);
        toast.add({ severity: "success", summary: "Siker", detail: "Mapping frissítve.", life: 2000 });
    } else {
        await PositionOrgLevelService.storeMapping(payload);
        toast.add({ severity: "success", summary: "Siker", detail: "Mapping létrehozva.", life: 2000 });
    }

    editOpen.value = false;
    await load();
};

const destroyRow = async (row) => {
    await PositionOrgLevelService.deleteMapping(row.id);
    toast.add({ severity: "success", summary: "Siker", detail: "Mapping törölve.", life: 2000 });
    await load();
};

onMounted(load);
</script>

<template>
    <Head :title="title" />
    <Toast />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex items-center gap-2">
                <Button label="Új mapping" icon="pi pi-plus" @click="openCreate" />
                <InputText v-model="q" placeholder="Keresés..." />
                <Select
                    v-model="orgLevelFilter"
                    :options="org_levels"
                    placeholder="Szint"
                    showClear
                />
                <Button label="Szűrés" severity="secondary" @click="load" />
            </div>

            <DataTable :value="rows" :loading="loading" dataKey="id">
                <Column field="position_label" header="Pozíció" />
                <Column field="position_key" header="Kulcs" />
                <Column field="org_level" header="Szint" />
                <Column header="Aktív">
                    <template #body="{ data }">
                        <Checkbox :modelValue="!!data.active" binary disabled />
                    </template>
                </Column>
                <Column header="">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button size="small" icon="pi pi-pencil" text @click="openEdit(data)" />
                            <Button size="small" icon="pi pi-trash" text severity="danger" @click="destroyRow(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>

    <Dialog v-model:visible="editOpen" modal header="Position mapping" :style="{ width: '520px' }">
        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-sm">Position label</label>
                <InputText v-model="form.position_label" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-sm">Org level</label>
                <Select v-model="form.org_level" :options="org_levels" class="w-full" />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.active" binary />
                <span>Aktív</span>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <Button label="Mégse" text @click="editOpen = false" />
                <Button label="Mentés" @click="save" />
            </div>
        </div>
    </Dialog>
</template>

