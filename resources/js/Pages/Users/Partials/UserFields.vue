<script setup>
import InputText from "primevue/inputtext";

const props = defineProps({
    modelValue: { type: Object, required: true }, // { name, email }
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const set = (key, val) => {
    emit("update:modelValue", { ...props.modelValue, [key]: val });
};
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-1">
            <label class="text-sm font-medium">Név</label>
            <InputText
                :modelValue="modelValue.name"
                class="w-full"
                autocomplete="off"
                :disabled="disabled"
                @update:modelValue="(v) => set('name', v)"
            />
            <div v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div class="space-y-1">
            <label class="text-sm font-medium">Email</label>
            <InputText
                :modelValue="modelValue.email"
                class="w-full"
                autocomplete="off"
                :disabled="disabled"
                @update:modelValue="(v) => set('email', v)"
            />
            <div v-if="errors.email" class="text-sm text-red-600">{{ errors.email }}</div>
        </div>
    </div>
</template>
