<script setup>
import { computed, ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { useAppMenu } from "@/composables/useAppMenu";

const page = usePage();
const { filteredMenu } = useAppMenu();

const sidebarOpen = ref(true);

const currentRouteName = computed(() => (page.props.ziggy?.location ? null : null));
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Topbar -->
    <header class="sticky top-0 z-40 border-b bg-white">
      <div
        class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
      >
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-md p-2 hover:bg-gray-100"
            @click="sidebarOpen = !sidebarOpen"
            title="Menü"
          >
            <!-- hamburger -->
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
            </svg>
          </button>

          <Link :href="route('dashboard')" class="font-semibold text-gray-900">
            ShiftSmith
          </Link>
        </div>

        <div class="flex items-center gap-3">
          <!-- ide jöhet gyorslink / kereső / értesítés -->
          <span class="text-sm text-gray-600">{{ page.props.auth?.user?.name }}</span>
        </div>
      </div>
    </header>

    <div class="mx-auto flex max-w-7xl">
      <!-- Sidebar -->
      <aside v-show="sidebarOpen" class="w-72 shrink-0 border-r bg-white">
        <nav class="p-3">
          <!-- NAV -->
          <div v-for="group in filteredMenu" :key="group.title" class="mb-4">
            <div
              class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
            >
              {{ group.title }}
            </div>

            <div class="space-y-1">
              <template v-for="item in group.items ?? []" :key="item.key ?? item.route">
                <template v-if="item && item.route && route().has(item.route)">
                  <Link
                    :href="route(item.route)"
                    class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    :class="{
                      'bg-gray-100 font-semibold text-gray-900': route().current(
                        item.route
                      ),
                    }"
                  >
                    {{ item.title }}
                  </Link>
                </template>

                <template v-else>
                  <div
                    class="block rounded-md px-3 py-2 text-sm text-gray-400"
                    title="Hiányzó route vagy item"
                  >
                    {{ item?.title ?? "(ismeretlen)" }}
                  </div>
                </template>
              </template>
            </div>
          </div>
          <!-- ./NAV -->
          <!--<div v-for="group in filteredMenu" :key="group.title" class="mb-4">
            <div
              class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
            >
              {{ group.title }}
            </div>

            <div class="space-y-1">
              <Link
                v-for="item in group.items"
                :key="item.key"
                v-if="route().has(item.route)"
                :href="route(item.route)"
                class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                :class="{
                  'bg-gray-100 font-semibold text-gray-900': route().current(item.route),
                }"
              >
                {{ item.title }}
              </Link>

              <div
                v-else
                class="block rounded-md px-3 py-2 text-sm text-gray-400"
                title="Hiányzó route"
              >
                {{ item.title }}
              </div>
            </div>
          </div>-->
        </nav>
      </aside>

      <!-- Content -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <slot name="header" />
        <slot />
      </main>
    </div>
  </div>
</template>
