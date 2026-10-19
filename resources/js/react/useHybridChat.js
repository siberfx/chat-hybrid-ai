import { useEffect, useState, useSyncExternalStore } from 'react';
import { ChatSession } from '../client/hybrid-chat.js';

/**
 * const chat = useHybridChat({ api: '/api/chat', name, email, meta });
 * chat.messages, chat.conversation, chat.send('Hi'), chat.requestHuman(), ...
 */
export function useHybridChat(options = {}) {
    const [session] = useState(() => new ChatSession(options));

    useEffect(() => {
        session.start();
        return () => session.stop();
    }, [session]);

    const state = useSyncExternalStore(session.subscribe, session.getState, session.getState);

    return {
        ...state,
        send: (body, visitor) => session.send(body, visitor),
        setOpen: (open) => session.setOpen(open),
        requestHuman: () => session.requestHuman(),
        emailTranscript: (email) => session.emailTranscript(email),
        end: () => session.end(),
        session,
    };
}
