<template>
    <div class="focusos-shell" :class="{ 'dark-mode': isDark }">

        <!-- Mobile Overlay -->
        <div class="mobile-overlay" v-if="mobileOpen" @click="mobileOpen = false" />

        <!-- Sidebar -->
        <aside class="sidebar"
            :class="{ 'sidebar--collapsed': sidebarCollapsed, 'sidebar--mobile-open': mobileOpen }">

            <!-- Logo -->
            <div class="sidebar__logo">
                <div class="logo-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="url(#grad)" stroke-width="2"/>
                        <circle cx="12" cy="12" r="5" fill="url(#grad)"/>
                        <defs>
                            <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#6366f1"/>
                                <stop offset="100%" style="stop-color:#8b5cf6"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span class="logo-text" v-show="!sidebarCollapsed">FocusOS</span>
                <!-- Mobile close -->
                <button class="mobile-close-btn" v-show="mobileOpen" @click="mobileOpen = false" aria-label="Close menu">✕</button>
            </div>

            <!-- Phase Badge -->
            <div class="phase-badge" v-show="!sidebarCollapsed" v-if="$page.props.currentProject">
                <span class="phase-badge__label">Active Phase</span>
                <span class="phase-badge__name">{{ $page.props.currentProject?.current_phase_name ?? 'No Phase Set' }}</span>
            </div>

            <!-- Navigation -->
            <nav class="sidebar__nav">
                <div class="nav-section">
                    <span class="nav-section__title" v-show="!sidebarCollapsed">Workspace</span>
                    <NavItem :href="route('dashboard')" icon="dashboard" label="Dashboard" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                    <NavItem :href="route('projects.index')" icon="projects" label="Projects" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                    <NavItem :href="route('leads.index')" icon="scraper" label="Lead Intelligence" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                </div>

                <div class="nav-section">
                    <span class="nav-section__title" v-show="!sidebarCollapsed">Daily</span>
                    <NavItem :href="route('tasks.today')" icon="tasks" label="Today's Tasks" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                    <NavItem :href="route('chat.index')" icon="chat" label="AI Coach" :collapsed="sidebarCollapsed" badge="AI" @click="mobileOpen = false" />
                    <NavItem :href="route('progress.index')" icon="progress" label="Progress" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                </div>

                <div class="nav-section">
                    <span class="nav-section__title" v-show="!sidebarCollapsed">Project</span>
                    <NavItem
                        :href="$page.props.currentProject?.id ? route('resources.index', $page.props.currentProject.id) : '#'"
                        icon="resources" label="Resources" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                    <NavItem :href="route('ideas.index')" icon="ideas" label="Future Ideas" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                    <NavItem
                        :href="$page.props.currentProject?.id ? route('routine.edit', $page.props.currentProject.id) : '#'"
                        icon="routine" label="Routine" :collapsed="sidebarCollapsed" @click="mobileOpen = false" />
                </div>
            </nav>

            <!-- Bottom: User + Settings -->
            <div class="sidebar__footer">
                <button class="collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed" :title="sidebarCollapsed ? 'Expand' : 'Collapse'">
                    <IconChevron :class="{ 'rotate-180': sidebarCollapsed }" />
                </button>
                <div class="user-row" v-show="!sidebarCollapsed">
                    <div class="user-avatar">
                        <img v-if="$page.props.auth.user.avatar" :src="$page.props.auth.user.avatar" :alt="$page.props.auth.user.name" />
                        <span v-else>{{ $page.props.auth.user.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ $page.props.auth.user.name }}</span>
                        <Link :href="route('profile.edit')" class="user-settings">Settings</Link>
                    </div>
                    <!-- Dark Mode Toggle -->
                    <button class="dark-toggle" @click="toggleDark" :title="isDark ? 'Light Mode' : 'Dark Mode'" id="dark-mode-toggle">
                        {{ isDark ? '☀️' : '🌙' }}
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-wrapper">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar__left">
                    <!-- Mobile Hamburger -->
                    <button class="hamburger-btn" @click="mobileOpen = true" aria-label="Open menu" id="hamburger-btn">
                        <span /><span /><span />
                    </button>
                    <h1 class="topbar__title" v-if="title">{{ title }}</h1>
                </div>
                <div class="topbar__right">
                    <slot name="header-actions" />
                    <!-- Flash messages -->
                    <Transition name="flash">
                        <div v-if="$page.props.flash?.success" class="flash flash--success">
                            {{ $page.props.flash.success }}
                        </div>
                    </Transition>
                </div>
            </header>

            <!-- Page Content -->
            <main class="main-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavItem from '@/Components/NavItem.vue';
import IconChevron from '@/Components/Icons/IconChevron.vue';

defineProps({
    title: String,
});

const sidebarCollapsed = ref(false);
const mobileOpen       = ref(false);
const isDark           = ref(true); // default dark

onMounted(() => {
    const stored = localStorage.getItem('focusos-dark-mode');
    isDark.value = stored === null ? true : stored === 'true';
});

const toggleDark = () => {
    isDark.value = !isDark.value;
    localStorage.setItem('focusos-dark-mode', isDark.value);
};
</script>

<style scoped>
/* ── Shell ─────────────────────────────────────── */
.focusos-shell {
    display: flex;
    min-height: 100vh;
    background: #0b0c10;
    color: #e2e8f0;
    font-family: 'Inter', sans-serif;
}

/* Light mode overrides */
.focusos-shell:not(.dark-mode) {
    background: #f1f5f9;
    color: #1e293b;
}
.focusos-shell:not(.dark-mode) .sidebar { background: #ffffff; border-right-color: #e2e8f0; }
.focusos-shell:not(.dark-mode) .topbar { background: #ffffff; border-bottom-color: #e2e8f0; }
.focusos-shell:not(.dark-mode) .nav-section__title { color: #94a3b8; }
.focusos-shell:not(.dark-mode) .user-name { color: #1e293b; }
.focusos-shell:not(.dark-mode) .topbar__title { color: #1e293b; }

/* ── Mobile Overlay ────────────────────────────── */
.mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 40;
    display: none;
}
@media (max-width: 768px) {
    .mobile-overlay { display: block; }
}

/* ── Sidebar ────────────────────────────────────── */
.sidebar {
    width: 240px;
    min-height: 100vh;
    background: #0f1117;
    border-right: 1px solid #1e2130;
    display: flex;
    flex-direction: column;
    transition: width 0.25s ease, transform 0.25s ease;
    flex-shrink: 0;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 50;
}

.sidebar--collapsed {
    width: 64px;
}

/* Mobile sidebar hidden by default */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        left: 0; top: 0; bottom: 0;
        width: 260px !important;
        transform: translateX(-100%);
    }
    .sidebar--mobile-open {
        transform: translateX(0);
    }
}

/* ── Logo ───────────────────────────────────────── */
.sidebar__logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px 16px;
    border-bottom: 1px solid #1e2130;
}

.logo-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.logo-text {
    font-size: 1.1rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    white-space: nowrap;
    flex: 1;
}

.mobile-close-btn {
    background: transparent;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
    margin-left: auto;
}

/* ── Phase Badge ────────────────────────────────── */
.phase-badge {
    margin: 12px 12px 4px;
    background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.08));
    border: 1px solid rgba(99,102,241,0.25);
    border-radius: 8px;
    padding: 8px 12px;
}

.phase-badge__label {
    display: block;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6366f1;
    margin-bottom: 2px;
}

.phase-badge__name {
    display: block;
    font-size: 0.78rem;
    font-weight: 500;
    color: #c4b5fd;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Nav ────────────────────────────────────────── */
.sidebar__nav {
    flex: 1;
    padding: 8px 0;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 4px;
}

.nav-section__title {
    display: block;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #4a5568;
    padding: 10px 18px 4px;
    white-space: nowrap;
}

/* ── Footer ─────────────────────────────────────── */
.sidebar__footer {
    border-top: 1px solid #1e2130;
    padding: 12px 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.collapse-btn {
    align-self: flex-end;
    background: #1a1d2a;
    border: 1px solid #2a2d3e;
    border-radius: 6px;
    color: #64748b;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
}

.collapse-btn:hover {
    color: #a5b4fc;
    border-color: #6366f1;
    background: rgba(99,102,241,0.1);
}

.collapse-btn svg {
    transition: transform 0.25s ease;
}

.rotate-180 {
    transform: rotate(180deg);
}

.user-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.user-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #e2e8f0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-settings {
    font-size: 0.7rem;
    color: #64748b;
    text-decoration: none;
    transition: color 0.15s;
}

.user-settings:hover {
    color: #6366f1;
}

.dark-toggle {
    background: transparent;
    border: 1px solid #1e2130;
    border-radius: 6px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    flex-shrink: 0;
    transition: border-color 0.15s;
}
.dark-toggle:hover { border-color: #6366f1; }

/* ── Main Wrapper ───────────────────────────────── */
.main-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

/* ── Topbar ─────────────────────────────────────── */
.topbar {
    height: 56px;
    background: #0f1117;
    border-bottom: 1px solid #1e2130;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.topbar__left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.topbar__title {
    font-size: 1rem;
    font-weight: 600;
    color: #e2e8f0;
    margin: 0;
}

.topbar__right {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Hamburger Button */
.hamburger-btn {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: transparent;
    border: 1px solid #1e2130;
    border-radius: 6px;
    padding: 7px 8px;
    cursor: pointer;
    flex-shrink: 0;
}
.hamburger-btn span {
    display: block;
    width: 18px;
    height: 2px;
    background: #64748b;
    border-radius: 2px;
}
@media (max-width: 768px) {
    .hamburger-btn { display: flex; }
}

/* ── Flash Messages ─────────────────────────────── */
.flash {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

.flash--success {
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}

.flash-enter-active, .flash-leave-active { transition: opacity 0.3s; }
.flash-enter-from, .flash-leave-to { opacity: 0; }

/* ── Main Content ───────────────────────────────── */
.main-content {
    flex: 1;
    padding: 28px;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .main-content { padding: 16px; }
    .topbar { padding: 0 16px; }
}
</style>
