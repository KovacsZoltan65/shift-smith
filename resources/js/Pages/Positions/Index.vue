<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

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
    filter: Object,
});

const title = trans("positions.title");

const toast = useToast();
const confirm = useConfirm();
const $t = trans;

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
];

const globalFilterFields = ["name", "active"];
const booleanOptions = [
    { label: trans("common.yes"), value: true },
    { label: trans("common.no"), value: false },
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

const onSaved = async (msg = trans("positions.messages.saved")) => {
    // Mentés után a lista forrásigazsága a backend, ezért újratöltjük.
    selected.value = [];
    await fetchPositions();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
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
        error.value = e?.message || trans("common.unknown_error");
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: trans("positions.dialogs.delete_confirm", { name: row.name }),
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
            let msg = trans("positions.messages.delete_failed_http", {
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
            detail: trans("positions.messages.deleted_success"),
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);
        await fetchPositions();
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

const confirmBulkDelete = () => {
    const ids = (selected.value ?? []).map((x) => x.id);
    if (!ids.length) return;

    confirm.require({
        message: trans("positions.dialogs.bulk_delete_confirm", {
            count: ids.length,
        }),
        header: trans("positions.actions.bulk_delete"),
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
            let msg = trans("positions.messages.bulk_delete_failed_http", {
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
            detail: trans("positions.messages.bulk_deleted_success", {
                count: ids.length,
            }),
            life: 2500,
        });

        selected.value = [];
        await fetchPositions();
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

// Első renderkor betöltjük az aktuális company scope adatait.
onMounted(() => {
    initFilters();
    fetchPositions();
});
</script>

<template>
    <Head :title="props.title || title" />

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
                        :label="$t('positions.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />

                    <Button
                        :label="$t('common.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchPositions"
                    />

                    <Button
                        v-if="canDelete"
                        :label="$t('positions.actions.bulk_delete')"
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
                        {{ $t("positions.selected_count", { count: selected.length }) }}
                    </div>

                    <div class="min-w-[260px]">
                        <CompanySelector
                            v-model="companyId"
                            :placeholder="$t('positions.placeholders.company')"
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
                            :label="$t('positions.filters.clear')"
                            variant="outlined"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                :placeholder="$t('positions.filters.keyword_search')"
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{ $t("common.no_results") }}</template>
                <template #loading>{{ $t("common.loading") }}</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="id" :header="$t('columns.id')" sortable style="width: 90px" />
                <Column
                    field="name"
                    filterField="name"
                    :header="$t('columns.name')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('positions.filters.name')"
                        />
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
                            {{ data.active ? $t("common.yes") : $t("common.no") }}
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
                            :placeholder="$t('positions.filters.status')"
                        />
                    </template>
                </Column>

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
                                :buttonTitle="$t('columns.actions')"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
