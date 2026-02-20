<script setup>
import { computed } from "vue";
import InputText from "primevue/inputtext";
import Checkbox from "primevue/checkbox";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const update = (patch) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};

const isFlexible = computed(() => !!props.modelValue?.is_flexible);
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

        <div>
            <label class="block text-sm font-medium mb-1">Cég</label>
            <CompanySelector
                :modelValue="modelValue.company_id"
                :disabled="disabled"
                @update:modelValue="(v) => update({ company_id: v ? Number(v) : null })"
            />
            <div v-if="errors.company_id" class="text-sm text-red-600 mt-1">
                {{ errors.company_id }}
            </div>
        </div>

        <!-- START TIME -->
        <div>
            <label class="block text-sm font-medium mb-1">Kezdés (HH:mm)</label>
            <InputText
                class="w-full"
                type="time"
                :disabled="disabled || isFlexible"
                :modelValue="modelValue.start_time"
                @update:modelValue="(v) => update({ start_time: v || null })"
            />
            <div v-if="errors.start_time" class="text-sm text-red-600 mt-1">
                {{ errors.start_time }}
            </div>
        </div>

        <!-- END TIME -->
        <div>
            <label class="block text-sm font-medium mb-1">Vége (HH:mm)</label>
            <InputText
                class="w-full"
                type="time"
                :disabled="disabled || isFlexible"
                :modelValue="modelValue.end_time"
                @update:modelValue="(v) => update({ end_time: v || null })"
            />
            <div v-if="errors.end_time" class="text-sm text-red-600 mt-1">
                {{ errors.end_time }}
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Munkaidő (perc)</label>
            <InputText
                class="w-full"
                type="number"
                :disabled="disabled"
                :modelValue="modelValue.work_time_minutes"
                @update:modelValue="(v) => update({ work_time_minutes: v === '' || v == null ? null : Number(v) })"
            />
            <div v-if="errors.work_time_minutes" class="text-sm text-red-600 mt-1">
                {{ errors.work_time_minutes }}
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Szünet (perc)</label>
            <InputText
                class="w-full"
                type="number"
                :disabled="disabled"
                :modelValue="modelValue.break_minutes"
                @update:modelValue="(v) => update({ break_minutes: v === '' || v == null ? null : Number(v) })"
            />
            <div v-if="errors.break_minutes" class="text-sm text-red-600 mt-1">
                {{ errors.break_minutes }}
            </div>
        </div>

        <!-- ACTIVE -->
        <div class="flex items-center gap-2 flex-wrap">
            <Checkbox
                inputId="work_shift_flexible"
                :binary="true"
                :disabled="disabled"
                :modelValue="!!modelValue.is_flexible"
                @update:modelValue="
                    (v) =>
                        update({
                            is_flexible: !!v,
                            start_time: v ? null : modelValue.start_time,
                            end_time: v ? null : modelValue.end_time,
                        })
                "
            />
            <label for="work_shift_flexible" class="text-sm text-gray-700"> Rugalmas </label>

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
