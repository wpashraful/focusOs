<template>
    <AppLayout title="Workspaces">
        <div class="page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Your Workspaces</h2>
                    <p class="page-header__sub">Organize your projects by workspace</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">+ New Workspace</button>
            </div>

            <!-- Workspaces Grid -->
            <div class="workspaces-grid" v-if="workspaces.length">
                <div v-for="ws in workspaces" :key="ws.id" class="ws-card">
                    <div class="ws-card__top">
                        <div class="ws-card__icon">🗂️</div>
                        <div class="ws-card__menu">
                            <button class="icon-btn" @click="startEdit(ws)" title="Edit">✏️</button>
                            <button class="icon-btn icon-btn--danger" @click="confirmDelete(ws)" title="Delete">🗑️</button>
                        </div>
                    </div>
                    <h3 class="ws-card__name">{{ ws.name }}</h3>
                    <p class="ws-card__desc" v-if="ws.description">{{ ws.description }}</p>
                    <div class="ws-card__footer">
                        <span class="ws-card__count">{{ ws.projects_count }} project{{ ws.projects_count !== 1 ? 's' : '' }}</span>
                        <Link :href="route('workspaces.projects.index', ws.id)" class="btn-sm">Open →</Link>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div class="empty-state" v-else>
                <div class="empty-state__icon">🗂️</div>
                <p class="empty-state__title">No workspaces yet</p>
                <p class="empty-state__sub">Create your first workspace to start organizing projects.</p>
                <button class="btn-primary" @click="showCreate = true">+ Create Workspace</button>
            </div>

            <!-- Create / Edit Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showCreate || editingWs" @click.self="closeModal">
                    <div class="modal">
                        <h3 class="modal__title">{{ editingWs ? 'Edit Workspace' : 'New Workspace' }}</h3>
                        <form @submit.prevent="submitForm">
                            <div class="field">
                                <label class="field__label">Name</label>
                                <input v-model="form.name" class="field__input" placeholder="e.g. Freelance Work" required />
                                <span v-if="form.errors.name" class="field__error">{{ form.errors.name }}</span>
                            </div>
                            <div class="field">
                                <label class="field__label">Description <span class="field__opt">(optional)</span></label>
                                <textarea v-model="form.description" class="field__input field__textarea" placeholder="What's this workspace for?" rows="3"></textarea>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="closeModal">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner" />
                                    {{ editingWs ? 'Save Changes' : 'Create' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- Delete confirm -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="deletingWs" @click.self="deletingWs = null">
                    <div class="modal modal--sm">
                        <h3 class="modal__title">Delete "{{ deletingWs.name }}"?</h3>
                        <p class="modal__sub">This will permanently delete the workspace and all its projects.</p>
                        <div class="modal__actions">
                            <button class="btn-ghost" @click="deletingWs = null">Cancel</button>
                            <button class="btn-danger" @click="doDelete" :disabled="deleteForm.processing">Delete</button>
                        </div>
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

defineProps({ workspaces: { type: Array, default: () => [] } });

const showCreate = ref(false);
const editingWs  = ref(null);
const deletingWs = ref(null);

const form = useForm({ name: '', description: '' });
const deleteForm = useForm({});

const startEdit = (ws) => {
    editingWs.value = ws;
    form.name = ws.name;
    form.description = ws.description ?? '';
};

const closeModal = () => {
    showCreate.value = false;
    editingWs.value  = null;
    form.reset();
};

const submitForm = () => {
    if (editingWs.value) {
        form.patch(route('workspaces.update', editingWs.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('workspaces.store'), { onSuccess: closeModal });
    }
};

const confirmDelete = (ws) => (deletingWs.value = ws);
const doDelete = () => {
    deleteForm.delete(route('workspaces.destroy', deletingWs.value.id), {
        onSuccess: () => (deletingWs.value = null),
    });
};
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 24px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub    { font-size: 0.82rem; color: #64748b; margin: 0; }

/* Grid */
.workspaces-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }

/* Card */
.ws-card {
    background: #0f1117; border: 1px solid #1e2130; border-radius: 12px;
    padding: 18px 20px; display: flex; flex-direction: column; gap: 8px;
    transition: border-color 0.2s, transform 0.15s;
}
.ws-card:hover { border-color: rgba(99,102,241,0.3); transform: translateY(-2px); }
.ws-card__top { display: flex; align-items: center; justify-content: space-between; }
.ws-card__icon { font-size: 1.4rem; }
.ws-card__menu { display: flex; gap: 6px; opacity: 0; transition: opacity 0.15s; }
.ws-card:hover .ws-card__menu { opacity: 1; }
.ws-card__name { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.ws-card__desc { font-size: 0.8rem; color: #64748b; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ws-card__footer { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.ws-card__count { font-size: 0.75rem; color: #4a5568; }

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px; padding: 9px 18px;
    font-size: 0.85rem; font-weight: 600; color: white;
    cursor: pointer; transition: opacity 0.2s, transform 0.15s; font-family: inherit;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-sm {
    font-size: 0.75rem; font-weight: 600; color: #6366f1;
    text-decoration: none; padding: 4px 10px; border-radius: 5px;
    border: 1px solid rgba(99,102,241,0.25); transition: all 0.15s;
}
.btn-sm:hover { background: rgba(99,102,241,0.1); }

.btn-ghost {
    background: transparent; border: 1px solid #2a2d3e; border-radius: 7px;
    padding: 8px 16px; color: #64748b; font-size: 0.85rem; cursor: pointer;
    font-family: inherit; transition: all 0.15s;
}
.btn-ghost:hover { border-color: #6366f1; color: #a5b4fc; }

.btn-danger {
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
    border-radius: 7px; padding: 8px 16px; color: #f87171;
    font-size: 0.85rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-danger:hover { background: rgba(239,68,68,0.2); }

.icon-btn {
    background: none; border: none; cursor: pointer;
    font-size: 0.9rem; padding: 2px 4px; border-radius: 4px;
    transition: background 0.15s;
}
.icon-btn:hover { background: rgba(99,102,241,0.1); }
.icon-btn--danger:hover { background: rgba(239,68,68,0.15); }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 20px;
    backdrop-filter: blur(4px);
}
.modal {
    background: #0f1117; border: 1px solid #2a2d3e; border-radius: 14px;
    padding: 28px; width: 100%; max-width: 440px;
    display: flex; flex-direction: column; gap: 18px;
}
.modal--sm { max-width: 360px; }
.modal__title { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.modal__sub   { font-size: 0.83rem; color: #64748b; margin: -6px 0 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 10px; }

/* Fields */
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__opt   { font-weight: 400; color: #4a5568; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; width: 100%;
    transition: border-color 0.2s;
}
.field__input:focus { border-color: #6366f1; }
.field__textarea { resize: vertical; }
.field__error { font-size: 0.75rem; color: #f87171; }

/* Empty */
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; text-align: center; }
.empty-state__icon { font-size: 3rem; }
.empty-state__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.empty-state__sub   { font-size: 0.82rem; color: #64748b; margin: 0 0 12px; }

.spinner {
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
