<template>
    <AppLayout :title="`${project.name} — Goals`">
        <div class="page">

            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Goals</h2>
                    <p class="page-header__sub">{{ goals.length }} goal{{ goals.length !== 1 ? 's' : '' }} · {{ project.name }}</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">+ New Goal</button>
            </div>

            <!-- Goals list -->
            <div class="goals-list" v-if="goals.length">
                <div v-for="g in goals" :key="g.id" class="goal-card" :class="`priority--${g.priority}`">
                    <div class="goal-card__header">
                        <div class="goal-card__left">
                            <span class="priority-dot" :class="`dot--${g.priority}`" />
                            <div>
                                <p class="goal-card__title">{{ g.title }}</p>
                                <p class="goal-card__desc" v-if="g.description">{{ g.description }}</p>
                            </div>
                        </div>
                        <div class="goal-card__right">
                            <span class="status-badge" :class="`status--${g.status}`">{{ g.status }}</span>
                            <button class="icon-btn" @click="startEdit(g)">✏️</button>
                            <button class="icon-btn icon-btn--danger" @click="deleteGoal(g)">🗑️</button>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div class="goal-progress">
                        <div class="goal-progress__bar">
                            <div class="goal-progress__fill" :style="{ width: g.progress + '%' }" />
                        </div>
                        <div class="goal-progress__labels">
                            <span class="goal-progress__current">{{ g.current_value }} {{ g.unit }}</span>
                            <span class="goal-progress__pct">{{ g.progress }}%</span>
                            <span class="goal-progress__target">/ {{ g.target_value }} {{ g.unit }}</span>
                        </div>
                    </div>

                    <!-- Quick update value -->
                    <div class="goal-update" v-if="updatingId === g.id">
                        <input v-model.number="updateVal" type="number" class="field__input field__input--sm" placeholder="New value" />
                        <button class="btn-sm-primary" @click="saveValue(g)">Save</button>
                        <button class="btn-sm-ghost" @click="updatingId = null">Cancel</button>
                    </div>
                    <button v-else class="btn-update" @click="startUpdate(g)">📊 Update Progress</button>
                </div>
            </div>

            <!-- Empty -->
            <div class="empty-state" v-else>
                <div class="empty-state__icon">🎯</div>
                <p class="empty-state__title">No goals yet</p>
                <p class="empty-state__sub">Define measurable goals for this project.</p>
                <button class="btn-primary" @click="showCreate = true">+ Create Goal</button>
            </div>

            <!-- Create / Edit Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showCreate || editingGoal" @click.self="closeModal">
                    <div class="modal">
                        <h3 class="modal__title">{{ editingGoal ? 'Edit Goal' : 'New Goal' }}</h3>
                        <form @submit.prevent="submitForm">
                            <div class="field">
                                <label class="field__label">Title</label>
                                <input v-model="form.title" class="field__input" placeholder="e.g. Send 5,000 cold emails" required />
                                <span v-if="form.errors.title" class="field__error">{{ form.errors.title }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Description <span class="field__opt">(optional)</span></label>
                                <textarea v-model="form.description" class="field__input field__textarea" rows="2" />
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label class="field__label">Target Value</label>
                                    <input v-model.number="form.target_value" type="number" class="field__input" min="0" required />
                                    <span v-if="form.errors.target_value" class="field__error">{{ form.errors.target_value }}</span>
                                </div>
                                <div class="field">
                                    <label class="field__label">Unit</label>
                                    <input v-model="form.unit" class="field__input" placeholder="emails, videos, $$" />
                                </div>
                            </div>
                            <div class="field">
                                <label class="field__label">Priority</label>
                                <div class="priority-options">
                                    <button v-for="p in ['low','medium','high']" :key="p" type="button"
                                        class="priority-opt" :class="[`p--${p}`, { 'priority-opt--active': form.priority === p }]"
                                        @click="form.priority = p">{{ p }}</button>
                                </div>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="closeModal">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner" />
                                    {{ editingGoal ? 'Save' : 'Create Goal' }}
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
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project: Object,
    goals:   { type: Array, default: () => [] },
});

const showCreate = ref(false);
const editingGoal = ref(null);
const updatingId  = ref(null);
const updateVal   = ref(0);

const form = useForm({ title: '', description: '', target_value: 0, unit: '', priority: 'medium' });

const startEdit = (g) => {
    editingGoal.value = g;
    form.title = g.title;
    form.description = g.description ?? '';
    form.target_value = g.target_value;
    form.unit = g.unit ?? '';
    form.priority = g.priority;
};
const closeModal = () => { showCreate.value = false; editingGoal.value = null; form.reset(); };

const submitForm = () => {
    if (editingGoal.value) {
        form.patch(route('goals.update', [props.project.id, editingGoal.value.id]), { onSuccess: closeModal });
    } else {
        form.post(route('goals.store', props.project.id), { onSuccess: closeModal });
    }
};

const deleteGoal = (g) => router.delete(route('goals.destroy', [props.project.id, g.id]), { preserveScroll: true });

const startUpdate = (g) => { updatingId.value = g.id; updateVal.value = g.current_value; };
const saveValue = (g) => {
    router.patch(route('goals.update', [props.project.id, g.id]), { current_value: updateVal.value }, {
        preserveScroll: true,
        onSuccess: () => { updatingId.value = null; },
    });
};
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

/* Goal Cards */
.goals-list { display: flex; flex-direction: column; gap: 12px; }
.goal-card {
    background: #0f1117; border: 1px solid #1e2130; border-radius: 12px;
    padding: 16px 20px; display: flex; flex-direction: column; gap: 12px;
    border-left: 3px solid #2a2d3e;
}
.priority--high   { border-left-color: #f59e0b; }
.priority--medium { border-left-color: #6366f1; }
.priority--low    { border-left-color: #475569; }

.goal-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.goal-card__left  { display: flex; align-items: flex-start; gap: 12px; }
.priority-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.dot--high   { background: #f59e0b; }
.dot--medium { background: #6366f1; }
.dot--low    { background: #475569; }
.goal-card__title { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin: 0 0 3px; }
.goal-card__desc  { font-size: 0.78rem; color: #64748b; margin: 0; }
.goal-card__right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.status-badge {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    padding: 2px 8px; border-radius: 4px;
}
.status--active    { background: rgba(16,185,129,0.12); color: #34d399; }
.status--completed { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.status--paused    { background: rgba(245,158,11,0.12); color: #fbbf24; }

.icon-btn { background: none; border: none; cursor: pointer; font-size: 0.9rem; padding: 3px; transition: opacity 0.15s; }
.icon-btn:hover { opacity: 0.8; }
.icon-btn--danger:hover { filter: hue-rotate(-20deg); }

/* Progress */
.goal-progress { display: flex; flex-direction: column; gap: 6px; }
.goal-progress__bar { height: 6px; background: #1e2130; border-radius: 3px; overflow: hidden; }
.goal-progress__fill { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 3px; transition: width 0.4s ease; }
.goal-progress__labels { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; }
.goal-progress__current { color: #a5b4fc; font-weight: 600; }
.goal-progress__pct     { color: #64748b; margin-left: auto; }
.goal-progress__target  { color: #4a5568; }

/* Quick update */
.goal-update { display: flex; align-items: center; gap: 8px; }
.field__input--sm { padding: 6px 10px; font-size: 0.82rem; }
.btn-update {
    background: none; border: none; font-size: 0.75rem; color: #4a5568;
    cursor: pointer; padding: 0; font-family: inherit; transition: color 0.15s;
}
.btn-update:hover { color: #a5b4fc; }
.btn-sm-primary {
    padding: 5px 12px; border-radius: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;
    color: white; font-size: 0.78rem; font-weight: 600; cursor: pointer; font-family: inherit;
}
.btn-sm-ghost {
    padding: 5px 12px; border-radius: 6px;
    border: 1px solid #2a2d3e; background: transparent;
    color: #64748b; font-size: 0.78rem; cursor: pointer; font-family: inherit;
}

/* Priority options */
.priority-options { display: flex; gap: 8px; }
.priority-opt {
    padding: 5px 14px; border-radius: 6px; border: 1px solid #2a2d3e;
    background: transparent; font-size: 0.8rem; cursor: pointer; font-family: inherit;
    color: #64748b; text-transform: capitalize; transition: all 0.15s;
}
.priority-opt.p--high.priority-opt--active   { border-color: #f59e0b; background: rgba(245,158,11,0.12); color: #fbbf24; }
.priority-opt.p--medium.priority-opt--active { border-color: #6366f1; background: rgba(99,102,241,0.12); color: #a5b4fc; }
.priority-opt.p--low.priority-opt--active    { border-color: #475569; background: rgba(71,85,105,0.12);  color: #94a3b8; }

/* Shared */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;
    border-radius: 8px; padding: 9px 18px; font-size: 0.85rem; font-weight: 600;
    color: white; cursor: pointer; transition: opacity 0.2s; font-family: inherit;
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

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__opt   { font-weight: 400; color: #4a5568; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; transition: border-color 0.2s;
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
