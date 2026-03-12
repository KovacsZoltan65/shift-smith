<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

// PrimeVue

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('register')" />

        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">
                        {{ $t("register") }}
                    </div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        {{ $t("register.description") }}
                    </div>
                </template>

                <template #content>
                    <!-- Globális hiba (ha van ilyen) -->
                    <Message
                        v-if="
                            form.hasErrors &&
                            (form.errors?.message || form.errors?.error)
                        "
                        severity="error"
                        :closable="false"
                        class="mb-4"
                    >
                        {{ form.errors?.message || form.errors?.error }}
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Name -->
                        <div>
                            <span class="p-float-label w-full">
                                <label for="name">{{
                                    $t("columns.name")
                                }}</label>

                                <InputText
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    autocomplete="name"
                                    class="w-full"
                                    :invalid="!!form.errors.name"
                                    autofocus
                                    required
                                />
                            </span>

                            <small
                                v-if="form.errors.name"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </small>
                        </div>

                        <!-- Email -->
                        <div>
                            <span class="p-float-label w-full">
                                <label for="email">{{
                                    $t("columns.email")
                                }}</label>

                                <InputText
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="username"
                                    class="w-full"
                                    :invalid="!!form.errors.email"
                                    required
                                />
                            </span>

                            <small
                                v-if="form.errors.email"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.email }}
                            </small>
                        </div>

                        <!-- Password -->
                        <div>
                            <span class="p-float-label w-full">
                                <label for="password">{{
                                    $t("auth.password")
                                }}</label>

                                <Password
                                    id="password"
                                    v-model="form.password"
                                    toggleMask
                                    :feedback="false"
                                    inputClass="w-full"
                                    class="w-full"
                                    autocomplete="new-password"
                                    :invalid="!!form.errors.password"
                                    required
                                />
                            </span>

                            <small
                                v-if="form.errors.password"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.password }}
                            </small>
                        </div>

                        <!-- Password confirmation -->
                        <div>
                            <span class="p-float-label w-full">
                                <label for="password_confirmation">{{
                                    $t("auth.password_confirmation")
                                }}</label>

                                <Password
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    toggleMask
                                    :feedback="false"
                                    inputClass="w-full"
                                    class="w-full"
                                    autocomplete="new-password"
                                    :invalid="
                                        !!form.errors.password_confirmation
                                    "
                                    required
                                />
                            </span>

                            <small
                                v-if="form.errors.password_confirmation"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.password_confirmation }}
                            </small>
                        </div>

                        <Divider class="my-2" />

                        <!-- Actions -->
                        <div class="space-y-3">
                            <Button
                                type="submit"
                                :label="$t('register')"
                                icon="pi pi-user-plus"
                                class="w-full"
                                :loading="form.processing"
                                :disabled="form.processing"
                            />

                            <div class="text-center">
                                <Link
                                    :href="route('login')"
                                    class="text-sm text-gray-600 hover:text-gray-900 hover:underline"
                                >
                                    {{ $t("register.have_account") }}
                                </Link>
                            </div>
                        </div>
                    </form>
                </template>

                <template #footer>
                    <div class="text-center text-xs text-gray-500">
                        © {{ new Date().getFullYear() }} Shift-Smith
                    </div>
                </template>
            </Card>
        </div>
    </GuestLayout>
</template>
