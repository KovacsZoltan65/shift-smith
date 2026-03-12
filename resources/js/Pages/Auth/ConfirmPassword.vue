<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";

// PrimeVue

const form = useForm({
    password: "",
});

const submit = () => {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.password_confirmation')" />

        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">
                        {{ $t("auth.password_confirmation") }}
                    </div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        {{ $t("auth.confirm_password.description") }}
                    </div>
                </template>

                <template #content>
                    <!-- Ha szeretnéd, ez lehet sima szöveg is, de a Message szépen Prime-os -->
                    <Message severity="info" :closable="false" class="mb-4">
                        {{ $t("auth.confirm_password.protected_area") }}
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
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
                                    autocomplete="current-password"
                                    :invalid="!!form.errors.password"
                                    autofocus
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

                        <Button
                            type="submit"
                            :label="$t('auth.confirm_password.submit')"
                            icon="pi pi-check"
                            class="w-full"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
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
