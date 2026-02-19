<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workPattern: { type: Object, default: null },
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
    const name = props.workPattern?.name ? `: ${props.workPattern.name}` : "";
    return `Hozzárendelt dolgozók${name}`;
});

const load = async () => {
    const id = Number(props.workPattern?.id ?? 0);
    if (!id) {
        rows.value = [];
        return;
    }

    loading.value = true;
    try {
        const res = await fetch(`/work-patterns/${id}/employees`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        rows.value = Array.isArray(json?.data) ? json.data : [];
    } catch (e) {
        rows.value = [];
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "A dolgozók listája nem tölthető.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => [visible.value, props.workPattern?.id],
    async ([isVisible]) => {
        if (!isVisible) return;
        // Minden megnyitáskor friss listát töltünk, hogy a modal mindig naprakész legyen.
        await load();
    }
);
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="title"
        :style="{ width: '64rem' }"
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
            <Column field="name" header="Név" />
            <Column field="email" header="Email">
                <template #body="{ data }">{{ data.email || "-" }}</template>
            </Column>
            <Column field="phone" header="Telefon">
                <template #body="{ data }">{{ data.phone || "-" }}</template>
            </Column>
            <Column field="date_from" header="Ettől" style="width: 130px" />
            <Column field="date_to" header="Eddig" style="width: 130px">
                <template #body="{ data }">{{ data.date_to || "jelenleg" }}</template>
            </Column>
            <Column field="is_primary" header="Elsődleges" style="width: 120px">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_primary ? 'Igen' : 'Nem'"
                        :severity="data.is_primary ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
        </DataTable>
    </Dialog>
</template>
