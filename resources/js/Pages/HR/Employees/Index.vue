<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

import CreateModal from "@/Pages/HR/Employees/CreateModal.vue";
import EditModal from "@/Pages/HR/Employees/EditModal.vue";
import WorkPatternModal from "@/Pages/HR/Employees/WorkPatternModal.vue";
import DeleteEmployeeDialog from "@/Components/Employees/DeleteEmployeeDialog.vue";
import EmployeeImportDialog from "@/Components/Employees/EmployeeImportDialog.vue";
import EmployeeService from "@/services/EmployeeService.js";

import { csrfFetch } from "@/lib/csrfFetch";

import { toYmd } from "@/helpers/functions.js";

const page = usePage();

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
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

const title = trans("employees.title");

const { has } = usePermissions();
const canViewAny = has(`${props.permissionPrefix}.viewAny`);
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
const deleteOpen = ref(false);
const deleteEmployee = ref(null);
const importOpen = ref(false);
const workPatternOpen = ref(false);
const selectedEmployeeForWorkPattern = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

const rows = ref([]);

// checkbox selection
const selected = ref([]);

const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        disabled: actionLoading.value || !canUpdate,
        command: () => openEditModal(row),
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: actionLoading.value || !canDelete,
        command: () => confirmDeleteOne(row),
    },
    {
        label: trans("employees.actions.work_pattern"),
        icon: "pi pi-calendar",
        disabled: actionLoading.value || !canViewEmployeeWorkPatterns,
        command: () => openWorkPatternModal(row),
    },
];
// ------------------------

const transferFormats = computed(() => [
    { label: trans("common.formats.csv"), value: "csv" },
    { label: trans("common.formats.json"), value: "json" },
    { label: trans("common.formats.xml"), value: "xml" },
    { label: trans("common.formats.xlsx"), value: "xlsx" },
]);

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
    { label: trans("true"), value: true },
    { label: trans("false"), value: false },
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

const openImport = () => {
    importOpen.value = true;
};

const openEditModal = (row) => {
    editEmployee.value = row;
    editOpen.value = true;
};

const openWorkPatternModal = (row) => {
    selectedEmployeeForWorkPattern.value = row;
    workPatternOpen.value = true;
};

const onSaved = async (msg = trans("common.success")) => {
    createOpen.value = false;
    editOpen.value = false;
    workPatternOpen.value = false;

    selected.value = [];
    await fetchEmployees();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: msg,
        life: 2000,
    });
};

const onImportCompleted = async (summary) => {
    if (!summary) {
        return;
    }

    if ((summary.imported_count ?? 0) > 0) {
        await fetchEmployees();
    }

    toast.add({
        severity: summary.failed_count > 0 ? "warn" : "success",
        summary: trans("common.success"),
        detail: trans("employees.import.messages.completed"),
        life: 2500,
    });
};

const downloadExport = async (format) => {
    actionLoading.value = true;

    try {
        const response = await EmployeeService.exportEmployees(format, {
            company_id: companyId.value,
        });

        EmployeeService.saveDownload(response, `employees-export.${format}`);

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("employees.import.messages.download_started"),
            life: 2000,
        });
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail:
                error?.response?.data?.message ||
                trans("employees.import.messages.export_failed"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const downloadTemplate = async (format) => {
    actionLoading.value = true;

    try {
        const response = await EmployeeService.downloadEmployeeTemplate(format);

        EmployeeService.saveDownload(response, `employees-template.${format}`);

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("employees.import.messages.download_started"),
            life: 2000,
        });
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail:
                error?.response?.data?.message ||
                trans("employees.import.messages.template_failed"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const exportMenuItems = computed(() =>
    transferFormats.value.map((item) => ({
        label: item.label,
        command: () => downloadExport(item.value),
    })),
);

const templateMenuItems = computed(() =>
    transferFormats.value.map((item) => ({
        label: item.label,
        command: () => downloadTemplate(item.value),
    })),
);

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
        error.value = e?.message || trans("common.unknown_error");
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    deleteEmployee.value = row;
    deleteOpen.value = true;
};

const onDeleted = async () => {
    deleteOpen.value = false;
    const deletedId = Number(deleteEmployee.value?.id || 0);
    if (deletedId > 0) {
        selected.value = selected.value.filter((x) => x.id !== deletedId);
    }
    deleteEmployee.value = null;
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: trans("employees.messages.deleted_success"),
        life: 2500,
    });
    await fetchEmployees();
};

const confirmBulkDelete = () => {
    const ids = (selected.value ?? []).map((x) => x.id);
    if (!ids.length) return;

    confirm.require({
        message: trans("employees.dialogs.delete_confirm", {
            count: ids.length,
        }),
        header: trans("employees.actions.bulk_delete"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
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
            let msg = trans("employees.messages.bulk_delete_failed_http", {
                status: res.status,
            });
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("employees.messages.bulk_deleted_success", {
                count: ids.length,
            }),
            life: 2500,
        });

        selected.value = [];
        await fetchEmployees();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("common.unknown_error"),
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
    <Head :title="title" />

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

    <DeleteEmployeeDialog
        v-model:visible="deleteOpen"
        :employee="deleteEmployee"
        :company-id="Number(companyId || 0)"
        @deleted="onDeleted"
    />

    <EmployeeImportDialog v-model="importOpen" @completed="onImportCompleted" />

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
                        :label="$t('employees.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        :disabled="loading"
                        @click="openCreate"
                        data-testid="employees-create"
                    />

                    <SplitButton
                        v-if="canViewAny"
                        :label="$t('employees.actions.export')"
                        icon="pi pi-download"
                        size="small"
                        severity="secondary"
                        :disabled="loading || actionLoading"
                        :model="exportMenuItems"
                        data-testid="employees-export"
                    />

                    <SplitButton
                        v-if="canViewAny"
                        :label="$t('employees.actions.download_template')"
                        icon="pi pi-file-export"
                        size="small"
                        severity="secondary"
                        :disabled="loading || actionLoading"
                        :model="templateMenuItems"
                        data-testid="employees-template"
                    />

                    <Button
                        v-if="canCreate"
                        :label="$t('employees.actions.import')"
                        icon="pi pi-upload"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        @click="openImport"
                        data-testid="employees-import"
                    />

                    <!-- FRISSÍTÉS -->
                    <Button
                        :label="$t('employees.actions.refresh')"
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
                        :label="$t('employees.actions.bulk_delete')"
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
                        {{
                            $t("employees.selected_count", {
                                count: selected.length,
                            })
                        }}
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">{{ $t("common.error") }}</div>
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
                            :label="$t('employees.filters.clear')"
                            variant="outlined"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                :placeholder="
                                    $t('employees.filters.keyword_search')
                                "
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{ $t("employees.states.empty") }}</template>
                <template #loading>{{
                    $t("employees.states.loading")
                }}</template>

                <!-- checkbox oszlop -->
                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column
                    field="id"
                    :header="$t('columns.id')"
                    sortable
                    style="width: 90px"
                />

                <Column
                    field="name"
                    filterField="name"
                    :header="$t('columns.name')"
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
                            :placeholder="$t('employees.filters.name')"
                        />
                    </template>
                </Column>

                <Column
                    field="email"
                    filterField="email"
                    :header="$t('columns.email')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('employees.filters.email')"
                        />
                    </template>
                </Column>
                <Column
                    field="phone"
                    filterField="phone"
                    :header="$t('columns.phone')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('employees.filters.phone')"
                        />
                    </template>
                </Column>

                <!-- BELÉPÉS -->
                <Column
                    field="hired_at"
                    :header="$t('columns.hired_at')"
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
                    :header="$t('columns.active')"
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
                            {{ data.active ? $t("true") : $t("false") }}
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
                            :placeholder="$t('employees.filters.status')"
                        />
                    </template>
                </Column>

                <!-- Actions -->
                <Column
                    :header="$t('columns.actions')"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                :buttonTitle="
                                    $t('employees.actions.edit_title', {
                                        name: data.name ?? data.id,
                                    })
                                "
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
