<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import LeaveCategoryFields from "@/Pages/Admin/LeaveCategories/Partials/LeaveCategoryFields.vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const saving = ref(false);
const errors = ref({});
const form = ref({
    name: "",
    description: "",
    active: true,
    order_index: 0,
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        description: "",
        active: true,
        order_index: 0,
    };
};

watch(() => open.value, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

const close = () => {
    open.value = false;
};

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        await LeaveCategoryService.store({
            name: String(form.value.name ?? "").trim(),
            description: String(form.value.description ?? "").trim() || null,
            active: !!form.value.active,
            order_index: Number(form.value.order_index ?? 0),
        });
        emit("saved", "Szabadsag kategoria letrehozva.");
        close();
    } catch (error) {
        errors.value = LeaveCategoryService.extractErrors(error) ?? {
            _global: error?.response?.data?.message ?? error?.message ?? "Mentes sikertelen.",
        };
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Uj szabadsag kategoria"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <LeaveCategoryFields v-model="form" :errors="errors" :disabled="saving" />

        <div v-if="errors?._global" class="mt-3 text-sm text-red-600">{{ errors._global }}</div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Megse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentes"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !canCreate"
                    data-testid="leave-category-create-save"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
