import { onBeforeUnmount, onMounted, shallowRef } from 'vue';
import { ChatSession } from '../client/hybrid-chat.js';

/**
 * const { state, send, requestHuman, ... } = useHybridChat({ api: '/api/chat', name, email, meta });
 * state.value.messages, state.value.conversation, ...
 */
export function useHybridChat(options = {}) {
    const session = new ChatSession(options);
    const state = shallowRef(session.getState());
    const unsubscribe = session.subscribe((next) => { state.value = next; });

    onMounted(() => session.start());
    onBeforeUnmount(() => {
        unsubscribe();
        session.stop();
    });

    return {
        state,
        send: (body, visitor) => session.send(body, visitor),
        setOpen: (open) => session.setOpen(open),
        requestHuman: () => session.requestHuman(),
        emailTranscript: (email) => session.emailTranscript(email),
        end: () => session.end(),
        session,
    };
}
