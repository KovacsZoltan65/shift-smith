<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

// PrimeVue

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: "" },
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const page = usePage();
const csrf =
    page.props?.csrf_token ??
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ??
    "";

const submit = () => {
    form.post(route("login"), {
        data: { _token: csrf },
        headers: { "X-CSRF-TOKEN": csrf },
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Bejelentkezés" />

        <!-- Centered container -->
        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">Bejelentkezés</div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        Lépj be a fiókodba a folytatáshoz.
                    </div>
                </template>

                <template #content>
                    <!-- Status message (pl. jelszó reset után) -->
                    <Message
                        v-if="status"
                        severity="success"
                        :closable="false"
                        class="mb-4"
                    >
                        {{ status }}
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Email -->
                        <div>
                            <span class="p-float-label w-full">
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
                                <label for="email">Email</label>
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
                                <Password
                                    id="password"
                                    v-model="form.password"
                                    toggleMask
                                    :feedback="false"
                                    inputClass="w-full"
                                    class="w-full"
                                    autocomplete="current-password"
                                    :invalid="!!form.errors.password"
                                    required
                                />
                                <label for="password">Jelszó</label>
                            </span>

                            <small
                                v-if="form.errors.password"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.password }}
                            </small>
                        </div>

                        <!-- Remember -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    inputId="remember"
                                    v-model="form.remember"
                                    :binary="true"
                                />
                                <label
                                    for="remember"
                                    class="cursor-pointer text-sm text-gray-600"
                                >
                                    Emlékezz rám
                                </label>
                            </div>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm text-gray-600 hover:text-gray-900 hover:underline"
                            >
                                Elfelejtetted?
                            </Link>
                        </div>

                        <Divider class="my-2" />

                        <!-- Submit -->
                        <Button
                            type="submit"
                            label="Belépés"
                            icon="pi pi-sign-in"
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
