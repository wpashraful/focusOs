<template>
    <AppLayout title="Dashboard">
        <div class="dashboard">

            <!-- Phase Goal Banner -->
            <div class="phase-banner" v-if="currentProject">
                <div class="phase-banner__inner">
                    <div>
                        <span class="phase-banner__phase">{{ currentProject.current_phase_name ?? 'Phase 1' }}</span>
                        <p class="phase-banner__goal">{{ currentProject.current_phase_goal ?? 'Set your phase goal in Project Settings' }}</p>
                    </div>
                    <div class="phase-banner__meta" v-if="currentProject.phase_ends_at">
                        <span class="phase-banner__label">Ends</span>
                        <span class="phase-banner__date">{{ formatDate(currentProject.phase_ends_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <StatCard icon="✅" label="Tasks Done Today" :value="stats.tasks_done_today" color="green" />
                <StatCard icon="📧" label="Emails Sent" :value="stats.emails_sent" suffix="/100" color="indigo" />
                <StatCard icon="🔗" label="LinkedIn Done" :value="stats.linkedin_done" suffix="/30" color="purple" />
                <StatCard icon="🎥" label="Content Today" :value="stats.content_today" suffix="/1" color="orange" />
            </div>

            <!-- Main Grid -->
            <div class="dashboard-grid">

                <!-- Today's Top Tasks -->
                <div class="card">
                    <div class="card__header">
                        <h2 class="card__title">📌 Top Tasks Today</h2>
                        <Link :href="route('tasks.today')" class="card__link">View all →</Link>
                    </div>
                    <div class="task-list" v-if="todaysTasks.length">
                        <div v-for="task in todaysTasks" :key="task.id" class="task-item">
                            <div class="task-item__dot" :class="`task-item__dot--${task.status}`" />
                            <span class="task-item__title">{{ task.title }}</span>
                            <span class="task-item__priority" :class="`priority--${task.priority}`">{{ task.priority }}</span>
                        </div>
                    </div>
                    <p class="empty-state" v-else>No tasks for today. Add some! 🎯</p>
                </div>

                <!-- Routine Timeline -->
                <div class="card">
                    <div class="card__header">
                        <h2 class="card__title">🕐 Today's Routine</h2>
                        <Link :href="route('routine.edit')" class="card__link">Edit →</Link>
                    </div>
                    <div class="routine-timeline" v-if="routineSlots.length">
                        <div v-for="slot in routineSlots" :key="slot.id"
                             class="routine-slot"
                             :class="{ 'routine-slot--active': isCurrentSlot(slot) }">
                            <span class="routine-slot__time">{{ slot.start_time }} – {{ slot.end_time }}</span>
                            <span class="routine-slot__label">{{ slot.label }}</span>
                            <span v-if="isCurrentSlot(slot)" class="routine-slot__now">NOW</span>
                        </div>
                    </div>
                    <p class="empty-state" v-else>No routine set. <Link :href="route('routine.edit')" class="link">Set one up →</Link></p>
                </div>

            </div>

            <!-- AI Quick Action -->
            <div class="ai-prompt-bar">
                <Link :href="route('chat.index')" class="ai-prompt-bar__inner">
                    <span class="ai-prompt-bar__icon">🤖</span>
                    <span class="ai-prompt-bar__text">Ask your AI Coach — "এখন কী করবো?"</span>
                    <span class="ai-prompt-bar__cta">Open Chat →</span>
                </Link>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    currentProject: Object,
    todaysTasks: { type: Array, default: () => [] },
    routineSlots: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            tasks_done_today: 0,
            emails_sent: 0,
            linkedin_done: 0,
            content_today: 0,
        }),
    },
});

const formatDate = (date) => new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

const isCurrentSlot = (slot) => {
    const now = new Date();
    const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
    const cur = now.getHours() * 60 + now.getMinutes();
    return cur >= toMin(slot.start_time) && cur < toMin(slot.end_time);
};
</script>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: 20px; }

/* Phase Banner */
.phase-banner {
    background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.08));
    border: 1px solid rgba(99,102,241,0.25);
    border-radius: 12px;
    padding: 16px 20px;
}
.phase-banner__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.phase-banner__phase {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: #6366f1; margin-bottom: 4px; display: block;
}
.phase-banner__goal { font-size: 0.95rem; font-weight: 500; color: #c4b5fd; margin: 0; }
.phase-banner__meta { text-align: right; flex-shrink: 0; }
.phase-banner__label { display: block; font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
.phase-banner__date { font-size: 0.85rem; font-weight: 600; color: #a5b4fc; }

/* Stats Row */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }

/* Card */
.card {
    background: #0f1117;
    border: 1px solid #1e2130;
    border-radius: 12px;
    padding: 18px 20px;
}
.card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.card__title { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.card__link { font-size: 0.75rem; color: #6366f1; text-decoration: none; transition: color 0.15s; }
.card__link:hover { color: #a5b4fc; }

/* Dashboard Grid */
.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 800px) { .dashboard-grid { grid-template-columns: 1fr; } }

/* Task List */
.task-list { display: flex; flex-direction: column; gap: 8px; }
.task-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px solid #1a1d2a; }
.task-item:last-child { border-bottom: none; }
.task-item__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.task-item__dot--pending { background: #f59e0b; }
.task-item__dot--in_progress { background: #6366f1; }
.task-item__dot--done { background: #10b981; }
.task-item__title { flex: 1; font-size: 0.83rem; color: #cbd5e1; }
.task-item__priority { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 1px 6px; border-radius: 4px; }
.priority--high { background: rgba(239,68,68,0.15); color: #f87171; }
.priority--medium { background: rgba(245,158,11,0.15); color: #fbbf24; }
.priority--low { background: rgba(100,116,139,0.15); color: #94a3b8; }

/* Routine Timeline */
.routine-timeline { display: flex; flex-direction: column; gap: 6px; }
.routine-slot { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 7px; transition: background 0.15s; }
.routine-slot--active { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2); }
.routine-slot__time { font-size: 0.72rem; color: #64748b; white-space: nowrap; min-width: 100px; }
.routine-slot__label { flex: 1; font-size: 0.83rem; color: #cbd5e1; }
.routine-slot__now { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; padding: 1px 6px; border-radius: 4px; background: rgba(99,102,241,0.2); color: #a5b4fc; letter-spacing: 0.05em; }

/* AI Prompt Bar */
.ai-prompt-bar { border-radius: 12px; overflow: hidden; }
.ai-prompt-bar__inner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 20px;
    background: linear-gradient(135deg, #1a1d2e, #151824);
    border: 1px solid #2a2d45;
    border-radius: 12px;
    text-decoration: none;
    transition: border-color 0.2s, background 0.2s;
    cursor: pointer;
}
.ai-prompt-bar__inner:hover { border-color: rgba(99,102,241,0.4); background: rgba(99,102,241,0.06); }
.ai-prompt-bar__icon { font-size: 1.4rem; }
.ai-prompt-bar__text { flex: 1; font-size: 0.88rem; color: #64748b; }
.ai-prompt-bar__cta { font-size: 0.8rem; font-weight: 600; color: #6366f1; white-space: nowrap; }

/* Misc */
.empty-state { font-size: 0.82rem; color: #4a5568; text-align: center; padding: 20px 0; }
.link { color: #6366f1; text-decoration: none; }
.link:hover { color: #a5b4fc; }
</style>
