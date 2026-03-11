<script setup>
import { computed, ref } from "vue";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";

import { Link, router, usePage } from "@inertiajs/vue3";

import { useAppMenu } from "@/composables/useAppMenu";

import TopbarLocaleSwitch from "@/Components/TopbarLocaleSwitch.vue";

const showingNavigationDropdown = ref(false);

// Sidebar nyit/zár (desktopon is)
const sidebarOpen = ref(true);

const { filteredMenu } = useAppMenu();
const page = usePage();

const quickLinks = computed(() => {
    const groups = filteredMenu.value ?? [];
    const flat = groups.flatMap((g) => g?.items ?? []);
    return flat.filter((i) => i && i.route).slice(0, 5);
});

const initials = computed(() => {
    const name = String(page.props?.auth?.user?.name ?? "User").trim();
    const parts = name.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] ?? "U";
    const b = parts[1]?.[0] ?? "";
    return (a + b).toUpperCase();
});

const currentCompanyName = computed(
    () => page.props?.companyContext?.current_company?.name ?? null,
);

const canSwitchCompany = computed(
    () => Number(page.props?.companyContext?.selectable_company_count ?? 0) > 1,
);

const logout = () => {
    const csrf =
        page.props?.csrf_token ??
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ??
        "";

    router.post(
        route("logout"),
        { _token: csrf },
        {
            headers: {
                "X-CSRF-TOKEN": csrf,
            },
        },
    );
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>
                            <!-- Sidebar toggle (desktop) -->
                            <button
                                type="button"
                                class="me-2 hidden items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 sm:inline-flex"
                                @click="sidebarOpen = !sidebarOpen"
                                title="Oldalsáv"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                </svg>
                            </button>

                            <!-- Quick access (desktop) -->
                            <TransitionGroup
                                name="quick"
                                tag="div"
                                class="flex items-center gap-2"
                            >
                                <div
                                    v-if="currentCompanyName"
                                    class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                                >
                                    Cég: {{ currentCompanyName }}
                                </div>

                                <div
                                    v-for="qi in quickLinks"
                                    :key="qi?.key ?? qi?.route ?? qi?.title"
                                    class="inline-block"
                                >
                                    <template
                                        v-if="
                                            qi &&
                                            qi.route &&
                                            route().has(qi.route)
                                        "
                                    >
                                        <Button
                                            severity="secondary"
                                            size="small"
                                            @click="
                                                router.visit(route(qi.route))
                                            "
                                        >
                                            {{ qi.title }}
                                        </Button>
                                    </template>
                                </div>
                            </TransitionGroup>

                        </div>

                        <div
                            class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3"
                        >
                            <TopbarLocaleSwitch />

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-2">
                                            <div
                                                class="text-sm font-medium text-gray-900"
                                            >
                                                <span
                                                    class="me-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700"
                                                >
                                                    {{ initials }}
                                                </span>
                                                {{ $page.props.auth.user.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{
                                                    $page.props.auth.user.email
                                                }}
                                            </div>

                                            <div
                                                v-if="
                                                    $page.props.auth.roles
                                                        ?.length
                                                "
                                                class="mt-2 flex flex-wrap gap-1"
                                            >
                                                <span
                                                    v-for="r in $page.props.auth
                                                        .roles"
                                                    :key="r"
                                                    class="rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700"
                                                >
                                                    {{ r }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="border-t border-gray-100"
                                        ></div>

                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink
                                            v-if="canSwitchCompany"
                                            :href="route('company.select')"
                                        >
                                            Cég váltás
                                        </DropdownLink>

                                        <button
                                            type="button"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                            @click="logout"
                                        >
                                            Log Out
                                        </button>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canSwitchCompany"
                                :href="route('company.select')"
                            >
                                Cég váltás
                            </ResponsiveNavLink>
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                @click="logout"
                            >
                                Log Out
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Content with Sidebar -->
            <div class="flex w-full min-h-[calc(100vh-4rem)]">
                <!-- Sidebar (desktop) -->
                <Transition name="sidebar">
                    <aside
                        v-show="sidebarOpen"
                        class="w-72 shrink-0 border-r border-gray-200 bg-white sm:block sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto"
                    >
                        <nav class="p-3">
                            <div
                                v-for="group in filteredMenu"
                                :key="group.title"
                                class="mb-4"
                            >
                                <div
                                    class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
                                >
                                    {{ group.title }}
                                </div>

                                <div class="space-y-1">
                                    <template
                                        v-for="item in group.items ?? []"
                                        :key="item.key ?? item.route"
                                    >
                                        <template
                                            v-if="
                                                item &&
                                                item.route &&
                                                route().has(item.route)
                                            "
                                        >
                                            <Link
                                                :href="route(item.route)"
                                                class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                :class="{
                                                    'bg-gray-100 font-semibold text-gray-900':
                                                        route().current(
                                                            item.route,
                                                        ),
                                                }"
                                            >
                                                {{ item.title }}
                                            </Link>
                                        </template>
                                        <template v-else>
                                            <div
                                                class="block rounded-md px-3 py-2 text-sm text-gray-400"
                                            >
                                                {{
                                                    item?.title ??
                                                    "(ismeretlen)"
                                                }}
                                            </div>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </nav>
                    </aside>
                </Transition>
                <!-- Page Content -->
                <main class="flex-1 overflow-x-auto p-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
