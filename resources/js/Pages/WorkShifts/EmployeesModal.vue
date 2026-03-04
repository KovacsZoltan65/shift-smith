<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import { useToast } from "primevue/usetoast";

import WorkShiftAssignmentService from "@/services/WorkShiftAssignmentService";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workShift: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();

const loading = ref(false);
const rows = ref([]);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const title = computed(() => {
    const name = props.workShift?.name ? `: ${props.workShift.name}` : "";
    return `Hozzárendelt dolgozók${name}`;
});

const load = async () => {
    const id = Number(props.workShift?.id ?? 0);
    if (!id) {
        rows.value = [];
        return;
    }

    loading.value = true;
    try {
        const response = await WorkShiftAssignmentService.list(id);
        rows.value = Array.isArray(response?.data?.data) ? response.data.data : [];
    } catch (e) {
        rows.value = [];
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || e?.message || "A dolgozók listája nem tölthető.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => [visible.value, props.workShift?.id],
    async ([isVisible]) => {
        if (!isVisible) return;
        await load();
    }
);
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="title"
        :style="{ width: '56rem' }"
    >
        <div class="mb-3 flex justify-end">
            <Button
                label="Frissítés"
                icon="pi pi-refresh"
                severity="secondary"
                size="small"
                :disabled="loading"
                :loading="loading"
                @click="load"
            />
        </div>

        <DataTable :value="rows" dataKey="id" :loading="loading" size="small">
            <template #empty>Nincs hozzárendelt dolgozó.</template>

            <Column field="employee_id" header="Dolgozó ID" style="width: 120px" />
            <Column field="employee_name" header="Dolgozó" />
            <Column field="work_pattern_name" header="Munkarend">
                <template #body="{ data }">
                    {{ data.work_pattern_name || data.work_schedule_name || "-" }}
                </template>
            </Column>
            <Column field="date" header="Nap" style="width: 130px" />
            <Column field="created_at" header="Létrehozva" />
        </DataTable>
    </Dialog>
</template>
