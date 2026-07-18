<template>
    <AppLayout title="AI Coach">
        <div class="chat-layout">

            <!-- Sidebar list of conversations -->
            <div class="chat-sidebar">
                <div class="chat-sidebar__header">
                    <button class="btn-new-chat" @click="showNewChat = true">+ New Session</button>
                </div>
                <div class="conversation-list" v-if="conversations.length">
                    <Link
                        v-for="c in conversations"
                        :key="c.id"
                        :href="route('chat.index', c.id)"
                        class="conv-item"
                        :class="{ 'conv-item--active': activeConversation && activeConversation.id === c.id }"
                    >
                        <span class="conv-item__icon">💬</span>
                        <div class="conv-item__body">
                            <span class="conv-item__title">{{ c.title ?? 'New Chat Session' }}</span>
                            <span class="conv-item__time">{{ formatTime(c.updated_at) }}</span>
                        </div>
                    </Link>
                </div>
                <div class="conversation-empty" v-else>
                    No chat history yet.
                </div>
            </div>

            <!-- Active Chat area -->
            <div class="chat-main">
                <template v-if="activeConversation">
                    <!-- Chat messages log -->
                    <div class="chat-messages" ref="messagesContainer">
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="msg-bubble-wrap"
                            :class="msg.role === 'user' ? 'msg-bubble-wrap--user' : 'msg-bubble-wrap--bot'"
                        >
                            <div class="avatar" :class="msg.role === 'user' ? 'avatar--user' : 'avatar--bot'">
                                {{ msg.role === 'user' ? 'U' : 'AI' }}
                            </div>
                            <div class="msg-bubble">
                                <p class="msg-bubble__content">{{ msg.content }}</p>
                            </div>
                        </div>

                        <!-- Live Stream Token Placeholder -->
                        <div v-if="streamingText" class="msg-bubble-wrap msg-bubble-wrap--bot">
                            <div class="avatar avatar--bot">AI</div>
                            <div class="msg-bubble">
                                <p class="msg-bubble__content">{{ streamingText }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Input Bar -->
                    <div class="chat-input-bar">
                        <form @submit.prevent="sendMessage" class="input-form">
                            <input
                                v-model="inputText"
                                class="chat-input"
                                placeholder="Type a message to your AI Coach..."
                                :disabled="isStreaming"
                                required
                                autofocus
                            />
                            <button type="submit" class="btn-send" :disabled="isStreaming || !inputText.trim()">
                                Send
                            </button>
                        </form>
                    </div>
                </template>

                <!-- No active session placeholder -->
                <div class="chat-main-empty" v-else>
                    <div class="welcome-box">
                        <span class="welcome-box__icon">🤖</span>
                        <h3>Welcome to your FocusOS AI Coach</h3>
                        <p>Discuss your daily goals, tasks, or request a routine delay recalculation.</p>
                        <button class="btn-primary" @click="showNewChat = true">Start a session</button>
                    </div>
                </div>
            </div>

            <!-- New Session Modal -->
            <Teleport to="body">
                <div class="modal-overlay" v-if="showNewChat" @click.self="showNewChat = false">
                    <div class="modal">
                        <h3 class="modal__title">Start New AI Session</h3>
                        <form @submit.prevent="submitNewChat">
                            <div class="field">
                                <label class="field__label">Session Title</label>
                                <input v-model="newChatForm.title" class="field__input" placeholder="e.g. Q3 Launch Strategy Prep" required />
                            </div>
                            <div class="field">
                                <label class="field__label">Link to Project <span class="field__opt">(optional)</span></label>
                                <select v-model="newChatForm.project_id" class="field__input field__select">
                                    <option :value="null">— Select Project —</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">
                                        {{ p.icon ?? '📁' }} {{ p.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="modal__actions">
                                <button type="button" class="btn-ghost" @click="showNewChat = false">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="newChatForm.processing">Start Chat</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, nextTick, watch, onMounted } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    conversations:      { type: Array, default: () => [] },
    activeConversation: Object,
    messages:           { type: Array, default: () => [] },
    projects:           { type: Array, default: () => [] },
});

const showNewChat = ref(false);
const inputText   = ref('');
const messagesContainer = ref(null);

// Streaming states
const isStreaming   = ref(false);
const streamingText = ref('');

const newChatForm = useForm({ title: '', project_id: null });

const submitNewChat = () => {
    newChatForm.post(route('chat.start'), {
        onSuccess: () => { showNewChat.value = false; newChatForm.reset(); }
    });
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

watch(() => props.messages, () => scrollToBottom(), { deep: true });

onMounted(() => {
    if (props.activeConversation) {
        scrollToBottom();
    }
});

// SSE Streaming Trigger (Step 4.6 / 4.7)
const sendMessage = async () => {
    if (!inputText.value.trim() || isStreaming.value) return;

    const userText = inputText.value;
    inputText.value = '';

    // 1. Instantly append user message locally to avoid latency wait
    props.messages.push({
        id: Date.now(),
        role: 'user',
        content: userText,
    });
    scrollToBottom();

    // 2. POST to store user message in DB
    await axios.post(route('chat.message', props.activeConversation.id), {
        content: userText,
    });

    // 3. Initiate SSE Streaming reader
    isStreaming.value = true;
    streamingText.value = '';

    try {
        const response = await fetch(route('chat.stream', props.activeConversation.id));
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                const trimmed = line.trim();
                if (trimmed.startsWith('data: ')) {
                    const dataStr = trimmed.slice(6);
                    if (dataStr === '[DONE]') {
                        break;
                    }
                    try {
                        const parsed = JSON.parse(dataStr);
                        if (parsed.token) {
                            streamingText.value += parsed.token;
                            scrollToBottom();
                        }
                    } catch (e) {
                        // ignore broken json chunks
                    }
                }
            }
        }
    } catch (err) {
        console.error('Streaming error', err);
    } finally {
        isStreaming.value = false;
        // Reload page to fetch the completed assistant message from DB statefully
        router.reload({ only: ['messages'] });
        streamingText.value = '';
    }
};

const formatTime = (d) => new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
</script>

<style scoped>
.chat-layout { display: flex; height: calc(100vh - 100px); background: #0b0c10; border: 1px solid #1e2130; border-radius: 14px; overflow: hidden; }

/* Sidebar */
.chat-sidebar { width: 280px; border-right: 1px solid #1e2130; display: flex; flex-direction: column; background: #0f1117; }
.chat-sidebar__header { padding: 16px; border-bottom: 1px solid #1e2130; }
.btn-new-chat {
    width: 100%; padding: 10px; border-radius: 8px; border: 1px dashed rgba(99,102,241,0.4);
    background: transparent; color: #a5b4fc; font-size: 0.85rem; font-weight: 600;
    cursor: pointer; transition: all 0.15s;
}
.btn-new-chat:hover { background: rgba(99,102,241,0.05); border-color: #6366f1; }

.conversation-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.conv-item {
    display: flex; align-items: center; gap: 10px; padding: 14px 16px;
    text-decoration: none; border-bottom: 1px solid #141620; transition: background 0.15s;
}
.conv-item:hover { background: #141620; }
.conv-item--active { background: rgba(99,102,241,0.06); border-left: 3px solid #6366f1; }
.conv-item__icon { font-size: 1.1rem; }
.conv-item__body { display: flex; flex-direction: column; gap: 2px; }
.conv-item__title { font-size: 0.82rem; font-weight: 600; color: #e2e8f0; }
.conv-item__time  { font-size: 0.7rem; color: #4a5568; }
.conversation-empty { padding: 20px; text-align: center; color: #4a5568; font-size: 0.8rem; }

/* Main Chat */
.chat-main { flex: 1; display: flex; flex-direction: column; background: #0b0c10; }
.chat-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; }

.msg-bubble-wrap { display: flex; gap: 12px; max-width: 80%; }
.msg-bubble-wrap--user { align-self: flex-end; flex-direction: row-reverse; }
.msg-bubble-wrap--bot  { align-self: flex-start; }

.avatar {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
.avatar--user { background: #2a2d3e; color: #94a3b8; }
.avatar--bot  { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; }

.msg-bubble { padding: 12px 16px; border-radius: 12px; }
.msg-bubble-wrap--user .msg-bubble { background: #6366f1; color: white; border-top-right-radius: 2px; }
.msg-bubble-wrap--bot  .msg-bubble { background: #0f1117; border: 1px solid #1e2130; color: #e2e8f0; border-top-left-radius: 2px; }
.msg-bubble__content { font-size: 0.88rem; margin: 0; line-height: 1.5; white-space: pre-wrap; }

/* Input Bar */
.chat-input-bar { padding: 16px 24px; border-top: 1px solid #1e2130; background: #0f1117; }
.input-form { display: flex; gap: 12px; }
.chat-input {
    flex: 1; background: #141620; border: 1px solid #2a2d3e;
    border-radius: 8px; padding: 12px 16px; font-size: 0.88rem;
    color: #e2e8f0; outline: none; transition: border-color 0.2s;
}
.chat-input:focus { border-color: #6366f1; }
.btn-send {
    background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;
    border-radius: 8px; padding: 0 20px; font-size: 0.88rem; font-weight: 600;
    color: white; cursor: pointer; transition: opacity 0.2s;
}
.btn-send:hover:not(:disabled) { opacity: 0.9; }
.btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

/* Empty state */
.chat-main-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; }
.welcome-box { text-align: center; max-width: 400px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
.welcome-box__icon { font-size: 3rem; }
.welcome-box__title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; }
.welcome-box p { font-size: 0.82rem; color: #64748b; margin: 0 0 10px; }

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

.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.82rem; font-weight: 500; color: #94a3b8; }
.field__opt   { font-weight: 400; color: #4a5568; }
.field__input {
    background: #141620; border: 1px solid #2a2d3e; border-radius: 8px;
    padding: 9px 12px; font-size: 0.88rem; color: #e2e8f0;
    outline: none; font-family: inherit; width: 100%; transition: border-color 0.2s;
}
.field__input:focus { border-color: #6366f1; }
.field__select { appearance: none; cursor: pointer; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;
    border-radius: 8px; padding: 9px 18px; font-size: 0.85rem; font-weight: 600;
    color: white; cursor: pointer; transition: opacity 0.2s; font-family: inherit;
}
.btn-primary:hover { opacity: 0.9; }

.btn-ghost {
    background: transparent; border: 1px solid #2a2d3e; border-radius: 7px;
    padding: 8px 16px; color: #64748b; font-size: 0.85rem;
    cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-ghost:hover { border-color: #6366f1; color: #a5b4fc; }
</style>
