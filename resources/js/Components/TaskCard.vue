<template>
    <div class="task-card" :class="[`priority--${task.priority}`, { 'task-card--done': task.status === 'done' }]">
        <div class="task-card__left">
            <button
                v-if="!readonly"
                class="task-card__check"
                :class="{ 'task-card__check--done': task.status === 'done' }"
                @click="$emit('complete')"
                :title="task.status === 'done' ? 'Completed' : 'Mark done'"
            >
                <svg v-if="task.status === 'done'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            <div v-else class="task-card__check task-card__check--done">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
        </div>

        <div class="task-card__body">
            <span class="task-card__title">{{ task.title }}</span>
            <div class="task-card__meta">
                <span v-if="task.goal" class="task-card__goal">🎯 {{ task.goal.title }}</span>
                <span v-if="task.scheduled_time" class="task-card__time">🕐 {{ task.scheduled_time }}</span>
                <span v-if="task.estimated_minutes" class="task-card__est">⏱ {{ task.estimated_minutes }}m</span>
            </div>
        </div>

        <div class="task-card__right">
            <span class="priority-badge" :class="`priority-badge--${task.priority}`">{{ task.priority }}</span>
            <button v-if="!readonly && task.status !== 'done'" class="skip-btn" @click="$emit('skip')" title="Skip">–</button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    task:     { type: Object, required: true },
    project:  Object,
    readonly: { type: Boolean, default: false },
});
defineEmits(['complete', 'skip']);
</script>

<style scoped>
.task-card {
    display: flex; align-items: center; gap: 12px;
    background: #0f1117; border: 1px solid #1e2130;
    border-radius: 10px; padding: 12px 14px;
    transition: border-color 0.15s, opacity 0.15s;
    border-left: 3px solid transparent;
}
.task-card:hover { border-color: #2a2d3e; }
.task-card--done { opacity: 0.55; }

.priority--urgent { border-left-color: #ef4444; }
.priority--high   { border-left-color: #f59e0b; }
.priority--medium { border-left-color: #6366f1; }
.priority--low    { border-left-color: #475569; }

.task-card__left { flex-shrink: 0; }
.task-card__check {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid #2a2d3e; background: transparent;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: transparent; transition: all 0.15s;
}
.task-card__check:hover { border-color: #6366f1; }
.task-card__check--done { background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color: transparent; color: white; }

.task-card__body { flex: 1; min-width: 0; }
.task-card__title { font-size: 0.88rem; font-weight: 500; color: #e2e8f0; display: block; }
.task-card__meta  { display: flex; gap: 10px; margin-top: 3px; flex-wrap: wrap; }
.task-card__goal  { font-size: 0.72rem; color: #8b5cf6; }
.task-card__time  { font-size: 0.72rem; color: #64748b; }
.task-card__est   { font-size: 0.72rem; color: #4a5568; }

.task-card__right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.priority-badge {
    font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
    padding: 2px 7px; border-radius: 4px; letter-spacing: 0.03em;
}
.priority-badge--urgent { background: rgba(239,68,68,0.15); color: #f87171; }
.priority-badge--high   { background: rgba(245,158,11,0.15); color: #fbbf24; }
.priority-badge--medium { background: rgba(99,102,241,0.15); color: #a5b4fc; }
.priority-badge--low    { background: rgba(71,85,105,0.15);  color: #94a3b8; }

.skip-btn {
    width: 20px; height: 20px; border-radius: 4px;
    border: 1px solid #2a2d3e; background: transparent;
    color: #4a5568; font-size: 1rem; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.skip-btn:hover { border-color: #ef4444; color: #f87171; }
</style>
