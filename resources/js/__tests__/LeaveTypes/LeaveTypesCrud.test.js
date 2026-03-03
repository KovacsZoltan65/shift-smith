import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";

import Index from "@/Pages/Admin/LeaveTypes/Index.vue";
import LeaveTypeService from "@/services/LeaveTypeService.js";
import { usePermissions } from "@/composables/usePermissions";

vi.mock("@inertiajs/vue3", () => ({
    Head: { template: "<div />" },
}));

const toastAdd = vi.fn();
vi.mock("primevue/usetoast", () => ({
    useToast: () => ({ add: toastAdd }),
}));

vi.mock("@/services/LeaveTypeService.js", () => ({
    default: {
        fetch: vi.fn(),
        show: vi.fn(),
        store: vi.fn(),
        update: vi.fn(),
        destroy: vi.fn(),
        extractErrors: (error) => error?.normalizedErrors ?? error?.response?.data?.errors ?? null,
    },
}));

const items = [
    {
        id: 1,
        code: "annual",
        name: "Szabadsag",
        category: "leave",
        affects_leave_balance: true,
        requires_approval: true,
        active: true,
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
    DataTable: {
        props: ["value"],
        template: `<div data-testid="datatable"><div v-for="row in (value ?? [])" :key="row.id">{{ row.name }}</div><slot /></div>`,
    },
    Column: { template: "<div><slot /></div>" },
    Tag: { props: ["value"], template: "<span>{{ value }}</span>" },
    MultiSelect: {
        inheritAttrs: true,
        props: ["modelValue", "options"],
        emits: ["update:modelValue", "change"],
        template: `<select multiple v-bind="$attrs" @change="$emit('update:modelValue', ['leave']); $emit('change', $event)"></select>`,
    },
    Select: {
        inheritAttrs: true,
        props: ["modelValue", "options"],
        emits: ["update:modelValue", "change"],
        template: `<select v-bind="$attrs" @change="$emit('update:modelValue', true); $emit('change', $event)"></select>`,
    },
    Dialog: { props: ["visible"], template: "<div><slot /><slot name='footer' /></div>" },
    Checkbox: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: `<input type="checkbox" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
    },
};

const fetchResponse = {
    data: {
        items,
        meta: { total: 1, current_page: 1, per_page: 10, last_page: 1 },
    },
};

describe("LeaveTypes CRUD", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        LeaveTypeService.fetch.mockResolvedValue(fetchResponse);
        LeaveTypeService.show.mockResolvedValue({ data: { data: items[0] } });
        LeaveTypeService.store.mockResolvedValue({ data: { data: items[0] } });
        LeaveTypeService.update.mockResolvedValue({ data: { data: items[0] } });
        LeaveTypeService.destroy.mockResolvedValue({ data: { deleted: true } });
    });

    it("onMounted fetch utan rendereli a listat", async () => {
        const wrapper = mount(Index, {
            props: { title: "Szabadsag tipusok", filter: {} },
            global: { stubs },
        });

        await flushPromises();

        expect(LeaveTypeService.fetch).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="datatable"]').exists()).toBe(true);
        expect(wrapper.text()).toContain("Szabadsag");
    });

    it("create flow: modal save utan fetch es toast", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        await wrapper.find('[data-testid="leave-types-create"]').trigger("click");
        await flushPromises();
        await wrapper.find('[data-testid="leave-type-create-save"]').trigger("click");
        await flushPromises();

        expect(LeaveTypeService.store).toHaveBeenCalled();
        expect(LeaveTypeService.fetch).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: "success" }));
    });

    it("edit flow: show betoltes, update, fetch es toast", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        await wrapper.vm.openEditModal(items[0]);
        await flushPromises();
        await wrapper.find('[data-testid="leave-type-edit-save"]').trigger("click");
        await flushPromises();

        expect(LeaveTypeService.show).toHaveBeenCalledWith(1);
        expect(LeaveTypeService.update).toHaveBeenCalledWith(1, expect.any(Object));
        expect(LeaveTypeService.fetch).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: "success" }));
    });

    it("delete flow: confirm modal utan destroy, fetch es toast", async () => {
        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        await wrapper.vm.openDeleteModal(items[0]);
        await flushPromises();
        await wrapper.find('[data-testid="leave-type-delete-confirm"]').trigger("click");
        await flushPromises();

        expect(LeaveTypeService.destroy).toHaveBeenCalledWith(1);
        expect(LeaveTypeService.fetch).toHaveBeenCalledTimes(2);
        expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: "success" }));
    });

    it("permission nelkul elrejti a create gombot", async () => {
        const { __allow } = usePermissions();
        __allow.delete("leave_types.create");

        const wrapper = mount(Index, {
            props: { filter: {} },
            global: { stubs },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="leave-types-create"]').exists()).toBe(false);

        __allow.add("leave_types.create");
    });
});
