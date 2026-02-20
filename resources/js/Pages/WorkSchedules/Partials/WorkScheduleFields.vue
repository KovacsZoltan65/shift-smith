<script setup>
import { computed } from "vue";

import InputText from "primevue/inputtext";
import DatePicker from "primevue/datepicker";
import Select from "primevue/select";

import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const set = (key, value) => {
    emit("update:modelValue", { ...props.modelValue, [key]: value });
};

const companyId = computed({
    get: () => props.modelValue.company_id ?? null,
    set: (v) => set("company_id", v),
});

const name = computed({
    get: () => props.modelValue.name ?? "",
    set: (v) => set("name", v),
});

const dateFrom = computed({
    get: () => props.modelValue.date_from ?? null,
    set: (v) => set("date_from", v),
});

const dateTo = computed({
    get: () => props.modelValue.date_to ?? null,
    set: (v) => set("date_to", v),
});

const status = computed({
    get: () => props.modelValue.status ?? "draft",
    set: (v) => set("status", v),
});

const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Published", value: "published" },
];

const err = (key) => props.errors?.[key];
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <!-- company_id (ha a rendszeredben nem kell, nyugodtan elrejthető később) -->
        <div>
            <label class="mb-1 block text-xs text-gray-600">Cég</label>
            <CompanySelector
                :modelValue="companyId"
                @update:modelValue="companyId = $event"
                :disabled="disabled"
                placeholder="Válassz céget..."
            />
            <small v-if="err('company_id')" class="text-red-600">{{
                err("company_id")
            }}</small>
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-600">Név</label>
            <InputText
                v-model="name"
                class="w-full"
                :disabled="disabled"
                autocomplete="off"
                placeholder="Pl.: Februári beosztás"
            />
            <small v-if="err('name')" class="text-red-600">{{ err("name") }}</small>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs text-gray-600">Dátum -tól</label>
                <DatePicker
                    v-model="dateFrom"
                    class="w-full"
                    dateFormat="yy-mm-dd"
                    showIcon
                    :disabled="disabled"
                />
                <small v-if="err('date_from')" class="text-red-600">{{
                    err("date_from")
                }}</small>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-600">Dátum -ig</label>
                <DatePicker
                    v-model="dateTo"
                    class="w-full"
                    dateFormat="yy-mm-dd"
                    showIcon
                    :disabled="disabled"
                />
                <small v-if="err('date_to')" class="text-red-600">{{
                    err("date_to")
                }}</small>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-600">Státusz</label>
            <Select
                v-model="status"
                class="w-full"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                :disabled="disabled"
            />
            <small v-if="err('status')" class="text-red-600">{{ err("status") }}</small>
        </div>
    </div>
</template>
