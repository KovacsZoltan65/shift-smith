<!-- resources/js/Pages/WorkSchedules/Index.vue -->
<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Menu from "primevue/menu";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import DatePicker from "primevue/datepicker";
import Select from "primevue/select";

import CreateModal from "@/Pages/WorkSchedules/CreateModal.vue";
import EditModal from "@/Pages/WorkSchedules/EditModal.vue";
import DeleteModal from "@/Pages/WorkSchedules/DeleteModal.vue";
import AssignmentModal from "@/Pages/WorkSchedules/AssignmentModal.vue";
import AutoPlanWizardDialog from "@/Pages/WorkSchedules/AutoPlanWizardDialog.vue";

import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import { csrfFetch } from "@/lib/csrfFetch";

import { toYmd } from "@/helpers/functions.js";

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();

const canCreate = has("work_schedules.create");
const canUpdate = has("work_schedules.update");
const canDelete = has("work_schedules.delete");
const canDeleteAny = has("work_schedules.deleteAny");
const canAssign = has("work_schedule_assignments.create");
const canAutoPlan = has("work_schedules.autoplan");

const props = defineProps({
    title: String,
    filter: Object,
});

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const assignmentOpen = ref(false);
const autoPlanOpen = ref(false);

const editWorkSchedule = ref(null);
const deleteWorkSchedule = ref(null);
const assignmentWorkSchedule = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);

// checkbox selection
const selected = ref([]);

// ------------------------
// Row actions menu
const rowMenu = ref();
const rowMenuModel = ref([]);

const isPublished = (row) => (row?.status ?? "") === "published";

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => openEditModal(row),
        },
        {
            label: "Hozzárendelés",
            icon: "pi pi-user-plus",
            disabled: actionLoading.value || !canAssign,
            command: () => openAssignmentModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete || isPublished(row),
            command: () => openDeleteModal(row),
        },
    ];

    rowMenu.value.toggle(event);
};
// ------------------------

// lazy state (Companies minta)
const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "id",
    sortOrder: -1,
});

const search = ref(props.filter?.search ?? "");
const companyId = ref(props.filter?.company_id ?? null);
const status = ref(props.filter?.status ?? null);
const dateFrom = ref(props.filter?.date_from ?? null);
const dateTo = ref(props.filter?.date_to ?? null);

const statusOptions = [
    { label: "Összes", value: null },
    { label: "Draft", value: "draft" },
    { label: "Published", value: "published" },
];

let t = null;

const openCreate = () => (createOpen.value = true);

const openEditModal = (row) => {
    editWorkSchedule.value = row;
    editOpen.value = true;
};

const openDeleteModal = (row) => {
    deleteWorkSchedule.value = row;
    deleteOpen.value = true;
};

const openAssignmentModal = (row) => {
    assignmentWorkSchedule.value = row;
    assignmentOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchWorkSchedules();
    toast.add({ severity: "success", summary: "Siker", detail: msg, life: 2000 });
};

const onDeleted = async (msg = "Törölve.") => {
    // ha a törölt benne volt a selection-ben, vegyük ki
    if (deleteWorkSchedule.value?.id) {
        selected.value = (selected.value ?? []).filter(
            (x) => x.id !== deleteWorkSchedule.value.id
        );
    }
    deleteWorkSchedule.value = null;
    await fetchWorkSchedules();
    toast.add({ severity: "success", summary: "Siker", detail: msg, life: 2000 });
};

const onAutoPlanGenerated = async () => {
    await fetchWorkSchedules();
};

const onSearchInput = () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchWorkSchedules();
    }, 300);
};

const buildQuery = () => {
    const order = lazy.value.sortOrder === 1 ? "asc" : "desc";

    const q = {
        ...(props.filter ?? {}),
        page: lazy.value.page + 1,
        per_page: lazy.value.rows,
        field: lazy.value.sortField,
        order,
        search: search.value?.trim() || "",
        company_id: companyId.value,
        status: status.value,
        date_from: toYmd(dateFrom.value),
        date_to: toYmd(dateTo.value),
    };

    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
    });

    return new URLSearchParams(q).toString();
};

const fetchWorkSchedules = async () => {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/work_schedules/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();

        rows.value = json.data ?? [];
        totalRecords.value = json.meta?.total ?? 0;
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchWorkSchedules();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchWorkSchedules();
};

const resetToFirstPageAndFetch = () => {
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchWorkSchedules();
};

const onCompanyChange = (value) => {
    companyId.value = value;
    resetToFirstPageAndFetch();
};

const onStatusChange = (value) => {
    status.value = value;
    resetToFirstPageAndFetch();
};

const onDateFromChange = (value) => {
    dateFrom.value = value;
    resetToFirstPageAndFetch();
};

const onDateToChange = (value) => {
    dateTo.value = value;
    resetToFirstPageAndFetch();
};

const confirmBulkDelete = () => {
    const all = selected.value ?? [];
    if (!all.length) return;

    const deletable = all.filter((x) => !isPublished(x)).map((x) => x.id);
    const blocked = all.filter((x) => isPublished(x)).length;

    if (!deletable.length) {
        toast.add({
            severity: "warn",
            summary: "Nem törölhető",
            detail: "Published státuszú beosztás nem törölhető.",
            life: 3500,
        });
        return;
    }

    const msg =
        blocked > 0
            ? `A kijelöltek közül ${blocked} db published, azokat kihagyom. Biztos törlöd a maradék ${deletable.length} db-ot?`
            : `Biztos törlöd a kijelölt ${deletable.length} beosztást?`;

    confirm.require({
        message: msg,
        header: "Bulk törlés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => bulkDelete(deletable),
    });
};

const bulkDelete = async (ids) => {
    actionLoading.value = true;

    try {
        const res = await csrfFetch(`/work_schedules/destroy_bulk`, {
            method: "DELETE",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
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
        await fetchWorkSchedules();
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

const statusBadgeClass = (s) => {
    if (s === "published") return "bg-green-100 text-green-800";
    return "bg-gray-100 text-gray-800";
};

onMounted(fetchWorkSchedules);
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <!-- CREATE -->
    <CreateModal v-model="createOpen" @saved="onSaved" :canCreate="canCreate" />

    <!-- EDIT -->
    <EditModal
        v-model="editOpen"
        :workSchedule="editWorkSchedule"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <!-- DELETE (külön modal) -->
    <DeleteModal
        v-model="deleteOpen"
        :workSchedule="deleteWorkSchedule"
        :canDelete="canDelete"
        @deleted="onDeleted"
    />

    <AssignmentModal
        v-model="assignmentOpen"
        :workSchedule="assignmentWorkSchedule"
        :canAssign="canAssign"
        @saved="() => onSaved('Hozzárendelés mentve.')"
    />

    <AutoPlanWizardDialog
        v-model="autoPlanOpen"
        @generated="onAutoPlanGenerated"
    />

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold">{{ title }}</h1>

                        <!-- CREATE -->
                        <Button
                            v-if="canCreate"
                            label="Új beosztás"
                            icon="pi pi-plus"
                            size="small"
                            @click="openCreate"
                            data-testid="work_schedules-create"
                        />

                        <Button
                            v-if="canAutoPlan"
                            label="AutoPlan"
                            icon="pi pi-cog"
                            severity="contrast"
                            size="small"
                            @click="autoPlanOpen = true"
                        />

                        <Button
                            label="Frissítés"
                            icon="pi pi-refresh"
                            severity="secondary"
                            size="small"
                            :disabled="loading || actionLoading"
                            :loading="loading"
                            @click="fetchWorkSchedules"
                            data-testid="work_schedules-refresh"
                        />
                        <!-- BULK DELETE -->
                        <Button
                            v-if="canDeleteAny"
                            label="Kijelöltek törlése"
                            icon="pi pi-trash"
                            severity="danger"
                            size="small"
                            :disabled="!selected?.length || actionLoading || loading"
                            :loading="actionLoading"
                            @click="confirmBulkDelete"
                            data-testid="work_schedules-bulk-delete"
                        />

                        <div v-if="selected?.length" class="text-sm text-gray-600">
                            Kijelölve: <b>{{ selected.length }}</b>
                        </div>
                    </div>

                    <span class="p-input-icon-left">
                        <i class="pi pi-search" />
                        <InputText
                            v-model="search"
                            placeholder="Keresés..."
                            class="w-64"
                            @input="onSearchInput"
                        />
                    </span>
                </div>

                <!-- FILTER BAR -->
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Cég</label>
                        <CompanySelector
                            :modelValue="companyId"
                            @update:modelValue="onCompanyChange"
                            placeholder="Összes"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Státusz</label>
                        <Select
                            class="w-full"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            :modelValue="status"
                            @update:modelValue="onStatusChange"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Dátum -tól</label>
                        <DatePicker
                            class="w-full"
                            dateFormat="yy-mm-dd"
                            showIcon
                            :modelValue="dateFrom"
                            @update:modelValue="onDateFromChange"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Dátum -ig</label>
                        <DatePicker
                            class="w-full"
                            dateFormat="yy-mm-dd"
                            showIcon
                            :modelValue="dateTo"
                            @update:modelValue="onDateToChange"
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
                v-model:selection="selected"
                :value="rows"
                dataKey="id"
                lazy
                paginator
                :rows="lazy.rows"
                :first="lazy.first"
                :totalRecords="totalRecords"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading || actionLoading"
                sortMode="single"
                :sortField="lazy.sortField"
                :sortOrder="lazy.sortOrder"
                @page="onPage"
                @sort="onSort"
                selectionMode="multiple"
            >
                <template #empty> Nincs találat. </template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column field="name" header="Név" sortable />

                <Column field="date_from" header="Kezdés" sortable style="width: 140px" />
                <Column field="date_to" header="Vége" sortable style="width: 140px" />

                <Column field="status" header="Státusz" sortable style="width: 140px">
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center rounded px-2 py-1 text-xs"
                            :class="statusBadgeClass(data.status)"
                            :title="
                                data.status === 'published'
                                    ? 'Published: törlés tiltva'
                                    : ''
                            "
                        >
                            {{ data.status }}
                        </span>
                    </template>
                </Column>

                <Column
                    field="create_at"
                    header="Létrehozva"
                    sortable
                    style="width: 180px"
                >
                    <template #body="{ data }">
                        {{ toYmd(data.created_at) }}
                    </template>
                </Column>

                <Column header="Műveletek" style="width: 120px">
                    <template #body="{ data }">
                        <Button
                            icon="pi pi-ellipsis-v"
                            severity="secondary"
                            text
                            rounded
                            :disabled="actionLoading"
                            @click="(e) => openRowMenu(e, data)"
                            data-testid="work_schedules-row-menu"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
