import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Index from "@/Pages/Scheduling/Calendar/Index.vue";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

const toastAdd = vi.fn();
vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

const employeeSelectorMock = vi.fn();
const shiftSelectorMock = vi.fn();
const positionSelectorMock = vi.fn();
const calendarFeedMock = vi.fn();
const bulkUpsertMock = vi.fn();

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
        bulkUpsert: (...args) => bulkUpsertMock(...args),
        createAssignment: vi.fn(),
        updateAssignment: vi.fn(),
        deleteAssignment: vi.fn(),
    },
}));

vi.mock("@/services/AbsenceService.js", () => ({
    default: {
        store: vi.fn(),
        update: vi.fn(),
        destroy: vi.fn(),
    },
}));

const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },
    Toast: { template: "<div />" },
    Select: {
        props: ["modelValue", "options", "optionLabel", "optionValue"],
        emits: ["update:modelValue"],
        template: `<select :value="modelValue" @change="$emit('update:modelValue', Number($event.target.value))"><slot /></select>`,
    },
    SelectButton: {
        props: ["modelValue", "options", "optionLabel", "optionValue"],
        emits: ["update:modelValue"],
        template: `
            <div>
                <button
                    v-for="option in options"
                    :key="option[optionValue]"
                    type="button"
                    @click="$emit('update:modelValue', option[optionValue])"
                >
                    {{ option[optionLabel] }}
                </button>
            </div>
        `,
    },
    MultiSelect: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<div />`,
    },
    ToggleSwitch: {
        props: ["modelValue", "disabled"],
        emits: ["update:modelValue"],
        template: `
            <button
                type="button"
                data-testid="planner-toggle"
                :disabled="disabled"
                @click="$emit('update:modelValue', !modelValue)"
            >
                {{ modelValue ? 'on' : 'off' }}
            </button>
        `,
    },
    InputNumber: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<input :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))" />`,
    },
    DatePicker: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<input />`,
    },
    Button: {
        props: ["label", "icon", "severity", "outlined", "disabled", "loading", "size"],
        emits: ["click"],
        template: `
            <button
                type="button"
                :data-label="label"
                :data-severity="severity"
                :data-outlined="String(!!outlined)"
                :disabled="disabled"
                @click="$emit('click', $event)"
            >
                {{ label }}
            </button>
        `,
    },
    AssignmentCreateModal: { template: "<div />" },
    AssignmentEditModal: { template: "<div />" },
    AbsenceModal: { template: "<div />" },
    AssignmentBulkAssignModal: {
        props: ["modelValue", "selectedDates"],
        emits: ["update:modelValue", "submit"],
        template: `
            <div v-if="modelValue">
                <button
                    type="button"
                    data-testid="bulk-submit"
                    @click="$emit('submit', { employee_ids: [5], work_shift_id: 7, dates: [...selectedDates] })"
                >
                    submit
                </button>
            </div>
        `,
    },
    CalendarBoard: {
        props: ["selectedDates", "plannerMode"],
        emits: ["toggle-date"],
        template: `
            <div>
                <div data-testid="selected-count">{{ selectedDates.length }}</div>
                <button
                    type="button"
                    data-testid="toggle-day"
                    :disabled="!plannerMode"
                    @click="$emit('toggle-date', '2026-03-06')"
                >
                    toggle-day
                </button>
            </div>
        `,
    },
};

function ok(data) {
    return Promise.resolve({ data });
}

describe("Calendar bulk selection state", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        vi.setSystemTime(new Date("2026-03-04T10:00:00"));

        employeeSelectorMock.mockResolvedValue({ data: [] });
        shiftSelectorMock.mockResolvedValue({ data: [] });
        positionSelectorMock.mockResolvedValue({ data: [] });
        calendarFeedMock.mockResolvedValue(
            ok({
                data: [],
                meta: {
                    range: { start: "2026-03-02", end: "2026-03-08" },
                    selected_date: "2026-03-08",
                    editable: true,
                },
            }),
        );
        bulkUpsertMock.mockResolvedValue(ok({ message: "ok" }));
    });

    it("bulk mentés után nullázza a gyors kijelölést és újra engedi a kézi napkijelölést", async () => {
        const wrapper = mount(Index, {
            props: {
                current_company_id: 1,
                schedules: [
                    { id: 1, name: "Márciusi", date_from: "2026-03-01", date_to: "2026-03-31", status: "draft" },
                ],
                permissions: { viewer: true, planner: true, absenceViewer: false, absencePlanner: false },
            },
            global: { stubs },
        });

        await flushPromises();

        await wrapper.find('[data-testid="planner-toggle"]').trigger("click");
        await flushPromises();

        const allButton = wrapper.find('[data-label="Osszes"]');
        expect(allButton.attributes("data-severity")).toBe("secondary");

        await allButton.trigger("click");
        await flushPromises();

        expect(wrapper.find('[data-testid="selected-count"]').text()).toBe("5");
        expect(wrapper.find('[data-label="Osszes"]').attributes("data-severity")).toBe("primary");

        await wrapper.find('[data-label="Bulk kijelöltek"]').trigger("click");
        await flushPromises();
        await wrapper.find('[data-testid="bulk-submit"]').trigger("click");
        await flushPromises();

        expect(bulkUpsertMock).toHaveBeenCalledWith(
            expect.objectContaining({
                employee_ids: [5],
                work_shift_id: 7,
                dates: expect.any(Array),
            }),
        );
        expect(wrapper.find('[data-testid="selected-count"]').text()).toBe("0");
        expect(wrapper.find('[data-label="Osszes"]').attributes("data-severity")).toBe("secondary");
        expect(wrapper.find('[data-label="Osszes"]').attributes("data-outlined")).toBe("true");

        await wrapper.find('[data-testid="toggle-day"]').trigger("click");
        await flushPromises();

        expect(wrapper.find('[data-testid="selected-count"]').text()).toBe("1");
    });
});
