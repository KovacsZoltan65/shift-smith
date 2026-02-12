// resources/js/__tests__/Companies/CompaniesCrud.test.js
// -----------------------------------------------------------------------------
// Cél:
// - Companies/Index.vue alap CRUD viselkedésének tesztelése Vitest + Vue Test Utils-szal
// - onMounted fetch -> lista render
// - Create / Edit modal flow
// - Delete one confirm + csrfFetch DELETE
// - Bulk delete confirm + csrfFetch DELETE
//
// Módszer:
// - PrimeVue komponensek stubolva (DataTable, Button, Modalok)
// - useToast + useConfirm mockolva
// - global fetch mockolva (lista lekérés)
// - csrfFetch mockolva (DELETE / bulk delete)
//
// Megjegyzés:
// - A bulk törlésnél nem DOM click-et tesztelünk, hanem közvetlenül a metódust hívjuk,
//   mert a gomb disabled állapota / PrimeVue komponensek belső működése törékennyé tenné
//   a DOM alapú tesztet.
// -----------------------------------------------------------------------------

import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

import Index from "@/Pages/Companies/Index.vue";

// -----------------------------------------------------------------------------
// Inertia stub (Head + usePage)
// - Head-et nem akarjuk renderelni, csak ne dobjon hibát
// - usePage-t visszaadjuk üres objektummal
// -----------------------------------------------------------------------------
vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
    usePage: () => ({}),
}));

// -----------------------------------------------------------------------------
// PrimeVue Toast mock
// - Index.vue a useToast().add(...) hívást használja
// - itt eltároljuk a hívásokat, hogy ellenőrizni tudjuk a toast megjelenést
// -----------------------------------------------------------------------------
const toastAdd = vi.fn();

vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

// -----------------------------------------------------------------------------
// PrimeVue Confirm mock
// - Index.vue a confirm.require({ accept: () => ... }) mintát használja
// - Mi a require-ben elmentjük az accept callbacket, hogy a tesztből meghívhassuk
//
// NOTE:
// - néha előfordul, hogy accept nem függvény, hanem objektum (PrimeVue "command" jelleg).
//   ezért van resolveConfirmAccept().
// -----------------------------------------------------------------------------
let confirmAccept = null;

function resolveConfirmAccept(accept) {
    // 1) klasszikus eset: accept függvény
    if (typeof accept === "function") return accept;

    // 2) ha objektum és valamelyik kulcs alatt van a függvény
    if (accept && typeof accept === "object") {
        for (const key of ["callback", "command", "action", "handler"]) {
            if (typeof accept[key] === "function") return accept[key];
        }
    }

    // 3) fallback: nem találtunk használható callback-et
    return null;
}

vi.mock("primevue/useconfirm", () => ({
    useConfirm: () => ({
        require: (opts) => {
            // eltesszük az accept-et, hogy később confirmAccept()-ként meghívható legyen
            confirmAccept = resolveConfirmAccept(opts?.accept);
        },
    }),
}));

// -----------------------------------------------------------------------------
// csrfFetch mock (DELETE-hez)
// - Index.vue törléshez csrfFetch-et használ
// - A mock itt visszaad egy "ok" választ
// -----------------------------------------------------------------------------
const csrfFetchMock = vi.fn();

vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

// -----------------------------------------------------------------------------
// Teszt adatok (fixtures)
// -----------------------------------------------------------------------------
const companiesList = [
    {
        id: 1,
        name: "Test Cég Kft.",
        email: "info@testceg.hu",
        phone: "+36 1 234 5678",
        active: true,
    },
    {
        id: 2,
        name: "Másik Cég Zrt.",
        email: "hello@masik.hu",
        phone: "+36 30 111 2222",
        active: false,
    },
];

// -----------------------------------------------------------------------------
// Stubs (PrimeVue + layout + modals)
// - Csak annyi UI működést adunk vissza, ami a tesztekhez szükséges
// -----------------------------------------------------------------------------
const stubs = {
    // Layout csak "wrapper"
    AuthenticatedLayout: { template: "<div><slot /></div>" },

    Toast: { template: "<div />" },
    ConfirmDialog: { template: "<div />" },

    // Button stub: legyen click, legyen disabled támogatás
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
                      {{ label }}<slot />
                   </button>`,
    },

    // InputText stub: v-model frissítés + input event
    InputText: {
        inheritAttrs: true,
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input v-bind="$attrs"
                          :value="modelValue"
                          @input="$emit('update:modelValue', $event.target.value);
                                  $emit('input', $event)" />`,
    },

    // DataTable stub: csak kirendereli a value listát
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

    // CreateModal: v-model + saved event szimuláció
    CreateModal: {
        name: "CreateModal",
        props: ["modelValue"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="create-modal">
            <button data-testid="create-save"
                    @click="$emit('saved', 'Mentve.');
                            $emit('update:modelValue', false)">
              save
            </button>
          </div>
        `,
    },

    // EditModal: v-model + saved event, plusz kiírjuk az id-t
    EditModal: {
        name: "EditModal",
        props: ["modelValue", "company"],
        emits: ["update:modelValue", "saved"],
        template: `
          <div v-if="modelValue" data-testid="edit-modal">
            <div data-testid="edit-company-id">{{ company?.id }}</div>
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
// fetch helper (sikeres JSON válasz imitálása)
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
describe("Companies CRUD (Index.vue) – onMounted fetch alapú", () => {
    beforeEach(() => {
        // tisztítunk mindent, hogy tesztek ne "szivárogjanak"
        vi.clearAllMocks();
        confirmAccept = null;

        // global fetch mock: /companies/fetch? esetén lista
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);

            if (u.startsWith("/companies/fetch?")) {
                // Companies mintában: { data: [...], meta: { total } }
                return mockFetchOk({
                    data: companiesList,
                    meta: { total: companiesList.length },
                });
            }

            // default: üres
            return mockFetchOk({ data: [] });
        });

        // csrfFetch alapértelmezett "ok"
        csrfFetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: "ok" }),
        });
    });

    // -------------------------------------------------------------------------
    // List render: onMounted fetch
    // -------------------------------------------------------------------------
    it("rendereli a listát az onMounted fetch után", async () => {
        const wrapper = mount(Index, {
            props: { title: "Cégek", filter: {} },
            global: { stubs },
        });

        // megvárjuk az onMounted + fetch + state update-t
        await flushPromises();

        expect(globalThis.fetch).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="datatable"]').exists()).toBe(true);

        // fixture adatok megjelennek
        expect(wrapper.text()).toContain("Test Cég Kft.");
        expect(wrapper.text()).toContain("Másik Cég Zrt.");
    });

    // -------------------------------------------------------------------------
    // Create flow: gomb -> modal -> saved -> új fetch + toast
    // -------------------------------------------------------------------------
    it("Létrehozási folyamat: gomb → modális → mentés → fetchCompanies + toast", async () => {
        const wrapper = mount(Index, {
            props: { title: "Cégek", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        // data-testid alapján stabilan megfogjuk a gombot
        const createBtn = wrapper.find('[data-testid="companies-create"]');
        expect(createBtn.exists()).toBe(true);

        await createBtn.trigger("click");
        expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(
            true,
        );

        // modal "save" -> saved event -> onSaved() -> fetch + toast
        await wrapper.find('[data-testid="create-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();

        // legyen legalább még egy fetch az initialon felül
        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/companies/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    // -------------------------------------------------------------------------
    // Edit flow: openEditModal -> saved -> toast + fetch
    // -------------------------------------------------------------------------
    it("Szerkesztési folyamat: openEditModal -> editOpen true -> mentés -> fetchCompanies + toast", async () => {
        const wrapper = mount(Index, {
            props: { title: "Cégek", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        // itt nem UI menüből kattintunk, hanem közvetlen metódust hívunk
        await wrapper.vm.openEditModal({ id: 1, name: "Test Cég Kft." });
        await flushPromises();

        expect(wrapper.find('[data-testid="edit-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="edit-company-id"]').text()).toBe(
            "1",
        );

        await wrapper.find('[data-testid="edit-save"]').trigger("click");
        await flushPromises();

        expect(toastAdd).toHaveBeenCalled();

        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/companies/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    // -------------------------------------------------------------------------
    // Delete one: confirm -> accept -> csrfFetch DELETE -> toast + fetch
    // -------------------------------------------------------------------------
    it("Törlési folyamat: confirmDeleteOne -> accept -> csrfFetch DELETE -> toast + fetchCompanies", async () => {
        const wrapper = mount(Index, {
            props: { title: "Cégek", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        // confirmDeleteOne meghívja confirm.require-t és beállítja accept-et
        wrapper.vm.confirmDeleteOne({ id: 1, name: "Test Cég Kft." });

        expect(confirmAccept).not.toBeNull();
        expect(typeof confirmAccept).toBe("function");

        // "felhasználó rányom a Törlésre"
        await confirmAccept();
        await flushPromises();

        // tényleges törlő hívás
        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/companies/1",
            expect.objectContaining({ method: "DELETE" }),
        );

        expect(toastAdd).toHaveBeenCalled();

        // refresh történt
        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/companies/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });

    // -------------------------------------------------------------------------
    // Bulk delete: selected beállít -> confirm -> accept -> csrfFetch DELETE -> toast + fetch
    //
    // Megjegyzés:
    // - Vue + script setup esetén wrapper.vm.selected néha ref, néha unwrapped value.
    // - emiatt "ref kompatibilis" módon állítjuk és olvassuk.
    // -------------------------------------------------------------------------
    it("Bulk törlés: confirmBulkDelete -> accept -> csrfFetch DELETE /companies/destroy_bulk -> selected ürül + fetchCompanies + toast", async () => {
        const wrapper = mount(Index, {
            props: { title: "Cégek", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        // selection beállítása ref/unwrapped kompatibilisen
        if (
            wrapper.vm.selected &&
            typeof wrapper.vm.selected === "object" &&
            "value" in wrapper.vm.selected
        ) {
            wrapper.vm.selected.value = [companiesList[0], companiesList[1]];
        } else {
            wrapper.vm.selected = [companiesList[0], companiesList[1]];
        }

        await flushPromises();

        // stabil: nem DOM click, hanem metódus hívás
        wrapper.vm.confirmBulkDelete();

        expect(confirmAccept).not.toBeNull();
        expect(typeof confirmAccept).toBe("function");

        await confirmAccept();
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/companies/destroy_bulk",
            expect.objectContaining({
                method: "DELETE",
                body: JSON.stringify({ ids: [1, 2] }),
            }),
        );

        expect(toastAdd).toHaveBeenCalled();

        // selected ürül ref/unwrapped kompatibilisen
        if (
            wrapper.vm.selected &&
            typeof wrapper.vm.selected === "object" &&
            "value" in wrapper.vm.selected
        ) {
            expect(wrapper.vm.selected.value).toEqual([]);
        } else {
            expect(wrapper.vm.selected).toEqual([]);
        }

        // refresh történt
        const calls = globalThis.fetch.mock.calls.map((c) => String(c[0]));
        expect(
            calls.filter((u) => u.startsWith("/companies/fetch?")).length,
        ).toBeGreaterThanOrEqual(2);
    });
});
