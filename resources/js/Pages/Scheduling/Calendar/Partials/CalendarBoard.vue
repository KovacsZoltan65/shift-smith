<script setup>
import { computed } from "vue";

const props = defineProps({
    events: { type: Array, default: () => [] },
    viewMode: { type: String, default: "month" }, // month|week|day
    anchorDate: { type: Date, required: true },
    plannerMode: { type: Boolean, default: false },
    selectedDates: { type: Array, default: () => [] },
    closedMonthKeys: { type: Array, default: () => [] },
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

    const normalizeTime = (value) => {
        const time = String(value ?? "").slice(0, 5);
        return /^\d{2}:\d{2}$/.test(time) ? time : "99:99";
    };

    for (const [k, arr] of map.entries()) {
        arr.sort((a, b) => {
            const aStart = normalizeTime(a?.extendedProps?.shift_start_time);
            const bStart = normalizeTime(b?.extendedProps?.shift_start_time);
            if (aStart !== bStart) return aStart.localeCompare(bStart);

            const aEnd = normalizeTime(a?.extendedProps?.shift_end_time);
            const bEnd = normalizeTime(b?.extendedProps?.shift_end_time);
            if (aEnd !== bEnd) return aEnd.localeCompare(bEnd);

            return String(a?.title ?? "").localeCompare(String(b?.title ?? ""), "hu");
        });
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
    if (!isInRange(day) || !isEditableDay(day) || isClosedMonthDay(day)) return;
    const id = Number(event.dataTransfer?.getData("text/plain") ?? 0);
    if (!id) return;
    emit("event-drop", { id, date: toYmd(day) });
};

const isSelected = (d) => props.selectedDates.includes(toYmd(d));
const isClosedMonthKey = (value) => props.closedMonthKeys.includes(String(value));
const isClosedMonthDay = (d) => isClosedMonthKey(toYmd(d).slice(0, 7));
const isClosedMonthEvent = (row) => isClosedMonthKey(String(row?.start ?? "").slice(0, 7));
const shiftColorClass = (row) => {
    const shiftId = Number(row?.extendedProps?.shift_id ?? 0);
    if (!shiftId) return "";
    return `shift-color-${Math.abs(shiftId) % 8}`;
};

const isInRange = (d) => {
    const day = toYmd(d);
    const from = props.scheduleRange?.from;
    const to = props.scheduleRange?.to;
    if (!from || !to) return true;
    return day >= from && day <= to;
};

const isEditableDay = (d) => {
    if (!props.todayYmd) return true;
    return toYmd(d).slice(0, 7) >= props.todayYmd.slice(0, 7);
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
                            isInRange(day) && isEditableDay(day) && !isClosedMonthDay(day)
                                ? 'text-slate-700 hover:text-slate-900'
                                : 'text-slate-400 cursor-not-allowed'
                        "
                        type="button"
                        :disabled="!isInRange(day) || !isEditableDay(day) || isClosedMonthDay(day)"
                        @click="emit('date-click', { date: toYmd(day) })"
                    >
                        {{ label(day) }}
                    </button>
                    <input
                        v-if="plannerMode"
                        type="checkbox"
                        class="h-4 w-4"
                        :disabled="!isInRange(day) || !isEditableDay(day) || isClosedMonthDay(day)"
                        :checked="isSelected(day)"
                        @change="emit('toggle-date', toYmd(day))"
                    />
                </div>

                <div class="space-y-1">
                    <button
                        v-for="row in grouped.get(toYmd(day)) || []"
                        :key="row.id"
                        class="w-full rounded border px-2 py-1 text-left text-xs transition hover:bg-sky-50"
                        :class="[row.className || [], shiftColorClass(row)]"
                        type="button"
                        :draggable="plannerMode && !!row.editable && row?.extendedProps?.entity_type === 'shift_assignment' && !isClosedMonthEvent(row)"
                        :disabled="plannerMode && (!row.editable || isClosedMonthEvent(row))"
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

<style scoped>
.shift-color-0 { border-color: #14b8a6; background: #f0fdfa; }
.shift-color-1 { border-color: #3b82f6; background: #eff6ff; }
.shift-color-2 { border-color: #f97316; background: #fff7ed; }
.shift-color-3 { border-color: #22c55e; background: #f0fdf4; }
.shift-color-4 { border-color: #eab308; background: #fefce8; }
.shift-color-5 { border-color: #ef4444; background: #fef2f2; }
.shift-color-6 { border-color: #6366f1; background: #eef2ff; }
.shift-color-7 { border-color: #06b6d4; background: #ecfeff; }
.absence-leave { border-color: #16a34a; background: #f0fdf4; }
.absence-sick_leave { border-color: #dc2626; background: #fef2f2; }
</style>
