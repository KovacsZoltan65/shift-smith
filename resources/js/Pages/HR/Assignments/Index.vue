<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AssignmentModal from "@/Pages/WorkSchedules/AssignmentModal.vue";
import { usePermissions } from "@/composables/usePermissions";

import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

const toast = useToast();
const { has } = usePermissions();

const canViewSchedules = has("work_schedules.viewAny");
const canAssign = has("work_schedule_assignments.create");
const canUpdate = has("work_schedule_assignments.update");
const canDelete = has("work_schedule_assignments.delete");
const canBulkDelete = has("work_schedule_assignments.bulkDelete");
const canViewAssignmentsAny = has("work_schedule_assignments.viewAny");
const canViewAssignments = has("work_schedule_assignments.view");
const canOpenAssignments = canViewAssignmentsAny || canViewAssignments || canAssign || canUpdate || canDelete;

const loading = ref(false);
const rows = ref([]);
const totalRecords = ref(0);
const search = ref("");
const error = ref(null);

const assignmentOpen = ref(false);
const selectedSchedule = ref(null);

const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "id",
    sortOrder: -1,
});

let searchTimer = null;

const buildQuery = () => {
    const order = lazy.value.sortOrder === 1 ? "asc" : "desc";

    const query = {
        page: lazy.value.page + 1,
        per_page: lazy.value.rows,
        field: lazy.value.sortField,
        order,
        search: search.value?.trim() || "",
    };

    Object.keys(query).forEach((key) => {
        if (query[key] === "" || query[key] === null || query[key] === undefined) {
            delete query[key];
        }
    });

    return new URLSearchParams(query).toString();
};

const fetchSchedules = async () => {
    if (!canViewSchedules) {
        rows.value = [];
        totalRecords.value = 0;
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/work_schedules/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const json = await response.json();
        rows.value = json?.data ?? [];
        totalRecords.value = json?.meta?.total ?? 0;
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error.value,
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchSchedules();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchSchedules();
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchSchedules();
    }, 300);
};

const openAssignments = (row) => {
    selectedSchedule.value = row;
    assignmentOpen.value = true;
};

onMounted(fetchSchedules);
</script>

<template>
    <Head title="Kiosztások" />

    <Toast />

    <AssignmentModal
        v-model="assignmentOpen"
        :workSchedule="selectedSchedule"
        :canAssign="canAssign"
        :canUpdate="canUpdate"
        :canDelete="canDelete"
        :canBulkDelete="canBulkDelete"
    />

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">Kiosztások</h1>
                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :loading="loading"
                        @click="fetchSchedules"
                    />
                </div>

                <span class="p-input-icon-left">
                    <i class="pi pi-search" />
                    <InputText
                        v-model="search"
                        placeholder="Beosztás keresése..."
                        class="w-72"
                        @input="onSearchInput"
                    />
                </span>
            </div>

            <div v-if="!canViewSchedules" class="rounded border border-amber-200 bg-amber-50 p-3 text-amber-800">
                Nincs jogosultságod a beosztások listázásához.
            </div>

            <DataTable
                v-else
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
                <template #empty>Nincs találat.</template>

                <Column field="name" header="Beosztás" sortable />
                <Column field="date_from" header="Kezdés" sortable style="width: 140px" />
                <Column field="date_to" header="Vége" sortable style="width: 140px" />
                <Column field="status" header="Státusz" sortable style="width: 130px" />

                <Column header="Kiosztások" style="width: 170px">
                    <template #body="{ data }">
                        <Button
                            label="Megnyitás"
                            icon="pi pi-users"
                            size="small"
                            :disabled="!canOpenAssignments"
                            @click="openAssignments(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
