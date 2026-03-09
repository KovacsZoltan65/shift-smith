<script setup>
import { trans } from "laravel-vue-i18n";
import { computed } from "vue";
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Textarea from "primevue/textarea";

// Közös űrlapmezők a létrehozó és szerkesztő dialoghoz, hogy a két folyamat ugyanazt a mezőkészletet használja.
const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const statusOptions = computed(() => [
    { label: trans("tenant_groups.statuses.draft"), value: "draft" },
    { label: trans("tenant_groups.statuses.active"), value: "active" },
    { label: trans("tenant_groups.statuses.archived"), value: "archived" },
]);

// Részleges frissítéseket küld felfelé, így a szülő dialog marad az egyetlen adatforrás.
const update = (patch) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t("tenant_groups.fields.name") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.name"
                :placeholder="$t('tenant_groups.placeholders.name')"
                @update:modelValue="(value) => update({ name: value })"
            />
            <div v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t("tenant_groups.fields.code") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.code"
                :placeholder="$t('tenant_groups.placeholders.code')"
                @update:modelValue="(value) => update({ code: value })"
            />
            <div v-if="errors.code" class="mt-1 text-sm text-red-600">{{ errors.code }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t("tenant_groups.fields.status") }}</label>
            <Select
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                showClear
                :placeholder="$t('tenant_groups.placeholders.status')"
                @update:modelValue="(value) => update({ status: value })"
            />
            <div v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t("tenant_groups.fields.notes") }}</label>
            <Textarea
                rows="4"
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.notes"
                :placeholder="$t('tenant_groups.placeholders.notes')"
                @update:modelValue="(value) => update({ notes: value })"
            />
            <div v-if="errors.notes" class="mt-1 text-sm text-red-600">{{ errors.notes }}</div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox
                inputId="tenant_group_active"
                :binary="true"
                :disabled="disabled"
                :modelValue="!!modelValue.active"
                @update:modelValue="(value) => update({ active: !!value })"
            />
            <label for="tenant_group_active" class="text-sm text-gray-700">{{ $t("tenant_groups.fields.active") }}</label>
            <div v-if="errors.active" class="text-sm text-red-600">{{ errors.active }}</div>
        </div>
    </div>
</template>
