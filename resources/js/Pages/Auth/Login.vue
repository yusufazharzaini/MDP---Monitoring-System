<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('auth.sign_in')" />

    <AuthLayout :title="t('auth.sign_in_title')" :subtitle="t('auth.sign_in_subtitle')">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="field-label">{{ t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    autocomplete="username"
                    required
                    autofocus
                    :placeholder="t('auth.email_placeholder')"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="field-label">{{ t('auth.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    autocomplete="current-password"
                    required
                />
                <InputError :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-muted">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="size-4 rounded border-line bg-canvas text-brand"
                />
                {{ t('auth.remember_me') }}
            </label>

            <PrimaryButton type="submit" :loading="form.processing">{{ t('auth.sign_in') }}</PrimaryButton>
        </form>
    </AuthLayout>
</template>
