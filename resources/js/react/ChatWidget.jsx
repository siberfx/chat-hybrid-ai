import { useEffect, useRef, useState } from 'react';
import { useHybridChat } from './useHybridChat.js';
import '../client/chat-widget.css';

const STATUS = {
    ai: 'Assistant · a person is one click away',
    waiting: 'Waiting for a team member…',
    human: 'Chatting with our team',
};

/**
 * <ChatWidget api="https://your-app.com/api/chat" name={user?.name} email={user?.email} meta={{ plan: 'pro' }} identity={chatIdentity} />
 */
export default function ChatWidget({ api = '/api/chat', name = null, email = null, meta = {}, identity = null }) {
    const chat = useHybridChat({ api, name, email, meta, identity });
    const [text, setText] = useState('');
    const [visitor, setVisitor] = useState({ name: name ?? '', email: email ?? '' });
    const list = useRef(null);

    useEffect(() => {
        list.current?.scrollTo({ top: list.current.scrollHeight });
    }, [chat.messages.length, chat.open]);

    if (!chat.ready || !chat.config) return null;

    const { config, conversation } = chat;
    const needsIntro = !conversation && (config.require_email || !name);

    const submit = async (event) => {
        event.preventDefault();
        if (needsIntro && config.require_email && !visitor.email) return;
        const body = text;
        setText('');
        if (!(await chat.send(body, visitor))) setText(body);
    };

    const emailTranscript = () => {
        const to = window.prompt('Send the transcript to:', conversation?.visitor_email ?? visitor.email);
        if (to) chat.emailTranscript(to).then((r) => window.alert(`Transcript sent to ${r.email}`), () => {});
    };

    const end = () => window.confirm('End this chat?') && chat.end().catch(() => {});

    return (
        <div className={`hc-root ${config.position === 'left' ? 'hc-left' : ''}`} style={{ '--hc-color': config.color }}>
            {chat.open && (
                <div className="hc-panel" role="dialog" aria-label="Chat">
                    <div className="hc-header">
                        <div>
                            <div className="hc-title">{config.title}</div>
                            <div className="hc-status">
                                {conversation ? STATUS[conversation.mode] : 'We usually reply in a few minutes'}
                            </div>
                        </div>
                        <div className="hc-actions">
                            {conversation?.mode === 'ai' && <button onClick={() => chat.requestHuman().catch(() => {})}>Person</button>}
                            {conversation && <button onClick={emailTranscript}>Email</button>}
                            {conversation && <button onClick={end}>End</button>}
                            <button aria-label="Close" onClick={() => chat.setOpen(false)}>✕</button>
                        </div>
                    </div>

                    <div className="hc-list" ref={list} role="log" aria-live="polite">
                        {chat.messages.length === 0 && (
                            <div className="hc-msg hc-theirs"><div className="hc-body">{config.welcome}</div></div>
                        )}
                        {chat.messages.map((m) => m.sender_type === 'system' ? (
                            <div key={m.id} className="hc-system">{m.body}</div>
                        ) : (
                            <div key={m.id} className={`hc-msg ${m.sender_type === 'visitor' ? 'hc-mine' : 'hc-theirs'} ${m.pending ? 'hc-pending' : ''}`}>
                                {m.sender_type !== 'visitor' && m.sender_name && <div className="hc-who">{m.sender_name}</div>}
                                <div className="hc-body">{m.body}</div>
                            </div>
                        ))}
                        {chat.awaitingReply && <div className="hc-typing">Typing…</div>}
                    </div>

                    {chat.error && <div className="hc-error">{chat.error}</div>}

                    <form onSubmit={submit}>
                        {needsIntro && (
                            <div className="hc-intro">
                                <input placeholder="Your name" maxLength={120} value={visitor.name}
                                    onChange={(e) => setVisitor({ ...visitor, name: e.target.value })} />
                                <input type="email" placeholder={config.require_email ? 'Email' : 'Email (optional)'}
                                    required={config.require_email} maxLength={190} value={visitor.email}
                                    onChange={(e) => setVisitor({ ...visitor, email: e.target.value })} />
                            </div>
                        )}
                        <div className="hc-composer">
                            <textarea rows={1} placeholder="Write a message…" maxLength={config.max_message_length}
                                value={text} onChange={(e) => setText(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) submit(e); }} />
                            <button className="hc-send" type="submit" disabled={chat.sending || !text.trim()}>Send</button>
                        </div>
                    </form>
                </div>
            )}

            <button className="hc-launcher" aria-label={chat.open ? 'Close chat' : 'Open chat'} onClick={() => chat.setOpen(!chat.open)}>
                💬
                {!chat.open && chat.unread > 0 && <span className="hc-badge">{chat.unread > 9 ? '9+' : chat.unread}</span>}
            </button>
        </div>
    );
}
