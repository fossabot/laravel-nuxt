<script lang="ts" setup>
const form = ref();
const router = useRouter();

const loading = ref(false);
const state = reactive({
  email: "test@test.com",
});

async function onSubmit(event: any) {
  form.value.clear();
  loading.value = true;

  const { data, error } = await useFetch<any>("forgot-password", {
    method: "POST",
    body: { ...event.data },
  });

  loading.value = false;

  if (error.value?.data) return form.value.setErrors(error.value.data);

  if (data.value) {
    useToast().add({
      title: "Success",
      description: data.value.message,
      color: "emerald",
    });
  }
}
</script>

<template>
  <div class="space-y-4">
    <UForm ref="form" :state="state" @submit="onSubmit" class="space-y-4">
      <UFormGroup label="Email" name="email" required>
        <UInput
          v-model="state.email"
          placeholder="you@example.com"
          icon="i-heroicons-envelope"
          trailing
          type="email"
          autofocus
        />
      </UFormGroup>

      <div class="flex items-center justify-end space-x-4">
        <UButton type="submit" label="Send reset link" :loading="loading" />
      </div>
    </UForm>

    <div class="text-sm">
      <NuxtLink class="text-sm" to="/auth/login">Back to Log In</NuxtLink>
    </div>
  </div>
</template>
