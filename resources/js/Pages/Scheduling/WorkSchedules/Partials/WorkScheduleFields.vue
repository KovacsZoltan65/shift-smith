<script setup>
import InputText from "primevue/inputtext";
import Select from "primevue/select";

const props = defineProps({
    modelValue: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const form = props.modelValue;

const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Publikált", value: "published" },
];

const set = (key, value) => emit("update:modelValue", { ...form, [key]: value });
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="mb-1 block text-sm">Név</label>
            <InputText
                class="w-full"
                :modelValue="form.name"
                :disabled="disabled"
                @update:modelValue="(value) => set('name', String(value ?? '').trimStart())"
            />
            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Kezdő dátum</label>
            <InputText
                class="w-full"
                type="date"
                :modelValue="form.date_from"
                :disabled="disabled"
                @update:modelValue="(value) => set('date_from', value)"
            />
            <div v-if="errors?.date_from" class="mt-1 text-sm text-red-600">
                {{ errors.date_from }}
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Záró dátum</label>
            <InputText
                class="w-full"
                type="date"
                :modelValue="form.date_to"
                :disabled="disabled"
                @update:modelValue="(value) => set('date_to', value)"
            />
            <div v-if="errors?.date_to" class="mt-1 text-sm text-red-600">
                {{ errors.date_to }}
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm">Státusz</label>
            <Select
                class="w-full"
                :modelValue="form.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                :disabled="disabled"
                @update:modelValue="(value) => set('status', value)"
            />
            <div v-if="errors?.status" class="mt-1 text-sm text-red-600">
                {{ errors.status }}
            </div>
        </div>
    </div>
</template>
