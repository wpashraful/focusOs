<template>
    <AppLayout :title="project.name">
        <div class="page">

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <Link :href="route('workspaces.index')" class="breadcrumb__link">Workspaces</Link>
                <span class="breadcrumb__sep">/</span>
                <Link :href="route('workspaces.projects.index', workspace.id)" class="breadcrumb__link">{{ workspace.name }}</Link>
                <span class="breadcrumb__sep">/</span>
                <span class="breadcrumb__current">{{ project.name }}</span>
            </div>

            <!-- Project Header -->
            <div class="project-header" :style="{ '--accent': project.color ?? '#6366f1' }">
                <div class="project-header__left">
                    <div class="project-header__icon">{{ project.icon ?? '📁' }}</div>
                    <div>
                        <h2 class="project-header__name">{{ project.name }}</h2>
                        <p class="project-header__desc" v-if="project.description">{{ project.description }}</p>
                    </div>
                </div>
                <span class="project-header__status" :class="`status--${project.status}`">{{ project.status }}</span>
            </div>

            <!-- Phase Banner -->
            <div class="phase-banner">
                <div class="phase-banner__left">
                    <span class="phase-banner__label">Current Phase</span>
                    <h3 class="phase-banner__name">{{ project.current_phase_name ?? 'No phase set' }}</h3>
                    <p class="phase-banner__goal" v-if="project.current_phase_goal">{{ project.current_phase_goal }}</p>
                </div>
                <div class="phase-banner__right">
                    <div v-if="project.phase_ends_at" class="phase-banner__dates">
                        <span class="phase-banner__date-label">Ends</span>
                        <span class="phase-banner__date">{{ formatDate(project.phase_ends_at) }}</span>
                        <span class="phase-banner__days" :class="{ 'phase-banner__days--soon': daysLeft < 7 }">
                            {{ daysLeft }} days left
                        </span>
                    </div>
                    <button class="btn-outline" @click="showPhaseEdit = !showPhaseEdit">
                        {{ showPhaseEdit ? 'Cancel' : '✏️ Edit Phase' }}
                    </button>
                </div>
            </div>

            <!-- Phase Edit Form -->
            <Transition name="slide">
                <div class="phase-form card" v-if="showPhaseEdit">
                    <h4 class="phase-form__title">{{ project.current_phase_name ? 'Update Phase' : 'Set Phase' }}</h4>
                    <p class="phase-form__note" v-if="project.current_phase_name">
                        ⚠️ The current phase will be archived in Phase Snapshots before updating.
                    </p>
                    <form @submit.prevent="submitPhase">
                        <div class="form-grid">
                            <div class="field">
                                <label class="field__label">Phase Name</label>
                                <input v-model="phaseForm.current_phase_name" class="field__input" placeholder="e.g. Phase 1 — Launch" required />
                                <span v-if="phaseForm.errors.current_phase_name" class="field__error">{{ phaseForm.errors.current_phase_name }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Goal</label>
                                <textarea v-model="phaseForm.current_phase_goal" class="field__input field__textarea" rows="2" placeholder="What do you want to achieve this phase?" />
                            </div>
                            <div class="field">
                                <label class="field__label">Start Date</label>
                                <input v-model="phaseForm.phase_started_at" type="date" class="field__input" />
                            </div>
                            <div class="field">
                                <label class="field__label">End Date</label>
                                <input v-model="phaseForm.phase_ends_at" type="date" class="field__input" />
                            </div>
                        </div>
                        <div class="phase-form__actions">
                            <button type="submit" class="btn-primary" :disabled="phaseForm.processing">
                                <span v-if="phaseForm.processing" class="spinner" />
                                Save Phase
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Phase Snapshots -->
            <div class="card" v-if="project.phase_snapshots && project.phase_snapshots.length">
                <h4 class="card__title">📚 Phase History</h4>
                <div class="snapshot-list">
                    <div v-for="snap in project.phase_snapshots" :key="snap.id" class="snapshot-item">
                        <div class="snapshot-item__dot" />
                        <div class="snapshot-item__body">
                            <p class="snapshot-item__name">{{ snap.phase_name }}</p>
                            <p class="snapshot-item__goal" v-if="snap.phase_goal">{{ snap.phase_goal }}</p>
                            <span class="snapshot-item__dates" v-if="snap.started_at || snap.ended_at">
                                {{ snap.started_at ? formatDate(snap.started_at) : '?' }} → {{ snap.ended_at ? formatDate(snap.ended_at) : 'ongoing' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder sections for later steps -->
            <div class="grid-2">
                <div class="card placeholder-card">
                    <h4 class="card__title">📌 Tasks <span class="badge">Coming Step 3</span></h4>
                    <p class="placeholder-text">Tasks will appear here once Step 3 is built.</p>
                </div>
                <div class="card placeholder-card">
                    <h4 class="card__title">🎯 Goals <span class="badge">Coming Step 3</span></h4>
                    <p class="placeholder-text">Project goals will appear here.</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    workspace: Object,
    project:   Object,
});

const showPhaseEdit = ref(false);

const phaseForm = useForm({
    current_phase_name: props.project.current_phase_name ?? '',
    current_phase_goal: props.project.current_phase_goal ?? '',
    phase_started_at:   props.project.phase_started_at ?? '',
    phase_ends_at:      props.project.phase_ends_at ?? '',
});

const submitPhase = () => {
    phaseForm.patch(route('workspaces.projects.phase', [props.workspace.id, props.project.id]), {
        onSuccess: () => (showPhaseEdit.value = false),
    });
};

const formatDate = (d) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

const daysLeft = computed(() => {
    if (!props.project.phase_ends_at) return null;
    const diff = new Date(props.project.phase_ends_at) - new Date();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
});
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }

.breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; }
.breadcrumb__link { color: #6366f1; text-decoration: none; }
.breadcrumb__link:hover { color: #a5b4fc; }
.breadcrumb__sep { color: #4a5568; }
.breadcrumb__current { color: #64748b; }

/* Project header */
.project-header {
    display: flex; align-items: center; justify-content: space-between;
    background: #0f1117; border: 1px solid #1e2130; border-radius: 12px;
    padding: 18px 22px; border-left: 4px solid var(--accent);
}
.project-header__left { display: flex; align-items: center; gap: 14px; }
.project-header__icon { font-size: 2rem; }
.project-header__name { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin: 0 0 3px; }
.project-header__desc { font-size: 0.82rem; color: #64748b; margin: 0; }
.project-header__status {
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
    padding: 3px 10px; border-radius: 20px;
}
.status--active    { background: rgba(16,185,129,0.12); color: #34d399; }
.status--paused    { background: rgba(245,158,11,0.12); color: #fbbf24; }
.status--completed { background: rgba(100,116,139,0.12); color: #94a3b8; }

/* Phase Banner */
.phase-banner {
    background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.06));
    border: 1px solid rgba(99,102,241,0.2); border-radius: 12px;
    padding: 18px 22px; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;
}
.phase-banner__label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6366f1; display: block; margin-bottom: 4px; }
.phase-banner__name  { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0 0 6px; }
.phase-banner__goal  { font-size: 0.83rem; color: #94a3b8; margin: 0; }
.phase-banner__right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; flex-shrink: 0; }
.phase-banner__dates { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.phase-banner__date-label { font-size: 0.65rem; color: #4a5568; text-transform: uppercase; }
.phase-banner__date  { font-size: 0.85rem; font-weight: 600; color: #a5b4fc; }
.phase-banner__days  { font-size: 0.72rem; color: #64748b; }
.phase-banner__days--soon { color: #f87171; font-weight: 600; }

/* Phase Form */
.phase-form { padding: 22px; display: flex; flex-direction: column; gap: 16px; }
.phase-form__title  { font-size: 0.95rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.phase-form__note   { font-size: 0.78rem; color: #f59e0b; margin: -6px 0 0; padding: 8px 12px; background: rgba(245,158,11,0.08); border-radius: 6px; }
.phase-form__actions { display: flex; justify-content: flex-end; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }

/* Card */
.card { background: #0f1117; border: 1px solid #1e2130; border-radius: 12px; padding: 18px 20px; }
.card__title { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
.badge { font-size: 0.62rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; background: rgba(99,102,241,0.15); color: #a5b4fc; text-transform: uppercase; letter-spacing: 0.03em; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 700px) { .grid-2 { grid-template-columns: 1fr; } }
.placeholder-card { opacity: 0.6; }
.placeholder-text { font-size: 0.8rem; color: #4a5568; }

/* Snapshots */
.snapshot-list { display: flex; flex-direction: column; gap: 12px; }
.snapshot-item { display: flex; gap: 12px; }
.snapshot-item__dot { width: 10px; height: 10px; border-radius: 50%; background: #2a2d3e; flex-shrink: 0; margin-top: 5px; }
.snapshot-item__name  { font-size: 0.85rem; font-weight: 600; color: #c4b5fd; margin: 0 0 2px; }
.snapshot-item__goal  { font-size: 0.78rem; color: #64748b; margin: 0 0 4px; }
.snapshot-item__dates { font-size: 0.72rem; color: #4a5568; }

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px; padding: 9px 20px;
    font-size: 0.85rem; font-weight: 600; color: white;
    cursor: pointer; transition: opacity 0.2s; font-family: inherit;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-outline {
    background: transparent; border: 1px solid rgba(99,102,241,0.3); border-radius: 7px;
    padding: 7px 14px; color: #a5b4fc; font-size: 0.8rem; cursor: pointer;
    font-family: inherit; transition: all 0.15s;
}
.btn-outline:hover { background: rgba(99,102,241,0.1); }

/* Fields */
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; width: 100%; transition: border-color 0.2s;
}
.field__input:focus { border-color: #6366f1; }
.field__textarea { resize: vertical; }
.field__error { font-size: 0.75rem; color: #f87171; }

/* Transitions */
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-10px); }

.spinner {
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
