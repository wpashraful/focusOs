<template>
    <AppLayout :title="`${workspace.name} — Projects`">
        <div class="page">

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <Link :href="route('workspaces.index')" class="breadcrumb__link">Workspaces</Link>
                <span class="breadcrumb__sep">/</span>
                <span class="breadcrumb__current">{{ workspace.name }}</span>
            </div>

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Projects</h2>
                    <p class="page-header__sub">{{ projects.length }} project{{ projects.length !== 1 ? 's' : '' }} in this workspace</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">+ New Project</button>
            </div>

            <!-- Projects Grid -->
            <div class="projects-grid" v-if="projects.length">
                <Link
                    v-for="p in projects" :key="p.id"
                    :href="route('workspaces.projects.show', [workspace.id, p.id])"
                    class="project-card"
                    :style="{ '--accent': p.color ?? '#6366f1' }"
                >
                    <div class="project-card__top">
                        <div class="project-card__icon">{{ p.icon ?? '📁' }}</div>
                        <span class="project-card__status" :class="`status--${p.status}`">{{ p.status }}</span>
                    </div>
                    <h3 class="project-card__name">{{ p.name }}</h3>
                    <p class="project-card__phase" v-if="p.current_phase_name">
                        📍 {{ p.current_phase_name }}
                    </p>
                    <p class="project-card__desc" v-if="p.description">{{ p.description }}</p>
                    <div class="project-card__bar" />
                </Link>
            </div>

            <!-- Empty -->
            <div class="empty-state" v-else>
                <div class="empty-state__icon">📁</div>
                <p class="empty-state__title">No projects yet</p>
                <p class="empty-state__sub">Create your first project in this workspace.</p>
                <button class="btn-primary" @click="showCreate = true">+ New Project</button>
            </div>

            <!-- Create Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showCreate" @click.self="showCreate = false">
                    <div class="modal">
                        <h3 class="modal__title">New Project</h3>
                        <form @submit.prevent="submitCreate">
                            <div class="field">
                                <label class="field__label">Name</label>
                                <input v-model="form.name" class="field__input" placeholder="e.g. Email Campaign Q3" required />
                                <span v-if="form.errors.name" class="field__error">{{ form.errors.name }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Description <span class="field__opt">(optional)</span></label>
                                <textarea v-model="form.description" class="field__input field__textarea" rows="2" placeholder="What's this project about?"></textarea>
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label class="field__label">Color</label>
                                    <div class="color-options">
                                        <button
                                            v-for="c in colors" :key="c"
                                            type="button"
                                            class="color-dot"
                                            :style="{ background: c }"
                                            :class="{ 'color-dot--active': form.color === c }"
                                            @click="form.color = c"
                                        />
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field__label">Icon</label>
                                    <div class="icon-options">
                                        <button
                                            v-for="ic in icons" :key="ic"
                                            type="button"
                                            class="icon-opt"
                                            :class="{ 'icon-opt--active': form.icon === ic }"
                                            @click="form.icon = ic"
                                        >{{ ic }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="showCreate = false; form.reset()">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner" />
                                    Create Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    workspace: Object,
    projects:  { type: Array, default: () => [] },
});

const showCreate = ref(false);
const form = useForm({ name: '', description: '', color: '#6366f1', icon: '📁' });

const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#64748b'];
const icons  = ['📁', '🚀', '💼', '🎯', '📊', '🔥', '💡', '🛠️', '📝', '🌟'];

const submitCreate = () => {
    form.post(route('workspaces.projects.store', props.workspace.id), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
};
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }

.breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; }
.breadcrumb__link { color: #6366f1; text-decoration: none; }
.breadcrumb__link:hover { color: #a5b4fc; }
.breadcrumb__sep { color: #4a5568; }
.breadcrumb__current { color: #64748b; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

/* Projects Grid */
.projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }

.project-card {
    background: #0f1117; border: 1px solid #1e2130; border-radius: 12px;
    padding: 18px 20px; display: flex; flex-direction: column; gap: 8px;
    text-decoration: none; position: relative; overflow: hidden;
    transition: border-color 0.2s, transform 0.15s;
}
.project-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.project-card__bar {
    position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    background: var(--accent); opacity: 0.6;
}

.project-card__top { display: flex; align-items: center; justify-content: space-between; }
.project-card__icon { font-size: 1.4rem; }
.project-card__status {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    padding: 2px 7px; border-radius: 4px; letter-spacing: 0.04em;
}
.status--active    { background: rgba(16,185,129,0.12); color: #34d399; }
.status--paused    { background: rgba(245,158,11,0.12); color: #fbbf24; }
.status--completed { background: rgba(100,116,139,0.12); color: #94a3b8; }

.project-card__name  { font-size: 0.95rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.project-card__phase { font-size: 0.75rem; color: #8b5cf6; margin: 0; }
.project-card__desc  { font-size: 0.78rem; color: #64748b; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Colors & Icons */
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.color-options { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.color-dot {
    width: 22px; height: 22px; border-radius: 50%; border: 2px solid transparent;
    cursor: pointer; transition: transform 0.15s, border-color 0.15s;
}
.color-dot:hover { transform: scale(1.15); }
.color-dot--active { border-color: white; transform: scale(1.15); }

.icon-options { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
.icon-opt {
    font-size: 1.1rem; padding: 4px 6px; border-radius: 6px; border: 1px solid transparent;
    background: #141620; cursor: pointer; transition: all 0.15s;
}
.icon-opt:hover { border-color: #6366f1; }
.icon-opt--active { border-color: #6366f1; background: rgba(99,102,241,0.15); }

/* Shared */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px; padding: 9px 18px;
    font-size: 0.85rem; font-weight: 600; color: white;
    cursor: pointer; transition: opacity 0.2s; font-family: inherit;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.5; }

.btn-ghost {
    background: transparent; border: 1px solid #2a2d3e; border-radius: 7px;
    padding: 8px 16px; color: #64748b; font-size: 0.85rem;
    cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-ghost:hover { border-color: #6366f1; color: #a5b4fc; }

.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 100; padding: 20px;
}
.modal {
    background: #0f1117; border: 1px solid #2a2d3e; border-radius: 14px;
    padding: 28px; width: 100%; max-width: 460px;
    display: flex; flex-direction: column; gap: 16px;
}
.modal__title   { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 10px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__opt   { font-weight: 400; color: #4a5568; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; width: 100%; transition: border-color 0.2s;
}
.field__input:focus { border-color: #6366f1; }
.field__textarea { resize: vertical; }
.field__error { font-size: 0.75rem; color: #f87171; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; text-align: center; }
.empty-state__icon  { font-size: 3rem; }
.empty-state__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.empty-state__sub   { font-size: 0.82rem; color: #64748b; margin: 0 0 12px; }

.spinner {
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
