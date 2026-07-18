<template>
    <AppLayout title="Settings — Telegram">
        <div class="settings-page">

            <div class="settings-header">
                <h2 class="settings-header__title">Telegram Integration</h2>
                <p class="settings-header__sub">Link your Telegram account to chat with your AI Coach directly in Telegram.</p>
            </div>

            <!-- Already linked -->
            <div v-if="telegramLinked" class="status-card status-card--linked">
                <div class="status-card__icon">✅</div>
                <div class="status-card__body">
                    <p class="status-card__title">Telegram account linked</p>
                    <p class="status-card__username">@{{ telegramUsername }}</p>
                </div>
                <form @submit.prevent="unlink">
                    <button type="submit" class="btn-danger" :disabled="unlinkForm.processing">
                        {{ unlinkForm.processing ? 'Unlinking…' : 'Unlink' }}
                    </button>
                </form>
            </div>

            <!-- Not linked -->
            <div v-else class="link-flow">

                <!-- Step 1 -->
                <div class="step-card">
                    <div class="step-card__num">1</div>
                    <div class="step-card__body">
                        <p class="step-card__title">Open the FocusOS Telegram bot</p>
                        <p class="step-card__sub">Search for <strong>@FocusOSBot</strong> on Telegram or click the button below.</p>
                        <a href="https://t.me/FocusOSBot" target="_blank" rel="noopener" class="btn-telegram">
                            <span>🤖</span> Open in Telegram
                        </a>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="step-card">
                    <div class="step-card__num">2</div>
                    <div class="step-card__body">
                        <p class="step-card__title">Send this code to the bot</p>
                        <p class="step-card__sub">Type <strong>/connect</strong> followed by your code:</p>

                        <div class="code-box">
                            <div class="code-box__command">/connect <span class="code-box__code">{{ linkCode }}</span></div>
                            <button class="code-box__copy" @click="copyCode" :class="{ 'code-box__copy--done': copied }">
                                {{ copied ? '✓ Copied' : 'Copy' }}
                            </button>
                        </div>

                        <p class="step-card__expiry">⏱ Code expires in 10 minutes. <button class="btn-text" @click="refresh">Generate new code</button></p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="step-card">
                    <div class="step-card__num">3</div>
                    <div class="step-card__body">
                        <p class="step-card__title">Done!</p>
                        <p class="step-card__sub">Once the bot confirms, your accounts are linked. Refresh this page to see your status.</p>
                        <button class="btn-secondary" @click="checkStatus">🔄 Check Status</button>
                    </div>
                </div>

            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    linkCode:         { type: String, default: '' },
    telegramLinked:   { type: Boolean, default: false },
    telegramUsername: { type: String, default: '' },
});

const copied = ref(false);
const unlinkForm = useForm({});

const copyCode = async () => {
    await navigator.clipboard.writeText(`/connect ${props.linkCode}`);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const refresh = () => router.reload({ only: ['linkCode'] });

const checkStatus = () => router.reload({ only: ['telegramLinked', 'telegramUsername'] });

const unlink = () => {
    unlinkForm.delete(route('telegram.unlink'));
};
</script>

<style scoped>
.settings-page { max-width: 600px; display: flex; flex-direction: column; gap: 24px; }

.settings-header__title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin: 0 0 6px; }
.settings-header__sub   { font-size: 0.85rem; color: #64748b; margin: 0; }

/* Linked card */
.status-card {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 20px; border-radius: 12px;
    border: 1px solid #1e2130; background: #0f1117;
}
.status-card--linked { border-color: rgba(16,185,129,0.3); background: rgba(16,185,129,0.05); }
.status-card__icon { font-size: 1.6rem; }
.status-card__body { flex: 1; }
.status-card__title { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin: 0 0 2px; }
.status-card__username { font-size: 0.82rem; color: #34d399; margin: 0; }

/* Steps */
.link-flow { display: flex; flex-direction: column; gap: 14px; }
.step-card {
    display: flex; gap: 16px;
    padding: 18px 20px; border-radius: 12px;
    background: #0f1117; border: 1px solid #1e2130;
}
.step-card__num {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-size: 0.8rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
}
.step-card__title { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin: 0 0 4px; }
.step-card__sub   { font-size: 0.82rem; color: #64748b; margin: 0 0 12px; }
.step-card__sub strong { color: #a5b4fc; }
.step-card__expiry { font-size: 0.75rem; color: #4a5568; margin: 10px 0 0; }

/* Code box */
.code-box {
    display: flex; align-items: center;
    background: #141620; border: 1px solid #2a2d3e;
    border-radius: 8px; padding: 10px 14px;
    gap: 12px;
}
.code-box__command { flex: 1; font-family: 'Courier New', monospace; font-size: 0.88rem; color: #94a3b8; }
.code-box__code { color: #a5b4fc; font-weight: 700; letter-spacing: 0.05em; }
.code-box__copy {
    font-size: 0.75rem; font-weight: 600;
    padding: 4px 10px; border-radius: 5px;
    border: 1px solid #2a2d3e; background: transparent;
    color: #64748b; cursor: pointer; transition: all 0.15s;
    font-family: inherit;
}
.code-box__copy:hover { color: #a5b4fc; border-color: #6366f1; }
.code-box__copy--done { color: #34d399; border-color: rgba(16,185,129,0.4); }

/* Buttons */
.btn-danger {
    padding: 7px 16px; border-radius: 7px;
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
    color: #f87171; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: all 0.15s; font-family: inherit;
}
.btn-danger:hover { background: rgba(239,68,68,0.2); }

.btn-telegram {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 18px; border-radius: 8px;
    background: #229ED9; color: white;
    font-size: 0.85rem; font-weight: 600;
    text-decoration: none; transition: opacity 0.15s;
}
.btn-telegram:hover { opacity: 0.85; }

.btn-secondary {
    padding: 8px 16px; border-radius: 7px;
    background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.25);
    color: #a5b4fc; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: all 0.15s; font-family: inherit;
}
.btn-secondary:hover { background: rgba(99,102,241,0.2); }

.btn-text {
    background: none; border: none; color: #6366f1;
    font-size: 0.75rem; cursor: pointer; padding: 0;
    font-family: inherit;
}
.btn-text:hover { color: #a5b4fc; }
</style>
