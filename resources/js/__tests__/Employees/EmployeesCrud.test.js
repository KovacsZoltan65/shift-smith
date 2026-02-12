// EmployeesCrud.test.js
// -----------------------------------------------------------------------------
// Cél:
// - Employees/Index.vue CRUD viselkedésének tesztelése
// - fetch alapú listázás
// - create / edit / delete / bulkDelete
// - search debounce
// - CompanySelector filter
//
// Architektúra:
// - PrimeVue komponensek stubolva
// - csrfFetch mockolva
// - useConfirm + useToast mockolva
// - global fetch mockolva
// -----------------------------------------------------------------------------

import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

import Index from "@/Pages/HR/Employees/Index.vue";

// -----------------------------------------------------------------------------
// Inertia stub (Head + usePage)
// -----------------------------------------------------------------------------
vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
    usePage: () => ({}),
}));

// -----------------------------------------------------------------------------
// Toast mock (PrimeVue)
// -----------------------------------------------------------------------------
const toastAdd = vi.fn();

vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

// -----------------------------------------------------------------------------
// ConfirmDialog mock (PrimeVue)
// -----------------------------------------------------------------------------
let confirmAccept = null;
const confirmRequire = vi.fn();

vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: (opts) => {
            // eltesszük az accept callbacket,
            // hogy később a tesztből meghívhassuk
            confirmRequire(opts);
            confirmAccept = opts?.accept ?? null;
        },
    }),
}));

// -----------------------------------------------------------------------------
// csrfFetch mock (DELETE / POST hívásokhoz)
// -----------------------------------------------------------------------------
const csrfFetchMock = vi.fn();

vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

// -----------------------------------------------------------------------------
// Teszt adatok (fixture)
// -----------------------------------------------------------------------------
const employeesList = [
    {
        id: 1,
        first_name: "Kovács",
        last_name: "János",
        name: "Kovács János",
        email: "kovacs.janos@testceg.hu",
        phone: "+36 1 234 5678",
        active: true,
        position: "Fejlesztő",
        hired_at: "2026-01-01",
    },
    {
        id: 2,
        first_name: "Nagy",
        last_name: "Anna",
        name: "Nagy Anna",
        email: "nagy.anna@testceg.hu",
        phone: "+36 30 111 2222",
        active: false,
        position: "HR",
        hired_at: null,
    },
];

// -----------------------------------------------------------------------------
// PrimeVue + egyéb komponens stubok
// -----------------------------------------------------------------------------
const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },

    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },

    // Egyszerű Button stub, ami átengedi a click-et
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
        template: `<button v-bind="$attrs"
                           :disabled="disabled"
                           @click="$emit('click', $event)">
                      {{ label }}
                      <slot />
                   </button>`,
    },

    InputText: {
        inheritAttrs: true,
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input v-bind="$attrs"
                          :value="modelValue"
                          @input="$emit('update:modelValue', $event.target.value);
                                  $emit('input', $event)" />`,
    },

    // DataTable: csak renderel, nincs PrimeVue belső logika
    DataTable: {
        props: ["value"],
        template: `
          <div data-testid="datatable">
            <div v-for="row in (value ?? [])"
                 :key="row.id"
                 class="row">
              <span class="row-name">
                {{ row.name ?? (row.first_name + ' ' + row.last_name) }}
              </span>
            </div>
            <slot />
          </div>
        `,
    },

    Column: { template: "<div><slot /></div>" },
    Menu: { template: "<div />" },

    // CompanySelector stub: csak update:modelValue emit
    CompanySelector: {
        props: ["modelValue", "placeholder", "onlyWithEmployees"],
        emits: ["update:modelValue"],
        template: `
          <div data-testid="company-selector">
            <button data-testid="company-set-1"
                    @click="$emit('update:modelValue', 1)">
              set1
            </button>
            <button data-testid="company-clear"
                    @click="$emit('update:modelValue', null)">
              clear
            </button>
          </div>
        `,
    },

    CreateModal: {
        name: "CreateModal",
        props: ["modelValue", "defaultCompanyId"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="create-modal">
            <div data-testid="create-default-company">
              {{ defaultCompanyId }}
            </div>
            <button data-testid="create-save"
                    @click="$emit('saved', 'Mentve.');
                            $emit('update:modelValue', false)">
              save
            </button>
          </div>
        `,
    },

    EditModal: {
        name: "EditModal",
        props: ["modelValue", "employee"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="edit-modal">
            <div data-testid="edit-employee-id">
              {{ employee?.id }}
            </div>
            <button data-testid="edit-save"
                    @click="$emit('saved', 'Mentve.');
                            $emit('update:modelValue', false)">
              save
            </button>
          </div>
        `,
    },
};

// -----------------------------------------------------------------------------
// fetch helper
// -----------------------------------------------------------------------------
function mockFetchOk(json) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => json,
    });
}

// -----------------------------------------------------------------------------
// Teszt suite
// -----------------------------------------------------------------------------
describe("Employees CRUD (Index.vue)", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        confirmAccept = null;

        // debounce miatt fake timers
        vi.useFakeTimers();

        // fetch mock (lista lekérés)
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/employees/fetch?")) {
                return mockFetchOk({
                    data: {
                        data: employeesList,
                        total: employeesList.length,
                    },
                });
            }

            return mockFetchOk({ data: [] });
        });

        // csrfFetch sikeres válasz
        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: "ok" }),
        });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // -------------------------------------------------------------------------
    // onMounted + list rendering
    // -------------------------------------------------------------------------
    it("onMounted lefut és rendereli a listát", async () => {
        const wrapper = mount(Index, {
            props: { title: "Dolgozók", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalled();
        expect(wrapper.text()).toContain("Kovács János");
        expect(wrapper.text()).toContain("Nagy Anna");
    });

    // -------------------------------------------------------------------------
    // Create flow
    // -------------------------------------------------------------------------
    it("Create flow: open modal -> saved -> új fetch + toast", async () => {
        const wrapper = mount(Index, {
            props: { default_company_id: 1 },
            global: { stubs },
        });

        await flushPromises();

        await wrapper.find('[data-testid="employees-create"]').trigger("click");

        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(
            true,
        );

        // ellenőrizzük a default company id-t
        expect(
            wrapper.find('[data-testid="create-default-company"]').text(),
        ).toBe("1");

        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Edit flow
    // -------------------------------------------------------------------------
    it("Edit flow: openEditModal -> saved -> toast", async () => {
        const wrapper = mount(Index, {
            props: {},
            global: { stubs },
        });

        await flushPromises();

        await wrapper.vm.openEditModal(employeesList[0]);
        await flushPromises();

        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="edit-employee-id"]').text()).toBe(
            "1",
        );

        await wrapper.find('[data-testid="edit-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Single delete
    // -------------------------------------------------------------------------
    it("Delete one: confirm -> accept -> DELETE -> toast + refresh", async () => {
        const wrapper = mount(Index, {
            global: { stubs },
        });

        await flushPromises();

        wrapper.vm.confirmDeleteOne(employeesList[0]);

        expect(confirmRequire).toHaveBeenCalledTimes(1);
        expect(confirmAccept).toBeTypeOf("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/employees/1",
            expect.objectContaining({ method: "DELETE" }),
        );

        expect(toastAdd).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Bulk delete – guard
    // -------------------------------------------------------------------------
    it("confirmBulkDelete: üres selection -> nem hív confirmot", async () => {
        const wrapper = mount(Index, {
            global: { stubs },
        });

        await flushPromises();

        wrapper.vm.confirmBulkDelete();

        expect(confirmRequire).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Bulk delete – logika
    // -------------------------------------------------------------------------
    it("bulkDelete(ids): POST destroy_bulk -> selected ürül + toast", async () => {
        const wrapper = mount(Index, {
            global: { stubs },
        });

        await flushPromises();

        wrapper.vm.selected = [employeesList[0], employeesList[1]];

        await wrapper.vm.bulkDelete([1, 2]);
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/employees/destroy_bulk",
            expect.objectContaining({
                method: "POST",
                body: JSON.stringify({ ids: [1, 2] }),
            }),
        );

        expect(toastAdd).toHaveBeenCalled();
        expect(wrapper.vm.selected).toEqual([]);
    });

    // -------------------------------------------------------------------------
    // Search debounce
    // -------------------------------------------------------------------------
    it("Search debounce: 300ms után fetch", async () => {
        const wrapper = mount(Index, {
            global: { stubs },
        });

        await flushPromises();

        const initial = globalThis.fetch.mock.calls.length;

        const input = wrapper.find('input[placeholder="Keresés..."]');
        await input.setValue("kov");
        await input.trigger("input");

        // még nem fut
        expect(globalThis.fetch.mock.calls.length).toBe(initial);

        vi.advanceTimersByTime(310);
        await flushPromises();

        expect(globalThis.fetch.mock.calls.length).toBeGreaterThan(initial);
    });

    // -------------------------------------------------------------------------
    // Company filter
    // -------------------------------------------------------------------------
    it("CompanySelector change -> új fetch", async () => {
        const wrapper = mount(Index, {
            global: { stubs },
        });

        await flushPromises();
        const initial = globalThis.fetch.mock.calls.length;

        await wrapper.find('[data-testid="company-set-1"]').trigger("click");
        await flushPromises();

        expect(globalThis.fetch.mock.calls.length).toBeGreaterThan(initial);
    });
});
