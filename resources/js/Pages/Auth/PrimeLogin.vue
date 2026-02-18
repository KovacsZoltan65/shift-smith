<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import Card from "primevue/card";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Checkbox from "primevue/checkbox";
import Button from "primevue/button";
import Toast from "primevue/toast";
import { ref } from "vue";

const props = defineProps({
  canResetPassword: { type: Boolean, default: false },
  status: { type: String, default: "" },
});

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

const toast = ref(null);

const submit = () => {
  form.post(route("login"), {
    onSuccess: () => {
      // optionally show a toast on success (Inertia will redirect)
    },
    onError: (errors) => {
      toast.value?.add({ severity: "error", summary: "Hiba", detail: Object.values(errors).flat().join(' '), life: 4000 });
    },
    onFinish: () => form.reset("password"),
  });
};
</script>

<template>
  <Head title="Bejelentkezés - PrimeUI" />

  <div class="min-h-screen bg-gradient-to-b from-white to-gray-50 dark:from-zinc-900 dark:to-zinc-800 flex items-center justify-center p-6">
    <Toast ref="toast" />

    <Card class="w-full max-w-4xl shadow-lg overflow-hidden md:flex md:gap-0">
      <div class="hidden md:flex md:w-1/2 bg-cover bg-center" style="background-image: url('/images/login-side.jpg');"></div>

      <div class="w-full md:w-1/2 p-8">
        <h2 class="text-2xl font-bold mb-2">Üdvözlünk a Shift‑Smithben</h2>
        <p class="text-sm text-gray-600 mb-6">Jelentkezz be a munkamenet folytatásához — PrimeVue alapú űrlap.</p>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <InputText id="email" v-model="form.email" class="w-full" placeholder="email@pelda.hu" />
            <small v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</small>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Jelszó</label>
            <Password id="password" v-model="form.password" toggleMask :feedback="false" class="w-full" />
            <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Checkbox v-model="form.remember" inputId="remember" />
              <label for="remember" class="text-sm text-gray-600">Emlékezz rám</label>
            </div>

            <Link v-if="props.canResetPassword" :href="route('password.request')" class="text-sm text-indigo-600 hover:underline">Elfelejtetted?</Link>
          </div>

          <div>
            <Button type="submit" label="Belépés" icon="pi pi-sign-in" class="w-full p-button-primary" :loading="form.processing" />
          </div>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500">
          <span>Nem rendelkezel fiókkal?</span>
          <Link :href="route('register')" class="ml-2 text-indigo-600 hover:underline">Regisztráció</Link>
        </div>

        <div class="mt-6 text-xs text-gray-400">© {{ new Date().getFullYear() }} Shift‑Smith</div>
      </div>
    </Card>
  </div>
</template>
