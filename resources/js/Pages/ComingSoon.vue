<template>
    <AppLayout :title="page">
        <div class="coming-soon">
            <div class="coming-soon__icon">🚧</div>
            <h2 class="coming-soon__title">{{ page }}</h2>
            <p class="coming-soon__sub">This section is being built. Check back soon!</p>
            <Link :href="route('dashboard')" class="coming-soon__back">← Back to Dashboard</Link>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
defineProps({ page: String });
</script>

<style scoped>
.coming-soon {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; min-height: 50vh; gap: 12px; text-align: center;
}
.coming-soon__icon { font-size: 3rem; }
.coming-soon__title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.coming-soon__sub { font-size: 0.88rem; color: #64748b; margin: 0; }
.coming-soon__back { font-size: 0.83rem; color: #6366f1; text-decoration: none; margin-top: 8px; }
.coming-soon__back:hover { color: #a5b4fc; }
</style>
