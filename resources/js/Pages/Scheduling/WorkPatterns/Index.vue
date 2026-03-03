<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import Button from "primevue/button";
import Column from "primevue/column";
import Checkbox from "primevue/checkbox";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Menu from "primevue/menu";
import Select from "primevue/select";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import CreateModal from "@/Pages/Scheduling/WorkPatterns/CreateModal.vue";
import EditModal from "@/Pages/Scheduling/WorkPatterns/EditModal.vue";
import EmployeeAssignModal from "@/Pages/Scheduling/WorkPatterns/EmployeeAssignModal.vue";
import EmployeesModal from "@/Pages/Scheduling/WorkPatterns/EmployeesModal.vue";
import { csrfFetch } from "@/lib/csrfFetch";
import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";

const { has } = usePermissions();

const canCreate = has("work_patterns.create");
const canUpdate = has("work_patterns.update");
const canDelete = has("work_patterns.delete");
const canBulkDelete = has("work_patterns.deleteAny");
const canAssignEmployee = has("employee_work_patterns.assign");

const props = defineProps({
    title: { type: String, default: "Munkarendek" },
    filter: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editWorkPattern = ref(null);
const employeeAssignOpen = ref(false);
const assignWorkPattern = ref(null);
const employeesOpen = ref(false);
const employeesWorkPattern = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

const rows = ref([]);
const selected = ref([]);

const companyId = ref(props.filter?.company_id ?? null);
const rowMenu = ref();
const rowMenuModel = ref([]);
const globalFilterFields = [
    "name",
    "daily_work_minutes",
    "break_minutes",
    "employees_count",
    "active",
];

const booleanOptions = [
    { label: "Aktiv", value: true },
    { label: "Inaktiv", value: false },
];

const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    daily_work_minutes: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    break_minutes: {
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

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editWorkPattern.value = row;
    editOpen.value = true;
};

const openAssignModal = (row) => {
    assignWorkPattern.value = row;
    employeeAssignOpen.value = true;
};

const openEmployeesModal = (row) => {
    employeesWorkPattern.value = row;
    employeesOpen.value = true;
};

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => openEditModal(row),
        },
        {
            label: "Dolgozó hozzárendelése",
            icon: "pi pi-user-plus",
            disabled: actionLoading.value || !canAssignEmployee,
            command: () => openAssignModal(row),
        },
        {
            label: "Dolgozók listája",
            icon: "pi pi-users",
            disabled: actionLoading.value,
            command: () => openEmployeesModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete,
            command: () => confirmDeleteOne(row),
        },
    ];

    rowMenu.value.toggle(event);
};

const onSaved = async (message = "Mentve.") => {
    createOpen.value = false;
    editOpen.value = false;
    employeeAssignOpen.value = false;
    employeesOpen.value = false;
    selected.value = [];
    await fetchWorkPatterns();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: message,
        life: 2500,
    });
};

const onCompanyChanged = () => {
    initFilters();
    fetchWorkPatterns();
};

const buildQuery = () => {
    const q = {
        page: 1,
        per_page: 100,
        field: "name",
        order: "asc",
        company_id: companyId.value || "",
    };

    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
    });

    return new URLSearchParams(q).toString();
};

const fetchWorkPatterns = async () => {
    if (!companyId.value) {
        rows.value = [];
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/work-patterns/fetch?${buildQuery()}`, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        rows.value = Array.isArray(json?.data)
            ? json.data
            : (json?.data?.data ?? []);
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: `Biztos törlöd: ${row?.name ?? `#${row?.id}`}?`,
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
        const res = await csrfFetch(`/work-patterns/${id}`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({ company_id: Number(companyId.value) }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(
                body?.message || `Törlés sikertelen (HTTP ${res.status})`,
            );
        }

        selected.value = selected.value.filter((x) => x.id !== id);
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Munkarend törölve",
            life: 2500,
        });
        await fetchWorkPatterns();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Ismeretlen hiba",
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
        message: `Biztos törlöd a kijelölt ${ids.length} munkarendet?`,
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
        const res = await csrfFetch("/work-patterns/destroy_bulk", {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ ids, company_id: Number(companyId.value) }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(
                body?.message || `Bulk törlés sikertelen (HTTP ${res.status})`,
            );
        }

        selected.value = [];
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: `Törölve: ${ids.length} db`,
            life: 2500,
        });
        await fetchWorkPatterns();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Ismeretlen hiba",
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const formatCore = (start, end) => {
    if (!start) return "Nem rugalmas";
    const from = String(start).slice(0, 5);
    const to = end ? String(end).slice(0, 5) : "-";
    return `${from}-${to}`;
};

const toggleActive = async (row, value) => {
    actionLoading.value = true;
    try {
        const payload = {
            company_id: Number(row.company_id),
            name: row.name,
            daily_work_minutes: Number(row.daily_work_minutes),
            break_minutes: Number(row.break_minutes),
            core_start_time: row.core_start_time,
            core_end_time: row.core_end_time,
            active: !!value,
        };

        const res = await csrfFetch(`/work-patterns/${row.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(
                body?.message || `Aktiválás sikertelen (HTTP ${res.status})`,
            );
        }

        row.active = !!value;
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Státusz frissítve",
            life: 2000,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Ismeretlen hiba",
            life: 3500,
        });
        await fetchWorkPatterns();
    } finally {
        actionLoading.value = false;
    }
};

onMounted(() => {
    initFilters();
    fetchWorkPatterns();
});
</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <CreateModal
        v-model="createOpen"
        :companyId="companyId"
        :canCreate="canCreate"
        @saved="onSaved"
    />

    <EditModal
        v-model="editOpen"
        :workPattern="editWorkPattern"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <EmployeeAssignModal
        v-model="employeeAssignOpen"
        :workPattern="assignWorkPattern"
        :canAssign="canAssignEmployee"
        @saved="onSaved"
    />

    <EmployeesModal
        v-model="employeesOpen"
        :workPattern="employeesWorkPattern"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        v-if="canCreate"
                        label="Új munkarend"
                        icon="pi pi-plus"
                        size="small"
                        :disabled="loading"
                        @click="openCreate"
                    />

                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchWorkPatterns"
                    />

                    <Button
                        v-if="canBulkDelete"
                        label="Kijelöltek törlése"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="
                            !selected?.length || loading || actionLoading
                        "
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        Kijelölve: <b>{{ selected.length }}</b>
                    </div>

                    <div class="min-w-[260px]">
                        <CompanySelector
                            v-model="companyId"
                            placeholder="Cég szűrő..."
                            @update:modelValue="onCompanyChanged"
                        />
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">Hiba</div>
                <div class="text-sm">{{ error }}</div>
            </div>

            <Menu ref="rowMenu" :model="rowMenuModel" popup />

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
                selectionMode="multiple"
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
                    <!--<div class="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            label="Szurok torlese"
                            severity="secondary"
                            size="small"
                            :disabled="!hasActiveFilters"
                            data-testid="work-patterns-clear-filters"
                            @click="clearFilters"
                        />
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="filters.global.value"
                                placeholder="Kereses..."
                                class="w-64"
                                data-testid="work-patterns-search"
                            />
                        </span>
                    </div>-->
                </template>

                <template #empty>Nincs találat.</template>

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
                    field="daily_work_minutes"
                    filterField="daily_work_minutes"
                    header="Napi perc"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Napi perc"
                        />
                    </template>
                </Column>
                <Column
                    field="break_minutes"
                    filterField="break_minutes"
                    header="Szünet perc"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Szunet perc"
                        />
                    </template>
                </Column>
                <Column header="Core idő">
                    <template #body="{ data }">
                        {{
                            formatCore(data.core_start_time, data.core_end_time)
                        }}
                    </template>
                </Column>
                <Column
                    field="employees_count"
                    header="Dolgozók száma"
                    style="width: 150px"
                >
                    <template #body="{ data }">
                        <!-- A darabszám kattintható: részletes dolgozólista modal megnyitása. -->
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
                    field="active"
                    filterField="active"
                    header="Aktív"
                    filter
                    sortable
                    dataType="boolean"
                    style="width: 120px"
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Checkbox
                            :modelValue="!!data.active"
                            binary
                            :disabled="actionLoading || !canUpdate"
                            @update:modelValue="(v) => toggleActive(data, !!v)"
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

                <Column
                    header="Műveletek"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <Button
                                icon="pi pi-ellipsis-v"
                                severity="secondary"
                                size="small"
                                text
                                rounded
                                :disabled="actionLoading"
                                @click="openRowMenu($event, data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
