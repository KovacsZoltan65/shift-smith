<script setup>
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const update = (patch) => {
    // Részleges mezőfrissítés: a parent form objektumot nem írjuk felül teljesen.
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Cég</label>
            <CompanySelector
                :modelValue="modelValue.company_id"
                @update:modelValue="(v) => update({ company_id: v })"
                :disabled="disabled"
                placeholder="Válassz céget..."
            />
            <div v-if="errors.company_id" class="text-sm text-red-600 mt-1">
                {{ errors.company_id }}
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Név</label>
            <InputText
                class="w-full"
                :disabled="disabled"
                :modelValue="modelValue.name"
                @update:modelValue="(v) => update({ name: v })"
            />
            <div v-if="errors.name" class="text-sm text-red-600 mt-1">
                {{ errors.name }}
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Leírás</label>
            <Textarea
                class="w-full"
                rows="3"
                :disabled="disabled"
                :modelValue="modelValue.description"
                @update:modelValue="(v) => update({ description: v })"
            />
            <div v-if="errors.description" class="text-sm text-red-600 mt-1">
                {{ errors.description }}
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox
                inputId="position_active"
                :binary="true"
                :disabled="disabled"
                :modelValue="!!modelValue.active"
                @update:modelValue="(v) => update({ active: !!v })"
            />
            <label for="position_active" class="text-sm text-gray-700">Aktív</label>
            <div v-if="errors.active" class="text-sm text-red-600 ml-2">
                {{ errors.active }}
            </div>
        </div>
    </div>
</template>
