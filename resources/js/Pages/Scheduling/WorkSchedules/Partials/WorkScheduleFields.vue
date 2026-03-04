<script setup>
import DatePicker from "primevue/datepicker";
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

const toDateValue = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value;

    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
};

const toYmd = (value) => {
    if (typeof value === "string") {
        const trimmed = value.trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }

        const parsed = new Date(trimmed);
        if (!Number.isNaN(parsed.getTime())) {
            const year = parsed.getFullYear();
            const month = String(parsed.getMonth() + 1).padStart(2, "0");
            const day = String(parsed.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        }

        return "";
    }

    if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
        return "";
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, "0");
    const day = String(value.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};
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
            <DatePicker
                class="w-full"
                :modelValue="toDateValue(form.date_from)"
                showIcon
                dateFormat="yy-mm-dd"
                :disabled="disabled"
                @update:modelValue="(value) => set('date_from', toYmd(value))"
            />
            <div v-if="errors?.date_from" class="mt-1 text-sm text-red-600">
                {{ errors.date_from }}
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Záró dátum</label>
            <DatePicker
                class="w-full"
                :modelValue="toDateValue(form.date_to)"
                showIcon
                dateFormat="yy-mm-dd"
                :disabled="disabled"
                @update:modelValue="(value) => set('date_to', toYmd(value))"
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
