<template>
    <AppLayout :title="`${project.name} — Resources`">
        <div class="page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h2 class="page-header__title">Project Resources (Knowledge Base)</h2>
                    <p class="page-header__sub">Upload documents, notes, or guidelines. The AI Coach will reference these in chat.</p>
                </div>
            </div>

            <div class="grid-layout">
                <!-- Upload Panel -->
                <div class="card upload-card">
                    <h3 class="card-title">Upload File</h3>
                    <form @submit.prevent="uploadFile" class="upload-form">
                        <div
                            class="drop-zone"
                            :class="{ 'drop-zone--drag': isDragging }"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                            @click="$refs.fileInput.click()"
                        >
                            <span class="drop-zone__icon">📤</span>
                            <p class="drop-zone__text" v-if="!selectedFile">
                                Drag & drop file here, or <span class="highlight">browse</span>
                            </p>
                            <p class="drop-zone__file" v-else>
                                📄 {{ selectedFile.name }} ({{ formatSize(selectedFile.size) }})
                            </p>
                            <span class="drop-zone__limits">Supports TXT, MD, PDF (max 10MB)</span>
                            <input
                                type="file"
                                ref="fileInput"
                                class="hidden-input"
                                accept=".txt,.md,.pdf"
                                @change="handleFileChange"
                            />
                        </div>

                        <div class="form-actions" v-if="selectedFile">
                            <button type="button" class="btn-ghost" @click="selectedFile = null">Clear</button>
                            <button type="submit" class="btn-primary" :disabled="uploading">
                                <span v-if="uploading" class="spinner" />
                                Start Ingestion
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Resources List -->
                <div class="card list-card">
                    <h3 class="card-title">Document Backlog</h3>
                    <div class="resources-table-wrap" v-if="resources.length">
                        <table class="resources-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Uploaded At</th>
                                    <th class="actions-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="res in resources" :key="res.id">
                                    <td class="res-name-cell">
                                        <span class="file-icon">📄</span>
                                        <span class="file-name">{{ res.name }}</span>
                                    </td>
                                    <td>
                                        <span class="status-badge" :class="`status--${res.status}`">
                                            {{ res.status }}
                                        </span>
                                    </td>
                                    <td class="res-date-cell">{{ formatDate(res.created_at) }}</td>
                                    <td class="res-actions-cell">
                                        <button class="btn-delete" @click="deleteResource(res)" title="Delete">🗑️</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty-state" v-else>
                        <span class="empty-state__icon">📚</span>
                        <p class="empty-state__title">No resources added yet</p>
                        <p class="empty-state__sub">Upload project docs so the AI Coach can answer questions about them.</p>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project:   Object,
    resources: { type: Array, default: () => [] },
});

const isDragging   = ref(false);
const selectedFile = ref(null);
const fileInput    = ref(null);
const uploading    = ref(false);

const handleFileChange = (e) => {
    selectedFile.value = e.target.files[0] || null;
};

const handleDrop = (e) => {
    isDragging.value = false;
    selectedFile.value = e.dataTransfer.files[0] || null;
};

const formatSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const uploadFile = () => {
    if (!selectedFile.value) return;

    uploading.value = true;

    const form = useForm({
        file: selectedFile.value,
    });

    form.post(route('resources.store', props.project.id), {
        onSuccess: () => {
            selectedFile.value = null;
            uploading.value = false;
            startPolling();
        },
        onError: () => {
            uploading.value = false;
        }
    });
};

const deleteResource = (res) => {
    if (confirm('Are you sure you want to delete this resource?')) {
        router.delete(route('resources.destroy', [props.project.id, res.id]), {
            preserveScroll: true,
        });
    }
};

const formatDate = (d) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

// Status polling for processing chunks (Step 8.4)
let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;

    pollInterval = setInterval(() => {
        // Only reload table states if any files are still processing
        const hasProcessing = props.resources.some(r => r.status === 'processing');
        if (hasProcessing) {
            router.reload({ only: ['resources'] });
        } else {
            stopPolling();
        }
    }, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 20px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.page-header__title { font-size: 1.15rem; font-weight: 700; color: #e2e8f0; margin: 0 0 4px; }
.page-header__sub   { font-size: 0.82rem; color: #64748b; margin: 0; }

.grid-layout { display: grid; grid-template-columns: 1fr 1.8fr; gap: 20px; }
@media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }

.card { background: #0f1117; border: 1px solid #1e2130; border-radius: 12px; padding: 22px; }
.card-title { font-size: 0.92rem; font-weight: 700; color: #e2e8f0; margin: 0 0 16px; }

/* Dropzone */
.drop-zone {
    border: 2px dashed #2a2d3e; border-radius: 10px; padding: 34px 20px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    cursor: pointer; text-align: center; transition: all 0.2s;
}
.drop-zone--drag { border-color: #6366f1; background: rgba(99,102,241,0.04); }
.drop-zone:hover { border-color: #6366f1; }
.drop-zone__icon { font-size: 2.2rem; margin-bottom: 12px; }
.drop-zone__text { font-size: 0.83rem; color: #64748b; margin: 0 0 4px; }
.drop-zone__text .highlight { color: #6366f1; font-weight: 600; }
.drop-zone__file { font-size: 0.85rem; font-weight: 600; color: #a5b4fc; margin: 0; }
.drop-zone__limits { font-size: 0.7rem; color: #4a5568; margin-top: 6px; }
.hidden-input { display: none; }

.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }

/* Table */
.resources-table-wrap { overflow-x: auto; }
.resources-table { width: 100%; border-collapse: collapse; text-align: left; }
.resources-table th { padding: 12px 16px; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e2130; }
.resources-table td { padding: 14px 16px; font-size: 0.83rem; color: #e2e8f0; border-bottom: 1px solid #1e2130; vertical-align: middle; }

.res-name-cell { display: flex; align-items: center; gap: 8px; }
.file-icon { font-size: 1.1rem; }
.file-name { font-weight: 500; }

.status-badge { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; }
.status--processing { background: rgba(245,158,11,0.12); color: #fbbf24; }
.status--ready      { background: rgba(16,185,129,0.12); color: #34d399; }
.status--failed     { background: rgba(239,68,68,0.12);  color: #f87171; }

.res-date-cell { color: #64748b; }
.res-actions-cell { text-align: right; }
.actions-header { text-align: right; }

.btn-delete { background: none; border: none; cursor: pointer; opacity: 0.6; transition: opacity 0.15s; font-size: 0.95rem; }
.btn-delete:hover { opacity: 1; filter: hue-rotate(-20deg); }

/* Buttons */
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
