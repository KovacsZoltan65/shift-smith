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
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import CreateModal from "@/Pages/Scheduling/WorkPatterns/CreateModal.vue";
import EditModal from "@/Pages/Scheduling/WorkPatterns/EditModal.vue";
import { csrfFetch } from "@/lib/csrfFetch";
import { usePermissions } from "@/composables/usePermissions";

const { has } = usePermissions();

const canCreate = has("work_patterns.create");
const canUpdate = has("work_patterns.update");
const canDelete = has("work_patterns.delete");
const canBulkDelete = has("work_patterns.bulkDelete");

const props = defineProps({
    title: { type: String, default: "Munkarendek" },
    filter: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editWorkPattern = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);
const selected = ref([]);

const companyId = ref(props.filter?.company_id ?? null);
const search = ref(props.filter?.search ?? "");
let searchTimer = null;

const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "id",
    sortOrder: -1,
});

const rowMenu = ref();
const rowMenuModel = ref([]);

const typeLabel = (type) => {
    if (type === "fixed_weekly") return "Fix heti";
    if (type === "rotating_shifts") return "Rotációs";
    return "Egyedi";
};

const typeSeverity = (type) => {
    if (type === "fixed_weekly") return "info";
    if (type === "rotating_shifts") return "warn";
    return "secondary";
};

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editWorkPattern.value = row;
    editOpen.value = true;
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
    selected.value = [];
    await fetchWorkPatterns();
    toast.add({ severity: "success", summary: "Siker", detail: message, life: 2500 });
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchWorkPatterns();
    }, 300);
};

const onCompanyChanged = () => {
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchWorkPatterns();
};

const buildQuery = () => {
    const order = lazy.value.sortOrder === 1 ? "asc" : "desc";
    const q = {
        page: lazy.value.page + 1,
        per_page: lazy.value.rows,
        field: lazy.value.sortField,
        order,
        search: search.value?.trim() || "",
        company_id: companyId.value || "",
    };

    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
    });

    return new URLSearchParams(q).toString();
};

const fetchWorkPatterns = async () => {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/work-patterns/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        rows.value = Array.isArray(json?.data) ? json.data : json?.data?.data ?? [];
        totalRecords.value = Number(json?.meta?.total ?? 0);
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
    fetchWorkPatterns();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchWorkPatterns();
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
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body?.message || `Törlés sikertelen (HTTP ${res.status})`);
        }

        selected.value = selected.value.filter((x) => x.id !== id);
        toast.add({ severity: "success", summary: "Siker", detail: "Munkarend törölve", life: 2500 });
        await fetchWorkPatterns();
    } catch (e) {
        toast.add({ severity: "error", summary: "Hiba", detail: e?.message || "Ismeretlen hiba", life: 3500 });
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
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ ids }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body?.message || `Bulk törlés sikertelen (HTTP ${res.status})`);
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
        toast.add({ severity: "error", summary: "Hiba", detail: e?.message || "Ismeretlen hiba", life: 3500 });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(fetchWorkPatterns);
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

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
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
                        :disabled="!selected?.length || loading || actionLoading"
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
                <template #empty>Nincs találat.</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column field="name" header="Név" sortable />

                <Column field="type" header="Típus" sortable>
                    <template #body="{ data }">
                        <Tag :value="typeLabel(data.type)" :severity="typeSeverity(data.type)" />
                    </template>
                </Column>

                <Column field="weekly_minutes" header="Heti perc" sortable />

                <Column field="active" header="Aktív" sortable style="width: 120px">
                    <template #body="{ data }">
                        <Tag
                            :value="data.active ? 'Aktív' : 'Inaktív'"
                            :severity="data.active ? 'success' : 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="Műveletek" headerStyle="width: 3rem" bodyStyle="white-space: nowrap;">
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
