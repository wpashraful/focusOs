<template>
    <AppLayout title="Today's Tasks">
        <div class="page">

            <!-- Date header -->
            <div class="today-header">
                <div>
                    <h2 class="today-header__title">Today's Focus</h2>
                    <p class="today-header__date">{{ formattedDate }}</p>
                </div>
                <div class="today-stats">
                    <div class="stat">
                        <span class="stat__val">{{ doneTasks.length }}</span>
                        <span class="stat__label">Done</span>
                    </div>
                    <div class="stat-sep">/</div>
                    <div class="stat">
                        <span class="stat__val">{{ tasks.length }}</span>
                        <span class="stat__label">Total</span>
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="progress-bar-wrap" v-if="tasks.length">
                <div class="progress-bar">
                    <div class="progress-bar__fill" :style="{ width: progressPct + '%' }" />
                </div>
                <span class="progress-bar__label">{{ progressPct }}% complete</span>
            </div>

            <!-- No project -->
            <div class="empty-state" v-if="!project">
                <div class="empty-state__icon">📁</div>
                <p class="empty-state__title">No active project</p>
                <p class="empty-state__sub">Create a workspace and project to start tracking tasks.</p>
                <Link :href="route('workspaces.index')" class="btn-primary">Go to Workspaces →</Link>
            </div>

            <!-- Task list -->
            <div v-else>
                <!-- Pending Tasks -->
                <div class="section" v-if="pendingTasks.length">
                    <h3 class="section__title">⏳ Pending</h3>
                    <div class="task-list">
                        <TaskCard
                            v-for="task in pendingTasks"
                            :key="task.id"
                            :task="task"
                            :project="project"
                            @complete="completeTask(task)"
                            @skip="skipTask(task)"
                        />
                    </div>
                </div>

                <!-- Done Tasks -->
                <div class="section" v-if="doneTasks.length">
                    <h3 class="section__title">✅ Completed</h3>
                    <div class="task-list task-list--done">
                        <TaskCard
                            v-for="task in doneTasks"
                            :key="task.id"
                            :task="task"
                            :project="project"
                            :readonly="true"
                        />
                    </div>
                </div>

                <!-- Empty -->
                <div class="empty-state" v-if="!tasks.length">
                    <div class="empty-state__icon">🎯</div>
                    <p class="empty-state__title">No tasks scheduled for today</p>
                    <p class="empty-state__sub">Add tasks from your project and set a due date to today.</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskCard from '@/Components/TaskCard.vue';

const props = defineProps({
    project: Object,
    tasks:   { type: Array, default: () => [] },
    date:    String,
});

const formattedDate = computed(() =>
    new Date(props.date + 'T00:00:00').toLocaleDateString('en-GB', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    })
);

const pendingTasks = computed(() => props.tasks.filter(t => t.status !== 'done' && t.status !== 'skipped'));
const doneTasks    = computed(() => props.tasks.filter(t => t.status === 'done'));
const progressPct  = computed(() => {
    if (!props.tasks.length) return 0;
    return Math.round((doneTasks.value.length / props.tasks.length) * 100);
});

const completeTask = (task) => {
    router.patch(route('tasks.update', [props.project.id, task.id]), { status: 'done' }, { preserveScroll: true });
};
const skipTask = (task) => {
    router.patch(route('tasks.update', [props.project.id, task.id]), { status: 'skipped' }, { preserveScroll: true });
};
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }

.today-header { display: flex; align-items: center; justify-content: space-between; }
.today-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 2px; }
.today-header__date  { font-size: 0.82rem; color: #64748b; margin: 0; }

.today-stats { display: flex; align-items: center; gap: 10px; }
.stat { display: flex; flex-direction: column; align-items: center; }
.stat__val   { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; line-height: 1; }
.stat__label { font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.stat-sep    { font-size: 1.2rem; color: #2a2d3e; margin-bottom: 12px; }

.progress-bar-wrap { display: flex; align-items: center; gap: 12px; }
.progress-bar { flex: 1; height: 6px; background: #1e2130; border-radius: 3px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 3px; transition: width 0.4s ease; }
.progress-bar__label { font-size: 0.75rem; color: #64748b; white-space: nowrap; }

.section { display: flex; flex-direction: column; gap: 10px; }
.section__title { font-size: 0.82rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
.task-list { display: flex; flex-direction: column; gap: 8px; }
.task-list--done { opacity: 0.5; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; text-align: center; }
.empty-state__icon  { font-size: 3rem; }
.empty-state__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.empty-state__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

.btn-primary {
    display: inline-flex; align-items: center;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px; padding: 9px 18px;
    font-size: 0.85rem; font-weight: 600; color: white;
    text-decoration: none; transition: opacity 0.2s;
}
.btn-primary:hover { opacity: 0.9; }
</style>
