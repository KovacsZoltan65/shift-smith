<script setup>
import InputText from "primevue/inputtext";
import Checkbox from "primevue/checkbox";
import DatePicker from "primevue/datepicker";

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
            <label class="block text-sm font-medium mb-1">Név</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.name"
                @update:modelValue="(v) => update({ name: v })"
                autocomplete="off"
            />
            <div v-if="errors.name" class="text-sm text-red-600 mt-1">
                {{ errors.name }}
            </div>
        </div>

        <!-- START DATE -->
        <div>
            <label class="block text-sm font-medium mb-1">Kezdő dátum</label>

            <DatePicker
                class="w-full"
                inputClass="w-full"
                :disabled="disabled"
                :modelValue="modelValue.start_date"
                @update:modelValue="(v) => update({ start_date: v })"
                dateFormat="yy-mm-dd"
                showIcon
            />

            <div v-if="errors.start_date" class="text-sm text-red-600 mt-1">
                {{ errors.start_date }}
            </div>
        </div>

        <!-- END DATE -->
        <div>
            <label class="block text-sm font-medium mb-1">Záró dátum</label>

            <DatePicker
                class="w-full"
                inputClass="w-full"
                :disabled="disabled"
                :modelValue="modelValue.end_date"
                @update:modelValue="(v) => update({ end_date: v })"
                dateFormat="yy-mm-dd"
                showIcon
            />

            <div v-if="errors.end_date" class="text-sm text-red-600 mt-1">
                {{ errors.end_date }}
            </div>
        </div>

        <!-- ACTIVE -->
        <div class="flex items-center gap-2">
            <Checkbox
                inputId="work_shift_active"
                :binary="true"
                :disabled="disabled"
                :modelValue="!!modelValue.active"
                @update:modelValue="(v) => update({ active: !!v })"
            />
            <label for="work_shift_active" class="text-sm text-gray-700"> Aktív </label>

            <div v-if="errors.active" class="text-sm text-red-600 ml-2">
                {{ errors.active }}
            </div>
        </div>
    </div>
</template>
