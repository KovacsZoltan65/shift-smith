<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

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

import CreateModal from "@/Pages/Companies/CreateModal.vue";
import EditModal from "@/Pages/Companies/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";
const { has } = usePermissions();

const props = defineProps({
    title: String,
    filter: Object,
    endpointBase: {
        type: String,
        default: "/companies",
    },
    permissionPrefix: {
        type: String,
        default: "companies",
    },
    hqBadge: {
        type: String,
        default: "",
    },
    fetchRouteName: {
        type: String,
        default: "",
    },
    detailRouteName: {
        type: String,
        default: "",
    },
    forbiddenRedirectRouteName: {
        type: String,
        default: "",
    },
});

const canCreate = computed(() => has(`${props.permissionPrefix}.create`));
const canUpdate = computed(() => has(`${props.permissionPrefix}.update`));
const canDelete = computed(() => has(`${props.permissionPrefix}.delete`));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editCompany = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const forbiddenHandled = ref(false);
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
            disabled: actionLoading.value || !canUpdate.value,
            command: () => openEditModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete.value,
            command: () => confirmDeleteOne(row),
        },
    ];

    rowMenu.value.toggle(event);
};
// ------------------------

// lazy state (Users minta)
const globalFilterFields = ["name", "email", "phone", "active"];
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
    editCompany.value = row;
    editOpen.value = true;
};

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchCompanies();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: msg,
        life: 2000,
    });
};

const fetchUrl = () => {
    const query = {
        page: 1,
        per_page: 100,
        field: "name",
        order: "asc",
    };

    if (props.fetchRouteName) {
        return route(props.fetchRouteName, query);
    }

    return `${props.endpointBase}/fetch?${new URLSearchParams(query).toString()}`;
};

const resolveDetailUrl = (id) => {
    if (props.detailRouteName) {
        return route(props.detailRouteName, id);
    }

    return `${props.endpointBase}/${id}`;
};

const handleForbidden = () => {
    if (forbiddenHandled.value) return;
    forbiddenHandled.value = true;

    toast.add({
        severity: "warn",
        summary: "Nincs jogosultság",
        detail: "A HQ cégek megtekintéséhez superadmin jogosultság szükséges.",
        life: 3500,
    });

    if (props.forbiddenRedirectRouteName) {
        setTimeout(() => {
            window.location.assign(route(props.forbiddenRedirectRouteName));
        }, 250);
    }
};

const fetchCompanies = async () => {
    if (forbiddenHandled.value) return;

    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(fetchUrl(), {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (res.status === 403) {
            handleForbidden();
            return;
        }

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();

        // Backend adapter:
        // - régi: { data: [...] }
        // - új (spatie data/egységes): { message, data: [...] } vagy { message, data: { data: [...] }, meta }
        const items = Array.isArray(json?.data)
            ? json.data
            : (json?.data?.data ?? []);

        rows.value = items;
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
        const res = await csrfFetch(resolveDetailUrl(id), {
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
            detail: "Cég törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchCompanies();
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
        message: `Biztos törlöd a kijelölt ${ids.length} céget?`,
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
            method: "DELETE",
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
        await fetchCompanies();
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
    fetchCompanies();
});
</script>

<template>
    <Head :title="props.title" />

    <Toast />
    <ConfirmDialog />

    <!-- CREATE MODAL -->
    <CreateModal v-model="createOpen" @saved="onSaved" :canCreate="canCreate" />

    <!-- EDIT MODAL -->
    <EditModal
        v-model="editOpen"
        :company="editCompany"
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

                    <!-- CREATE -->
                    <Button
                        v-if="canCreate"
                        label="Új cég"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="companies-create"
                    />

                    <!-- FRISSÍTÉS -->
                    <Button
                        label="Frissítés"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchCompanies"
                        data-testid="companies-refresh"
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
                        data-testid="companies-bulk-delete"
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

            <Menu
                v-if="canAnyRowAction"
                ref="rowMenu"
                :model="rowMenuModel"
                popup
            />

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
                selectionMode="multiple"
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
                    v-if="canAnyRowAction"
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
                                :title="`Műveletek: ${data.name}`"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
