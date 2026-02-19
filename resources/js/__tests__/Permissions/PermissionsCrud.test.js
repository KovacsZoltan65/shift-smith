import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";

import Index from "@/Pages/Admin/Permissions/Index.vue";

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
            confirmAccept = opts.accept;
        },
    }),
}));

const csrfFetchMock = vi.fn();
vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

const permissionsList = [
    { id: 1, name: "companies.viewAny", guard_name: "web" },
    { id: 2, name: "roles.viewAny", guard_name: "web" },
];

const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },
    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },
    Tag: {
        props: ["value"],
        template: `<span data-testid="tag">{{ value }}</span>`,
    },
    Button: {
        inheritAttrs: true,
        props: [
            "label",
            "icon",
            "size",
            "severity",
            "loading",
            "disabled",
            "text",
            "rounded",
        ],
        template: `<button v-bind="$attrs" :disabled="disabled" @click="$emit('click', $event)">{{ label }}<slot /></button>`,
    },
    InputText: {
        inheritAttrs: true,
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value); $emit('input', $event)" />`,
    },
    DataTable: {
        props: ["value"],
        template: `
          <div data-testid="datatable">
            <div v-for="row in (value ?? [])" :key="row.id" class="row">
              <span class="row-name">{{ row.name }}</span>
            </div>
            <slot />
          </div>
        `,
    },
    Column: { template: "<div><slot /></div>" },
    Menu: { template: "<div />" },
    CreateModal: {
        name: "CreateModal",
        props: ["modelValue"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="create-modal">
            <button data-testid="create-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
          </div>
        `,
    },
    EditModal: {
        name: "EditModal",
        props: ["modelValue", "permission"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="edit-modal">
            <div data-testid="edit-permission-id">{{ permission?.id }}</div>
            <button data-testid="edit-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
          </div>
        `,
    },
};

function mockFetchOk(json) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => json,
    });
}

describe("Permissions CRUD (Index.vue)", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        confirmAccept = null;

        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/admin/permissions/fetch?")) {
                return mockFetchOk({
                    data: { current_page: 1, data: permissionsList },
                    meta: { total: permissionsList.length },
                    filter: {},
                });
            }

            if (u === "/admin/permissions/1") {
                return mockFetchOk({
                    message: "ok",
                    data: {
                        id: 1,
                        name: "companies.viewAny",
                        guard_name: "web",
                    },
                });
            }

            return mockFetchOk({ data: [] });
        });

        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: "ok" }),
        });
    });

    it("rendereli a listát az onMounted fetch után", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        expect(wrapper.find('[data-testid="datatable"]').exists()).toBe(true);
        expect(wrapper.text()).toContain("companies.viewAny");
        expect(wrapper.text()).toContain("roles.viewAny");
    });

    it("kezeli az új list payloadot is (data: [...])", async () => {
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/admin/permissions/fetch?")) {
                return mockFetchOk({
                    message: "ok",
                    data: permissionsList,
                    meta: { total: permissionsList.length },
                    filter: {},
                });
            }

            return mockFetchOk({ data: [] });
        });

        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        expect(wrapper.text()).toContain("companies.viewAny");
    });

    it("Létrehozás: modális mentés után frissít és toastot küld", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.find('[data-testid="permissions-create"]').trigger("click");
        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(true);

        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();

        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/admin/permissions/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    it("Szerkesztés: detail fetch után nyitja a modált", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.vm.openEditModal({ id: 1, name: "companies.viewAny" });
        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalledWith(
            "/admin/permissions/1",
            expect.any(Object),
        );
        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="edit-permission-id"]').text()).toBe("1");
    });

    it("Törlés: confirm után csrfFetch DELETE és lista frissül", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        wrapper.vm.confirmDeleteOne({ id: 1, name: "companies.viewAny" });
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/admin/permissions/1",
            expect.objectContaining({ method: "DELETE" }),
        );
    });

    it("Bulk törlés: confirm után /destroy_bulk hívás történik", async () => {
        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        wrapper.vm.selected = [permissionsList[0], permissionsList[1]];
        wrapper.vm.confirmBulkDelete();
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/admin/permissions/destroy_bulk",
            expect.objectContaining({
                method: "DELETE",
                body: JSON.stringify({ ids: [1, 2] }),
            }),
        );
        expect(wrapper.vm.selected).toEqual([]);
    });

    it("Edit hiba esetén nem nyit modált és error toastot küld", async () => {
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);
            if (u.startsWith("/admin/permissions/fetch?")) {
                return mockFetchOk({
                    data: { current_page: 1, data: permissionsList },
                    meta: { total: permissionsList.length },
                    filter: {},
                });
            }
            if (u === "/admin/permissions/1") {
                return { ok: false, status: 500, json: async () => ({}) };
            }
            return mockFetchOk({ data: [] });
        });

        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.vm.openEditModal({ id: 1, name: "companies.viewAny" });
        await flushPromises();

        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(false);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({ severity: "error", detail: "HTTP 500" }),
        );
    });
});
