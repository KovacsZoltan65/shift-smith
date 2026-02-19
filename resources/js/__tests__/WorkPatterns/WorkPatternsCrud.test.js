import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Index from "@/Pages/Scheduling/WorkPatterns/Index.vue";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

const toastAdd = vi.fn();
vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

let confirmAccept = null;
const confirmRequire = vi.fn();
vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: (opts) => {
            confirmRequire(opts);
            confirmAccept = opts?.accept ?? null;
        },
    }),
}));

const csrfFetchMock = vi.fn();
vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

const rows = [
    { id: 1, company_id: 1, name: "Fix nappal", type: "fixed_weekly", weekly_minutes: 2400, active: true },
    { id: 2, company_id: 1, name: "Éjszakás", type: "rotating_shifts", weekly_minutes: 2200, active: false },
];

const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },
    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },
    Button: {
        props: ["label", "disabled", "loading"],
        emits: ["click"],
        template: `<button :disabled="disabled" @click="$emit('click', $event)">{{ label }}<slot /></button>`,
    },
    InputText: {
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value);$emit('input',$event)" />`,
    },
    DataTable: {
        props: ["value"],
        template: `<div><div v-for="r in (value ?? [])" :key="r.id">{{ r.name }}</div><slot /></div>`,
    },
    Column: { template: "<div><slot /></div>" },
    Menu: { template: "<div />" },
    Tag: { props: ["value"], template: "<span>{{ value }}</span>" },
    CompanySelector: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<button data-testid="company-set-1" @click="$emit('update:modelValue', 1)">set-company</button>`,
    },
    CreateModal: {
        props: ["modelValue"],
        emits: ["update:modelValue", "saved"],
        template: `<div v-if="modelValue"><button data-testid="create-save" @click="$emit('saved', 'ok');$emit('update:modelValue', false)">save</button></div>`,
    },
    EditModal: {
        props: ["modelValue", "workPattern"],
        emits: ["update:modelValue", "saved"],
        template: `<div v-if="modelValue"><button data-testid="edit-save" @click="$emit('saved', 'ok');$emit('update:modelValue', false)">save</button></div>`,
    },
};

function ok(json) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => json,
    });
}

describe("WorkPatterns CRUD (Index.vue)", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        confirmAccept = null;
        vi.useFakeTimers();

        globalThis.fetch = vi.fn(async (url) => {
            if (String(url).startsWith("/work-patterns/fetch?")) {
                return ok({
                    data: rows,
                    meta: { total: rows.length, current_page: 1, per_page: 10, last_page: 1 },
                });
            }
            if (String(url).startsWith("/work-patterns/")) {
                return ok({ data: rows[0] });
            }
            return ok({ data: [] });
        });

        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: "ok" }),
        });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("onMounted lefut és betölti a listát", async () => {
        const wrapper = mount(Index, { props: { title: "Munkarendek", filter: {} }, global: { stubs } });
        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalled();
        expect(wrapper.text()).toContain("Fix nappal");
        expect(wrapper.text()).toContain("Éjszakás");
    });

    it("create flow: modal save után toast és refresh", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.vm.openCreate();
        await flushPromises();
        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();
    });

    it("single delete: confirm accept -> DELETE", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        wrapper.vm.confirmDeleteOne(rows[0]);
        expect(confirmRequire).toHaveBeenCalled();
        expect(confirmAccept).toBeTypeOf("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/work-patterns/1",
            expect.objectContaining({ method: "DELETE" }),
        );
    });

    it("bulk delete: DELETE destroy_bulk hívás", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.vm.bulkDelete([1, 2]);
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/work-patterns/destroy_bulk",
            expect.objectContaining({
                method: "DELETE",
                body: JSON.stringify({ ids: [1, 2] }),
            }),
        );
    });
});
