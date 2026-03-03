<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
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
import Menu from "primevue/menu";
import Select from "primevue/select";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    title: { type: String, default: "Szabadsag tipusok" },
    filter: { type: Object, default: () => ({}) },
});

const { has } = usePermissions();
const toast = useToast();

const canCreate = computed(() => has("leave_types.create"));
const canUpdate = computed(() => has("leave_types.update"));
const canDelete = computed(() => has("leave_types.delete"));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

const rows = ref([]);
const loading = ref(false);
const dt = ref(null);
const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editTarget = ref(null);
const deleteTarget = ref(null);
const rowMenu = ref();
const rowMenuModel = ref([]);
const globalFilterFields = ["code", "name", "category"];

const categoryOptions = [
    { label: "Szabadsag", value: "leave" },
    { label: "Betegszabadsag", value: "sick_leave" },
    { label: "Fizetett tavollet", value: "paid_absence" },
    { label: "Fizetes nelkuli tavollet", value: "unpaid_absence" },
];

const booleanOptions = [
    { label: "Igen", value: true },
    { label: "Nem", value: false },
];

const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    code: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    category: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    active: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
});

const filters = ref(createInitialFilters());

const initFilters = () => {
    filters.value = createInitialFilters();
};

const clearFilter = () => {
    initFilters();
    dt.value?.clearFilter?.();
};

const hasActiveFilters = computed(() => {
    const currentFilters = filters.value ?? {};

    return Object.values(currentFilters).some((entry) => {
        if (!entry || typeof entry !== "object") {
            return false;
        }

        if ("value" in entry) {
            return entry.value !== null && entry.value !== "";
        }

        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }

        return false;
    });
});

const categoryLabel = (value) =>
    categoryOptions.find((option) => option.value === value)?.label ?? value;

const fetchRows = async () => {
    loading.value = true;

    try {
        const { data } = await LeaveTypeService.fetch({
            perPage: 100,
            sortBy: "name",
            sortDir: "asc",
        });

        rows.value = Array.isArray(data?.items) ? data.items : [];
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail:
                error?.response?.data?.message ??
                error?.message ??
                "Lista betoltese sikertelen.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const openEditModal = (row) => {
    editTarget.value = row;
    editOpen.value = true;
};

const openDeleteModal = (row) => {
    deleteTarget.value = row;
    deleteOpen.value = true;
};

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztes",
            icon: "pi pi-pencil",
            disabled: loading.value || !canUpdate.value,
            command: () => openEditModal(row),
        },
        {
            label: "Torles",
            icon: "pi pi-trash",
            disabled: loading.value || !canDelete.value,
            command: () => openDeleteModal(row),
        },
    ];

    rowMenu.value.toggle(event);
};

const onSaved = async (message) => {
    createOpen.value = false;
    editOpen.value = false;
    deleteOpen.value = false;
    editTarget.value = null;
    deleteTarget.value = null;
    await fetchRows();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: message,
        life: 2500,
    });
};

onMounted(() => {
    initFilters();
    fetchRows();
});
</script>

<template>
    <Head :title="title" />

    <Toast />

    <CreateModal v-model="createOpen" :canCreate="canCreate" @saved="onSaved" />
    <EditModal
        v-model="editOpen"
        :leaveType="editTarget"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />
    <DeleteModal
        v-model="deleteOpen"
        :leaveType="deleteTarget"
        :canDelete="canDelete"
        @deleted="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <!-- Új elem -->
                    <Button
                        v-if="canCreate"
                        label="Uj tipus"
                        icon="pi pi-plus"
                        size="small"
                        data-testid="leave-types-create"
                        @click="createOpen = true"
                    />

                    <!-- Frissítés -->
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
            </div>

            <Menu
                v-if="canAnyRowAction"
                ref="rowMenu"
                :model="rowMenuModel"
                popup
            />

            <DataTable
                ref="dt"
                :value="rows"
                dataKey="id"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading"
                sortMode="multiple"
                removableSort
                v-model:filters="filters"
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
            >
                <template #header>
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            label="Clear"
                            variant="outlined"
                            @click="clearFilter()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                placeholder="Keyword Search"
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>Nincs talalat.</template>
                <template #loading>
                    Loading customers data. Please wait.
                </template>

                <!-- Kód -->
                <Column
                    field="code"
                    filterField="code"
                    header="Kod"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                    :filterMenuStyle="{ width: '14rem' }"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Kod keresese"
                        />
                    </template>
                </Column>

                <!-- Name -->
                <Column
                    field="name"
                    filterField="name"
                    header="Nev"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                    :filterMenuStyle="{ width: '14rem' }"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Nev keresese"
                        />
                    </template>
                </Column>

                <!-- Category -->
                <Column
                    field="category"
                    filterField="category"
                    header="Kategoria"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                    :filterMenuStyle="{ width: '16rem' }"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="categoryLabel(data.category)"
                            severity="info"
                        />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="categoryOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            placeholder="Kategoria"
                        />
                    </template>
                </Column>

                <!-- Affect Leave Balance -->
                <Column
                    field="affects_leave_balance"
                    header="Keretet csokkenti"
                    sortable
                >
                    <template #body="{ data }">
                        <Tag
                            :value="data.affects_leave_balance ? 'Igen' : 'Nem'"
                            :severity="
                                data.affects_leave_balance
                                    ? 'success'
                                    : 'secondary'
                            "
                        />
                    </template>
                </Column>

                <!-- Approval -->
                <Column field="requires_approval" header="Jovahagyas" sortable>
                    <template #body="{ data }">
                        <Tag
                            :value="data.requires_approval ? 'Kotelezo' : 'Nem'"
                            :severity="
                                data.requires_approval ? 'warning' : 'secondary'
                            "
                        />
                    </template>
                </Column>

                <!-- Active -->
                <Column
                    field="active"
                    filterField="active"
                    header="Aktiv"
                    filter
                    sortable
                    dataType="boolean"
                    :showFilterMatchModes="false"
                    :filterMenuStyle="{ width: '12rem' }"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="data.active ? 'Aktiv' : 'Inaktiv'"
                            :severity="data.active ? 'success' : 'danger'"
                        />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="booleanOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            placeholder="Statusz"
                        />
                    </template>
                </Column>

                <!-- Actions -->
                <Column
                    v-if="canAnyRowAction"
                    header="Muveletek"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                icon="pi pi-ellipsis-v"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                :disabled="loading"
                                data-testid="leave-types-actions"
                                @click="openRowMenu($event, data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div class="text-xs text-slate-500">
                TODO: nagy lista eseten server-side filtering + pagination.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
