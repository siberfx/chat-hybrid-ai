<!--
  <ChatWidget api="https://your-app.com/api/chat" :name="user?.name" :email="user?.email" :meta="{ plan: 'pro' }" />
-->
<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useHybridChat } from './useHybridChat.js';
import '../client/chat-widget.css';

const props = defineProps({
    api: { type: String, default: '/api/chat' },
    name: { type: String, default: null },
    email: { type: String, default: null },
    meta: { type: Object, default: () => ({}) },
});

const STATUS = {
    ai: 'Assistant · a person is one click away',
    waiting: 'Waiting for a team member…',
    human: 'Chatting with our team',
};

const chat = useHybridChat({ api: props.api, name: props.name, email: props.email, meta: props.meta });
const state = chat.state;
const text = ref('');
const visitor = reactive({ name: props.name ?? '', email: props.email ?? '' });
const list = ref(null);

const config = computed(() => state.value.config);
const conversation = computed(() => state.value.conversation);
const needsIntro = computed(() => !conversation.value && (config.value?.require_email || !props.name));

watch(() => [state.value.messages.length, state.value.open], async () => {
    await nextTick();
    list.value?.scrollTo({ top: list.value.scrollHeight });
});

async function submit() {
    if (needsIntro.value && config.value.require_email && !visitor.email) return;
    const body = text.value;
    text.value = '';
    if (!(await chat.send(body, { ...visitor }))) text.value = body;
}

function onEnter(event) {
    if (!event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

function emailTranscript() {
    const to = window.prompt('Send the transcript to:', conversation.value?.visitor_email ?? visitor.email);
    if (to) chat.emailTranscript(to).then((r) => window.alert(`Transcript sent to ${r.email}`), () => {});
}

function end() {
    if (window.confirm('End this chat?')) chat.end().catch(() => {});
}
</script>

<template>
    <div v-if="state.ready && config" class="hc-root" :class="{ 'hc-left': config.position === 'left' }" :style="{ '--hc-color': config.color }">
        <div v-if="state.open" class="hc-panel" role="dialog" aria-label="Chat">
            <div class="hc-header">
                <div>
                    <div class="hc-title">{{ config.title }}</div>
                    <div class="hc-status">{{ conversation ? STATUS[conversation.mode] : 'We usually reply in a few minutes' }}</div>
                </div>
                <div class="hc-actions">
                    <button v-if="conversation?.mode === 'ai'" @click="chat.requestHuman().catch(() => {})">Person</button>
                    <button v-if="conversation" @click="emailTranscript">Email</button>
                    <button v-if="conversation" @click="end">End</button>
                    <button aria-label="Close" @click="chat.setOpen(false)">✕</button>
                </div>
            </div>

            <div ref="list" class="hc-list" role="log" aria-live="polite">
                <div v-if="state.messages.length === 0" class="hc-msg hc-theirs"><div class="hc-body">{{ config.welcome }}</div></div>
                <template v-for="m in state.messages" :key="m.id">
                    <div v-if="m.sender_type === 'system'" class="hc-system">{{ m.body }}</div>
                    <div v-else class="hc-msg" :class="[m.sender_type === 'visitor' ? 'hc-mine' : 'hc-theirs', { 'hc-pending': m.pending }]">
                        <div v-if="m.sender_type !== 'visitor' && m.sender_name" class="hc-who">{{ m.sender_name }}</div>
                        <div class="hc-body">{{ m.body }}</div>
                    </div>
                </template>
                <div v-if="state.awaitingReply" class="hc-typing">Typing…</div>
            </div>

            <div v-if="state.error" class="hc-error">{{ state.error }}</div>

            <form @submit.prevent="submit">
                <div v-if="needsIntro" class="hc-intro">
                    <input v-model="visitor.name" placeholder="Your name" maxlength="120">
                    <input v-model="visitor.email" type="email" :placeholder="config.require_email ? 'Email' : 'Email (optional)'"
                        :required="config.require_email" maxlength="190">
                </div>
                <div class="hc-composer">
                    <textarea v-model="text" rows="1" placeholder="Write a message…" :maxlength="config.max_message_length"
                        @keydown.enter="onEnter" />
                    <button class="hc-send" type="submit" :disabled="state.sending || !text.trim()">Send</button>
                </div>
            </form>
        </div>

        <button class="hc-launcher" :aria-label="state.open ? 'Close chat' : 'Open chat'" @click="chat.setOpen(!state.open)">
            💬
            <span v-if="!state.open && state.unread > 0" class="hc-badge">{{ state.unread > 9 ? '9+' : state.unread }}</span>
        </button>
    </div>
</template>
