<template>
    <AppLayout :title="`${project.name} — Routine`">
        <div class="page">

            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Daily Routine</h2>
                    <p class="page-header__sub">{{ routine.name }} · {{ routine.slots.length }} time blocks</p>
                </div>
                <button class="btn-primary" @click="showAdd = true">+ Add Slot</button>
            </div>

            <!-- Timeline View -->
            <div class="timeline-wrap" v-if="routine.slots.length">
                <div class="timeline">
                    <div
                        v-for="slot in routine.slots"
                        :key="slot.id"
                        class="timeline-slot"
                        :class="[`cat--${slot.category}`, { 'timeline-slot--active': isActive(slot) }]"
                    >
                        <div class="timeline-slot__time">
                            <span>{{ slot.start_time }}</span>
                            <div class="timeline-slot__line" />
                            <span>{{ slot.end_time }}</span>
                        </div>
                        <div class="timeline-slot__card">
                            <div class="timeline-slot__top">
                                <span class="timeline-slot__label">{{ slot.label }}</span>
                                <span v-if="isActive(slot)" class="now-badge">NOW</span>
                                <span class="cat-badge" :class="`cat-badge--${slot.category}`">{{ slot.category }}</span>
                            </div>
                            <span class="timeline-slot__duration">{{ duration(slot) }} min</span>
                            <div class="timeline-slot__actions">
                                <button class="icon-action" @click="deleteSlot(slot)" title="Delete">🗑️</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty -->
            <div class="empty-state" v-else>
                <div class="empty-state__icon">🕐</div>
                <p class="empty-state__title">No time blocks yet</p>
                <p class="empty-state__sub">Build your daily routine by adding time slots.</p>
            </div>

            <!-- Add Slot Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showAdd" @click.self="showAdd = false">
                    <div class="modal">
                        <h3 class="modal__title">Add Time Slot</h3>
                        <form @submit.prevent="submitSlot">
                            <div class="form-row">
                                <div class="field">
                                    <label class="field__label">Start Time</label>
                                    <input v-model="form.start_time" type="time" class="field__input" required />
                                    <span v-if="form.errors.start_time" class="field__error">{{ form.errors.start_time }}</span>
                                </div>
                                <div class="field">
                                    <label class="field__label">End Time</label>
                                    <input v-model="form.end_time" type="time" class="field__input" required />
                                    <span v-if="form.errors.end_time" class="field__error">{{ form.errors.end_time }}</span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field__label">Label</label>
                                <input v-model="form.label" class="field__input" placeholder="e.g. Deep Work — Email Campaign" required />
                                <span v-if="form.errors.label" class="field__error">{{ form.errors.label }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Category</label>
                                <div class="cat-options">
                                    <button
                                        v-for="c in categories" :key="c.value"
                                        type="button"
                                        class="cat-option"
                                        :class="{ 'cat-option--active': form.category === c.value }"
                                        @click="form.category = c.value"
                                    >{{ c.label }}</button>
                                </div>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="showAdd = false; form.reset()">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner" />
                                    Add Slot
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
    routine: Object,
});

const showAdd = ref(false);
const form = useForm({ start_time: '', end_time: '', label: '', category: 'work' });

const categories = [
    { value: 'work',     label: '💼 Work' },
    { value: 'break',    label: '☕ Break' },
    { value: 'exercise', label: '🏃 Exercise' },
    { value: 'learning', label: '📚 Learning' },
    { value: 'personal', label: '🧘 Personal' },
    { value: 'other',    label: '📌 Other' },
];

const submitSlot = () => {
    form.post(route('routine-slots.store', [props.project.id, props.routine.id]), {
        onSuccess: () => { showAdd.value = false; form.reset(); },
    });
};

const deleteSlot = (slot) => {
    router.delete(route('routine-slots.destroy', [props.project.id, props.routine.id, slot.id]), {
        preserveScroll: true,
    });
};

const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const now = new Date();
const curMin = now.getHours() * 60 + now.getMinutes();
const isActive = (slot) => curMin >= toMin(slot.start_time) && curMin < toMin(slot.end_time);
const duration = (slot) => toMin(slot.end_time) - toMin(slot.start_time);
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

/* Timeline */
.timeline-wrap { overflow-x: auto; }
.timeline { display: flex; flex-direction: column; gap: 2px; }

.timeline-slot { display: flex; gap: 12px; align-items: stretch; }
.timeline-slot__time {
    display: flex; flex-direction: column; align-items: flex-end;
    min-width: 48px; font-size: 0.68rem; color: #4a5568;
    padding: 6px 0; gap: 4px;
}
.timeline-slot__line { flex: 1; width: 1px; background: #1e2130; align-self: center; margin: 0 auto; }

.timeline-slot__card {
    flex: 1; background: #0f1117; border: 1px solid #1e2130;
    border-radius: 8px; padding: 10px 14px;
    display: flex; flex-direction: column; gap: 4px;
    transition: border-color 0.15s;
    border-left: 3px solid #2a2d3e;
}
.timeline-slot--active .timeline-slot__card {
    border-left-color: #6366f1;
    background: rgba(99,102,241,0.05);
}

/* Category colors */
.cat--work     .timeline-slot__card { border-left-color: #6366f1; }
.cat--break    .timeline-slot__card { border-left-color: #10b981; }
.cat--exercise .timeline-slot__card { border-left-color: #f59e0b; }
.cat--learning .timeline-slot__card { border-left-color: #3b82f6; }
.cat--personal .timeline-slot__card { border-left-color: #8b5cf6; }
.cat--other    .timeline-slot__card { border-left-color: #64748b; }

.timeline-slot__top  { display: flex; align-items: center; gap: 8px; }
.timeline-slot__label { font-size: 0.88rem; font-weight: 500; color: #e2e8f0; flex: 1; }
.timeline-slot__duration { font-size: 0.72rem; color: #4a5568; }
.timeline-slot__actions { display: flex; justify-content: flex-end; }

.now-badge {
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
    padding: 1px 6px; border-radius: 3px;
    background: rgba(99,102,241,0.2); color: #a5b4fc;
}
.cat-badge {
    font-size: 0.62rem; padding: 1px 6px; border-radius: 3px; text-transform: capitalize;
}
.cat-badge--work     { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.cat-badge--break    { background: rgba(16,185,129,0.12); color: #34d399; }
.cat-badge--exercise { background: rgba(245,158,11,0.12); color: #fbbf24; }
.cat-badge--learning { background: rgba(59,130,246,0.12); color: #93c5fd; }
.cat-badge--personal { background: rgba(139,92,246,0.12); color: #c4b5fd; }
.cat-badge--other    { background: rgba(100,116,139,0.12); color: #94a3b8; }

/* Buttons */
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

.icon-action { background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 2px; opacity: 0; transition: opacity 0.15s; }
.timeline-slot:hover .icon-action { opacity: 1; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 100; padding: 20px;
}
.modal {
    background: #0f1117; border: 1px solid #2a2d3e; border-radius: 14px;
    padding: 28px; width: 100%; max-width: 440px;
    display: flex; flex-direction: column; gap: 16px;
}
.modal__title   { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 10px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; transition: border-color 0.2s;
}
.field__input:focus { border-color: #6366f1; }
.field__error { font-size: 0.75rem; color: #f87171; }

.cat-options { display: flex; flex-wrap: wrap; gap: 6px; }
.cat-option {
    padding: 5px 12px; border-radius: 6px;
    border: 1px solid #2a2d3e; background: #141620;
    font-size: 0.78rem; color: #64748b; cursor: pointer;
    transition: all 0.15s; font-family: inherit;
}
.cat-option:hover { border-color: #6366f1; color: #a5b4fc; }
.cat-option--active { border-color: #6366f1; background: rgba(99,102,241,0.12); color: #a5b4fc; }

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
