<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

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

import CreateModal from "@/Pages/WorkShifts/CreateModal.vue";
import EditModal from "@/Pages/WorkShifts/EditModal.vue";
import AssignmentModal from "@/Pages/WorkShifts/AssignmentModal.vue";

import WorkShiftService from "@/services/WorkShiftService.js";
import { usePermissions } from "@/composables/usePermissions";

const { has } = usePermissions();

const props = defineProps({
    title: String,
    filter: Object,
});

const canCreate = computed(() => has("work_shifts.create"));
const canUpdate = computed(() => has("work_shifts.update"));
const canDelete = computed(() => has("work_shifts.delete"));
const canAssignEmployee = computed(() => has("work_shifts.update"));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value || canAssignEmployee.value);

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editShift = ref(null);
const assignmentOpen = ref(false);
const assignmentShift = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);
const selected = ref([]);

const rowMenu = ref();
const rowMenuModel = ref([]);

const lazy = ref({
    first: 0,
    rows: Number(props.filter?.per_page ?? 10),
    page: Math.max(Number(props.filter?.page ?? 1) - 1, 0),
    sortField: props.filter?.field ?? "id",
    sortOrder: props.filter?.order === "asc" ? 1 : -1,
});
lazy.value.first = lazy.value.page * lazy.value.rows;

const search = ref(props.filter?.search ?? "");
let t = null;

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

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchWorkShifts();
    toast.add({ severity: "success", summary: "Siker", detail: msg, life: 2000 });
};

const onSearchInput = () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchWorkShifts();
    }, 300);
};

const buildParams = () => {
    const order = lazy.value.sortOrder === 1 ? "asc" : "desc";

    const params = {
        page: lazy.value.page + 1,
        per_page: lazy.value.rows,
        field: lazy.value.sortField,
        order,
        search: search.value?.trim() || undefined,
    };

    Object.keys(params).forEach((k) => {
        if (params[k] === null || params[k] === undefined || params[k] === "") {
            delete params[k];
        }
    });

    return params;
};

const syncPagination = (meta) => {
    const currentPage = Number(meta?.current_page ?? lazy.value.page + 1);
    const perPage = Number(meta?.per_page ?? lazy.value.rows);
    const total = Number(meta?.total ?? 0);

    lazy.value.page = Math.max(currentPage - 1, 0);
    lazy.value.rows = perPage > 0 ? perPage : lazy.value.rows;
    lazy.value.first = lazy.value.page * lazy.value.rows;
    totalRecords.value = total;
};

const fetchWorkShifts = async () => {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await WorkShiftService.getWorkShifts(buildParams());
        rows.value = Array.isArray(data?.data) ? data.data : [];
        syncPagination(data?.meta ?? {});
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchWorkShifts();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchWorkShifts();
};

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
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

    rowMenu.value.toggle(event);
};

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
            detail: e?.response?.data?.message || e?.message || "Ismeretlen hiba",
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
            detail: e?.response?.data?.message || e?.message || "Ismeretlen hiba",
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(fetchWorkShifts);
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

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
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
                        :disabled="!selected?.length || actionLoading || loading"
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                        data-testid="work_shifts-bulk-delete"
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

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">Hiba</div>
                <div class="text-sm">{{ error }}</div>
            </div>

            <Menu v-if="canAnyRowAction" ref="rowMenu" :model="rowMenuModel" popup />

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
                <template #empty>Nincs találat.</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column field="name" header="Név" sortable />
                <Column field="start_time" header="Kezdés" sortable style="width: 120px" />
                <Column field="end_time" header="Vége" sortable style="width: 120px" />
                <Column field="work_time_minutes" header="Munkaidő" sortable style="width: 130px" />
                <Column field="break_minutes" header="Szünet" sortable style="width: 120px" />
                <Column field="created_at" header="Létrehozva" sortable style="width: 190px">
                    <template #body="{ data }">
                        {{ formatCreatedAt(data.created_at) }}
                    </template>
                </Column>
                <Column field="active" header="Aktív" sortable style="width: 120px">
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
                </Column>

                <Column
                    v-if="canAnyRowAction"
                    header="Műveletek"
                    headerStyle="width: 120px"
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
                                :title="`Műveletek: ${data.name}`"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
