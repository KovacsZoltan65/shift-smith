<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { trans } from "laravel-vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import CreateModal from "@/Pages/Scheduling/WorkSchedules/CreateModal.vue";
import EditModal from "@/Pages/Scheduling/WorkSchedules/EditModal.vue";
import { usePermissions } from "@/composables/usePermissions";
import WorkScheduleService from "@/services/WorkScheduleService";
import { toYmd } from "@/helpers/functions.js";
import { IconField, InputIcon } from "primevue";

/**
 * WorkSchedules index oldal.
 *
 * A munkabeosztások listáját jeleníti meg DataTable szűréssel, rendezéssel
 * és CRUD műveletekkel. A page a company scope-ra épül, ezért a selector és
 * az API hívások ugyanahhoz a kiválasztott céghez kötődnek.
 */
const { has } = usePermissions();

const canCreate = has("work_schedules.create");
const canUpdate = has("work_schedules.update");
const canDelete = has("work_schedules.delete");
const canBulkDelete = has("work_schedules.deleteAny");

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    hqBadge: { type: String, default: "" },
});

const title = trans("work_schedules.title");
const toast = useToast();
const confirm = useConfirm();
const $t = trans;

// Táblázat state
const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);
const rows = ref([]);

// Dialog state
const selected = ref([]);
const companyId = ref(props.filter?.company_id ?? null);

const createOpen = ref(false);
const editOpen = ref(false);
const editWorkSchedule = ref(null);

const statusOptions = [
    { label: trans("work_schedules.status.draft"), value: "draft" },
    { label: trans("work_schedules.status.published"), value: "published" },
];

const globalFilterFields = [
    "name",
    "date_from",
    "date_to",
    "status",
    "assignments_count",
];

const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    status: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    date_from: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    date_to: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
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

const hasActiveFilters = computed(() =>
    Object.values(filters.value ?? {}).some((entry) => {
        if (!entry || typeof entry !== "object") return false;
        if ("value" in entry) return entry.value !== null && entry.value !== "";
        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }
        return false;
    }),
);

const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        disabled: actionLoading.value || !canUpdate,
        command: () => {
            editWorkSchedule.value = row;
            editOpen.value = true;
        },
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: actionLoading.value || !canDelete,
        command: () => confirmDeleteOne(row),
    },
];

const fetchWorkSchedules = async () => {
    if (!companyId.value) {
        rows.value = [];
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const response = await WorkScheduleService.getWorkSchedules({
            page: 1,
            per_page: 100,
            field: "name",
            order: "asc",
            company_id: companyId.value,
        });

        rows.value = Array.isArray(response?.data?.data)
            ? response.data.data
            : [];
    } catch (err) {
        error.value =
            err?.message ?? trans("work_schedules.messages.fetch_failed");
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const onCompanyChanged = async () => {
    selected.value = [];
    initFilters();
    await fetchWorkSchedules();
};

const onSaved = async (message = trans("work_schedules.messages.saved")) => {
    createOpen.value = false;
    editOpen.value = false;
    selected.value = [];
    await fetchWorkSchedules();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: message,
        life: 2500,
    });
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: trans("work_schedules.dialogs.delete_confirm", {
            name: row?.name ?? `#${row?.id}`,
        }),
        header: trans("common.confirmation"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => deleteOne(row.id),
    });
};

const deleteOne = async (id) => {
    actionLoading.value = true;
    try {
        await WorkScheduleService.deleteWorkSchedule(
            id,
            Number(companyId.value),
        );
        await onSaved(trans("work_schedules.messages.deleted_success"));
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail:
                err?.message ?? trans("work_schedules.messages.delete_failed"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const confirmBulkDelete = () => {
    const ids = selected.value.map((row) => Number(row.id)).filter(Boolean);
    if (!ids.length) return;

    confirm.require({
        message: trans("work_schedules.dialogs.bulk_delete_confirm", {
            count: ids.length,
        }),
        header: trans("common.confirmation"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => deleteMany(ids),
    });
};

const deleteMany = async (ids) => {
    actionLoading.value = true;
    try {
        await WorkScheduleService.deleteWorkSchedules(
            ids,
            Number(companyId.value),
        );
        await onSaved(
            trans("work_schedules.messages.bulk_deleted_success", {
                count: ids.length,
            }),
        );
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail:
                err?.message ??
                trans("work_schedules.messages.bulk_delete_failed"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(fetchWorkSchedules);
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
        :workSchedule="editWorkSchedule"
        :canUpdate="canUpdate"
        @saved="onSaved"
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

                    <Button
                        v-if="canCreate"
                        :label="$t('work_schedules.actions.create')"
                        icon="pi pi-plus"
                        @click="createOpen = true"
                    />
                    <Button
                        :label="$t('common.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        :loading="loading"
                        @click="fetchWorkSchedules"
                    />
                    <Button
                        v-if="canBulkDelete"
                        :label="$t('work_schedules.actions.bulk_delete')"
                        icon="pi pi-trash"
                        severity="danger"
                        outlined
                        :disabled="!selected.length || actionLoading"
                        @click="confirmBulkDelete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        {{
                            $t("work_schedules.selected_count", {
                                count: selected.length,
                            })
                        }}
                    </div>

                    <div class="min-w-[260px]">
                        <CompanySelector
                            v-model="companyId"
                            :placeholder="
                                $t('work_schedules.placeholders.company')
                            "
                            @update:modelValue="onCompanyChanged"
                        />
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
                :loading="loading"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                stripedRows
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
                removableSort
            >
                <template #header>
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="$t('work_schedules.filters.clear')"
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
                                    $t('work_schedules.filters.keyword_search')
                                "
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{
                    $t("work_schedules.states.empty")
                }}</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column
                    field="name"
                    filterField="name"
                    :header="$t('columns.name')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        {{ data.name }}
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('work_schedules.filters.name')"
                        />
                    </template>
                </Column>

                <Column
                    field="date_from"
                    filterField="date_from"
                    :header="$t('work_schedules.fields.date_from')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        {{ toYmd(data.date_from) ?? "-" }}
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('work_schedules.filters.date')"
                        />
                    </template>
                </Column>

                <Column
                    field="date_to"
                    filterField="date_to"
                    :header="$t('work_schedules.fields.date_to')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        {{ toYmd(data.date_to) ?? "-" }}
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('work_schedules.filters.date')"
                        />
                    </template>
                </Column>

                <Column
                    field="status"
                    filterField="status"
                    :header="$t('work_schedules.fields.status')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="
                                data.status === 'published'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-amber-100 text-amber-700'
                            "
                        >
                            {{
                                data.status === "published"
                                    ? $t("work_schedules.status.published")
                                    : $t("work_schedules.status.draft")
                            }}
                        </span>
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            :placeholder="$t('work_schedules.filters.all')"
                            class="min-w-40"
                            showClear
                        />
                    </template>
                </Column>

                <Column
                    field="assignments_count"
                    :header="$t('work_schedules.fields.assignments')"
                    sortable
                >
                    <template #body="{ data }">
                        {{ Number(data.assignments_count ?? 0) }}
                    </template>
                </Column>

                <Column
                    :header="$t('columns.actions')"
                    bodyClass="text-right"
                    headerClass="text-right"
                >
                    <template #body="{ data }">
                        <RowActionMenu
                            :items="buildRowMenuItems(data)"
                            :disabled="actionLoading"
                            :buttonTitle="$t('columns.actions')"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
