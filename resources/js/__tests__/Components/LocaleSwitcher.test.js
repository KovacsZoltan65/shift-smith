import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

import LocaleSwitcher from "@/Components/LocaleSwitcher.vue";

const postMock = vi.fn();
const loadLanguageAsyncMock = vi.fn(() => Promise.resolve("hu"));
const getActiveLanguageMock = vi.fn(() => "en");

const pageProps = {
    locale: "en",
    supported_locales: ["en", "hu"],
    available_locales: [
        { code: "en", name: "English" },
        { code: "hu", name: "Magyar" },
    ],
};

vi.mock("@inertiajs/vue3", () => ({
    router: {
        post: (...args) => postMock(...args),
    },
    usePage: () => ({
        props: pageProps,
    }),
}));

vi.mock("laravel-vue-i18n", () => ({
    trans: (key) =>
        ({
            "common.language": "Nyelv",
            "locales.en": "Angol",
            "locales.hu": "Magyar",
        })[key] ?? key,
    loadLanguageAsync: (...args) => loadLanguageAsyncMock(...args),
    getActiveLanguage: () => getActiveLanguageMock(),
}));

describe("LocaleSwitcher", () => {
    beforeEach(() => {
        postMock.mockReset();
        loadLanguageAsyncMock.mockClear();
        globalThis.route = vi.fn(() => "/locale");
    });

    it("kirendereli a támogatott locale opciókat", () => {
        const wrapper = mount(LocaleSwitcher, {
            global: {
                stubs: {
                    Select: {
                        props: ["modelValue", "options", "optionLabel", "optionValue"],
                        template: `
                            <div>
                                <div data-testid="selected">{{ modelValue }}</div>
                                <div
                                    v-for="option in options"
                                    :key="option.value"
                                    class="option"
                                >
                                    {{ option.label }}
                                </div>
                            </div>
                        `,
                    },
                },
                mocks: {
                    $t: (key) => ({ "common.language": "Nyelv" })[key] ?? key,
                },
            },
        });

        expect(wrapper.text()).toContain("Nyelv");
        expect(wrapper.text()).toContain("Angol");
        expect(wrapper.text()).toContain("Magyar");
        expect(wrapper.get('[data-testid="selected"]').text()).toBe("en");
    });

    it("locale váltáskor elküldi a backend kérését és betölti az új nyelvet", async () => {
        postMock.mockImplementation((url, payload, options) => {
            options?.onSuccess?.({ props: { locale: payload.locale } });
            options?.onFinish?.();
        });

        const wrapper = mount(LocaleSwitcher, {
            global: {
                stubs: {
                    Select: {
                        props: ["modelValue", "options"],
                        emits: ["update:modelValue"],
                        template: `
                            <button
                                data-testid="switch"
                                @click="$emit('update:modelValue', 'hu')"
                            >
                                switch
                            </button>
                        `,
                    },
                },
                mocks: {
                    $t: (key) => ({ "common.language": "Nyelv" })[key] ?? key,
                },
            },
        });

        await wrapper.get('[data-testid="switch"]').trigger("click");

        expect(postMock).toHaveBeenCalledWith(
            expect.any(String),
            { locale: "hu" },
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
        expect(loadLanguageAsyncMock).toHaveBeenCalledWith("hu");
    });
});
