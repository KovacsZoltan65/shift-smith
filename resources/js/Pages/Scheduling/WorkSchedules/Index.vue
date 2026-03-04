<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Menu from "primevue/menu";
import Select from "primevue/select";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import CreateModal from "@/Pages/Scheduling/WorkSchedules/CreateModal.vue";
import EditModal from "@/Pages/Scheduling/WorkSchedules/EditModal.vue";
import { usePermissions } from "@/composables/usePermissions";
import WorkScheduleService from "@/services/WorkScheduleService";
import { toYmd } from "@/helpers/functions.js";

const { has } = usePermissions();

const canCreate = has("work_schedules.create");
const canUpdate = has("work_schedules.update");
const canDelete = has("work_schedules.delete");
const canBulkDelete = has("work_schedules.deleteAny");

const props = defineProps({
    title: { type: String, default: "Munkabeosztások" },
    filter: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirm = useConfirm();

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);
const rows = ref([]);
const selected = ref([]);
const companyId = ref(props.filter?.company_id ?? null);

const createOpen = ref(false);
const editOpen = ref(false);
const editWorkSchedule = ref(null);

const rowMenu = ref();
const rowMenuModel = ref([]);

const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Publikált", value: "published" },
];

const globalFilterFields = ["name", "date_from", "date_to", "status", "assignments_count"];

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
                (constraint) => constraint?.value !== null && constraint?.value !== "",
            );
        }
        return false;
    }),
);

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => {
                editWorkSchedule.value = row;
                editOpen.value = true;
            },
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

        rows.value = Array.isArray(response?.data?.data) ? response.data.data : [];
    } catch (err) {
        error.value = err?.message ?? "Betöltési hiba.";
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

const onSaved = async (message = "Mentve.") => {
    createOpen.value = false;
    editOpen.value = false;
    selected.value = [];
    await fetchWorkSchedules();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: message,
        life: 2500,
    });
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
        await WorkScheduleService.deleteWorkSchedule(id, Number(companyId.value));
        await onSaved("Munkabeosztás törölve.");
    } catch (err) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: err?.message ?? "Törlés sikertelen.",
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
        message: `Biztos törlöd a kijelölt ${ids.length} munkabeosztást?`,
        header: "Megerősítés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => deleteMany(ids),
    });
};

const deleteMany = async (ids) => {
    actionLoading.value = true;
    try {
        await WorkScheduleService.deleteWorkSchedules(ids, Number(companyId.value));
        await onSaved("Kijelölt munkabeosztások törölve.");
    } catch (err) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: err?.message ?? "Bulk törlés sikertelen.",
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

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ title }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Toast />
                <ConfirmDialog />
                <Menu ref="rowMenu" :model="rowMenuModel" popup />

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

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="w-full max-w-sm">
                                <label class="mb-1 block text-xs text-slate-600">Cég</label>
                                <CompanySelector v-model="companyId" @update:modelValue="onCompanyChanged" />
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="canCreate"
                                    label="Új munkabeosztás"
                                    icon="pi pi-plus"
                                    @click="createOpen = true"
                                />
                                <Button
                                    label="Frissítés"
                                    icon="pi pi-refresh"
                                    severity="secondary"
                                    :loading="loading"
                                    @click="fetchWorkSchedules"
                                />
                                <Button
                                    v-if="canBulkDelete"
                                    label="Kijelöltek törlése"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    :disabled="!selected.length || actionLoading"
                                    @click="confirmBulkDelete"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                icon="pi pi-filter-slash"
                                label="Szűrők törlése"
                                severity="secondary"
                                size="small"
                                :disabled="!hasActiveFilters"
                                @click="clearFilters"
                            />
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText
                                    v-model="filters.global.value"
                                    class="w-72"
                                    placeholder="Keresés..."
                                />
                            </span>
                        </div>
                    </div>

                    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ error }}
                    </div>

                    <DataTable
                        ref="dt"
                        v-model:selection="selected"
                        :value="rows"
                        dataKey="id"
                        :loading="loading"
                        paginator
                        :rows="10"
                        :rowsPerPageOptions="[10, 25, 50, 100]"
                        stripedRows
                        filterDisplay="menu"
                        :filters="filters"
                        :globalFilterFields="globalFilterFields"
                        removableSort
                    >
                        <template #empty>Nincs munkabeosztás.</template>

                        <Column selectionMode="multiple" headerStyle="width: 3rem" />

                        <Column field="name" header="Név" sortable>
                            <template #body="{ data }">
                                {{ data.name }}
                            </template>
                        </Column>

                        <Column field="date_from" header="Kezdet" sortable>
                            <template #body="{ data }">
                                {{ toYmd(data.date_from) ?? "-" }}
                            </template>
                        </Column>

                        <Column field="date_to" header="Vége" sortable>
                            <template #body="{ data }">
                                {{ toYmd(data.date_to) ?? "-" }}
                            </template>
                        </Column>

                        <Column field="status" header="Státusz" sortable>
                            <template #body="{ data }">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="data.status === 'published'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-amber-100 text-amber-700'"
                                >
                                    {{ data.status === "published" ? "Publikált" : "Draft" }}
                                </span>
                            </template>
                            <template #filter="{ filterModel }">
                                <Select
                                    v-model="filterModel.value"
                                    :options="statusOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Mind"
                                    class="min-w-40"
                                    showClear
                                />
                            </template>
                        </Column>

                        <Column field="assignments_count" header="Beosztások" sortable>
                            <template #body="{ data }">
                                {{ Number(data.assignments_count ?? 0) }}
                            </template>
                        </Column>

                        <Column header="Műveletek" bodyClass="text-right" headerClass="text-right">
                            <template #body="{ data }">
                                <Button
                                    icon="pi pi-ellipsis-v"
                                    text
                                    rounded
                                    aria-label="Műveletek"
                                    @click="openRowMenu($event, data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
