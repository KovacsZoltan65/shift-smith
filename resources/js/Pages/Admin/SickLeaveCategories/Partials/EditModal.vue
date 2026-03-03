<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import SickLeaveCategoryFields from "@/Pages/Admin/SickLeaveCategories/Partials/SickLeaveCategoryFields.vue";
import SickLeaveCategoryService from "@/services/SickLeaveCategoryService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loading = ref(false);
const saving = ref(false);
const errors = ref({});
const form = ref({
    code: "",
    name: "",
    description: "",
    active: true,
    order_index: 0,
});

const reset = () => {
    loading.value = false;
    saving.value = false;
    errors.value = {};
    form.value = {
        code: "",
        name: "",
        description: "",
        active: true,
        order_index: 0,
    };
};

const close = () => {
    open.value = false;
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};

    try {
        const { data } = await SickLeaveCategoryService.show(id);
        const row = data?.data ?? {};

        form.value = {
            code: row.code ?? "",
            name: row.name ?? "",
            description: row.description ?? "",
            active: !!row.active,
            order_index: Number(row.order_index ?? 0),
        };
    } catch (error) {
        errors.value = {
            _global: error?.response?.data?.message ?? error?.message ?? "Betoltes sikertelen.",
        };
    } finally {
        loading.value = false;
    }
};

watch(() => props.modelValue, async (isOpen) => {
    if (!isOpen) {
        reset();
        return;
    }

    const id = Number(props.category?.id ?? 0);
    if (!id) {
        errors.value = { _global: "Nincs kivalasztott betegszabadsag kategoria." };
        return;
    }

    await load(id);
});

const submit = async () => {
    const id = Number(props.category?.id ?? 0);
    if (!id) {
        errors.value = { _global: "Nincs kivalasztott betegszabadsag kategoria." };
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        await SickLeaveCategoryService.update(id, {
            name: String(form.value.name ?? "").trim(),
            description: String(form.value.description ?? "").trim() || null,
            active: !!form.value.active,
            order_index: Number(form.value.order_index ?? 0),
        });
        emit("saved", "Betegszabadsag kategoria frissitve.");
        close();
    } catch (error) {
        errors.value = SickLeaveCategoryService.extractErrors(error) ?? {
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
        header="Betegszabadság kategória szerkesztése"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="loading" class="text-sm text-slate-500">Betoltes...</div>

        <template v-else>
            <SickLeaveCategoryFields
                v-model="form"
                :errors="errors"
                :disabled="saving"
                :isEdit="true"
            />
            <div v-if="errors?._global" class="mt-3 text-sm text-red-600">{{ errors._global }}</div>
        </template>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Mégse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || loading || !canUpdate"
                    data-testid="sick-leave-category-edit-save"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
