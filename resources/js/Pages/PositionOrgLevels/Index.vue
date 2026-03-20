<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import { trans } from "laravel-vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "primevue/usetoast";
import PositionOrgLevelService from "@/services/PositionOrgLevelService.js";

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    org_levels: { type: Array, default: () => [] },
});

const title = trans("position_org_levels.title");
const toast = useToast();
const $t = trans;
const loading = ref(false);
const rows = ref([]);
const editOpen = ref(false);
const editing = ref(null);
const form = ref({
    position_label: "",
    org_level: "staff",
    active: true,
});
const q = ref(props.filter?.q || "");
const orgLevelFilter = ref(props.filter?.org_level || null);

const load = async () => {
    loading.value = true;
    try {
        const { data } = await PositionOrgLevelService.getMappings({
            q: q.value || undefined,
            org_level: orgLevelFilter.value || undefined,
            per_page: 100,
            page: 1,
        });
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editing.value = null;
    form.value = { position_label: "", org_level: "staff", active: true };
    editOpen.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    form.value = {
        position_label: row.position_label,
        org_level: row.org_level,
        active: !!row.active,
    };
    editOpen.value = true;
};

const save = async () => {
    const payload = {
        position_label: form.value.position_label,
        org_level: form.value.org_level,
        active: !!form.value.active,
    };

    if (editing.value?.id) {
        await PositionOrgLevelService.updateMapping(editing.value.id, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("position_org_levels.messages.updated_success"),
            life: 2000,
        });
    } else {
        await PositionOrgLevelService.storeMapping(payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("position_org_levels.messages.created_success"),
            life: 2000,
        });
    }

    editOpen.value = false;
    await load();
};

const destroyRow = async (row) => {
    await PositionOrgLevelService.deleteMapping(row.id);
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: trans("position_org_levels.messages.deleted_success"),
        life: 2000,
    });
    await load();
};

onMounted(load);
</script>

<template>
    <Head :title="title" />
    <Toast />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex items-center gap-2">
                <Button
                    :label="$t('position_org_levels.actions.create')"
                    icon="pi pi-plus"
                    @click="openCreate"
                />
                <InputText
                    v-model="q"
                    :placeholder="$t('position_org_levels.filters.search')"
                />
                <Select
                    v-model="orgLevelFilter"
                    :options="org_levels"
                    :placeholder="$t('position_org_levels.fields.org_level')"
                    showClear
                />
                <Button
                    :label="$t('position_org_levels.actions.filter')"
                    severity="secondary"
                    @click="load"
                />
            </div>

            <DataTable :value="rows" :loading="loading" dataKey="id">
                <Column
                    field="position_label"
                    :header="$t('columns.position')"
                />
                <Column field="position_key" :header="$t('columns.key')" />
                <Column
                    field="org_level"
                    :header="$t('position_org_levels.fields.org_level')"
                />
                <Column :header="$t('columns.active')">
                    <template #body="{ data }">
                        <Checkbox :modelValue="!!data.active" binary disabled />
                    </template>
                </Column>
                <Column header="">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                size="small"
                                icon="pi pi-pencil"
                                text
                                @click="openEdit(data)"
                            />
                            <Button
                                size="small"
                                icon="pi pi-trash"
                                text
                                severity="danger"
                                @click="destroyRow(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>

    <Dialog
        v-model:visible="editOpen"
        modal
        :header="$t('position_org_levels.dialogs.edit_title')"
        :style="{ width: '520px' }"
    >
        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-sm">{{
                    $t("position_org_levels.fields.position_label")
                }}</label>
                <InputText v-model="form.position_label" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-sm">{{
                    $t("position_org_levels.fields.org_level")
                }}</label>
                <Select
                    v-model="form.org_level"
                    :options="org_levels"
                    class="w-full"
                />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.active" binary />
                <span>{{ $t("columns.active") }}</span>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <Button
                    :label="$t('common.cancel')"
                    text
                    @click="editOpen = false"
                />
                <Button :label="$t('common.save')" @click="save" />
            </div>
        </div>
    </Dialog>
</template>
