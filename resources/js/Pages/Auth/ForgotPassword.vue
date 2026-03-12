<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";

// PrimeVue

defineProps({
    status: { type: String, default: "" },
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post(route("password.email"));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.forgot_password.title')" />

        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">
                        {{ $t("auth.forgot_password.title") }}
                    </div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        {{ $t("auth.forgot_password.description") }}
                    </div>
                </template>

                <template #content>
                    <!-- Status (sikeres email küldés) -->
                    <Message
                        v-if="status"
                        severity="success"
                        :closable="false"
                        class="mb-4"
                    >
                        {{ status }}
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
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
                                    autofocus
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

                        <Button
                            type="submit"
                            :label="$t('auth.forgot_password.submit')"
                            icon="pi pi-envelope"
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
