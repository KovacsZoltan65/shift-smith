import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";

import Index from "@/Pages/Admin/LeaveCategories/Index.vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";
import { usePermissions } from "@/composables/usePermissions";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

vi.mock("@primevue/core/api", () => ({
    FilterMatchMode: {
        CONTAINS: "contains",
        EQUALS: "equals",
    },
    FilterOperator: {
        AND: "and",
    },
}));

const toastAdd = vi.fn();
vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

vi.mock("@/services/LeaveCategoryService.js", () => ({
    default: {
        fetch: vi.fn(),
        show: vi.fn(),
        store: vi.fn(),
        update: vi.fn(),
        destroy: vi.fn(),
        selector: vi.fn(),
        extractErrors: (error) => error?.normalizedErrors ?? error?.response?.data?.errors ?? null,
    },
}));

const items = [
    {
        id: 1,
        code: "leave",
        name: "Szabadsag",
        description: "Altalanos szabadsag kategoria",
        active: true,
        order_index: 10,
    },
    {
        id: 2,
        code: "sick_leave",
        name: "Betegszabadsag",
        description: "Betegseghez kapcsolodo tavollet",
        active: false,
        order_index: 20,
    },
];

const stubs = {
    AuthenticatedLayout: { template: "<div><slot /></div>" },
    Toast: { template: "<div />" },
    Button: {
        inheritAttrs: true,
        props: ["label", "disabled", "loading", "icon", "severity", "size", "text", "rounded"],
        emits: ["click"],
        template: `<button v-bind="$attrs" :disabled="disabled" @click="$emit('click', $event)">{{ label }}<slot /></button>`,
    },
    InputText: {
        inheritAttrs: true,
        props: ["modelValue"],
        emits: ["update:modelValue", "input"],
        template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value); $emit('input', $event)" />`,
    },
    InputNumber: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<input type="number" :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))" />`,
    },
    Textarea: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
    },
    Checkbox: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<input type="checkbox" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
    },
    DataTable: {
        props: ["value", "filters", "globalFilterFields"],
        computed: {
            filteredRows() {
                const rows = this.value ?? [];
                const globalValue = String(this.filters?.global?.value ?? "").trim().toLowerCase();

                if (!globalValue) {
                    return rows;
                }

                const fields = this.globalFilterFields ?? [];

                return rows.filter((row) =>
                    fields.some((field) => String(row?.[field] ?? "").toLowerCase().includes(globalValue)),
                );
            },
        },
        template: `
            <div data-testid="datatable">
                <slot name="header" />
                <div v-for="row in filteredRows" :key="row.id">{{ row.code }} {{ row.name }}</div>
                <slot />
            </div>
        `,
    },
    Column: { template: "<div><slot /></div>" },
    Menu: { template: "<div />" },
    Tag: { props: ["value"], template: "<span>{{ value }}</span>" },
    Select: {
        inheritAttrs: true,
        props: ["modelValue", "options"],
        emits: ["update:modelValue", "change"],
        template: `<select v-bind="$attrs" @change="$emit('update:modelValue', true); $emit('change', $event)"></select>`,
    },
    Dialog: { props: ["visible"], template: "<div><slot /><slot name='footer' /></div>" },
};

describe("LeaveCategories CRUD", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        LeaveCategoryService.fetch.mockResolvedValue({
            data: {
                items,
                meta: { total: 2, current_page: 1, per_page: 10, last_page: 1 },
            },
        });
        LeaveCategoryService.show.mockResolvedValue({ data: { data: items[0] } });
        LeaveCategoryService.store.mockResolvedValue({ data: { data: items[0] } });
        LeaveCategoryService.update.mockResolvedValue({ data: { data: items[0] } });
        LeaveCategoryService.destroy.mockResolvedValue({ data: { deleted: true } });
    });

    it("onMounted fetch utan rendereli a listat", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();

        expect(LeaveCategoryService.fetch).toHaveBeenCalled();
        expect(wrapper.text()).toContain("Szabadsag");
    });

    it("global search a DataTable filters state-en szuri a listat", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();

        const search = wrapper.find('[data-testid="leave-categories-search"]');
        await search.setValue("beteg");
        await flushPromises();

        expect(wrapper.text()).toContain("Betegszabadsag");
        expect(wrapper.text()).not.toContain("Altalanos szabadsag");
    });

    it("clear filters reseteli a built-in filters state-et", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();
        wrapper.vm.filters.global.value = "teszt";
        await flushPromises();

        await wrapper.find('[data-testid="leave-categories-clear-filters"]').trigger("click");

        expect(wrapper.vm.filters.global.value).toBe(null);
    });

    it("create flow utan ujrafetch es toast tortenik", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        await wrapper.find('[data-testid="leave-categories-create"]').trigger("click");
        await flushPromises();
        await wrapper.find('[data-testid="leave-category-create-save"]').trigger("click");
        await flushPromises();

        expect(LeaveCategoryService.store).toHaveBeenCalled();
        expect(LeaveCategoryService.fetch).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: "success" }));
    });

    it("permission nelkul elrejti a create gombot", async () => {
        const { __allow } = usePermissions();
        __allow.delete("leave_categories.create");

        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="leave-categories-create"]').exists()).toBe(false);

        __allow.add("leave_categories.create");
    });
});
