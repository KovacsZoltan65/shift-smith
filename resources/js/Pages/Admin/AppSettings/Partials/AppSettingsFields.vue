<script setup>
import { computed } from "vue";
import Checkbox from "primevue/checkbox";
import Dropdown from "primevue/dropdown";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const typeOptions = [
    { label: "int", value: "int" },
    { label: "bool", value: "bool" },
    { label: "string", value: "string" },
    { label: "json", value: "json" },
];

const isBoolType = computed(() => props.modelValue?.type === "bool");
const isIntType = computed(() => props.modelValue?.type === "int");
const isStringType = computed(() => props.modelValue?.type === "string");
const isJsonType = computed(() => props.modelValue?.type === "json");

const patch = (changes) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...changes });
};

const onTypeChange = (type) => {
    const next = { type };

    if (type === "bool") next.value = Boolean(props.modelValue?.value);
    if (type === "int") next.value = props.modelValue?.value ?? null;
    if (type === "string") next.value = props.modelValue?.value ?? "";
    if (type === "json") {
        const current = props.modelValue?.value;
        next.value =
            typeof current === "string"
                ? current
                : JSON.stringify(current ?? {}, null, 2);
    }

    patch(next);
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Kulcs</label>
                <InputText
                    class="w-full"
                    :disabled="disabled"
                    :modelValue="modelValue.key"
                    @update:modelValue="(value) => patch({ key: value })"
                />
                <div v-if="errors.key" class="mt-1 text-sm text-red-600">{{ errors.key }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Típus</label>
                <Dropdown
                    class="w-full"
                    :disabled="disabled"
                    :modelValue="modelValue.type"
                    :options="typeOptions"
                    optionLabel="label"
                    optionValue="value"
                    @update:modelValue="onTypeChange"
                />
                <div v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</div>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Csoport</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.group"
                @update:modelValue="(value) => patch({ group: value })"
            />
            <div v-if="errors.group" class="mt-1 text-sm text-red-600">{{ errors.group }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Label</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.label"
                @update:modelValue="(value) => patch({ label: value })"
            />
            <div v-if="errors.label" class="mt-1 text-sm text-red-600">{{ errors.label }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Leírás</label>
            <Textarea
                class="w-full"
                rows="3"
                :disabled="disabled"
                :modelValue="modelValue.description"
                @update:modelValue="(value) => patch({ description: value })"
            />
            <div v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Érték</label>

            <InputNumber
                v-if="isIntType"
                class="w-full"
                fluid
                :disabled="disabled"
                :modelValue="modelValue.value"
                @update:modelValue="(value) => patch({ value })"
            />

            <div v-else-if="isBoolType" class="flex items-center gap-2 pt-2">
                <Checkbox
                    inputId="app-setting-bool"
                    :binary="true"
                    :disabled="disabled"
                    :modelValue="Boolean(modelValue.value)"
                    @update:modelValue="(value) => patch({ value: Boolean(value) })"
                />
                <label for="app-setting-bool" class="text-sm text-gray-700">Igaz / hamis</label>
            </div>

            <InputText
                v-else-if="isStringType"
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.value"
                @update:modelValue="(value) => patch({ value })"
            />

            <Textarea
                v-else-if="isJsonType"
                class="w-full font-mono"
                rows="8"
                :disabled="disabled"
                :modelValue="modelValue.value"
                @update:modelValue="(value) => patch({ value })"
            />

            <div v-if="errors.value" class="mt-1 text-sm text-red-600">{{ errors.value }}</div>
        </div>
    </div>
</template>
