<script setup>
import { computed, ref, watch } from "vue";


import LeaveTypeFields from "@/Pages/Admin/LeaveTypes/Partials/LeaveTypeFields.vue";
import LeaveTypeService from "@/services/LeaveTypeService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
    categoryOptions: { type: Array, default: () => [] },
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
    category: "leave",
    affects_leave_balance: true,
    requires_approval: true,
    active: true,
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        category: "leave",
        affects_leave_balance: true,
        requires_approval: true,
        active: true,
    };
};

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            reset();
        }
    },
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        await LeaveTypeService.store({
            name: String(form.value.name ?? "").trim(),
            category: form.value.category,
            affects_leave_balance: !!form.value.affects_leave_balance,
            requires_approval: !!form.value.requires_approval,
            active: !!form.value.active,
        });
        emit("saved", "Szabadsag tipus letrehozva.");
        close();
    } catch (error) {
        errors.value = LeaveTypeService.extractErrors(error) ?? {
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
        header="Uj szabadsag tipus"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <LeaveTypeFields
            v-model="form"
            :errors="errors"
            :disabled="saving"
            :categoryOptions="categoryOptions"
        />

        <div v-if="errors?._global" class="mt-3 text-sm text-red-600">{{ errors._global }}</div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Megse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentes"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !canCreate"
                    data-testid="leave-type-create-save"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
