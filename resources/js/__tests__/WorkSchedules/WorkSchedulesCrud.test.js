import { beforeEach, describe, expect, it, vi } from "vitest";

import Index from "@/Pages/WorkSchedules/Index.vue";
import { usePermissions } from "@/composables/usePermissions";

import {
    createPrimeCrudStubs,
    flushUi,
    mockFetchOk,
    mountPrimeVue,
    resolveConfirmAccept,
} from "@/__tests__/helpers/frontendTestUtils";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

const toastAdd = vi.fn();
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

const csrfFetchMock = vi.fn();
vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

const rows = [
    {
        id: 1,
        name: "Március 1. hét",
        date_from: "2026-03-01",
        date_to: "2026-03-07",
        status: "draft",
        created_at: "2026-02-20",
    },
    {
        id: 2,
        name: "Március 2. hét",
        date_from: "2026-03-08",
        date_to: "2026-03-14",
        status: "published",
        created_at: "2026-02-21",
    },
];

const stubs = createPrimeCrudStubs({
    CompanySelector: {
        props: ["modelValue", "placeholder"],
        emits: ["update:modelValue"],
        template: `
            <div data-testid="company-selector">
                <button data-testid="company-set-2" @click="$emit('update:modelValue', 2)">set2</button>
                <button data-testid="company-clear" @click="$emit('update:modelValue', null)">clear</button>
            </div>
        `,
    },
    CreateModal: {
        props: ["modelValue"],
        emits: ["update:modelValue", "saved"],
        template: `
            <div v-if="modelValue" data-testid="create-modal">
                <button data-testid="create-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
            </div>
        `,
    },
    EditModal: { template: "<div />" },
    DeleteModal: { template: "<div />" },
    AssignmentModal: { template: "<div />" },
    AutoPlanWizardDialog: { template: "<div />" },
});

describe("WorkSchedules CRUD (Index.vue)", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        confirmAccept = null;

        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/work_schedules/fetch?")) {
                return mockFetchOk({
                    data: rows,
                    meta: {
                        current_page: 1,
                        per_page: 10,
                        total: rows.length,
                        last_page: 1,
                    },
                });
            }

            return mockFetchOk({ data: [], meta: { total: 0 } });
        });

        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ deleted: 1 }),
        });
    });

    it("onMounted után lekéri és rendereli a listát", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Beosztások", filter: {} },
            global: { stubs },
        });

        await flushUi();

        expect(globalThis.fetch).toHaveBeenCalled();
        expect(wrapper.text()).toContain("Március 1. hét");
        expect(wrapper.text()).toContain("Március 2. hét");
    });

    it("CompanySelector váltáskor refetch történik company_id parammal", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Beosztások", filter: {} },
            global: { stubs },
        });

        await flushUi();

        await wrapper.find('[data-testid="company-set-2"]').trigger("click");
        await flushUi();

        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(calls.some((u) => u.includes("company_id=2"))).toBe(true);
    });

    it("create flow: modal megnyit, saved után refetch + toast", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Beosztások", filter: {} },
            global: { stubs },
        });
        await flushUi();

        await wrapper.find('[data-testid="work_schedules-create"]').trigger("click");
        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(true);

        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushUi();

        expect(globalThis.fetch.mock.calls.length).toBeGreaterThanOrEqual(2);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({ severity: "success" }),
        );
    });

    it("bulk delete csak draft rekordokra megy, majd selection reset + refetch", async () => {
        const wrapper = mountPrimeVue(Index, {
            props: { title: "Beosztások", filter: {} },
            global: { stubs },
        });
        await flushUi();

        wrapper.vm.selected = [rows[0], rows[1]]; // draft + published
        wrapper.vm.confirmBulkDelete();
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushUi();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/work_schedules/destroy_bulk",
            expect.objectContaining({
                method: "DELETE",
                body: JSON.stringify({ ids: [1] }),
            }),
        );
        expect(wrapper.vm.selected).toEqual([]);
        expect(globalThis.fetch.mock.calls.length).toBeGreaterThanOrEqual(2);
    });

    it("permission nélkül elrejti create és bulk delete gombokat", async () => {
        const { __allow } = usePermissions();
        __allow.delete("work_schedules.create");
        __allow.delete("work_schedules.deleteAny");

        const wrapper = mountPrimeVue(Index, {
            props: { title: "Beosztások", filter: {} },
            global: { stubs },
        });
        await flushUi();

        expect(
            wrapper.find('[data-testid="work_schedules-create"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="work_schedules-bulk-delete"]').exists(),
        ).toBe(false);

        __allow.add("work_schedules.create");
        __allow.add("work_schedules.deleteAny");
    });
});
