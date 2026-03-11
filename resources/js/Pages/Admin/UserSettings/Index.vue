<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UserService from "@/services/UserService.js";
import UserSettingsService from "@/services/UserSettingsService.js";
import { usePermissions } from "@/composables/usePermissions";

import { useToast } from "primevue/usetoast";

import BulkDeleteModal from "./Partials/BulkDeleteModal.vue";
import CreateModal from "./Partials/CreateModal.vue";
import DeleteModal from "./Partials/DeleteModal.vue";
import EditModal from "./Partials/EditModal.vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    title: String,
    filter: Object,
    targetUserId: Number,
});

const page = usePage();
const { has } = usePermissions();
const toast = useToast();

const canCreate = computed(() => has("user_settings.create"));
const canUpdate = computed(() => has("user_settings.update"));
const canDelete = computed(() => has("user_settings.delete"));
const canDeleteAny = computed(() => has("user_settings.deleteAny"));
const canManageOthers = computed(() => has("user_settings.manageOthers"));
const companyName = computed(
    () => page.props.companyContext?.current_company?.name ?? trans("user_settings.placeholders.company"),
);

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const bulkDeleteOpen = ref(false);
const selected = ref([]);
const selectedItem = ref(null);
const loading = ref(false);
const rows = ref([]);
const error = ref("");
const users = ref([]);
const groupOptions = ref([]);
const dt = ref(null);

const typeOptions = [
    { label: "int", value: "int" },
    { label: "bool", value: "bool" },
    { label: "string", value: "string" },
    { label: "json", value: "json" },
];

const selectedUserId = ref(
    props.filter?.user_id ??
        props.targetUserId ??
        page.props.auth?.user?.id ??
        null,
);
const globalFilterFields = ["key", "group", "type", "value_preview"];
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    key: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    group: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    type: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
});

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        key: {
            operator: FilterOperator.AND,
            constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
        },
        group: {
            operator: FilterOperator.AND,
            constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
        },
        type: {
            operator: FilterOperator.AND,
            constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
        },
    };
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
        if (Array.isArray(entry.constraints))
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        return false;
    });
});

const fetchUsers = async () => {
    if (!canManageOthers.value) return;

    try {
        const { data } = await UserService.fetchUsersToSelect();
        users.value = (data ?? []).map((user) => ({
            label: `${user.name} (${user.email})`,
            value: user.id,
        }));
    } catch (_) {
        users.value = [];
    }
};

const fetchUserSettings = async () => {
    loading.value = true;
    error.value = "";

    try {
        const { data } = await UserSettingsService.fetch({
            user_id: selectedUserId.value || undefined,
            page: 1,
            perPage: 100,
            sortBy: "key",
            sortDir: "asc",
        });

        rows.value = data?.items ?? [];
        groupOptions.value = (data?.options?.groups ?? []).map((value) => ({
            label: value,
            value,
        }));
    } catch (err) {
        error.value =
            err?.response?.data?.message ?? err?.message ?? trans("user_settings.messages.fetch_failed");
    } finally {
        loading.value = false;
    }
};

const handleSaved = async (message) => {
    await fetchUserSettings();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: message,
        life: 2500,
    });
};

const handleDeleted = async (message) => {
    selected.value = [];
    selectedItem.value = null;
    await fetchUserSettings();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: message,
        life: 2500,
    });
};

watch(
    () => selectedUserId.value,
    () => {
        if (canManageOthers.value) fetchUserSettings();
    },
);

onMounted(async () => {
    initFilters();
    await fetchUsers();
    await fetchUserSettings();
});
</script>

<template>
    <Head :title="title" />
    <Toast />

    <CreateModal
        v-model="createOpen"
        :target-user-id="selectedUserId"
        @saved="handleSaved"
    />
    <EditModal
        v-model="editOpen"
        :user-setting-id="selectedItem?.id ?? null"
        :target-user-id="selectedUserId"
        @saved="handleSaved"
    />
    <DeleteModal
        v-model="deleteOpen"
        :item="selectedItem"
        :target-user-id="selectedUserId"
        @deleted="handleDeleted"
    />
    <BulkDeleteModal
        v-model="bulkDeleteOpen"
        :items="selected"
        :target-user-id="selectedUserId"
        @deleted="handleDeleted"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="mb-4 flex items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <Badge :value="companyName" severity="contrast" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canCreate"
                    :label="trans('user_settings.actions.create')"
                    icon="pi pi-plus"
                    @click="createOpen = true"
                />
                <Button
                    :label="trans('user_settings.actions.refresh')"
                    icon="pi pi-refresh"
                    severity="secondary"
                    :loading="loading"
                    @click="fetchUserSettings"
                />
                <Button
                    v-if="canDeleteAny"
                    :label="trans('user_settings.actions.bulk_delete')"
                    icon="pi pi-trash"
                    severity="danger"
                    :disabled="!selected.length"
                    @click="bulkDeleteOpen = true"
                />
                <Select
                    v-if="canManageOthers"
                    v-model="selectedUserId"
                    :options="users"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('user_settings.filters.user')"
                    class="w-72"
                />
            </div>

            <div
                v-if="error"
                class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"
            >
                {{ error }}
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
                :loading="loading"
                sortMode="multiple"
                removableSort
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
                responsiveLayout="scroll"
            >
                <template #header>
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="trans('user_settings.filters.clear')"
                            variant="outlined"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                :placeholder="trans('user_settings.filters.keyword_search')"
                            />
                        </IconField>
                    </div>
                </template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column
                    field="key"
                    filterField="key"
                    :header="trans('columns.key')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="trans('user_settings.filters.key')"
                        />
                    </template>
                </Column>
                <Column
                    field="group"
                    filterField="group"
                    :header="trans('columns.group')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="groupOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            :placeholder="trans('user_settings.filters.group')"
                        />
                    </template>
                </Column>
                <Column
                    field="type"
                    filterField="type"
                    :header="trans('columns.type')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Tag :value="data.type" severity="info" />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="typeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            :placeholder="trans('user_settings.filters.type')"
                        />
                    </template>
                </Column>
                <Column :header="trans('columns.value')">
                    <template #body="{ data }">
                        <span class="font-mono text-sm">{{
                            data.value_preview || "-"
                        }}</span>
                    </template>
                </Column>
                <Column field="updated_at" :header="trans('columns.updated_at')" sortable>
                    <template #body="{ data }">
                        {{
                            data.updated_at
                                ? new Date(data.updated_at).toLocaleString()
                                : "-"
                        }}
                    </template>
                </Column>
                <Column header="" headerStyle="width: 12rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="canUpdate"
                                size="small"
                                severity="secondary"
                                :label="trans('edit')"
                                @click="
                                    selectedItem = data;
                                    editOpen = true;
                                "
                            />
                            <Button
                                v-if="canDelete"
                                size="small"
                                severity="danger"
                                :label="trans('delete')"
                                @click="
                                    selectedItem = data;
                                    deleteOpen = true;
                                "
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
