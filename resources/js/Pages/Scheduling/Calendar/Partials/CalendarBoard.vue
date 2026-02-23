<script setup>
import { computed } from "vue";
import Button from "primevue/button";

const props = defineProps({
    events: { type: Array, default: () => [] },
    viewMode: { type: String, default: "month" }, // month|week|day
    anchorDate: { type: Date, required: true },
    plannerMode: { type: Boolean, default: false },
    selectedDates: { type: Array, default: () => [] },
    scheduleRange: {
        type: Object,
        default: () => ({ from: null, to: null }),
    },
    todayYmd: { type: String, default: "" },
});

const emit = defineEmits([
    "event-click",
    "date-click",
    "date-range-change",
    "toggle-date",
    "event-drop",
]);

const toYmd = (d) => {
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return "";
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, "0");
    const day = String(dt.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};

const startOfWeek = (d) => {
    const dt = new Date(d);
    const day = (dt.getDay() + 6) % 7;
    dt.setDate(dt.getDate() - day);
    dt.setHours(0, 0, 0, 0);
    return dt;
};

const monthDates = computed(() => {
    const base = new Date(props.anchorDate);
    const first = new Date(base.getFullYear(), base.getMonth(), 1);
    const start = startOfWeek(first);
    const out = [];
    for (let i = 0; i < 42; i += 1) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        out.push(d);
    }
    return out;
});

const weekDates = computed(() => {
    const start = startOfWeek(props.anchorDate);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        return d;
    });
});

const dayDates = computed(() => [new Date(props.anchorDate)]);

const visibleDates = computed(() => {
    if (props.viewMode === "day") return dayDates.value;
    if (props.viewMode === "week") return weekDates.value;
    return monthDates.value;
});

const grouped = computed(() => {
    const map = new Map();
    for (const d of visibleDates.value) {
        map.set(toYmd(d), []);
    }

    for (const e of props.events ?? []) {
        const key = String(e?.start ?? "").slice(0, 10);
        if (!map.has(key)) continue;
        map.get(key).push(e);
    }

    for (const [k, arr] of map.entries()) {
        arr.sort((a, b) => String(a.title ?? "").localeCompare(String(b.title ?? ""), "hu"));
        map.set(k, arr);
    }

    return map;
});

const label = (d) =>
    new Intl.DateTimeFormat("hu-HU", {
        month: "2-digit",
        day: "2-digit",
        weekday: "short",
    }).format(d);

const onDragStart = (event, row) => {
    event.dataTransfer?.setData("text/plain", String(row.id));
};

const onDrop = (event, day) => {
    if (!props.plannerMode) return;
    if (!isInRange(day) || !isEditableDay(day)) return;
    const id = Number(event.dataTransfer?.getData("text/plain") ?? 0);
    if (!id) return;
    emit("event-drop", { id, date: toYmd(day) });
};

const isSelected = (d) => props.selectedDates.includes(toYmd(d));

const isInRange = (d) => {
    const day = toYmd(d);
    const from = props.scheduleRange?.from;
    const to = props.scheduleRange?.to;
    if (!from || !to) return true;
    return day >= from && day <= to;
};

const isEditableDay = (d) => {
    if (!props.todayYmd) return true;
    return toYmd(d) >= props.todayYmd;
};
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-white p-3">
        <div
            class="grid gap-3"
            :class="{
                'grid-cols-1': viewMode === 'day',
                'grid-cols-2 md:grid-cols-4 lg:grid-cols-7': viewMode !== 'day',
            }"
        >
            <div
                v-for="day in visibleDates"
                :key="toYmd(day)"
                class="min-h-36 rounded-md border border-slate-200 p-2"
                :class="[
                    isInRange(day) ? 'bg-slate-50' : 'bg-slate-100/60 opacity-70',
                    isSelected(day) ? 'ring-2 ring-sky-300 bg-sky-50/70' : '',
                ]"
                @dragover.prevent
                @drop="onDrop($event, day)"
            >
                <div class="mb-2 flex items-center justify-between">
                    <button
                        class="text-xs font-semibold"
                        :class="
                            isInRange(day) && isEditableDay(day)
                                ? 'text-slate-700 hover:text-slate-900'
                                : 'text-slate-400 cursor-not-allowed'
                        "
                        type="button"
                        :disabled="!isInRange(day) || !isEditableDay(day)"
                        @click="emit('date-click', { date: toYmd(day) })"
                    >
                        {{ label(day) }}
                    </button>
                    <input
                        v-if="plannerMode"
                        type="checkbox"
                        class="h-4 w-4"
                        :disabled="!isInRange(day) || !isEditableDay(day)"
                        :checked="isSelected(day)"
                        @change="emit('toggle-date', toYmd(day))"
                    />
                </div>

                <div class="space-y-1">
                    <button
                        v-for="row in grouped.get(toYmd(day)) || []"
                        :key="row.id"
                        class="w-full rounded border px-2 py-1 text-left text-xs transition hover:bg-sky-50"
                        :class="row.className || []"
                        type="button"
                        :draggable="plannerMode && !!row.editable"
                        :disabled="plannerMode && !row.editable"
                        @dragstart="onDragStart($event, row)"
                        @click="emit('event-click', row)"
                    >
                        {{ row.title }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <Button
                v-if="plannerMode"
                icon="pi pi-refresh"
                label="Aktuális tartomány újratöltése"
                severity="secondary"
                size="small"
                @click="
                    emit('date-range-change', {
                        start: toYmd(visibleDates[0]),
                        end: toYmd(visibleDates[visibleDates.length - 1]),
                    })
                "
            />
        </div>
    </div>
</template>
