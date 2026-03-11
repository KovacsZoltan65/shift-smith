import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

import EmployeeImportDialog from "@/Components/Employees/EmployeeImportDialog.vue";

vi.mock("laravel-vue-i18n", () => ({
    trans: (key, params = {}) =>
        Object.entries(params).reduce(
            (message, [name, value]) => message.replace(`:${name}`, value),
            key,
        ),
}));

const { employeeServiceMock } = vi.hoisted(() => ({
    employeeServiceMock: {
        importEmployees: vi.fn(),
        extractErrors: vi.fn(() => null),
    },
}));

vi.mock("@/services/EmployeeService.js", () => ({
    default: employeeServiceMock,
}));

const stubs = {
    Dialog: {
        props: ["visible"],
        template: `<div v-if="visible"><slot /></div>`,
    },
    Button: {
        props: ["label", "loading", "disabled"],
        template: `<button :disabled="disabled" @click="$emit('click', $event)">{{ label }}<slot /></button>`,
    },
    Select: {
        props: ["modelValue", "options", "optionLabel", "optionValue"],
        emits: ["update:modelValue"],
        template: `<select
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option
                v-for="option in (options ?? [])"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label ?? option.labelKey ?? option.value }}
            </option>
        </select>`,
    },
    FileUpload: {
        props: ["chooseLabel"],
        emits: ["select", "clear"],
        template: `
            <div>
                <slot
                    name="header"
                    :chooseCallback="() => $emit('select', { files: [{ name: 'employees.csv', type: 'text/csv' }] })"
                    :clearCallback="() => $emit('clear')"
                    :files="[]"
                />
                <slot
                    name="content"
                    :files="[]"
                    :messages="[]"
                    :removeFileCallback="() => {}"
                />
                <slot name="empty" />
            </div>
        `,
    },
};

describe("EmployeeImportDialog", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("emits the structured import summary after a successful import", async () => {
        employeeServiceMock.importEmployees.mockResolvedValueOnce({
            data: {
                data: {
                    total_rows: 2,
                    imported_count: 1,
                    failed_count: 1,
                    skipped_count: 0,
                    rows: [
                        { row_number: 1, status: "imported", message: "Imported successfully.", errors: [] },
                        { row_number: 2, status: "failed", message: "Row validation failed.", errors: ["Email is required."] },
                    ],
                },
            },
        });

        const wrapper = mount(EmployeeImportDialog, {
            props: {
                modelValue: true,
            },
            global: {
                stubs,
                mocks: {
                    $t: (key, params = {}) =>
                        Object.entries(params).reduce(
                            (message, [name, value]) =>
                                message.replace(`:${name}`, value),
                            key,
                        ),
                },
            },
        });

        await wrapper.find('[data-testid="employee-import-file"]').trigger("click");

        await wrapper.find('[data-testid="employee-import-submit"]').trigger("click");
        await flushPromises();
        await flushPromises();

        expect(employeeServiceMock.importEmployees).toHaveBeenCalledWith(
            expect.objectContaining({ name: "employees.csv" }),
            "csv",
        );
        expect(wrapper.emitted("completed")?.length).toBeGreaterThan(0);
    });
});
