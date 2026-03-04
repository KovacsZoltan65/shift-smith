import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Index from "@/Pages/Scheduling/Calendar/Index.vue";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: vi.fn() }),
}));

vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: vi.fn(),
    }),
}));

const employeeSelectorMock = vi.fn();
const shiftSelectorMock = vi.fn();
const positionSelectorMock = vi.fn();
const calendarFeedMock = vi.fn();

vi.mock("@/services/EmployeeService.js", () => ({
    default: {
        getToSelect: (...args) => employeeSelectorMock(...args),
    },
}));

vi.mock("@/services/WorkShiftService.js", () => ({
    default: {
        getToSelect: (...args) => shiftSelectorMock(...args),
    },
}));

vi.mock("@/services/PositionService.js", () => ({
    default: {
        getToSelect: (...args) => positionSelectorMock(...args),
    },
}));

vi.mock("@/services/WorkScheduleAssignmentService.js", () => ({
    default: {
        getCalendarFeed: (...args) => calendarFeedMock(...args),
        createAssignment: vi.fn(),
        updateAssignment: vi.fn(),
        deleteAssignment: vi.fn(),
        bulkUpsert: vi.fn(),
    },
}));

vi.mock("@/services/AbsenceService.js", () => ({
    default: {
        store: vi.fn(),
        update: vi.fn(),
        destroy: vi.fn(),
    },
}));

vi.mock("@/services/MonthClosureService.js", () => ({
    default: {
        close: vi.fn(),
        reopen: vi.fn(),
    },
}));

const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },
    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },
    Select: {
        props: ["modelValue", "options", "optionLabel", "optionValue"],
        emits: ["update:modelValue"],
        template: `<select :value="modelValue" @change="$emit('update:modelValue', Number($event.target.value))"><slot /></select>`,
    },
    SelectButton: {
        props: ["modelValue", "options", "optionLabel", "optionValue"],
        emits: ["update:modelValue"],
        template: `<div />`,
    },
    MultiSelect: { template: "<div />" },
    ToggleSwitch: {
        props: ["modelValue", "disabled"],
        emits: ["update:modelValue"],
        template: `<button type="button" data-testid="planner-toggle" :disabled="disabled" @click="$emit('update:modelValue', !modelValue)">planner</button>`,
    },
    InputNumber: { template: "<input />" },
    DatePicker: { template: "<input />" },
    Button: {
        props: ["label", "disabled", "severity", "icon", "outlined", "size", "loading"],
        emits: ["click"],
        template: `<button type="button" :data-label="label" :disabled="disabled" @click="$emit('click', $event)">{{ label }}</button>`,
    },
    CalendarBoard: { template: "<div />" },
    AssignmentCreateModal: { template: "<div />" },
    AssignmentEditModal: { template: "<div />" },
    AssignmentBulkAssignModal: { template: "<div />" },
    AbsenceModal: { template: "<div />" },
};

function ok(data) {
    return Promise.resolve({ data });
}

describe("Calendar month lock UI", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date("2026-03-15T10:00:00"));

        employeeSelectorMock.mockResolvedValue({ data: [] });
        shiftSelectorMock.mockResolvedValue({ data: [] });
        positionSelectorMock.mockResolvedValue({ data: [] });
        calendarFeedMock.mockResolvedValue(
            ok({
                data: [],
                meta: {
                    range: { start: "2026-03-01", end: "2026-03-31" },
                    selected_date: "2026-03-31",
                    editable: true,
                    month_lock: {
                        id: 5,
                        year: 2026,
                        month: 3,
                        is_closed: true,
                        closed_at: "2026-03-15 10:00:00",
                        closed_by_name: "Admin User",
                        note: "Lezárva",
                    },
                    closed_month_keys: ["2026-03"],
                },
            }),
        );
    });

    it("lezárt hónapnál lock badge-et mutat és tiltja a planner kapcsolót", async () => {
        const wrapper = mount(Index, {
            props: {
                current_company_id: 1,
                schedules: [
                    { id: 1, name: "Márciusi", date_from: "2026-03-01", date_to: "2026-03-31", status: "draft" },
                ],
                month_lock: {
                    id: 5,
                    year: 2026,
                    month: 3,
                    is_closed: true,
                    closed_at: "2026-03-15 10:00:00",
                    closed_by_name: "Admin User",
                    note: "Lezárva",
                },
                permissions: {
                    viewer: true,
                    planner: true,
                    absenceViewer: false,
                    absencePlanner: false,
                    monthClosureViewAny: true,
                    monthClosureClose: true,
                    monthClosureReopen: true,
                },
            },
            global: {
                stubs,
                directives: {
                    tooltip: () => {},
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain("Hónap lezárva: 2026-03");
        expect(wrapper.find('[data-testid="planner-toggle"]').attributes("disabled")).toBeDefined();
        expect(wrapper.find('[data-label="Újranyitás"]').exists()).toBe(true);
        expect(wrapper.find('[data-label="Hónap lezárása"]').exists()).toBe(false);
    });
});
