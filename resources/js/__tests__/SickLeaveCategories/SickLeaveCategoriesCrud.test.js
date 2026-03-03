import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";

import Index from "@/Pages/Admin/SickLeaveCategories/Index.vue";
import SickLeaveCategoryService from "@/services/SickLeaveCategoryService.js";
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

vi.mock("@/services/SickLeaveCategoryService.js", () => ({
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
        code: "sajat_betegseg",
        name: "Sajat betegseg",
        description: "Altalanos betegseg",
        active: true,
        order_index: 1,
    },
    {
        id: 2,
        code: "gyermek_apolasa",
        name: "Gyermek apolasa",
        description: "Gyermekhez kapcsolodo",
        active: false,
        order_index: 2,
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

describe("SickLeaveCategories CRUD", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        SickLeaveCategoryService.fetch.mockResolvedValue({
            data: {
                items,
                meta: { total: 2, current_page: 1, per_page: 10, last_page: 1 },
            },
        });
        SickLeaveCategoryService.show.mockResolvedValue({ data: { data: items[0] } });
        SickLeaveCategoryService.store.mockResolvedValue({ data: { data: items[0] } });
        SickLeaveCategoryService.update.mockResolvedValue({ data: { data: items[0] } });
        SickLeaveCategoryService.destroy.mockResolvedValue({ data: { deleted: true } });
    });

    it("onMounted fetch utan rendereli a listat", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();

        expect(SickLeaveCategoryService.fetch).toHaveBeenCalled();
        expect(wrapper.text()).toContain("Sajat betegseg");
    });

    it("global search a DataTable filters state-en szuri a listat", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();

        const search = wrapper.find('[data-testid="sick-leave-categories-search"]');
        await search.setValue("gyermek");
        await flushPromises();

        expect(wrapper.text()).toContain("Gyermek apolasa");
        expect(wrapper.text()).not.toContain("sajat_betegseg");
    });

    it("clear filters reseteli a built-in filters state-et", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });

        await flushPromises();
        wrapper.vm.filters.global.value = "teszt";
        await flushPromises();

        await wrapper.find('[data-testid="sick-leave-categories-clear-filters"]').trigger("click");

        expect(wrapper.vm.filters.global.value).toBe(null);
    });

    it("create flow utan ujrafetch es toast tortenik", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        await wrapper.find('[data-testid="sick-leave-categories-create"]').trigger("click");
        await flushPromises();
        await wrapper.find('[data-testid="sick-leave-category-create-save"]').trigger("click");
        await flushPromises();

        expect(SickLeaveCategoryService.store).toHaveBeenCalled();
        expect(SickLeaveCategoryService.fetch).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: "success" }));
    });

    it("permission nelkul elrejti a create gombot", async () => {
        const { __allow } = usePermissions();
        __allow.delete("sick_leave_categories.create");

        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="sick-leave-categories-create"]').exists()).toBe(false);

        __allow.add("sick_leave_categories.create");
    });
});
