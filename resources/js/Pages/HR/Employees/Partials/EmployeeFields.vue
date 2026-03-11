<script setup>
import { computed } from "vue";


import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import PositionSelector from "@/Components/Selectors/PositionSelector.vue";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    // ha editnél le akarod tiltani a cég váltást:
    lockCompany: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const form = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const fieldError = (key) => {
    const e = props.errors?.[key];
    return Array.isArray(e) ? e[0] : e || null;
};

</script>

<template>
    <div class="space-y-4">
        <!-- Company -->
        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t("columns.company") }} *</label>
            <CompanySelector
                v-model="form.company_id"
                :placeholder="$t('employees.form.select_company')"
                :disabled="disabled || lockCompany"
            />
            <div v-if="fieldError('company_id')" class="mt-1 text-sm text-red-600">
                {{ fieldError("company_id") }}
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.last_name") }} *</label>
                <InputText v-model="form.last_name" class="w-full" :disabled="disabled" />
                <div v-if="fieldError('last_name')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("last_name") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.first_name") }} *</label>
                <InputText
                    v-model="form.first_name"
                    class="w-full"
                    :disabled="disabled"
                />
                <div v-if="fieldError('first_name')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("first_name") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.email") }}</label>
                <InputText v-model="form.email" class="w-full" :disabled="disabled" />
                <div v-if="fieldError('email')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("email") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.phone") }}</label>
                <InputText v-model="form.phone" class="w-full" :disabled="disabled" />
                <div v-if="fieldError('phone')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("phone") }}
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.position") }}</label>
                <PositionSelector
                    v-model="form.position_id"
                    :companyId="form.company_id"
                    :disabled="disabled"
                    :placeholder="$t('employees.form.select_position')"
                />
                <div v-if="fieldError('position_id')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("position_id") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.birth_date") }} *</label>
                <DatePicker
                    v-model="form.birth_date"
                    class="w-full"
                    :disabled="disabled"
                    dateFormat="yy-mm-dd"
                    showIcon
                />
                <div v-if="fieldError('birth_date')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("birth_date") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("columns.hired_at") }}</label>
                <DatePicker
                    v-model="form.hired_at"
                    class="w-full"
                    :disabled="disabled"
                    dateFormat="yy-mm-dd"
                    showIcon
                />
                <div v-if="fieldError('hired_at')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("hired_at") }}
                </div>
            </div>

            <div class="flex items-end gap-2">
                <Checkbox v-model="form.active" binary :disabled="disabled" />
                <label class="text-sm">{{ $t("employees.form.active") }}</label>
                <div v-if="fieldError('active')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("active") }}
                </div>
            </div>
        </div>
    </div>
</template>
