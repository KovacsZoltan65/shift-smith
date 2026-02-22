<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from "primevue/button";
import Select from "primevue/select";
import MultiSelect from "primevue/multiselect";
import ToggleSwitch from "primevue/toggleswitch";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

import CalendarBoard from "@/Pages/Scheduling/Calendar/Partials/CalendarBoard.vue";
import AssignmentCreateModal from "@/Pages/Scheduling/Calendar/AssignmentCreateModal.vue";
import AssignmentEditModal from "@/Pages/Scheduling/Calendar/AssignmentEditModal.vue";
import AssignmentBulkAssignModal from "@/Pages/Scheduling/Calendar/AssignmentBulkAssignModal.vue";

import EmployeeService from "@/services/EmployeeService.js";
import WorkShiftService from "@/services/WorkShiftService.js";
import PositionService from "@/services/PositionService.js";
import WorkScheduleAssignmentService from "@/services/WorkScheduleAssignmentService.js";

const props = defineProps({
    title: { type: String, default: "Naptár tervező" },
    current_company_id: { type: Number, required: true },
    schedules: { type: Array, default: () => [] },
    permissions: {
        type: Object,
        default: () => ({ viewer: false, planner: false }),
    },
});

const toast = useToast();

const scheduleId = ref(Number(props.schedules?.[0]?.id ?? 0) || null);
const viewMode = ref("month");
const anchorDate = ref(new Date());
const plannerMode = ref(false);
const selectedEmployeeIds = ref([]);
const selectedShiftIds = ref([]);
const selectedPositionIds = ref([]);
const selectedDates = ref([]);
const loading = ref(false);
const events = ref([]);

const createOpen = ref(false);
const editOpen = ref(false);
const bulkOpen = ref(false);
const createDate = ref(null);
const selectedEvent = ref(null);

const employeeOptions = ref([]);
const shiftOptions = ref([]);
const positionOptions = ref([]);

const canPlanner = computed(() => !!props.permissions?.planner);
const selectedSchedule = computed(() =>
    props.schedules.find((x) => Number(x.id) === Number(scheduleId.value)) ?? null
);

const scheduleRange = computed(() => ({
    from: selectedSchedule.value?.date_from ?? null,
    to: selectedSchedule.value?.date_to ?? null,
}));

watch(canPlanner, (ok) => {
    if (!ok) plannerMode.value = false;
}, { immediate: true });

watch(
    selectedSchedule,
    (row) => {
        if (!row) return;
        selectedDates.value = selectedDates.value.filter((d) => d >= row.date_from && d <= row.date_to);
        if (row.status === "published") {
            plannerMode.value = false;
        }
    },
    { immediate: true }
);

const visibleRange = computed(() => {
    const base = new Date(anchorDate.value);
    const toYmd = (d) => d.toISOString().slice(0, 10);

    if (viewMode.value === "day") {
        return { start: toYmd(base), end: toYmd(base) };
    }

    if (viewMode.value === "week") {
        const start = new Date(base);
        const day = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - day);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        return { start: toYmd(start), end: toYmd(end) };
    }

    const monthStart = new Date(base.getFullYear(), base.getMonth(), 1);
    const start = new Date(monthStart);
    const day = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - day);
    const end = new Date(start);
    end.setDate(start.getDate() + 41);
    return { start: toYmd(start), end: toYmd(end) };
});

const loadSelectors = async () => {
    const companyId = Number(props.current_company_id ?? 0);
    if (!companyId) return;

    const [employees, shifts, positions] = await Promise.all([
        EmployeeService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
        WorkShiftService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
        PositionService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
    ]);

    employeeOptions.value = Array.isArray(employees.data) ? employees.data : [];
    shiftOptions.value = Array.isArray(shifts.data) ? shifts.data : [];
    positionOptions.value = Array.isArray(positions.data) ? positions.data : [];
};

const loadEvents = async () => {
    if (!scheduleId.value) {
        events.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await WorkScheduleAssignmentService.getCalendarFeed({
            schedule_id: Number(scheduleId.value),
            start: visibleRange.value.start,
            end: visibleRange.value.end,
            employee_ids: selectedEmployeeIds.value,
            work_shift_ids: selectedShiftIds.value,
            position_ids: selectedPositionIds.value,
        });
        events.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Naptár feed hiba.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const refresh = async () => {
    selectedDates.value = [];
    await loadEvents();
};

const onDateClick = ({ date }) => {
    if (!plannerMode.value) return;
    createDate.value = date;
    createOpen.value = true;
};

const onEventClick = (event) => {
    selectedEvent.value = event;
    if (plannerMode.value) {
        editOpen.value = true;
        return;
    }

    toast.add({
        severity: "info",
        summary: "Részletek",
        detail: event.title,
        life: 3000,
    });
};

const handleCreate = async (payload) => {
    try {
        await WorkScheduleAssignmentService.createAssignment(payload);
        createOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés létrehozva.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Mentés sikertelen.",
            life: 3500,
        });
    }
};

const handleUpdate = async ({ id, payload }) => {
    try {
        await WorkScheduleAssignmentService.updateAssignment(id, payload);
        editOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés frissítve.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Frissítés sikertelen.",
            life: 3500,
        });
    }
};

const handleDelete = async (id) => {
    try {
        await WorkScheduleAssignmentService.deleteAssignment(id);
        editOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés törölve.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Törlés sikertelen.",
            life: 3500,
        });
    }
};

const onEventDrop = async ({ id, date }) => {
    const row = events.value.find((x) => Number(x.id) === Number(id));
    if (!row) return;

    await handleUpdate({
        id,
        payload: {
            work_schedule_id: Number(row.extendedProps.schedule_id),
            employee_id: Number(row.extendedProps.employee_id),
            work_shift_id: Number(row.extendedProps.shift_id),
            date,
        },
    });
};

const toggleSelectedDate = (ymd) => {
    if (scheduleRange.value.from && ymd < scheduleRange.value.from) return;
    if (scheduleRange.value.to && ymd > scheduleRange.value.to) return;

    if (selectedDates.value.includes(ymd)) {
        selectedDates.value = selectedDates.value.filter((x) => x !== ymd);
    } else {
        selectedDates.value = [...selectedDates.value, ymd].sort();
    }
};

const handleBulk = async (payload) => {
    if (!payload.employee_ids?.length) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Válassz legalább egy dolgozót.", life: 3200 });
        return;
    }
    if (!payload.work_shift_id) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Válassz műszakot.", life: 3200 });
        return;
    }
    if (!payload.dates?.length) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Nincs kijelölt nap.", life: 3200 });
        return;
    }

    try {
        await WorkScheduleAssignmentService.bulkUpsert(payload);
        bulkOpen.value = false;
        selectedDates.value = [];
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Bulk mentés kész.", life: 2200 });
    } catch (e) {
        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === "object") {
            const detail = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" | ");
            toast.add({
                severity: "warn",
                summary: "Validációs hiba",
                detail: detail || "A megadott adatok hibásak.",
                life: 5000,
            });
            return;
        }
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Bulk mentés sikertelen.",
            life: 3500,
        });
    }
};

watch([scheduleId, viewMode, anchorDate], loadEvents);
watch([selectedEmployeeIds, selectedShiftIds, selectedPositionIds], loadEvents);

onMounted(async () => {
    await loadSelectors();
    await loadEvents();
});
</script>

<template>
    <Head :title="title" />
    <Toast />

    <AssignmentCreateModal
        v-model="createOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :defaultDate="createDate"
        :loading="loading"
        @submit="handleCreate"
    />

    <AssignmentEditModal
        v-model="editOpen"
        :assignment="selectedEvent"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :loading="loading"
        @update="handleUpdate"
        @delete="handleDelete"
    />

    <AssignmentBulkAssignModal
        v-model="bulkOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :selectedDates="selectedDates"
        :loading="loading"
        @submit="handleBulk"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <div class="min-w-64">
                    <label class="mb-1 block text-xs text-slate-600">Munkabeosztás</label>
                    <Select
                        v-model="scheduleId"
                        :options="schedules"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        placeholder="Válassz beosztást"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs text-slate-600">Nézet</label>
                    <Select
                        v-model="viewMode"
                        :options="[
                            { label: 'Havi', value: 'month' },
                            { label: 'Heti', value: 'week' },
                            { label: 'Napi', value: 'day' },
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-32"
                    />
                </div>

                <div class="min-w-64">
                    <label class="mb-1 block text-xs text-slate-600">Dolgozó szűrő</label>
                    <MultiSelect
                        v-model="selectedEmployeeIds"
                        :options="employeeOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <div class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Műszak szűrő</label>
                    <MultiSelect
                        v-model="selectedShiftIds"
                        :options="shiftOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <div class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Pozíció szűrő</label>
                    <MultiSelect
                        v-model="selectedPositionIds"
                        :options="positionOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <Button icon="pi pi-refresh" severity="secondary" :loading="loading" @click="refresh" />

                <div v-if="canPlanner" class="ml-auto flex items-center gap-2">
                    <span class="text-sm">Planner mód</span>
                    <ToggleSwitch v-model="plannerMode" :disabled="selectedSchedule?.status === 'published'" />
                </div>

                <Button
                    v-if="plannerMode"
                    label="Bulk kijelöltek"
                    icon="pi pi-list-check"
                    :disabled="selectedDates.length === 0"
                    @click="bulkOpen = true"
                />
            </div>

            <CalendarBoard
                :events="events"
                :viewMode="viewMode"
                :anchorDate="anchorDate"
                :plannerMode="plannerMode"
                :selectedDates="selectedDates"
                :scheduleRange="scheduleRange"
                @event-click="onEventClick"
                @date-click="onDateClick"
                @toggle-date="toggleSelectedDate"
                @date-range-change="loadEvents"
                @event-drop="onEventDrop"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.shift-1) { border-color: #14b8a6; background: #f0fdfa; }
:deep(.shift-2) { border-color: #3b82f6; background: #eff6ff; }
:deep(.shift-3) { border-color: #f97316; background: #fff7ed; }
</style>
