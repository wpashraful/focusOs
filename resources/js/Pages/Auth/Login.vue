<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({ email: '', password: '', remember: false });

const submit = () => {
    form.post(route('login'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In — FocusOS" />

        <div class="auth-header">
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-sub">Sign in to your FocusOS workspace</p>
        </div>

        <div v-if="status" class="alert alert--success">{{ status }}</div>

        <form @submit.prevent="submit" class="auth-form">
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
                    autofocus
                    autocomplete="username"
                />
                <span v-if="form.errors.email" class="field__error">{{ form.errors.email }}</span>
            </div>

            <!-- Password -->
            <div class="field">
                <div class="field__row">
                    <label class="field__label" for="password">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="field__link">
                        Forgot password?
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="field__input"
                    :class="{ 'field__input--error': form.errors.password }"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />
                <span v-if="form.errors.password" class="field__error">{{ form.errors.password }}</span>
            </div>

            <!-- Remember -->
            <label class="checkbox-row">
                <input type="checkbox" v-model="form.remember" class="checkbox" />
                <span class="checkbox-row__label">Remember me</span>
            </label>

            <!-- Submit -->
            <button type="submit" class="btn-primary" :disabled="form.processing">
                <span v-if="form.processing" class="btn-spinner" />
                <span>{{ form.processing ? 'Signing in…' : 'Sign In' }}</span>
            </button>

            <p class="auth-footer-text">
                Don't have an account?
                <Link :href="route('register')" class="auth-link">Create one →</Link>
            </p>
        </form>
    </GuestLayout>
</template>

<style scoped>
.auth-header { text-align: center; margin-bottom: 24px; }
.auth-title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0 0 6px; }
.auth-sub { font-size: 0.85rem; color: #64748b; margin: 0; }

.alert { padding: 10px 14px; border-radius: 8px; font-size: 0.82rem; margin-bottom: 16px; }
.alert--success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #34d399; }

.auth-form { display: flex; flex-direction: column; gap: 16px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field__row { display: flex; align-items: center; justify-content: space-between; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__link { font-size: 0.75rem; color: #6366f1; text-decoration: none; }
.field__link:hover { color: #a5b4fc; }

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

.checkbox-row { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.checkbox {
    width: 15px; height: 15px; border-radius: 4px;
    accent-color: #6366f1; cursor: pointer;
}
.checkbox-row__label { font-size: 0.82rem; color: #64748b; }

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
