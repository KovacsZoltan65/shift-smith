<script setup>
import InputText from "primevue/inputtext";
import Checkbox from "primevue/checkbox";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const update = (patch) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <!-- NAME -->
        <div>
            <label class="block text-sm font-medium mb-1">{{ $t("columns.name") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.name"
                @update:modelValue="(v) => update({ name: v })"
                autocomplete="organization"
            />
            <div v-if="errors.name" class="text-sm text-red-600 mt-1">
                {{ errors.name }}
            </div>
        </div>

        <!-- EMAIL -->
        <div>
            <label class="block text-sm font-medium mb-1">{{ $t("columns.email") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.email"
                @update:modelValue="(v) => update({ email: v })"
                autocomplete="email"
            />
            <div v-if="errors.email" class="text-sm text-red-600 mt-1">
                {{ errors.email }}
            </div>
        </div>

        <!-- ADDRESS -->
        <div>
            <label class="block text-sm font-medium mb-1">{{ $t("companies.form.address") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.address"
                @update:modelValue="(v) => update({ address: v })"
                autocomplete="street-address"
            />
            <div v-if="errors.address" class="text-sm text-red-600 mt-1">
                {{ errors.address }}
            </div>
        </div>

        <!-- PHONE -->
        <div>
            <label class="block text-sm font-medium mb-1">{{ $t("columns.phone") }}</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.phone"
                @update:modelValue="(v) => update({ phone: v })"
                autocomplete="tel"
            />
            <div v-if="errors.phone" class="text-sm text-red-600 mt-1">
                {{ errors.phone }}
            </div>
        </div>

        <!-- ACTIVE -->
        <div class="flex items-center gap-2">
            <Checkbox
                inputId="company_active"
                :binary="true"
                :disabled="disabled"
                :modelValue="!!modelValue.active"
                @update:modelValue="(v) => update({ active: !!v })"
            />
            <label for="company_active" class="text-sm text-gray-700">{{ $t("columns.active") }}</label>

            <div v-if="errors.active" class="text-sm text-red-600 ml-2">
                {{ errors.active }}
            </div>
        </div>
    </div>
</template>
