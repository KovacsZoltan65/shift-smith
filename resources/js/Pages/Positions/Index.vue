<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

import CreateModal from "@/Pages/Positions/CreateModal.vue";
import EditModal from "@/Pages/Positions/EditModal.vue";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";

/**
 * Positions index oldal.
 *
 * A pozíciók listázását végzi company scope szerint, PrimeVue DataTable
 * szűréssel és CRUD dialógusokkal. A kiválasztott company minden fetch és
 * törlési művelet implicit bemenete.
 */

const { has } = usePermissions();
const canCreate = has("positions.create");
const canUpdate = has("positions.update");
const canDelete = has("positions.delete");

const props = defineProps({
    title: String,
    filter: Object,
});

const toast = useToast();
const confirm = useConfirm();

// Modal állapotok és szerkesztendő rekord.
const createOpen = ref(false);
const editOpen = ref(false);
const editPosition = ref(null);

// Táblázat betöltési / művelet állapotok.
const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

// Lista és kijelölés állapot.
const rows = ref([]);
const selected = ref([]);
// A tenant scope kulcsa: fetch/delete hívások mindig ehhez a céghez mennek.
const companyId = ref(props.filter?.company_id ?? null);

// Sor műveletek
const buildRowMenuItems = (row) => [
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

const globalFilterFields = ["name", "active"];
const booleanOptions = [
    { label: "Igen", value: true },
    { label: "Nem", value: false },
];

// DataTable szűrő definíciók
const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
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
    editPosition.value = row;
    editOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    // Mentés után a lista forrásigazsága a backend, ezért újratöltjük.
    selected.value = [];
    await fetchPositions();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: msg,
        life: 2000,
    });
};

const onCompanyChanged = () => {
    initFilters();
    fetchPositions();
};

const buildQuery = () => {
    const q = {
        page: 1,
        per_page: 100,
        field: "name",
        order: "asc",
        company_id: companyId.value || "",
    };

    // Csak értelmes paramétereket küldünk a query-ben.
    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
    });

    return new URLSearchParams(q).toString();
};

const fetchPositions = async () => {
    // Cég nélkül nem kérdezünk le adatot.
    if (!companyId.value) {
        rows.value = [];
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/positions/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
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
        const res = await csrfFetch(`/positions/${id}`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            body: JSON.stringify({ company_id: Number(companyId.value) }),
        });

        if (!res.ok) {
            // Backend hibaüzenetet preferáljuk, ha érkezik.
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
            detail: "Pozíció törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);
        await fetchPositions();
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
        message: `Biztos törlöd a kijelölt ${ids.length} pozíciót?`,
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
        const res = await csrfFetch(`/positions/destroy_bulk`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ ids, company_id: Number(companyId.value) }),
        });

        if (!res.ok) {
            // Backend hibaüzenetet preferáljuk, ha érkezik.
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
        await fetchPositions();
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

// Első renderkor betöltjük az aktuális company scope adatait.
onMounted(() => {
    initFilters();
    fetchPositions();
});
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <CreateModal
        v-model="createOpen"
        :companyId="companyId"
        @saved="onSaved"
        :canCreate="canCreate"
    />

    <EditModal
        v-model="editOpen"
        :position="editPosition"
        :companyId="companyId"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        v-if="canCreate"
                        label="Új pozíció"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />

                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchPositions"
                    />

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

                <Column
                    header="Műveletek"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                buttonTitle="Műveletek"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
