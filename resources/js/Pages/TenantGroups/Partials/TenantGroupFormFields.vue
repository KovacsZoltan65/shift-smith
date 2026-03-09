<script setup>
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

const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Active", value: "active" },
    { label: "Archived", value: "archived" },
];

// Részleges frissítéseket küld felfelé, így a szülő dialog marad az egyetlen adatforrás.
const update = (patch) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium">Name</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.name"
                @update:modelValue="(value) => update({ name: value })"
            />
            <div v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Code</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.code"
                @update:modelValue="(value) => update({ code: value })"
            />
            <div v-if="errors.code" class="mt-1 text-sm text-red-600">{{ errors.code }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Status</label>
            <Select
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                showClear
                placeholder="Select status"
                @update:modelValue="(value) => update({ status: value })"
            />
            <div v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Notes</label>
            <Textarea
                rows="4"
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.notes"
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
            <label for="tenant_group_active" class="text-sm text-gray-700">Active</label>
            <div v-if="errors.active" class="text-sm text-red-600">{{ errors.active }}</div>
        </div>
    </div>
</template>
