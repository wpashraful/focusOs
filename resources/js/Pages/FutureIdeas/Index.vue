<template>
    <AppLayout title="Future Ideas">
        <div class="ideas-page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header__title">💡 Future Ideas Board</h1>
                    <p class="page-header__sub" v-if="project">{{ project.name }}</p>
                </div>
                <button class="btn-add" @click="showForm = !showForm" id="add-idea-btn">
                    <span>{{ showForm ? '✕ Cancel' : '+ New Idea' }}</span>
                </button>
            </div>

            <!-- Add Idea Form -->
            <Transition name="slide">
                <div v-if="showForm" class="idea-form-card">
                    <h3 class="idea-form-card__title">Capture a New Idea</h3>
                    <form @submit.prevent="submitIdea" class="idea-form">
                        <input v-model="form.title" type="text" placeholder="What's the idea?" class="form-input" required id="idea-title" />
                        <textarea v-model="form.content" placeholder="Describe it (optional)..." class="form-textarea" rows="3" id="idea-content" />
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="save-idea-btn">Save Idea</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Status Tabs -->
            <div class="tabs">
                <button v-for="tab in tabs" :key="tab.key"
                    class="tab" :class="{ 'tab--active': activeTab === tab.key }"
                    @click="activeTab = tab.key"
                    :id="`tab-${tab.key}`">
                    {{ tab.label }}
                    <span class="tab__count">{{ countByStatus(tab.key) }}</span>
                </button>
            </div>

            <!-- Empty State -->
            <div v-if="!filteredIdeas.length" class="empty-state">
                <div class="empty-state__icon">🧠</div>
                <p class="empty-state__text">No {{ activeTab }} ideas yet.</p>
                <p class="empty-state__sub">You can also say "Save idea: [your idea]" to the AI Coach!</p>
            </div>

            <!-- Ideas Grid -->
            <div v-else class="ideas-grid">
                <div v-for="idea in filteredIdeas" :key="idea.id" class="idea-card" :class="`idea-card--${idea.status}`">
                    <div class="idea-card__top">
                        <span class="status-badge" :class="`status-badge--${idea.status}`">{{ idea.status }}</span>
                        <div class="idea-card__actions">
                            <button v-if="idea.status !== 'reviewed'" @click="changeStatus(idea, 'reviewed')" class="action-btn" title="Mark Reviewed" :id="`review-${idea.id}`">👁</button>
                            <button v-if="idea.status !== 'promoted'" @click="changeStatus(idea, 'promoted')" class="action-btn action-btn--promote" title="Promote to Task" :id="`promote-${idea.id}`">🚀</button>
                            <button @click="deleteIdea(idea)" class="action-btn action-btn--delete" title="Delete" :id="`delete-${idea.id}`">🗑</button>
                        </div>
                    </div>
                    <h3 class="idea-card__title">{{ idea.title }}</h3>
                    <p v-if="idea.content" class="idea-card__content">{{ idea.content }}</p>
                    <div class="idea-card__footer">
                        <span class="idea-card__date">{{ formatDate(idea.created_at) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    ideas:   { type: Array, default: () => [] },
    project: Object,
});

const showForm  = ref(false);
const activeTab = ref('all');

const form = useForm({ title: '', content: '' });

const tabs = [
    { key: 'all',      label: 'All Ideas' },
    { key: 'pending',  label: 'Pending' },
    { key: 'reviewed', label: 'Reviewed' },
    { key: 'promoted', label: 'Promoted' },
];

const filteredIdeas = computed(() => {
    if (activeTab.value === 'all') return props.ideas;
    return props.ideas.filter(i => i.status === activeTab.value);
});

const countByStatus = (tab) => {
    if (tab === 'all') return props.ideas.length;
    return props.ideas.filter(i => i.status === tab).length;
};

const formatDate = (d) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

const submitIdea = () => {
    form.post(route('ideas.store'), {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const changeStatus = (idea, status) => {
    router.patch(route('ideas.update', idea.id), { status }, { preserveScroll: true });
};

const deleteIdea = (idea) => {
    if (!confirm(`Delete "${idea.title}"?`)) return;
    router.delete(route('ideas.destroy', idea.id), { preserveScroll: true });
};
</script>

<style scoped>
.ideas-page { display: flex; flex-direction: column; gap: 20px; }

/* Header */
.page-header { display: flex; align-items: center; justify-content: space-between; }
.page-header__title { font-size: 1.4rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.page-header__sub { font-size: 0.82rem; color: #64748b; margin: 4px 0 0; }
.btn-add { padding: 9px 18px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; border-radius: 8px; font-size: 0.83rem; font-weight: 600; cursor: pointer; transition: opacity 0.15s; }
.btn-add:hover { opacity: 0.85; }

/* Form Card */
.idea-form-card { background: #131620; border: 1px solid rgba(99,102,241,0.3); border-radius: 16px; padding: 20px; }
.idea-form-card__title { font-size: 0.9rem; font-weight: 700; color: #e2e8f0; margin: 0 0 14px; }
.idea-form { display: flex; flex-direction: column; gap: 12px; }
.form-input { background: #0b0c10; border: 1px solid #1e2130; border-radius: 8px; padding: 10px 14px; color: #e2e8f0; font-size: 0.88rem; outline: none; transition: border-color 0.15s; }
.form-input:focus { border-color: #6366f1; }
.form-textarea { background: #0b0c10; border: 1px solid #1e2130; border-radius: 8px; padding: 10px 14px; color: #e2e8f0; font-size: 0.85rem; font-family: inherit; outline: none; resize: vertical; transition: border-color 0.15s; }
.form-textarea:focus { border-color: #6366f1; }
.form-actions { display: flex; justify-content: flex-end; }
.btn-primary { padding: 9px 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; border-radius: 8px; font-size: 0.83rem; font-weight: 600; cursor: pointer; }

/* Tabs */
.tabs { display: flex; gap: 8px; border-bottom: 1px solid #1e2130; padding-bottom: 1px; }
.tab { padding: 8px 16px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #64748b; font-size: 0.83rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s; margin-bottom: -1px; }
.tab:hover { color: #a5b4fc; }
.tab--active { color: #a5b4fc; border-bottom-color: #6366f1; }
.tab__count { background: #1e2130; border-radius: 999px; padding: 1px 7px; font-size: 0.68rem; }
.tab--active .tab__count { background: rgba(99,102,241,0.2); color: #a5b4fc; }

/* Empty State */
.empty-state { text-align: center; padding: 48px 20px; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: 8px; }
.empty-state__text { font-size: 0.9rem; font-weight: 600; color: #64748b; margin: 0; }
.empty-state__sub { font-size: 0.78rem; color: #374151; margin: 6px 0 0; }

/* Ideas Grid */
.ideas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.idea-card { background: #131620; border: 1px solid #1e2130; border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 10px; transition: border-color 0.2s, transform 0.15s; }
.idea-card:hover { border-color: rgba(99,102,241,0.3); transform: translateY(-2px); }
.idea-card--promoted { border-color: rgba(16,185,129,0.2); }
.idea-card--reviewed { border-color: rgba(99,102,241,0.15); }
.idea-card__top { display: flex; align-items: center; justify-content: space-between; }
.status-badge { padding: 2px 9px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
.status-badge--pending  { background: rgba(100,116,139,0.12); color: #94a3b8; }
.status-badge--reviewed { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.status-badge--promoted { background: rgba(16,185,129,0.12); color: #34d399; }
.idea-card__actions { display: flex; gap: 6px; }
.action-btn { background: transparent; border: 1px solid #1e2130; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.75rem; transition: all 0.15s; }
.action-btn:hover { border-color: #6366f1; background: rgba(99,102,241,0.1); }
.action-btn--promote:hover { border-color: #10b981; background: rgba(16,185,129,0.1); }
.action-btn--delete:hover { border-color: #ef4444; background: rgba(239,68,68,0.1); }
.idea-card__title { font-size: 0.92rem; font-weight: 700; color: #e2e8f0; margin: 0; line-height: 1.4; }
.idea-card__content { font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.6; }
.idea-card__footer { display: flex; justify-content: flex-end; }
.idea-card__date { font-size: 0.68rem; color: #374151; }

/* Animations */
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-10px); }

/* Responsive */
@media (max-width: 600px) {
    .ideas-grid { grid-template-columns: 1fr; }
    .tabs { overflow-x: auto; }
}
</style>
