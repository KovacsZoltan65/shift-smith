import { beforeEach, describe, expect, it, vi } from "vitest";

import Index from "@/Pages/WorkShifts/Index.vue";
import { usePermissions } from "@/composables/usePermissions";

import {
    createPrimeCrudStubs,
    flushUi,
    mountPrimeVue,
    resolveConfirmAccept,
} from "@/__tests__/helpers/frontendTestUtils";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

const { toastAdd, workShiftServiceMock } = vi.hoisted(() => ({
    toastAdd: vi.fn(),
    workShiftServiceMock: {
        getWorkShifts: vi.fn(),
        deleteWorkShift: vi.fn(),
        deleteWorkShifts: vi.fn(),
    },
}));

vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

let confirmAccept = null;
vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: (opts) => {
            confirmAccept = resolveConfirmAccept(opts?.accept);
        },
    }),
}));

vi.mock("@/services/WorkShiftService.js", () => ({
    default: workShiftServiceMock,
}));

const rows = [
    {
        id: 1,
        name: "Reggeli műszak",
        start_time: "06:00",
        end_time: "14:00",
        work_time_minutes: 480,
        break_minutes: 30,
        active: true,
    },
    {
        id: 2,
        name: "Délutáni műszak",
        start_time: "14:00",
        end_time: "22:00",
        work_time_minutes: 480,
        break_minutes: 30,
        active: true,
    },
];

const stubs = createPrimeCrudStubs({
    CreateModal: {
        props: ["modelValue"],
        emits: ["update:modelValue", "saved"],
        template: `
            <div v-if="modelValue" data-testid="create-modal">
                <button data-testid="create-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
            </div>
        `,
    },
    EditModal: {
        props: ["modelValue", "workShift"],
        emits: ["update:modelValue", "saved"],
        template: `
            <div v-if="modelValue" data-testid="edit-modal">
                <div data-testid="edit-work-shift-id">{{ workShift?.id }}</div>
                <button data-testid="edit-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
            </div>
        `,
    },
    AssignmentModal: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<div v-if="modelValue" data-testid="assignment-modal" />`,
    },
});

describe("WorkShifts CRUD (Index.vue)", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        confirmAccept = null;

        workShiftServiceMock.getWorkShifts.mockResolvedValue({
            data: {
                data: rows,
                meta: {
                    current_page: 1,
                    per_page: 10,
                    total: rows.length,
                },
            },
        });
        workShiftServiceMock.deleteWorkShift.mockResolvedValue({ data: {} });
        workShiftServiceMock.deleteWorkShifts.mockResolvedValue({
            data: { deleted: 2 },
        });
    });

    it("onMounted után lekéri és rendereli a listát", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Műszakok", filter: {} },
            global: { stubs },
        });

        await flushUi();

        expect(workShiftServiceMock.getWorkShifts).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain("Reggeli műszak");
        expect(wrapper.text()).toContain("Délutáni műszak");
    });

    it("create flow: modal megnyit, saved után refetch + toast", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Műszakok", filter: {} },
            global: { stubs },
        });

        await flushUi();

        await wrapper.find('[data-testid="work_shifts-create"]').trigger("click");
        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(true);

        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushUi();

        expect(workShiftServiceMock.getWorkShifts).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({ severity: "success" }),
        );
    });

    it("delete flow: confirm -> deleteWorkShift -> refetch + toast", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Műszakok", filter: {} },
            global: { stubs },
        });

        await flushUi();

        wrapper.vm.confirmDeleteOne(rows[0]);
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushUi();

        expect(workShiftServiceMock.deleteWorkShift).toHaveBeenCalledWith(1);
        expect(workShiftServiceMock.getWorkShifts).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({ severity: "success" }),
        );
    });

    it("bulk delete: confirm -> deleteWorkShifts -> selection reset + refetch", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Műszakok", filter: {} },
            global: { stubs },
        });

        await flushUi();

        wrapper.vm.selected = [rows[0], rows[1]];
        wrapper.vm.confirmBulkDelete();

        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushUi();

        expect(workShiftServiceMock.deleteWorkShifts).toHaveBeenCalledWith([1, 2]);
        expect(wrapper.vm.selected).toEqual([]);
        expect(workShiftServiceMock.getWorkShifts).toHaveBeenCalledTimes(2);
    });

    it("permission nélkül elrejti create és bulk delete gombokat", async () => {
        const { __allow } = usePermissions();
        __allow.delete("work_shifts.create");
        __allow.delete("work_shifts.delete");

        const wrapper = mountPrimeVue(Index, {
            props: { title: "Műszakok", filter: {} },
            global: { stubs },
        });
        await flushUi();

        expect(wrapper.find('[data-testid="work_shifts-create"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('[data-testid="work_shifts-bulk-delete"]').exists(),
        ).toBe(false);

        __allow.add("work_shifts.create");
        __allow.add("work_shifts.delete");
    });
});
