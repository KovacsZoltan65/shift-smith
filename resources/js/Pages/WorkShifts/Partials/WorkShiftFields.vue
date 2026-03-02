<script setup>
import { computed, watch } from "vue";
import InputText from "primevue/inputtext";
import Checkbox from "primevue/checkbox";
import Button from "primevue/button";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const update = (patch) => {
    emit("update:modelValue", { ...(props.modelValue ?? {}), ...patch });
};
const active = computed(() => !!props.modelValue?.active);

const parseTimeToMinutes = (time) => {
    if (typeof time !== "string" || !/^\d{2}:\d{2}$/.test(time)) return null;
    const [h, m] = time.split(":").map(Number);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return h * 60 + m;
};

const shiftDurationMinutes = computed(() => {
    const start = parseTimeToMinutes(props.modelValue?.start_time);
    const end = parseTimeToMinutes(props.modelValue?.end_time);
    if (start == null || end == null) return 0;
    if (end < start) return end + 1440 - start;
    if (end === start) return 0;
    return end - start;
});
const shiftStartMinutes = computed(() => parseTimeToMinutes(props.modelValue?.start_time));
const shiftEndBaseMinutes = computed(() => parseTimeToMinutes(props.modelValue?.end_time));
const isOvernight = computed(() => {
    if (shiftStartMinutes.value == null || shiftEndBaseMinutes.value == null) return false;
    return shiftEndBaseMinutes.value < shiftStartMinutes.value;
});
const shiftEndMinutes = computed(() => {
    if (shiftStartMinutes.value == null || shiftEndBaseMinutes.value == null) return null;
    return isOvernight.value ? shiftEndBaseMinutes.value + 1440 : shiftEndBaseMinutes.value;
});
const shiftLabel = computed(() => {
    const start = props.modelValue?.start_time ?? "--:--";
    const end = props.modelValue?.end_time ?? "--:--";
    return `${start}-${end}`;
});

const breaks = computed(() => (Array.isArray(props.modelValue?.breaks) ? props.modelValue.breaks : []));

const breakDiagnostics = computed(() =>
    breaks.value.map((row) => {
        const start = parseTimeToMinutes(row?.break_start_time);
        const endBase = parseTimeToMinutes(row?.break_end_time);
        if (start == null || endBase == null) {
            return {
                valid: true,
                breakLabel: `${row?.break_start_time ?? "--:--"}-${row?.break_end_time ?? "--:--"}`,
                minutes: 0,
                warning: null,
            };
        }

        const end = endBase <= start ? endBase + 1440 : endBase;
        const duration = Math.max(0, end - start);

        let alignedStart = start;
        while (shiftStartMinutes.value != null && alignedStart < shiftStartMinutes.value) {
            alignedStart += 1440;
        }
        const alignedEnd = alignedStart + duration;

        const inShift =
            shiftStartMinutes.value != null &&
            shiftEndMinutes.value != null &&
            alignedStart >= shiftStartMinutes.value &&
            alignedEnd <= shiftEndMinutes.value &&
            duration > 0;

        const breakLabel = `${row?.break_start_time ?? "--:--"}-${row?.break_end_time ?? "--:--"}`;

        return {
            valid: inShift,
            breakLabel,
            minutes: duration,
            warning: inShift
                ? null
                : `A szünet kilóg a műszakból. Shift: ${shiftLabel.value}. Break: ${breakLabel}`,
        };
    })
);
const breakWarnings = computed(() =>
    breakDiagnostics.value.filter((row) => row.warning).map((row) => row.warning)
);
const breakTotalMinutes = computed(() =>
    breakDiagnostics.value.reduce((sum, row) => sum + row.minutes, 0)
);

const workTimeMinutes = computed(() => Math.max(0, shiftDurationMinutes.value - breakTotalMinutes.value));

const syncComputed = () => {
    update({
        break_minutes: breakTotalMinutes.value,
        work_time_minutes: workTimeMinutes.value,
    });
};

watch(
    () => [props.modelValue?.start_time, props.modelValue?.end_time, JSON.stringify(breaks.value)],
    syncComputed,
    { immediate: true }
);

const addBreak = () => {
    update({
        breaks: [
            ...breaks.value,
            {
                break_start_time: null,
                break_end_time: null,
            },
        ],
    });
};

const removeBreak = (index) => {
    update({
        breaks: breaks.value.filter((_, i) => i !== index),
    });
};

const updateBreak = (index, patch) => {
    update({
        breaks: breaks.value.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    });
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

        <!-- START TIME -->
        <div>
            <label class="block text-sm font-medium mb-1">Kezdés (HH:mm)</label>
            <InputText
                class="w-full"
                type="time"
                :disabled="disabled"
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
                :disabled="disabled"
                :modelValue="modelValue.end_time"
                @update:modelValue="(v) => update({ end_time: v || null })"
            />
            <div v-if="errors.end_time" class="text-sm text-red-600 mt-1">
                {{ errors.end_time }}
            </div>
            <div
                v-if="isOvernight"
                class="inline-flex mt-2 rounded bg-amber-100 text-amber-800 text-xs px-2 py-1"
            >
                Átnyúló műszak (másnap)
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Munkaidő (perc)</label>
            <InputText
                class="w-full"
                type="number"
                :disabled="disabled"
                readonly
                :modelValue="modelValue.work_time_minutes"
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
                readonly
                :modelValue="modelValue.break_minutes"
            />
            <div v-if="errors.break_minutes" class="text-sm text-red-600 mt-1">
                {{ errors.break_minutes }}
            </div>
        </div>

        <div class="rounded border p-3 space-y-3">
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium">Szünetek</label>
                <Button
                    label="Szünet hozzáadása"
                    icon="pi pi-plus"
                    size="small"
                    severity="secondary"
                    :disabled="disabled"
                    @click="addBreak"
                />
            </div>

            <div v-if="errors.breaks" class="text-sm text-red-600">
                {{ errors.breaks }}
            </div>
            <div v-if="breakWarnings.length" class="text-sm text-amber-700 space-y-1">
                <div v-for="(warning, idx) in breakWarnings" :key="`warn-${idx}`">
                    {{ warning }}
                </div>
            </div>

            <div v-if="breaks.length === 0" class="text-sm text-gray-500">
                Nincs rögzített szünet.
            </div>

            <div v-for="(row, index) in breaks" :key="index" class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-5">
                    <label class="block text-xs mb-1">Kezdés</label>
                    <InputText
                        class="w-full"
                        type="time"
                        :disabled="disabled"
                        :modelValue="row.break_start_time"
                        @update:modelValue="(v) => updateBreak(index, { break_start_time: v || null })"
                    />
                </div>
                <div class="md:col-span-5">
                    <label class="block text-xs mb-1">Vége</label>
                    <InputText
                        class="w-full"
                        type="time"
                        :disabled="disabled"
                        :modelValue="row.break_end_time"
                        @update:modelValue="(v) => updateBreak(index, { break_end_time: v || null })"
                    />
                </div>
                <div class="md:col-span-2">
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        :disabled="disabled"
                        @click="removeBreak(index)"
                    />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <Checkbox
                inputId="work_shift_active"
                :binary="true"
                :disabled="disabled"
                :modelValue="active"
                @update:modelValue="(v) => update({ active: !!v })"
            />
            <label for="work_shift_active" class="text-sm text-gray-700"> Aktív </label>

            <div v-if="errors.active" class="text-sm text-red-600 ml-2">
                {{ errors.active }}
            </div>
        </div>
    </div>
</template>
