<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account — FocusOS" />

        <div class="auth-header">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-sub">Start your 90-day focus journey</p>
        </div>

        <form @submit.prevent="submit" class="auth-form">
            <!-- Name -->
            <div class="field">
                <label class="field__label" for="name">Full Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="field__input"
                    :class="{ 'field__input--error': form.errors.name }"
                    placeholder="Your name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <span v-if="form.errors.name" class="field__error">{{ form.errors.name }}</span>
            </div>

            <!-- Email -->
            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field__input"
                    :class="{ 'field__input--error': form.errors.email }"
                    placeholder="you@example.com"
                    required
                    autocomplete="username"
                />
                <span v-if="form.errors.email" class="field__error">{{ form.errors.email }}</span>
            </div>

            <!-- Password -->
            <div class="field">
                <label class="field__label" for="password">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="field__input"
                    :class="{ 'field__input--error': form.errors.password }"
                    placeholder="Min. 8 characters"
                    required
                    autocomplete="new-password"
                />
                <span v-if="form.errors.password" class="field__error">{{ form.errors.password }}</span>
            </div>

            <!-- Confirm Password -->
            <div class="field">
                <label class="field__label" for="password_confirmation">Confirm Password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="field__input"
                    :class="{ 'field__input--error': form.errors.password_confirmation }"
                    placeholder="Repeat password"
                    required
                    autocomplete="new-password"
                />
                <span v-if="form.errors.password_confirmation" class="field__error">{{ form.errors.password_confirmation }}</span>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-primary" :disabled="form.processing">
                <span v-if="form.processing" class="btn-spinner" />
                <span>{{ form.processing ? 'Creating account…' : 'Create Account' }}</span>
            </button>

            <p class="auth-footer-text">
                Already have an account?
                <Link :href="route('login')" class="auth-link">Sign in →</Link>
            </p>
        </form>
    </GuestLayout>
</template>

<style scoped>
.auth-header { text-align: center; margin-bottom: 24px; }
.auth-title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0 0 6px; }
.auth-sub { font-size: 0.85rem; color: #64748b; margin: 0; }

.auth-form { display: flex; flex-direction: column; gap: 14px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }

.field__input {
    background: #141620;
    border: 1px solid #2a2d3e;
    border-radius: 8px;
    padding: 10px 13px;
    font-size: 0.88rem;
    color: #e2e8f0;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
    font-family: inherit;
}
.field__input::placeholder { color: #3a3f55; }
.field__input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.field__input--error { border-color: #ef4444; }
.field__error { font-size: 0.75rem; color: #f87171; }

.btn-primary {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px;
    padding: 11px 20px;
    font-size: 0.88rem; font-weight: 600; color: white;
    cursor: pointer; transition: opacity 0.2s, transform 0.15s;
    font-family: inherit; margin-top: 4px;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-spinner {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.auth-footer-text { text-align: center; font-size: 0.82rem; color: #64748b; margin: 4px 0 0; }
.auth-link { color: #6366f1; text-decoration: none; font-weight: 500; }
.auth-link:hover { color: #a5b4fc; }
</style>
