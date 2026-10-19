/*!
 * chat-hybrid-ai API client — no dependencies, works with any framework.
 *
 *   ChatClient   the public visitor API (api/chat), authenticated by X-Chat-Token.
 *   ChatSession  a ready-made visitor chat: start/resume, polling, unread count.
 *                Subscribe to it from React, Vue, Svelte, ...
 *   AdminClient  the admin API (api/chat-admin with a Sanctum token, or
 *                chat-admin/api with the session cookie).
 */

export class ApiError extends Error {
    constructor(message, status, body) {
        super(message);
        this.status = status;
        this.body = body;
    }
}

async function request(base, method, path, { body, headers = {}, credentials = 'omit' } = {}) {
    const res = await fetch(base.replace(/\/$/, '') + path, {
        method,
        credentials,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...headers },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (res.status === 204) return null;

    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new ApiError(json.message || 'Request failed', res.status, json);

    return json;
}

export class ChatClient {
    constructor({ api = '/api/chat', id = null, token = null } = {}) {
        this.api = api;
        this.id = id;
        this.token = token;
    }

    call(method, path, body) {
        return request(this.api, method, path, {
            body,
            headers: this.token ? { 'X-Chat-Token': this.token } : {},
        });
    }

    conv(path = '') {
        if (!this.id) throw new Error('No conversation started.');
        return `/conversations/${this.id}${path}`;
    }

    config() { return this.call('GET', '/config'); }

    /** { name?, email?, subject?, message?, meta?, identity? } → { token, conversation, messages } */
    async start(data = {}) {
        const json = await this.call('POST', '/conversations', data);
        this.id = json.conversation.id;
        this.token = json.token;
        return json;
    }

    show() { return this.call('GET', this.conv()); }
    update(data) { return this.call('PATCH', this.conv(), data); } // { name?, email?, identity? }
    messages(after = 0) { return this.call('GET', this.conv(`/messages?after=${after}`)); }
    send(body) { return this.call('POST', this.conv('/messages'), { body }); }
    markRead() { return this.call('POST', this.conv('/read')); }
    requestHuman() { return this.call('POST', this.conv('/human')); }
    close() { return this.call('POST', this.conv('/close')); }
    transcript(email) { return this.call('POST', this.conv('/transcript'), email ? { email } : {}); }
}

export class AdminClient {
    /**
     * Token apps: new AdminClient({ token: 'sanctum-token' })
     * Same-site SPA on the session: new AdminClient({ api: '/chat-admin/api', credentials: 'include', headers: { 'X-XSRF-TOKEN': ... } })
     */
    constructor({ api = '/api/chat-admin', token = null, headers = {}, credentials = 'omit' } = {}) {
        this.api = api;
        this.credentials = credentials;
        this.headers = token ? { Authorization: `Bearer ${token}`, ...headers } : headers;
    }

    call(method, path, body) {
        return request(this.api, method, path, { body, headers: this.headers, credentials: this.credentials });
    }

    config() { return this.call('GET', '/config'); }
    stats() { return this.call('GET', '/stats'); }

    /** { status?, mode?, mine?, unread?, search?, per_page?, page? } */
    conversations(filters = {}) {
        const query = new URLSearchParams(
            Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && v !== '' && v !== false)
                .map(([k, v]) => [k, v === true ? '1' : String(v)])
        ).toString();
        return this.call('GET', `/conversations${query ? `?${query}` : ''}`);
    }

    show(id) { return this.call('GET', `/conversations/${id}`); }
    messages(id, after = 0) { return this.call('GET', `/conversations/${id}/messages?after=${after}`); }
    send(id, body) { return this.call('POST', `/conversations/${id}/messages`, { body }); }
    takeOver(id) { return this.call('POST', `/conversations/${id}/take-over`); }
    returnToAi(id) { return this.call('POST', `/conversations/${id}/return-to-ai`); }
    close(id) { return this.call('POST', `/conversations/${id}/close`); }
    reopen(id) { return this.call('POST', `/conversations/${id}/reopen`); }
    transcript(id, emails) { return this.call('POST', `/conversations/${id}/transcript`, emails ? { emails } : {}); }
    destroy(id) { return this.call('DELETE', `/conversations/${id}`); }
}

const initialState = {
    ready: false,
    config: null,
    conversation: null,
    messages: [],
    open: false,
    unread: 0,
    sending: false,
    awaitingReply: false,
    error: null,
};

/**
 * A visitor chat with the same behaviour as the bundled bubble. The state is
 * replaced (never mutated) on every change, so it plugs straight into
 * React's useSyncExternalStore or a Vue shallowRef.
 */
export class ChatSession {
    /**
     * identity: the signed id from HybridChat::identity($user), so the AI's
     * tools can look up the logged-in user's own records.
     */
    constructor({ api = '/api/chat', name = null, email = null, meta = {}, identity = null, storage = globalThis.localStorage } = {}) {
        this.client = new ChatClient({ api });
        this.visitor = { name, email, meta, identity };
        this.storage = storage;
        this.storageKey = `hybrid-chat:${api}`;
        this.state = initialState;
        this.listeners = new Set();
        this.lastId = 0;
        this.timer = null;
        this.running = false;
        this.onVisibility = () => { if (!document.hidden) this.poll(); };
    }

    subscribe = (listener) => {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    };

    getState = () => this.state;

    set(patch) {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener(this.state));
    }

    /** Load the config and resume a saved conversation. Safe to call twice. */
    async start() {
        if (this.running) return;
        this.running = true;
        globalThis.document?.addEventListener('visibilitychange', this.onVisibility);

        try {
            const config = this.state.config ?? await this.client.config();
            this.set({ config });
            await this.resume();
        } catch (error) {
            this.set({ error: error.message });
        }

        this.set({ ready: true });
        this.schedule();
    }

    stop() {
        this.running = false;
        clearTimeout(this.timer);
        globalThis.document?.removeEventListener('visibilitychange', this.onVisibility);
    }

    setOpen(open) {
        this.set({ open, unread: open ? 0 : this.state.unread });
        if (open && this.state.conversation) this.client.markRead().catch(() => {});
        this.schedule();
    }

    async send(body, visitor = {}) {
        body = body.trim();
        if (!body || this.state.sending) return false;

        const pending = { id: `pending-${Date.now()}`, sender_type: 'visitor', body, pending: true };
        this.set({ sending: true, error: null, messages: [...this.state.messages, pending] });

        try {
            if (!this.state.conversation) {
                const json = await this.client.start({
                    name: visitor.name || this.visitor.name || null,
                    email: visitor.email || this.visitor.email || null,
                    message: body,
                    meta: this.visitor.meta,
                    identity: this.visitor.identity,
                });
                this.save();
                this.ingest(json);
            } else {
                await this.client.send(body);
                await this.poll();
            }
            this.set({ awaitingReply: Boolean(this.state.config?.ai && this.state.conversation?.mode === 'ai') });
            return true;
        } catch (error) {
            this.set({ messages: this.state.messages.filter((m) => m !== pending), error: error.message });
            return false;
        } finally {
            this.set({ sending: false });
            this.schedule();
        }
    }

    async requestHuman() {
        await this.guard(() => this.client.requestHuman());
        await this.poll();
    }

    async emailTranscript(email) {
        return this.guard(() => this.client.transcript(email));
    }

    /** Close the conversation and start fresh next time. */
    async end() {
        await this.guard(() => this.client.close());
        this.forget();
    }

    async guard(fn) {
        try {
            this.set({ error: null });
            return await fn();
        } catch (error) {
            this.set({ error: error.message });
            throw error;
        }
    }

    async resume() {
        const saved = this.load();
        if (!saved) return;

        this.client.id = saved.id;
        this.client.token = saved.token;

        try {
            const json = await this.client.show();
            if (json.conversation.status === 'closed') return this.forget();
            this.ingest(json);
            this.set({ unread: 0 });
        } catch {
            this.forget();
        }
    }

    async poll() {
        if (!this.state.conversation) return;
        try {
            this.ingest(await this.client.messages(this.lastId));
        } catch (error) {
            if (error.status === 404) this.forget();
        }
    }

    ingest(json) {
        const fresh = (json.messages || []).filter((m) => m.id > this.lastId);
        const conversation = json.conversation ?? this.state.conversation;
        const patch = { conversation };

        if (fresh.length) {
            this.lastId = fresh[fresh.length - 1].id;
            const replies = fresh.filter((m) => m.sender_type !== 'visitor');
            patch.messages = [...this.state.messages.filter((m) => !m.pending), ...fresh];
            if (replies.length) patch.awaitingReply = false;
            if (!this.state.open) patch.unread = this.state.unread + replies.filter((m) => m.sender_type !== 'system').length;
        }

        if (conversation?.mode !== 'ai') patch.awaitingReply = false;
        this.set(patch);
    }

    schedule() {
        clearTimeout(this.timer);
        if (!this.running || !this.state.conversation) return;

        const base = this.state.config?.poll_interval || 3000;
        const delay = globalThis.document?.hidden ? base * 10 : this.state.open ? base : base * 5;
        this.timer = setTimeout(() => this.poll().then(() => this.schedule()), delay);
    }

    load() {
        try {
            const saved = JSON.parse(this.storage?.getItem(this.storageKey) || 'null');
            return saved?.id && saved?.token ? saved : null;
        } catch {
            return null;
        }
    }

    save() {
        try {
            this.storage?.setItem(this.storageKey, JSON.stringify({ id: this.client.id, token: this.client.token }));
        } catch {}
    }

    forget() {
        try { this.storage?.removeItem(this.storageKey); } catch {}
        this.client.id = null;
        this.client.token = null;
        this.lastId = 0;
        clearTimeout(this.timer);
        this.set({ conversation: null, messages: [], unread: 0, awaitingReply: false });
    }
}
