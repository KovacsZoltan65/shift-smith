<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

import CreateModal from "@/Pages/WorkShifts/CreateModal.vue";
import EditModal from "@/Pages/WorkShifts/EditModal.vue";
import AssignmentModal from "@/Pages/WorkShifts/AssignmentModal.vue";
import EmployeesModal from "@/Pages/WorkShifts/EmployeesModal.vue";

import WorkShiftService from "@/services/WorkShiftService.js";
import { usePermissions } from "@/composables/usePermissions";

/**
 * WorkShifts index oldal.
 *
 * A műszak törzsadatok listáját, a DataTable szűrőit és a műszakhoz kötődő
 * dolgozó/hozzárendelés dialogokat fogja össze egy nézetben.
 */
const { has } = usePermissions();

const props = defineProps({
    title: String,
    filter: Object,
});

const canCreate = computed(() => has("work_shifts.create"));
const canUpdate = computed(() => has("work_shifts.update"));
const canDelete = computed(() => has("work_shifts.delete"));
const canAssignEmployee = computed(() => has("work_shifts.update"));
const canAnyRowAction = computed(
    () => canUpdate.value || canDelete.value || canAssignEmployee.value,
);

const toast = useToast();
const confirm = useConfirm();

// Dialog state
const createOpen = ref(false);
const editOpen = ref(false);
const editShift = ref(null);
const assignmentOpen = ref(false);
const assignmentShift = ref(null);
const employeesOpen = ref(false);
const employeesShift = ref(null);

// Táblázat state
const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

const rows = ref([]);
const selected = ref([]);

// A sor műveletek a jogosultságtól és a futó API műveletektől is függenek.
const globalFilterFields = [
    "name",
    "start_time",
    "end_time",
    "work_time_minutes",
    "break_minutes",
    "employees_count",
    "active",
];
const booleanOptions = [
    { label: "Igen", value: true },
    { label: "Nem", value: false },
];
const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    start_time: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    end_time: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
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

const clearFilters = () => {
    initFilters();
    dt.value?.clearFilter?.();
};

const hasActiveFilters = computed(() => {
    const currentFilters = filters.value ?? {};

    return Object.values(currentFilters).some((entry) => {
        if (!entry || typeof entry !== "object") return false;
        if ("value" in entry) return entry.value !== null && entry.value !== "";
        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }
        return false;
    });
});

const formatCreatedAt = (value) => {
    if (!value) return "-";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return date.toLocaleString("hu-HU", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editShift.value = row;
    editOpen.value = true;
};

const openAssignmentModal = (row) => {
    assignmentShift.value = row;
    assignmentOpen.value = true;
};

const openEmployeesModal = (row) => {
    employeesShift.value = row;
    employeesOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchWorkShifts();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: msg,
        life: 2000,
    });
};

const buildParams = () => {
    const params = {
        page: 1,
        per_page: 100,
        field: "name",
        order: "asc",
    };

    Object.keys(params).forEach((k) => {
        if (params[k] === null || params[k] === undefined || params[k] === "") {
            delete params[k];
        }
    });

    return params;
};

const fetchWorkShifts = async () => {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await WorkShiftService.getWorkShifts(buildParams());
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        error.value =
            e?.response?.data?.message || e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};

const buildRowMenuItems = (row) => [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate.value,
            command: () => openEditModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete.value,
            command: () => confirmDeleteOne(row),
        },
        {
            label: "Dolgozó hozzárendelés",
            icon: "pi pi-user-plus",
            disabled: actionLoading.value || !canAssignEmployee.value,
            command: () => openAssignmentModal(row),
        },
    ];

const confirmDeleteOne = (row) => {
    confirm.require({
        message: `Biztos törlöd: ${row.name}?`,
        header: "Megerősítés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => deleteOne(row.id),
    });
};

const deleteOne = async (id) => {
    actionLoading.value = true;

    try {
        await WorkShiftService.deleteWorkShift(id);

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Műszak törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);
        await fetchWorkShifts();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail:
                e?.response?.data?.message || e?.message || "Ismeretlen hiba",
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const confirmBulkDelete = () => {
    const ids = (selected.value ?? []).map((x) => x.id);
    if (!ids.length) return;

    confirm.require({
        message: `Biztos törlöd a kijelölt ${ids.length} műszakot?`,
        header: "Bulk törlés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => bulkDelete(ids),
    });
};

const bulkDelete = async (ids) => {
    actionLoading.value = true;

    try {
        const { data } = await WorkShiftService.deleteWorkShifts(ids);

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: `Törölve: ${data?.deleted ?? ids.length} db`,
            life: 2500,
        });

        selected.value = [];
        await fetchWorkShifts();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail:
                e?.response?.data?.message || e?.message || "Ismeretlen hiba",
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(() => {
    initFilters();
    fetchWorkShifts();
});
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <CreateModal v-model="createOpen" @saved="onSaved" :canCreate="canCreate" />

    <EditModal
        v-model="editOpen"
        :workShift="editShift"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AssignmentModal
        v-model="assignmentOpen"
        :workShift="assignmentShift"
        :canAssign="canAssignEmployee"
        :canDelete="canAssignEmployee"
    />

    <EmployeesModal
        v-model="employeesOpen"
        :workShift="employeesShift"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        v-if="canCreate"
                        label="Új műszak"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="work_shifts-create"
                    />

                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchWorkShifts"
                        data-testid="work_shifts-refresh"
                    />

                    <Button
                        v-if="canDelete"
                        label="Kijelöltek törlése"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="
                            !selected?.length || actionLoading || loading
                        "
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                        data-testid="work_shifts-bulk-delete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        Kijelölve: <b>{{ selected.length }}</b>
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">Hiba</div>
                <div class="text-sm">{{ error }}</div>
            </div>

            <DataTable
                ref="dt"
                v-model:selection="selected"
                v-model:filters="filters"
                :value="rows"
                dataKey="id"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading || actionLoading"
                sortMode="multiple"
                removableSort
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
                            @click="clearFilters()"
                        />
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="filters.global.value"
                                placeholder="Kereses..."
                            />
                        </span>
                    </div>
                </template>

                <template #empty>Nincs talalat.</template>
                <template #loading>Betoltes...</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column
                    field="name"
                    filterField="name"
                    header="Név"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Nev keresese"
                        />
                    </template>
                </Column>
                <Column
                    field="start_time"
                    filterField="start_time"
                    header="Kezdés"
                    filter
                    sortable
                    style="width: 120px"
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Kezdes"
                        />
                    </template>
                </Column>
                <Column
                    field="end_time"
                    filterField="end_time"
                    header="Vége"
                    filter
                    sortable
                    style="width: 120px"
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Vege"
                        />
                    </template>
                </Column>
                <Column
                    field="work_time_minutes"
                    header="Munkaidő"
                    sortable
                    style="width: 130px"
                />
                <Column
                    field="break_minutes"
                    header="Szünet"
                    sortable
                    style="width: 120px"
                />
                <Column
                    field="employees_count"
                    header="Dolgozók"
                    sortable
                    style="width: 130px"
                >
                    <template #body="{ data }">
                        <Button
                            :label="String(data.employees_count ?? 0)"
                            text
                            size="small"
                            class="p-0"
                            @click="openEmployeesModal(data)"
                        />
                    </template>
                </Column>
                <Column
                    field="created_at"
                    header="Létrehozva"
                    sortable
                    style="width: 190px"
                >
                    <template #body="{ data }">
                        {{ formatCreatedAt(data.created_at) }}
                    </template>
                </Column>
                <Column
                    field="active"
                    filterField="active"
                    header="Aktív"
                    filter
                    sortable
                    style="width: 120px"
                    dataType="boolean"
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center rounded px-2 py-1 text-xs"
                            :class="
                                data.active
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-600'
                            "
                        >
                            {{ data.active ? "Igen" : "Nem" }}
                        </span>
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

                <Column
                    v-if="canAnyRowAction"
                    header="Műveletek"
                    headerStyle="width: 120px"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                :buttonTitle="`Műveletek: ${data.name}`"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
