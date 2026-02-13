<script setup>
import { onMounted, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";

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

import CreateModal from "@/Pages/HR/Employees/CreateModal.vue";
import EditModal from "@/Pages/HR/Employees/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

const page = usePage();

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();
const canCreate = has("employees.create");
const canUpdate = has("employees.update");
const canDelete = has("employees.delete");

const props = defineProps({
    title: { type: String, default: "Dolgozók" },
    filter: { type: Object, default: () => ({}) },
    default_company_id: { type: [Number, String, null], default: null }, // backend index adja
});

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editEmployee = ref(null);

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
    ];

    rowMenu.value.toggle(event);
};
// ------------------------

// lazy state (Companies/Users minta)
const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "id",
    sortOrder: -1,
});

const search = ref(props.filter?.search ?? "");

// Company filter (CompanySelector)
const companyId = ref(
    props.filter?.company_id ??
        (props.default_company_id ? Number(props.default_company_id) : null)
);

let t = null;

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editEmployee.value = row;
    editOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    createOpen.value = false;
    editOpen.value = false;

    selected.value = [];
    await fetchEmployees();
    toast.add({ severity: "success", summary: "Siker", detail: msg, life: 2000 });
};

const onSearchInput = () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchEmployees();
    }, 300);
};

const onCompanyChanged = () => {
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchEmployees();
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
        const res = await fetch(`/employees/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const out = normalizeFetchPayload(json);

        rows.value = out.rows;
        totalRecords.value = out.total;
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
    fetchEmployees();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchEmployees();
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
        const res = await csrfFetch(`/employees/${id}`, {
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
        const res = await csrfFetch(`/employees/destroy_bulk`, {
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

onMounted(fetchEmployees);
</script>

<template>
    <Head title="Dolgozók" />

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

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

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

                    <!-- BULK DELETE -->
                    <Button
                        v-if="canDelete"
                        label="Kijelöltek törlése"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="!selected?.length || actionLoading || loading"
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                        data-testid="employees-bulk-delete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        Kijelölve: <b>{{ selected.length }}</b>
                    </div>

                    <!-- CompanySelector -->
                    <div class="min-w-[260px]">
                        <CompanySelector
                            v-model="companyId"
                            placeholder="Cég szűrő..."
                            @update:modelValue="onCompanyChanged"
                            :only-with-employees="true"
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
                <template #empty> Nincs találat. </template>

                <!-- checkbox oszlop -->
                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column field="id" header="ID" sortable style="width: 90px" />

                <Column field="name" header="Név" sortable>
                    <template #body="{ data }">
                        <div class="font-medium">
                            {{
                                data.name ??
                                `${data.first_name ?? ""} ${data.last_name ?? ""}`.trim()
                            }}
                        </div>
                        <div v-if="data.position" class="text-xs text-gray-500">
                            {{ data.position }}
                        </div>
                    </template>
                </Column>

                <Column field="email" header="Email" sortable />
                <Column field="phone" header="Telefon" sortable />
                <Column field="hired_at" header="Belépés" sortable style="width: 140px">
                    <template #body="{ data }">
                        <span class="text-sm">
                            {{ data.hired_at ?? "-" }}
                        </span>
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
