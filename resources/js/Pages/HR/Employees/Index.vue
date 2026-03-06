<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import Menu from "primevue/menu";
import Select from "primevue/select";

import CreateModal from "@/Pages/HR/Employees/CreateModal.vue";
import EditModal from "@/Pages/HR/Employees/EditModal.vue";
import WorkPatternModal from "@/Pages/HR/Employees/WorkPatternModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { toYmd } from "@/helpers/functions.js";

const page = usePage();

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    title: { type: String, default: "Dolgozók" },
    filter: { type: Object, default: () => ({}) },
    default_company_id: { type: [Number, String, null], default: null },
    endpointBase: { type: String, default: "/employees" },
    permissionPrefix: { type: String, default: "employees" },
    permissionPrefix2: { type: String, default: "employee_work_patterns" },
    hqBadge: { type: String, default: "" },
    fetchRouteName: { type: String, default: "" },
    detailRouteName: { type: String, default: "" },
    forbiddenRedirectRouteName: { type: String, default: "" },
});

const { has } = usePermissions();
const canCreate = has(`${props.permissionPrefix}.create`);
const canUpdate = has(`${props.permissionPrefix}.update`);
const canDelete = has(`${props.permissionPrefix}.delete`);
const canViewEmployeeWorkPatterns = has(`${props.permissionPrefix2}.view`);
const canAssignEmployeeWorkPatterns = has(`${props.permissionPrefix2}.assign`);
const canUnassignEmployeeWorkPatterns = has(
    `${props.permissionPrefix2}.unassign`,
);

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editEmployee = ref(null);
const workPatternOpen = ref(false);
const selectedEmployeeForWorkPattern = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

const rows = ref([]);

// checkbox selection
const selected = ref([]);

// ------------------------
// Row actions menu
const rowMenu = ref();
const rowMenuModel = ref([]);
const rowMenuRow = ref(null);

const openRowMenu = (event, row) => {
    rowMenuRow.value = row;

    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => openEditModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete,
            command: () => confirmDeleteOne(row),
        },
        {
            label: "Munkarend",
            icon: "pi pi-calendar",
            disabled: actionLoading.value || !canViewEmployeeWorkPatterns,
            command: () => openWorkPatternModal(row),
        },
    ];

    rowMenu.value.toggle(event);
};
// ------------------------

const companyId = ref(
    page.props?.companyContext?.current_company_id ??
        props.filter?.company_id ??
        (props.default_company_id ? Number(props.default_company_id) : null),
);
const globalFilterFields = [
    "name",
    "first_name",
    "last_name",
    "position_name",
    "email",
    "phone",
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
    email: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    phone: {
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

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editEmployee.value = row;
    editOpen.value = true;
};

const openWorkPatternModal = (row) => {
    selectedEmployeeForWorkPattern.value = row;
    workPatternOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    createOpen.value = false;
    editOpen.value = false;
    workPatternOpen.value = false;

    selected.value = [];
    await fetchEmployees();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: msg,
        life: 2000,
    });
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

const normalizeFetchPayload = (json) => {
    // 1) backend csomag: { data: paginatorObject }
    // paginatorObject: { data: [...], total: ... } (Laravel standard)
    // 2) Companies mintád: { data: [...], meta: { total: ... } }
    const d = json?.data;

    // ha paginator object jött
    if (d && typeof d === "object" && Array.isArray(d.data)) {
        return {
            rows: d.data,
            total: Number(d.total ?? d.meta?.total ?? 0),
        };
    }

    // ha tömb jött
    if (Array.isArray(d)) {
        return {
            rows: d,
            total: Number(json?.meta?.total ?? 0),
        };
    }

    return { rows: [], total: 0 };
};

const fetchEmployees = async () => {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`${props.endpointBase}/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const out = normalizeFetchPayload(json);

        rows.value = out.rows;
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    const label =
        row?.name ||
        `${row?.first_name ?? ""} ${row?.last_name ?? ""}`.trim() ||
        `#${row?.id}`;

    confirm.require({
        message: `Biztos törlöd: ${label}?`,
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
        const res = await csrfFetch(`${props.endpointBase}/${id}`, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) {
            let msg = `Törlés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Dolgozó törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchEmployees();
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
        message: `Biztos törlöd a kijelölt ${ids.length} dolgozót?`,
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
        const res = await csrfFetch(`${props.endpointBase}/destroy_bulk`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ ids }),
        });

        if (!res.ok) {
            let msg = `Bulk törlés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: `Törölve: ${ids.length} db`,
            life: 2500,
        });

        selected.value = [];
        await fetchEmployees();
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

onMounted(() => {
    initFilters();
    fetchEmployees();
});
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <!-- CREATE MODAL -->
    <CreateModal
        v-model="createOpen"
        :defaultCompanyId="companyId"
        :canCreate="canCreate"
        @saved="onSaved"
    />

    <!-- EDIT MODAL -->
    <EditModal
        v-model="editOpen"
        :employee="editEmployee"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <WorkPatternModal
        v-model="workPatternOpen"
        :employee="selectedEmployeeForWorkPattern"
        :canAssign="canAssignEmployeeWorkPatterns"
        :canUnassign="canUnassignEmployeeWorkPatterns"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <span
                        v-if="hqBadge"
                        class="inline-flex items-center rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"
                    >
                        {{ hqBadge }}
                    </span>

                    <!-- CREATE -->
                    <Button
                        v-if="canCreate"
                        label="Új dolgozó"
                        icon="pi pi-plus"
                        size="small"
                        :disabled="loading"
                        @click="openCreate"
                        data-testid="employees-create"
                    />

                    <!-- FRISSÍTÉS -->
                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchEmployees"
                        data-testid="employees-refresh"
                    />

                    <!-- BULK DELETE -->
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
                        data-testid="employees-bulk-delete"
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
                <template #loading>Betoltes...</template>

                <!-- checkbox oszlop -->
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
                    <template #body="{ data }">
                        <div class="font-medium">
                            {{
                                data.name ??
                                `${data.first_name ?? ""} ${data.last_name ?? ""}`.trim()
                            }}
                        </div>
                        <div
                            v-if="data.position_name"
                            class="text-xs text-gray-500"
                        >
                            {{ data.position_name }}
                        </div>
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Nev keresese"
                        />
                    </template>
                </Column>

                <Column
                    field="email"
                    filterField="email"
                    header="Email"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Email keresese"
                        />
                    </template>
                </Column>
                <Column
                    field="phone"
                    filterField="phone"
                    header="Telefon"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Telefon keresese"
                        />
                    </template>
                </Column>

                <!-- BELÉPÉS -->
                <Column
                    field="hired_at"
                    header="Belépés"
                    sortable
                    style="width: 140px"
                >
                    <template #body="{ data }">
                        <span class="text-sm">
                            {{ toYmd(data.hired_at) ?? "-" }}
                        </span>
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

                <!-- Actions -->
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
                                :title="`Műveletek: ${data.name ?? data.id}`"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
