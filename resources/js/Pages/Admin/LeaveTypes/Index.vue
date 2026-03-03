<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateModal from "@/Pages/Admin/LeaveTypes/Partials/CreateModal.vue";
import DeleteModal from "@/Pages/Admin/LeaveTypes/Partials/DeleteModal.vue";
import EditModal from "@/Pages/Admin/LeaveTypes/Partials/EditModal.vue";
import LeaveTypeService from "@/services/LeaveTypeService.js";
import { usePermissions } from "@/composables/usePermissions";

import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import MultiSelect from "primevue/multiselect";
import Select from "primevue/select";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    title: { type: String, default: "Szabadsag tipusok" },
    filter: { type: Object, default: () => ({}) },
});

const { has } = usePermissions();
const toast = useToast();

const canCreate = computed(() => has("leave_types.create"));
const canUpdate = computed(() => has("leave_types.update"));
const canDelete = computed(() => has("leave_types.delete"));

const rows = ref([]);
const totalRecords = ref(0);
const loading = ref(false);

const categoryOptions = [
    { label: "Szabadsag", value: "leave" },
    { label: "Betegszabadsag", value: "sick_leave" },
    { label: "Fizetett tavollet", value: "paid_absence" },
    { label: "Fizetes nelkuli tavollet", value: "unpaid_absence" },
];

const activeOptions = [
    { label: "Mindegy", value: null },
    { label: "Aktiv", value: true },
    { label: "Inaktiv", value: false },
];

const search = ref(props.filter?.q ?? "");
const categories = ref(Array.isArray(props.filter?.category) ? props.filter.category : []);
const active = ref(
    props.filter?.active === null || props.filter?.active === undefined
        ? null
        : Boolean(props.filter.active),
);

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editTarget = ref(null);
const deleteTarget = ref(null);

let searchTimer = null;

const lazy = ref({
    first: 0,
    rows: Number(props.filter?.perPage ?? 10),
    page: Math.max(Number(props.filter?.page ?? 1) - 1, 0),
    sortField: props.filter?.sortBy ?? "name",
    sortOrder: (props.filter?.sortDir ?? "asc") === "asc" ? 1 : -1,
});

lazy.value.first = lazy.value.page * lazy.value.rows;

const categoryLabel = (value) =>
    categoryOptions.find((option) => option.value === value)?.label ?? value;

const buildParams = () => {
    const params = {
        q: search.value?.trim() || undefined,
        category: categories.value?.length ? categories.value : undefined,
        active: active.value,
        page: lazy.value.page + 1,
        perPage: lazy.value.rows,
        sortBy: lazy.value.sortField,
        sortDir: lazy.value.sortOrder === 1 ? "asc" : "desc",
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === "") {
            delete params[key];
        }
    });

    return params;
};

const fetchRows = async () => {
    loading.value = true;

    try {
        const { data } = await LeaveTypeService.fetch(buildParams());
        rows.value = Array.isArray(data?.items) ? data.items : [];

        const meta = data?.meta ?? {};
        totalRecords.value = Number(meta?.total ?? 0);
        lazy.value.page = Math.max(Number(meta?.current_page ?? 1) - 1, 0);
        lazy.value.rows = Number(meta?.per_page ?? lazy.value.rows);
        lazy.value.first = lazy.value.page * lazy.value.rows;
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error?.response?.data?.message ?? error?.message ?? "Lista betoltese sikertelen.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        lazy.value.page = 0;
        lazy.value.first = 0;
        fetchRows();
    }, 300);
};

const onFilterChange = () => {
    lazy.value.page = 0;
    lazy.value.first = 0;
    fetchRows();
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchRows();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.page = 0;
    lazy.value.first = 0;
    fetchRows();
};

const openEditModal = (row) => {
    editTarget.value = row;
    editOpen.value = true;
};

const openDeleteModal = (row) => {
    deleteTarget.value = row;
    deleteOpen.value = true;
};

const onSaved = async (message) => {
    createOpen.value = false;
    editOpen.value = false;
    deleteOpen.value = false;
    editTarget.value = null;
    deleteTarget.value = null;
    await fetchRows();
    toast.add({ severity: "success", summary: "Siker", detail: message, life: 2500 });
};

onMounted(fetchRows);
</script>

<template>
    <Head :title="title" />

    <Toast />

    <CreateModal v-model="createOpen" :canCreate="canCreate" @saved="onSaved" />
    <EditModal v-model="editOpen" :leaveType="editTarget" :canUpdate="canUpdate" @saved="onSaved" />
    <DeleteModal v-model="deleteOpen" :leaveType="deleteTarget" :canDelete="canDelete" @deleted="onSaved" />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <Button
                        v-if="canCreate"
                        label="Uj tipus"
                        icon="pi pi-plus"
                        size="small"
                        data-testid="leave-types-create"
                        @click="createOpen = true"
                    />
                    <Button
                        label="Frissites"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :loading="loading"
                        data-testid="leave-types-refresh"
                        @click="fetchRows"
                    />
                </div>

                <span class="p-input-icon-left">
                    <i class="pi pi-search" />
                    <InputText
                        v-model="search"
                        class="w-72"
                        placeholder="Kereses kod vagy nev alapjan..."
                        data-testid="leave-types-search"
                        @input="onSearchInput"
                    />
                </span>
            </div>

            <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-600">Kategoriak</label>
                    <MultiSelect
                        v-model="categories"
                        :options="categoryOptions"
                        optionLabel="label"
                        optionValue="value"
                        display="chip"
                        filter
                        showClear
                        class="w-full"
                        data-testid="leave-types-category-filter"
                        @change="onFilterChange"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs text-slate-600">Statusz</label>
                    <Select
                        v-model="active"
                        :options="activeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        data-testid="leave-types-active-filter"
                        @change="onFilterChange"
                    />
                </div>
            </div>

            <DataTable
                :value="rows"
                dataKey="id"
                lazy
                paginator
                :rows="lazy.rows"
                :first="lazy.first"
                :totalRecords="totalRecords"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading"
                sortMode="single"
                :sortField="lazy.sortField"
                :sortOrder="lazy.sortOrder"
                @page="onPage"
                @sort="onSort"
            >
                <template #empty>Nincs talalat.</template>

                <Column field="code" header="Kod" sortable />
                <Column field="name" header="Nev" sortable />
                <Column field="category" header="Kategoria" sortable>
                    <template #body="{ data }">
                        <Tag :value="categoryLabel(data.category)" severity="info" />
                    </template>
                </Column>
                <Column header="Keretet csokkenti">
                    <template #body="{ data }">
                        <Tag
                            :value="data.affects_leave_balance ? 'Igen' : 'Nem'"
                            :severity="data.affects_leave_balance ? 'success' : 'secondary'"
                        />
                    </template>
                </Column>
                <Column header="Jovahagyas">
                    <template #body="{ data }">
                        <Tag
                            :value="data.requires_approval ? 'Kotelezo' : 'Nem'"
                            :severity="data.requires_approval ? 'warning' : 'secondary'"
                        />
                    </template>
                </Column>
                <Column field="active" header="Aktiv" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.active ? 'Aktiv' : 'Inaktiv'" :severity="data.active ? 'success' : 'danger'" />
                    </template>
                </Column>
                <Column header="Muveletek" bodyStyle="white-space: nowrap;">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="canUpdate"
                                icon="pi pi-pencil"
                                text
                                rounded
                                size="small"
                                :disabled="loading"
                                data-testid="leave-types-edit"
                                @click="openEditModal(data)"
                            />
                            <Button
                                v-if="canDelete"
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                size="small"
                                :disabled="loading"
                                data-testid="leave-types-delete"
                                @click="openDeleteModal(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
