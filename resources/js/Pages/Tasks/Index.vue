<template>
    <AppLayout :title="`${project.name} — Tasks`">
        <div class="page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Tasks</h2>
                    <p class="page-header__sub">{{ filteredTasks.length }} task{{ filteredTasks.length !== 1 ? 's' : '' }}</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">+ New Task</button>
            </div>

            <!-- Filters -->
            <div class="filters">
                <button v-for="f in filters" :key="f.value"
                    class="filter-btn" :class="{ 'filter-btn--active': activeFilter === f.value }"
                    @click="activeFilter = f.value">
                    {{ f.label }}
                </button>
            </div>

            <!-- Task Groups by priority -->
            <div v-if="filteredTasks.length" class="task-groups">
                <div v-for="priority in ['urgent','high','medium','low']" :key="priority">
                    <div v-if="byPriority(priority).length">
                        <h3 class="group-title" :class="`gp--${priority}`">{{ priority.toUpperCase() }}</h3>
                        <div class="task-list">
                            <div v-for="task in byPriority(priority)" :key="task.id" class="task-row"
                                :class="{ 'task-row--done': task.status === 'done' }">
                                <!-- Checkbox -->
                                <button class="check-btn" :class="{ 'check-btn--done': task.status === 'done' }"
                                    @click="toggleDone(task)">
                                    <svg v-if="task.status === 'done'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>

                                <div class="task-row__body">
                                    <p class="task-row__title">{{ task.title }}</p>
                                    <div class="task-row__meta">
                                        <span v-if="task.goal" class="meta-chip meta-chip--goal">🎯 {{ task.goal.title }}</span>
                                        <span v-if="task.due_date" class="meta-chip" :class="isOverdue(task.due_date) ? 'meta-chip--overdue' : 'meta-chip--date'">
                                            📅 {{ formatDate(task.due_date) }}
                                        </span>
                                        <span v-if="task.estimated_minutes" class="meta-chip meta-chip--est">⏱ {{ task.estimated_minutes }}m</span>
                                        <!-- Subtask progress -->
                                        <span v-if="task.subtasks && task.subtasks.length" class="meta-chip meta-chip--sub">
                                            ☑ {{ task.subtasks.filter(s=>s.done).length }}/{{ task.subtasks.length }}
                                        </span>
                                    </div>
                                </div>

                                <div class="task-row__actions">
                                    <span class="status-badge" :class="`st--${task.status}`">{{ statusLabel(task.status) }}</span>
                                    <button class="icon-btn" @click="startEdit(task)">✏️</button>
                                    <button class="icon-btn icon-btn--del" @click="deleteTask(task)">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty -->
            <div class="empty-state" v-else>
                <div class="empty-state__icon">📋</div>
                <p class="empty-state__title">{{ activeFilter === 'all' ? 'No tasks yet' : 'No tasks matching filter' }}</p>
                <p class="empty-state__sub">{{ activeFilter === 'all' ? 'Break your goals into actionable tasks.' : 'Try a different filter.' }}</p>
                <button v-if="activeFilter === 'all'" class="btn-primary" @click="showCreate = true">+ Create Task</button>
            </div>

            <!-- Create / Edit Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showCreate || editingTask" @click.self="closeModal">
                    <div class="modal">
                        <h3 class="modal__title">{{ editingTask ? 'Edit Task' : 'New Task' }}</h3>
                        <form @submit.prevent="submitForm">
                            <div class="field">
                                <label class="field__label">Title</label>
                                <input v-model="form.title" class="field__input" placeholder="What needs to be done?" required />
                                <span v-if="form.errors.title" class="field__error">{{ form.errors.title }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Linked Goal <span class="field__opt">(optional)</span></label>
                                <select v-model="form.goal_id" class="field__input field__select">
                                    <option :value="null">— No goal —</option>
                                    <option v-for="g in goals" :key="g.id" :value="g.id">{{ g.title }}</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label class="field__label">Priority</label>
                                    <select v-model="form.priority" class="field__input field__select">
                                        <option value="urgent">🔴 Urgent</option>
                                        <option value="high">🟠 High</option>
                                        <option value="medium">🟡 Medium</option>
                                        <option value="low">⚪ Low</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field__label">Due Date</label>
                                    <input v-model="form.due_date" type="date" class="field__input" />
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label class="field__label">Scheduled Time</label>
                                    <input v-model="form.scheduled_time" type="time" class="field__input" />
                                </div>
                                <div class="field">
                                    <label class="field__label">Est. Minutes</label>
                                    <input v-model.number="form.estimated_minutes" type="number" min="1" class="field__input" placeholder="30" />
                                </div>
                            </div>
                            <div class="field">
                                <label class="field__label">Notes <span class="field__opt">(optional)</span></label>
                                <textarea v-model="form.notes" class="field__input field__textarea" rows="2" />
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="closeModal">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner" />
                                    {{ editingTask ? 'Save' : 'Create Task' }}
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
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project: Object,
    tasks:   { type: Array, default: () => [] },
    goals:   { type: Array, default: () => [] },
});

const activeFilter = ref('all');
const filters = [
    { value: 'all',         label: 'All' },
    { value: 'pending',     label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'done',        label: 'Done' },
    { value: 'today',       label: 'Today' },
];

const filteredTasks = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    if (activeFilter.value === 'all')         return props.tasks;
    if (activeFilter.value === 'today')       return props.tasks.filter(t => t.due_date === today);
    return props.tasks.filter(t => t.status === activeFilter.value);
});

const byPriority = (p) => filteredTasks.value.filter(t => t.priority === p);

const showCreate  = ref(false);
const editingTask = ref(null);
const form = useForm({ title: '', goal_id: null, priority: 'medium', due_date: '', scheduled_time: '', estimated_minutes: '', notes: '' });

const startEdit = (t) => {
    editingTask.value = t;
    Object.assign(form, { title: t.title, goal_id: t.goal_id, priority: t.priority, due_date: t.due_date ?? '', scheduled_time: t.scheduled_time ?? '', estimated_minutes: t.estimated_minutes ?? '', notes: t.notes ?? '' });
};
const closeModal = () => { showCreate.value = false; editingTask.value = null; form.reset(); };

const submitForm = () => {
    if (editingTask.value) {
        form.patch(route('tasks.update', [props.project.id, editingTask.value.id]), { onSuccess: closeModal });
    } else {
        form.post(route('tasks.store', props.project.id), { onSuccess: closeModal });
    }
};

const toggleDone = (t) => {
    const newStatus = t.status === 'done' ? 'pending' : 'done';
    router.patch(route('tasks.update', [props.project.id, t.id]), { status: newStatus }, { preserveScroll: true });
};
const deleteTask = (t) => router.delete(route('tasks.destroy', [props.project.id, t.id]), { preserveScroll: true });

const isOverdue = (d) => d && new Date(d) < new Date(new Date().toDateString());
const formatDate = (d) => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
const statusLabel = (s) => ({ pending: 'Pending', in_progress: 'In Progress', done: 'Done', skipped: 'Skipped' }[s] || s);
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

.filters { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-btn {
    padding: 5px 14px; border-radius: 20px; border: 1px solid #2a2d3e;
    background: transparent; color: #64748b; font-size: 0.78rem;
    cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.filter-btn:hover { border-color: #6366f1; color: #a5b4fc; }
.filter-btn--active { border-color: #6366f1; background: rgba(99,102,241,0.12); color: #a5b4fc; }

.task-groups { display: flex; flex-direction: column; gap: 16px; }
.group-title { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.1em; margin: 0 0 8px; }
.gp--urgent { color: #f87171; }
.gp--high   { color: #fbbf24; }
.gp--medium { color: #a5b4fc; }
.gp--low    { color: #64748b; }

.task-list { display: flex; flex-direction: column; gap: 6px; }

.task-row {
    display: flex; align-items: center; gap: 12px;
    background: #0f1117; border: 1px solid #1e2130; border-radius: 9px;
    padding: 10px 14px; transition: border-color 0.15s;
}
.task-row:hover { border-color: #2a2d3e; }
.task-row--done { opacity: 0.5; }

.check-btn {
    width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid #2a2d3e; background: transparent; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: transparent; transition: all 0.15s;
}
.check-btn:hover { border-color: #6366f1; }
.check-btn--done { background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color: transparent; color: white; }

.task-row__body { flex: 1; min-width: 0; }
.task-row__title { font-size: 0.87rem; font-weight: 500; color: #e2e8f0; margin: 0 0 4px; }
.task-row__meta  { display: flex; gap: 8px; flex-wrap: wrap; }
.meta-chip { font-size: 0.7rem; padding: 1px 7px; border-radius: 3px; }
.meta-chip--goal    { color: #8b5cf6; background: rgba(139,92,246,0.1); }
.meta-chip--date    { color: #64748b; background: rgba(100,116,139,0.1); }
.meta-chip--overdue { color: #f87171; background: rgba(239,68,68,0.1); }
.meta-chip--est     { color: #4a5568; }
.meta-chip--sub     { color: #10b981; background: rgba(16,185,129,0.1); }

.task-row__actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.status-badge { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 2px 7px; border-radius: 3px; }
.st--pending     { background: rgba(100,116,139,0.1); color: #94a3b8; }
.st--in_progress { background: rgba(245,158,11,0.1);  color: #fbbf24; }
.st--done        { background: rgba(16,185,129,0.1);  color: #34d399; }
.st--skipped     { background: rgba(71,85,105,0.1);   color: #64748b; }

.icon-btn { background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 2px; opacity: 0; transition: opacity 0.15s; }
.task-row:hover .icon-btn { opacity: 1; }

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
    padding: 28px; width: 100%; max-width: 500px;
    display: flex; flex-direction: column; gap: 14px;
    max-height: 90vh; overflow-y: auto;
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
.field__select { appearance: none; cursor: pointer; }
.field__textarea { resize: vertical; }
.field__error { font-size: 0.75rem; color: #f87171; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; text-align: center; }
.empty-state__icon  { font-size: 3rem; }
.empty-state__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.empty-state__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

.spinner {
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
