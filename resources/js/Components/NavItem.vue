<template>
    <Link :href="href" class="nav-item" :class="{ 'nav-item--active': isActive, 'nav-item--collapsed': collapsed }">
        <span class="nav-item__icon" v-html="iconSvg" />
        <span class="nav-item__label" v-show="!collapsed">{{ label }}</span>
        <span v-if="badge && !collapsed" class="nav-item__badge">{{ badge }}</span>
    </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, required: true },
    label: { type: String, required: true },
    collapsed: { type: Boolean, default: false },
    badge: { type: String, default: null },
});

const page = usePage();
const isActive = computed(() => page.url.startsWith(new URL(props.href, window.location.origin).pathname));

const icons = {
    dashboard: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>`,
    projects:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h18M3 17h18"/><circle cx="3" cy="7" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="17" r="1" fill="currentColor"/></svg>`,
    tasks:     `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>`,
    chat:      `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>`,
    progress:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>`,
    resources: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`,
    ideas:     `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 017 7c0 3.1-1.9 5.8-4.7 6.8L14 21H10l-.3-5.2C6.9 14.8 5 12.1 5 9a7 7 0 017-7z"/><line x1="10" y1="21" x2="14" y2="21"/></svg>`,
    routine:   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
    scraper:   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>`,
};

const iconSvg = computed(() => icons[props.icon] ?? icons.dashboard);
</script>

<style scoped>
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    border-radius: 0;
    text-decoration: none;
    color: #64748b;
    font-size: 0.83rem;
    font-weight: 500;
    transition: all 0.15s ease;
    position: relative;
    white-space: nowrap;
}

.nav-item:hover {
    color: #c4b5fd;
    background: rgba(99, 102, 241, 0.06);
}

.nav-item--active {
    color: #a5b4fc;
    background: rgba(99, 102, 241, 0.1);
}

.nav-item--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 0 2px 2px 0;
}

.nav-item--collapsed {
    justify-content: center;
    padding: 10px;
}

.nav-item__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 16px;
    height: 16px;
}

.nav-item__label {
    flex: 1;
}

.nav-item__badge {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    letter-spacing: 0.04em;
}
</style>
