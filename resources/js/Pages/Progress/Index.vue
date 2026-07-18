<template>
    <AppLayout title="Progress">
        <div class="progress-page">

            <!-- No Project -->
            <div v-if="!project" class="no-project">
                <div class="no-project__icon">📊</div>
                <h2 class="no-project__title">No Active Project</h2>
                <p class="no-project__sub">Create a project to start tracking your progress.</p>
                <Link :href="route('projects.index')" class="btn-primary">Go to Projects →</Link>
            </div>

            <template v-else>
                <!-- Header -->
                <div class="page-header">
                    <div>
                        <h1 class="page-header__title">Progress & Review</h1>
                        <p class="page-header__sub">{{ project.name }}</p>
                    </div>
                    <div class="date-badge">{{ todayFormatted }}</div>
                </div>

                <!-- Daily Score Row -->
                <div class="score-row">
                    <!-- Big Score -->
                    <div class="score-card score-card--main">
                        <div class="score-ring-wrap">
                            <svg class="score-ring" viewBox="0 0 120 120">
                                <circle class="score-ring__bg" cx="60" cy="60" r="50" />
                                <circle class="score-ring__fill"
                                    cx="60" cy="60" r="50"
                                    :stroke-dasharray="`${dailyScore * 3.14} 314`"
                                    :class="scoreColor" />
                            </svg>
                            <div class="score-ring__label">
                                <span class="score-ring__num">{{ dailyScore }}%</span>
                                <span class="score-ring__sub">Today</span>
                            </div>
                        </div>
                        <div class="score-card__info">
                            <h3 class="score-card__title">Daily Focus Score</h3>
                            <p class="score-card__detail">{{ todayDone }} of {{ todayTotal }} tasks completed</p>
                            <div class="score-bar">
                                <div class="score-bar__fill" :style="{ width: dailyScore + '%' }" :class="scoreColor" />
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="quick-stats">
                        <div class="quick-stat">
                            <span class="quick-stat__num">{{ todayDone }}</span>
                            <span class="quick-stat__label">Done Today</span>
                        </div>
                        <div class="quick-stat">
                            <span class="quick-stat__num">{{ todayTotal - todayDone }}</span>
                            <span class="quick-stat__label">Remaining</span>
                        </div>
                        <div class="quick-stat">
                            <span class="quick-stat__num">{{ goalProgress.length }}</span>
                            <span class="quick-stat__label">Active Goals</span>
                        </div>
                        <div class="quick-stat">
                            <span class="quick-stat__num">{{ avgWeekly }}%</span>
                            <span class="quick-stat__label">Avg This Week</span>
                        </div>
                    </div>
                </div>

                <!-- Weekly Bar Chart -->
                <div class="card">
                    <div class="card__header">
                        <h2 class="card__title">📅 Weekly Completion</h2>
                    </div>
                    <div class="weekly-chart">
                        <div v-for="day in weeklyStats" :key="day.date" class="weekly-bar">
                            <div class="weekly-bar__track">
                                <div class="weekly-bar__fill"
                                    :style="{ height: day.percent + '%' }"
                                    :class="day.percent >= 75 ? 'bar--green' : day.percent >= 40 ? 'bar--yellow' : 'bar--red'" />
                            </div>
                            <div class="weekly-bar__label">{{ day.label }}</div>
                            <div class="weekly-bar__pct">{{ day.percent }}%</div>
                        </div>
                    </div>
                </div>

                <!-- Goal Progress -->
                <div class="card" v-if="goalProgress.length">
                    <div class="card__header">
                        <h2 class="card__title">🎯 Goal Progress</h2>
                    </div>
                    <div class="goal-list">
                        <div v-for="goal in goalProgress" :key="goal.id" class="goal-row">
                            <div class="goal-row__header">
                                <span class="goal-row__title">{{ goal.title }}</span>
                                <span class="goal-row__meta">{{ goal.done }}/{{ goal.total }} tasks</span>
                            </div>
                            <div class="goal-progress-bar">
                                <div class="goal-progress-bar__fill"
                                    :style="{ width: goal.percent + '%' }"
                                    :class="goal.percent >= 75 ? 'bar--green' : goal.percent >= 40 ? 'bar--indigo' : 'bar--dim'" />
                            </div>
                            <span class="goal-pct">{{ goal.percent }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Daily Metric Logs -->
                <div class="card" v-if="metricLogs.length">
                    <div class="card__header">
                        <h2 class="card__title">📈 Daily Metrics (7 Days)</h2>
                    </div>
                    <div class="metrics-grid">
                        <div v-for="metric in metricLogs" :key="metric.label" class="metric-card">
                            <div class="metric-card__header">
                                <span class="metric-card__label">{{ metric.label }}</span>
                                <span class="metric-card__target">Target: {{ metric.target_count }} {{ metric.unit }}</span>
                            </div>
                            <div class="metric-sparkline">
                                <div v-for="log in metric.logs" :key="log.date" class="metric-bar-wrap">
                                    <div class="metric-bar__track">
                                        <div class="metric-bar__fill"
                                            :style="{ height: Math.min((log.achieved / metric.target_count) * 100, 100) + '%' }" />
                                    </div>
                                    <span class="metric-bar__val">{{ log.achieved }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project:      Object,
    dailyScore:   Number,
    todayDone:    Number,
    todayTotal:   Number,
    weeklyStats:  Array,
    goalProgress: Array,
    metricLogs:   Array,
});

const todayFormatted = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

const scoreColor = computed(() => {
    if (props.dailyScore >= 75) return 'score--green';
    if (props.dailyScore >= 40) return 'score--yellow';
    return 'score--red';
});

const avgWeekly = computed(() => {
    if (!props.weeklyStats?.length) return 0;
    const sum = props.weeklyStats.reduce((a, d) => a + d.percent, 0);
    return Math.round(sum / props.weeklyStats.length);
});
</script>

<style scoped>
.progress-page { display: flex; flex-direction: column; gap: 24px; }

/* No project */
.no-project { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 50vh; gap: 12px; text-align: center; }
.no-project__icon { font-size: 3rem; }
.no-project__title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.no-project__sub { font-size: 0.88rem; color: #64748b; margin: 0; }
.btn-primary { display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-top: 8px; }

/* Page header */
.page-header { display: flex; align-items: center; justify-content: space-between; }
.page-header__title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.page-header__sub { font-size: 0.82rem; color: #64748b; margin: 4px 0 0; }
.date-badge { padding: 6px 14px; background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.25); border-radius: 20px; font-size: 0.78rem; font-weight: 500; color: #a5b4fc; }

/* Score Row */
.score-row { display: grid; grid-template-columns: 1fr auto; gap: 20px; }
.score-card--main { background: #131620; border: 1px solid #1e2130; border-radius: 16px; padding: 24px; display: flex; gap: 24px; align-items: center; }
.score-ring-wrap { position: relative; width: 120px; height: 120px; flex-shrink: 0; }
.score-ring { transform: rotate(-90deg); width: 120px; height: 120px; }
.score-ring__bg { fill: none; stroke: #1e2130; stroke-width: 10; }
.score-ring__fill { fill: none; stroke-width: 10; stroke-linecap: round; transition: stroke-dasharray 0.8s ease; }
.score-ring__fill.score--green { stroke: #10b981; }
.score-ring__fill.score--yellow { stroke: #f59e0b; }
.score-ring__fill.score--red { stroke: #ef4444; }
.score-ring__label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.score-ring__num { font-size: 1.4rem; font-weight: 800; color: #e2e8f0; }
.score-ring__sub { font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.score-card__title { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0 0 6px; }
.score-card__detail { font-size: 0.82rem; color: #64748b; margin: 0 0 12px; }
.score-bar { height: 6px; background: #1e2130; border-radius: 999px; overflow: hidden; }
.score-bar__fill { height: 100%; border-radius: 999px; transition: width 0.8s ease; }
.score-bar__fill.score--green { background: #10b981; }
.score-bar__fill.score--yellow { background: #f59e0b; }
.score-bar__fill.score--red { background: #ef4444; }

/* Quick Stats */
.quick-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-stat { background: #131620; border: 1px solid #1e2130; border-radius: 12px; padding: 16px; text-align: center; }
.quick-stat__num { display: block; font-size: 1.6rem; font-weight: 800; color: #a5b4fc; }
.quick-stat__label { display: block; font-size: 0.7rem; font-weight: 600; color: #4a5568; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 4px; }

/* Card */
.card { background: #131620; border: 1px solid #1e2130; border-radius: 16px; padding: 20px; }
.card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.card__title { font-size: 0.95rem; font-weight: 700; color: #e2e8f0; margin: 0; }

/* Weekly Chart */
.weekly-chart { display: flex; gap: 12px; align-items: flex-end; height: 140px; }
.weekly-bar { display: flex; flex-direction: column; align-items: center; flex: 1; gap: 4px; height: 100%; }
.weekly-bar__track { flex: 1; width: 100%; background: #1e2130; border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end; }
.weekly-bar__fill { width: 100%; border-radius: 6px; transition: height 0.8s ease; min-height: 4px; }
.bar--green { background: linear-gradient(180deg, #10b981, #059669); }
.bar--yellow { background: linear-gradient(180deg, #f59e0b, #d97706); }
.bar--red { background: linear-gradient(180deg, #ef4444, #dc2626); }
.bar--indigo { background: linear-gradient(180deg, #6366f1, #4f46e5); }
.bar--dim { background: #2d3148; }
.weekly-bar__label { font-size: 0.7rem; color: #64748b; font-weight: 600; }
.weekly-bar__pct { font-size: 0.65rem; color: #4a5568; }

/* Goal Progress */
.goal-list { display: flex; flex-direction: column; gap: 16px; }
.goal-row { display: flex; flex-direction: column; gap: 6px; }
.goal-row__header { display: flex; align-items: center; justify-content: space-between; }
.goal-row__title { font-size: 0.85rem; font-weight: 600; color: #c4b5fd; }
.goal-row__meta { font-size: 0.75rem; color: #64748b; }
.goal-progress-bar { height: 8px; background: #1e2130; border-radius: 999px; overflow: hidden; }
.goal-progress-bar__fill { height: 100%; border-radius: 999px; transition: width 0.8s ease; }
.goal-pct { font-size: 0.72rem; color: #4a5568; align-self: flex-end; }

/* Metrics Grid */
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.metric-card { background: #0f1117; border: 1px solid #1e2130; border-radius: 12px; padding: 14px; }
.metric-card__header { display: flex; flex-direction: column; gap: 2px; margin-bottom: 12px; }
.metric-card__label { font-size: 0.82rem; font-weight: 600; color: #e2e8f0; }
.metric-card__target { font-size: 0.7rem; color: #64748b; }
.metric-sparkline { display: flex; gap: 6px; align-items: flex-end; height: 60px; }
.metric-bar-wrap { display: flex; flex-direction: column; align-items: center; flex: 1; gap: 3px; height: 100%; }
.metric-bar__track { flex: 1; width: 100%; background: #1e2130; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end; }
.metric-bar__fill { width: 100%; border-radius: 4px; background: linear-gradient(180deg, #6366f1, #4f46e5); transition: height 0.6s ease; min-height: 3px; }
.metric-bar__val { font-size: 0.6rem; color: #4a5568; }

/* Responsive */
@media (max-width: 768px) {
    .score-row { grid-template-columns: 1fr; }
    .quick-stats { grid-template-columns: repeat(4, 1fr); }
    .weekly-chart { height: 100px; }
}
@media (max-width: 480px) {
    .quick-stats { grid-template-columns: 1fr 1fr; }
    .metrics-grid { grid-template-columns: 1fr; }
}
</style>
