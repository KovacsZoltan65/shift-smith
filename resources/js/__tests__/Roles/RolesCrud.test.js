// resources/js/__tests__/Roles/RolesCrud.test.js
// -----------------------------------------------------------------------------
// Cél:
// - Roles/Index.vue CRUD viselkedésének tesztelése (Vitest + Vue Test Utils)
// - onMounted fetch -> lista render
// - Create / Edit modal flow
// - Delete one confirm + csrfFetch DELETE
//
// Megközelítés:
// - Inertia (Head) stubolva, hogy ne kelljen teljes Inertia környezet
// - PrimeVue composable-ok (useToast/useConfirm) mockolva
// - PrimeVue UI komponensek (Button, DataTable, stb.) stubolva
// - HTTP: globalThis.fetch mockolva (GET list + GET detail)
// - csrfFetch mockolva (DELETE)
//
// Megjegyzés:
// - A UI-ból több dolog menü/ellipsis alól indul, de tesztben stabilabb közvetlenül
//   a komponens metódusait hívni (openEditModal, confirmDeleteOne), mert a PrimeVue Menu
//   és DataTable belső működése itt stubolva van.
// -----------------------------------------------------------------------------

import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

import Index from "@/Pages/Admin/Roles/Index.vue";

// -----------------------------------------------------------------------------
// Inertia Head stub
// - Az Index.vue használja a <Head> komponenst
// - A tesztnek mindegy, csak ne dobjon hibát a mount közben
// -----------------------------------------------------------------------------
vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

// -----------------------------------------------------------------------------
// PrimeVue toast mock
// - A komponens a useToast().add(...) hívásokkal küld üzeneteket
// - toastAdd = vi.fn() -> tudjuk assert-elni, hogy toast megjelent
// -----------------------------------------------------------------------------
const toastAdd = vi.fn();
vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

// -----------------------------------------------------------------------------
// PrimeVue confirm mock
// - A komponens confirm.require({ accept: () => ... }) mintát használ
// - A mock-ban eltesszük az accept callback-et confirmAccept változóba,
//   így a teszt "szimulálni tudja", hogy user rányom a Törlésre.
// -----------------------------------------------------------------------------
let confirmAccept = null;
vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: (opts) => {
            confirmAccept = opts.accept;
        },
    }),
}));

// -----------------------------------------------------------------------------
// csrfFetch mock (DELETE-hez)
// - A komponens törléshez csrfFetch-et használ
// - mock: visszaad sikeres választ, és ellenőrizhetővé teszi a hívásokat
// -----------------------------------------------------------------------------
const csrfFetchMock = vi.fn();
vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

// -----------------------------------------------------------------------------
// Teszt fixture adatok
// - Ez lesz a "backendből érkező" lista
// -----------------------------------------------------------------------------
const rolesList = [
    { id: 1, name: "Admin", guard_name: "web", users_count: 1 },
    { id: 2, name: "User", guard_name: "web", users_count: 0 },
];

// -----------------------------------------------------------------------------
// UI komponensek stubolása
//
// Miért kell?
// - PrimeVue komponensek (DataTable/Menu/Button stb.) nem célpontjai a tesztnek.
// - A teszt a "mi logikánkat" akarja ellenőrizni (fetch, modal, delete),
//   nem a PrimeVue belső renderét.
//
// Mire figyelünk?
// - Button átengedje a data-testid-t és click-et
// - DataTable meg tudja jeleníteni a value (rows) tömböt
// - Modalok v-model + saved event (minimálisan szimulálva)
// -----------------------------------------------------------------------------
const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },

    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },

    // Tag-ot a UI kiírhatja (pl. guard/active)
    // Nem minden tesztben használjuk, de ártani nem árt.
    Tag: {
        props: ["value"],
        template: `<span data-testid="tag">{{ value }}</span>`,
    },

    // Button stub: nagyon fontos az inheritAttrs és a click emit
    // így működik a data-testid és a trigger("click") is.
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

    // InputText stub: v-model + input esemény
    InputText: {
        inheritAttrs: true,
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value); $emit('input', $event)" />`,
    },

    // DataTable: a roles lista megjelenítéséhez elég a value-t kirajzolni
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

    // CreateModal: v-model nyitás/zárás + saved event
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

    // EditModal: v-model + role átadás + saved event
    EditModal: {
        name: "EditModal",
        props: ["modelValue", "role"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="edit-modal">
            <div data-testid="edit-role-id">{{ role?.id }}</div>
            <button data-testid="edit-save" @click="$emit('saved', 'Mentve.'); $emit('update:modelValue', false)">save</button>
          </div>
        `,
    },
};

// -----------------------------------------------------------------------------
// fetch helper: minimális Response objektum imitálása
// -----------------------------------------------------------------------------
function mockFetchOk(json) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => json,
    });
}

// -----------------------------------------------------------------------------
// Tesztek
// -----------------------------------------------------------------------------
describe("Roles CRUD (Index.vue) – onMounted fetch alapú", () => {
    beforeEach(() => {
        // Tesztek között mindent nullázunk:
        // - mockok
        // - confirmAccept (ne "szivárogjon")
        vi.clearAllMocks();
        confirmAccept = null;

        // globalThis.fetch mock:
        // - /admin/roles/fetch? -> listázás
        // - /admin/roles/1 -> detail (EditModal előtt)
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/admin/roles/fetch?")) {
                // a backend itt paginator-szerű payloadot ad
                return mockFetchOk({
                    data: { current_page: 1, data: rolesList },
                    meta: { total: rolesList.length },
                    filter: {
                        search: "",
                        field: "id",
                        order: "desc",
                        page: 1,
                        per_page: 10,
                    },
                });
            }

            if (u === "/admin/roles/1") {
                // openEditModal sokszor egy detail GET-et kér
                return mockFetchOk({
                    id: 1,
                    name: "Admin",
                    guard_name: "web",
                    permission_ids: [1, 2],
                });
            }

            // default: ne dőljön el, ha más fetch is történik
            return mockFetchOk({ data: [] });
        });

        // csrfFetch (DELETE) sikeres válasz
        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: "ok" }),
        });
    });

    // -------------------------------------------------------------------------
    // 1) Lista render
    // - mount -> onMounted -> fetchRoles -> rows feltölt
    // - DataTable stub kirendereli a rows-t
    // -------------------------------------------------------------------------
    it("rendereli a listát az onMounted fetch után", async () => {
        const wrapper = mount(Index, { global: { stubs } });

        // megvárjuk: onMounted, fetch, json, state update
        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="datatable"]').exists()).toBe(true);

        expect(wrapper.text()).toContain("Admin");
        expect(wrapper.text()).toContain("User");
    });

    // -------------------------------------------------------------------------
    // 2) Create flow
    // - roles-create gomb -> CreateModal nyílik
    // - modal save -> saved event -> onSaved -> fetchRoles + toast
    // -------------------------------------------------------------------------
    it("Létrehozási folyamat: gomb → modális → mentés → fetchRoles + toast", async () => {
        const wrapper = mount(Index, { global: { stubs } });

        await flushPromises(); // initial fetchRoles

        const createBtn = wrapper.find('[data-testid="roles-create"]');
        expect(createBtn.exists()).toBe(true);

        await createBtn.trigger("click");
        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(
            true,
        );

        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushPromises();

        // saved -> onSaved -> fetchRoles + toast
        expect(toastAdd).toHaveBeenCalled();

        // /admin/roles/fetch legalább 2x: mount + saved után
        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/admin/roles/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    // -------------------------------------------------------------------------
    // 3) Edit flow
    // - openEditModal -> detail fetch (/admin/roles/1)
    // - EditModal nyílik -> saved -> fetchRoles + toast
    //
    // Megjegyzés:
    // - UI-ban ezt egy menüből indítod, itt direkt metódushívás a stabilitásért.
    // -------------------------------------------------------------------------
    it("Szerkesztési folyamat: openEditModal -> GET /admin/roles/1 -> editOpen true -> mentés -> fetchRoles + toast", async () => {
        const wrapper = mount(Index, { global: { stubs } });

        await flushPromises(); // initial fetchRoles

        await wrapper.vm.openEditModal({ id: 1, name: "Admin" });
        await flushPromises();

        // detail fetch megtörtént
        expect(globalThis.fetch).toHaveBeenCalledWith(
            "/admin/roles/1",
            expect.any(Object),
        );

        // modal nyitva, role átadva
        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="edit-role-id"]').text()).toBe("1");

        await wrapper.find('[data-testid="edit-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();

        // refresh történt
        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/admin/roles/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    // -------------------------------------------------------------------------
    // 4) Delete one flow
    // - confirmDeleteOne -> confirm.require -> accept callback eltárolva
    // - confirmAccept() -> deleteOne -> csrfFetch DELETE -> toast + refresh
    // -------------------------------------------------------------------------
    it("Törlési folyamat: confirmDeleteOne -> accept -> csrfFetch DELETE -> toast + fetchRoles", async () => {
        const wrapper = mount(Index, { global: { stubs } });

        await flushPromises(); // initial fetchRoles

        wrapper.vm.confirmDeleteOne({ id: 1, name: "Admin" });
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/admin/roles/1",
            expect.objectContaining({ method: "DELETE" }),
        );

        expect(toastAdd).toHaveBeenCalled();

        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/admin/roles/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    it("Bulk törlés: confirmBulkDelete -> accept -> csrfFetch DELETE /admin/roles/destroy_bulk -> selected ürül", async () => {
        const wrapper = mount(Index, { global: { stubs } });

        await flushPromises();

        wrapper.vm.selected = [rolesList[0], rolesList[1]];
        wrapper.vm.confirmBulkDelete();

        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/admin/roles/destroy_bulk",
            expect.objectContaining({
                method: "DELETE",
                body: JSON.stringify({ ids: [1, 2] }),
            }),
        );

        expect(wrapper.vm.selected).toEqual([]);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({ severity: "success" }),
        );
    });

    it("Edit hiba esetén nem nyit modált és error toastot mutat", async () => {
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);
            if (u.startsWith("/admin/roles/fetch?")) {
                return mockFetchOk({
                    data: { current_page: 1, data: rolesList },
                    meta: { total: rolesList.length },
                    filter: {},
                });
            }
            if (u === "/admin/roles/1") {
                return {
                    ok: false,
                    status: 500,
                    json: async () => ({}),
                };
            }
            return mockFetchOk({ data: [] });
        });

        const wrapper = mount(Index, { global: { stubs } });
        await flushPromises();

        await wrapper.vm.openEditModal({ id: 1, name: "Admin" });
        await flushPromises();

        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(false);
        expect(toastAdd).toHaveBeenCalledWith(
            expect.objectContaining({
                severity: "error",
                detail: "HTTP 500",
            }),
        );
    });
});
