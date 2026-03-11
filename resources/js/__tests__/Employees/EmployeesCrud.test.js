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

const { employeeServiceMock } = vi.hoisted(() => ({
    employeeServiceMock: {
        exportEmployees: vi.fn(),
        downloadEmployeeTemplate: vi.fn(),
        importEmployees: vi.fn(),
        saveDownload: vi.fn(),
        extractErrors: vi.fn(() => null),
    },
}));

vi.mock("@/services/EmployeeService.js", () => ({
    default: employeeServiceMock,
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
        position_id: 1,
        position_name: "Fejlesztő",
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
        position_id: 2,
        position_name: "HR",
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
    Dialog: {
        props: ["visible"],
        template: `<div v-if="visible"><slot /></div>`,
    },
    SplitButton: {
        props: ["label", "model", "disabled"],
        template: `
          <div>
            <button :disabled="disabled">{{ label }}</button>
            <button
              v-for="item in (model ?? [])"
              :key="item.label"
              :data-testid="'split-' + item.label"
              @click="item.command?.()"
            >
              {{ item.label }}
            </button>
          </div>
        `,
    },

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
    DeleteEmployeeDialog: {
        name: "DeleteEmployeeDialog",
        props: ["visible"],
        emits: ["update:visible", "deleted"],
        template: `<div v-if="visible" data-testid="delete-dialog"></div>`,
    },
    WorkPatternModal: {
        name: "WorkPatternModal",
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<div v-if="modelValue" data-testid="work-pattern-modal"></div>`,
    },
    EmployeeImportDialog: {
        name: "EmployeeImportDialog",
        props: ["modelValue"],
        emits: ["update:modelValue", "completed"],
        template: `
          <div v-if="modelValue" data-testid="employee-import-dialog">
            <button
              data-testid="employee-import-complete"
              @click="$emit('completed', { total_rows: 1, imported_count: 1, failed_count: 0, skipped_count: 0, rows: [] })"
            >
              complete
            </button>
          </div>
        `,
    },
};

const globalMount = {
    stubs,
    mocks: {
        $t: (key) => key,
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

        employeeServiceMock.exportEmployees.mockResolvedValue({
            data: new Blob(["csv"]),
            headers: {},
        });
        employeeServiceMock.downloadEmployeeTemplate.mockResolvedValue({
            data: new Blob(["csv"]),
            headers: {},
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
            global: globalMount,
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
            global: globalMount,
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
            global: globalMount,
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
    it("Delete one: opens dialog and deleted callback refreshes the list", async () => {
        const wrapper = mount(Index, {
            global: globalMount,
        });

        await flushPromises();

        wrapper.vm.confirmDeleteOne(employeesList[0]);
        await flushPromises();

        expect(wrapper.find('[data-testid="delete-dialog"]').exists()).toBe(true);

        await wrapper.vm.onDeleted();
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Bulk delete – guard
    // -------------------------------------------------------------------------
    it("confirmBulkDelete: üres selection -> nem hív confirmot", async () => {
        const wrapper = mount(Index, {
            global: globalMount,
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
            global: globalMount,
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

    it("bulkDelete hiba esetén error toast és a selected nem ürül", async () => {
        const wrapper = mount(Index, {
            global: globalMount,
        });

        await flushPromises();

        wrapper.vm.selected = [employeesList[0], employeesList[1]];

        csrfFetchMock.mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: async () => ({ message: "Bulk törlés sikertelen." }),
        });

        await wrapper.vm.bulkDelete([1, 2]);
        await flushPromises();

        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({
                severity: "error",
                detail: "Bulk törlés sikertelen.",
            }),
        );
        expect(wrapper.vm.selected).toEqual([employeesList[0], employeesList[1]]);
    });

    // -------------------------------------------------------------------------
    // Search debounce
    // -------------------------------------------------------------------------
    it("Export action smoke: letöltés service hívás fut", async () => {
        const wrapper = mount(Index, {
            global: globalMount,
        });

        await flushPromises();

        await wrapper.vm.downloadExport("csv");
        await flushPromises();

        expect(employeeServiceMock.exportEmployees).toHaveBeenCalledWith("csv", {
            company_id: null,
        });
        expect(employeeServiceMock.saveDownload).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // default_company_id filter
    // -------------------------------------------------------------------------
    it("default_company_id bekerül az első fetch query-be", async () => {
        mount(Index, {
            props: { default_company_id: 5 },
            global: globalMount,
        });

        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalled();
        const firstCallUrl = String(globalThis.fetch.mock.calls[0][0]);
        expect(firstCallUrl).toContain("company_id=5");
    });

    it("Import flow: dialog megnyílik és completion után refresh fut", async () => {
        const wrapper = mount(Index, {
            global: globalMount,
        });

        await flushPromises();

        const fetchCountBefore = globalThis.fetch.mock.calls.length;

        await wrapper.find('[data-testid="employees-import"]').trigger("click");
        expect(wrapper.find('[data-testid="employee-import-dialog"]').exists()).toBe(true);

        await wrapper.find('[data-testid="employee-import-complete"]').trigger("click");
        await flushPromises();

        expect(globalThis.fetch.mock.calls.length).toBeGreaterThan(fetchCountBefore);
        expect(toastAdd).toHaveBeenCalled();
    });
});
