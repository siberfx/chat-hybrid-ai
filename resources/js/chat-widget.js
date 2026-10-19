/*!
 * chat-hybrid-ai bubble — no dependencies.
 *
 * <script src="https://your-app.test/api/chat/widget.js" defer></script>
 * Optional data-* attributes: data-api, data-name, data-email, data-color,
 * data-title, data-meta (JSON), data-identity (signed, see HybridChat::identity). window.HybridChat exposes open/close/toggle.
 */
(function () {
    'use strict';

    if (window.HybridChat) return;

    var script = document.currentScript || document.querySelector('script[src*="widget.js"]');
    var ds = (script && script.dataset) || {};
    var API = (ds.api || (script ? script.src.replace(/\/widget\.js.*$/, '') : '/api/chat')).replace(/\/$/, '');
    var STORE = 'hybrid-chat:' + API;

    var state = {
        config: null,
        conversation: null,
        token: null,
        messages: [],
        lastId: 0,
        open: false,
        unread: 0,
        awaitingAi: false,
        timer: null,
        busy: false,
    };

    // ---------- storage ----------
    function load() {
        try {
            var saved = JSON.parse(localStorage.getItem(STORE) || 'null');
            if (saved && saved.id && saved.token) {
                state.conversation = { id: saved.id };
                state.token = saved.token;
            }
        } catch (e) {}
    }

    function save() {
        try {
            if (state.conversation && state.token) {
                localStorage.setItem(STORE, JSON.stringify({ id: state.conversation.id, token: state.token }));
            } else {
                localStorage.removeItem(STORE);
            }
        } catch (e) {}
    }

    // ---------- api ----------
    function api(method, path, body) {
        var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
        if (state.token) headers['X-Chat-Token'] = state.token;

        return fetch(API + path, {
            method: method,
            headers: headers,
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'omit',
        }).then(function (res) {
            if (res.status === 204) return null;
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok) {
                    var err = new Error((json && json.message) || 'Request failed');
                    err.status = res.status;
                    throw err;
                }
                return json;
            });
        });
    }

    function convPath(suffix) {
        return '/conversations/' + state.conversation.id + (suffix || '');
    }

    // ---------- dom ----------
    var host = document.createElement('div');
    host.id = 'hybrid-chat';
    var root = host.attachShadow({ mode: 'open' });

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') node.textContent = attrs[k];
            else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), attrs[k]);
            else node.setAttribute(k, attrs[k]);
        });
        (children || []).forEach(function (c) { if (c) node.appendChild(c); });
        return node;
    }

    var ui = {};

    function build() {
        var c = state.config;
        var color = ds.color || c.color;
        var side = c.position === 'left' ? 'left' : 'right';

        root.appendChild(el('style', { text: css(color, side) }));

        ui.badge = el('span', { class: 'badge', hidden: '' });
        ui.button = el('button', { class: 'launcher', 'aria-label': 'Open chat', onclick: toggle }, [iconChat(), ui.badge]);

        ui.status = el('div', { class: 'status' });
        ui.menuBtn = el('button', { class: 'icon', 'aria-label': 'Menu', onclick: toggleMenu }, [iconDots()]);
        ui.menu = el('div', { class: 'menu', hidden: '' }, [
            ui.humanItem = el('button', { text: 'Talk to a person', onclick: requestHuman }),
            el('button', { text: 'Email me this chat', onclick: showTranscriptForm }),
            el('button', { text: 'End chat', onclick: endChat }),
        ]);

        var header = el('div', { class: 'header' }, [
            el('div', {}, [el('div', { class: 'title', text: ds.title || c.title || 'Chat' }), ui.status]),
            el('div', { class: 'actions' }, [
                ui.menuBtn,
                el('button', { class: 'icon', 'aria-label': 'Close', onclick: toggle }, [iconClose()]),
            ]),
            ui.menu,
        ]);

        ui.list = el('div', { class: 'list', role: 'log', 'aria-live': 'polite' });
        ui.typing = el('div', { class: 'typing', hidden: '' }, [el('span'), el('span'), el('span')]);
        ui.notice = el('div', { class: 'notice', hidden: '' });

        ui.intro = el('form', { class: 'intro', hidden: '', onsubmit: submitIntro }, [
            el('p', { text: c.welcome || '' }),
            ui.nameInput = el('input', { name: 'name', placeholder: 'Your name', maxlength: '120' }),
            ui.emailInput = el('input', { name: 'email', type: 'email', placeholder: c.require_email ? 'Email' : 'Email (optional)', maxlength: '190' }),
        ]);
        if (c.require_email) ui.emailInput.required = true;
        if (ds.name) ui.nameInput.value = ds.name;
        if (ds.email) ui.emailInput.value = ds.email;

        ui.input = el('textarea', { rows: '1', placeholder: 'Write a message…', maxlength: String(c.max_message_length || 4000) });
        ui.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
        ui.input.addEventListener('input', autosize);

        ui.send = el('button', { class: 'send', 'aria-label': 'Send', onclick: send }, [iconSend()]);
        ui.composer = el('div', { class: 'composer' }, [ui.input, ui.send]);

        ui.panel = el('div', { class: 'panel', role: 'dialog', 'aria-label': 'Chat', hidden: '' }, [
            header, ui.intro, ui.list, ui.typing, ui.notice, ui.composer,
        ]);

        root.appendChild(ui.panel);
        root.appendChild(ui.button);
        document.body.appendChild(host);
    }

    function autosize() {
        ui.input.style.height = 'auto';
        ui.input.style.height = Math.min(ui.input.scrollHeight, 120) + 'px';
    }

    function render() {
        var conv = state.conversation;
        var hasConv = conv && conv.status;
        var needsIntro = !hasConv && (state.config.require_email || !ds.name);

        ui.intro.hidden = !(needsIntro && state.messages.length === 0);
        ui.list.hidden = !ui.intro.hidden;
        ui.menuBtn.hidden = !hasConv;

        if (!hasConv && !ui.intro.hidden) {
            ui.status.textContent = 'We usually reply in a few minutes';
        } else if (!hasConv) {
            ui.status.textContent = state.config.ai ? 'Assistant' : 'Support';
        } else if (conv.status === 'closed') {
            ui.status.textContent = 'Conversation closed';
        } else {
            ui.status.textContent = { ai: 'Assistant · a person is one click away', waiting: 'Waiting for a team member…', human: 'Chatting with our team' }[conv.mode] || '';
        }

        ui.humanItem.hidden = !(hasConv && conv.mode === 'ai' && conv.status === 'open');
        ui.typing.hidden = !state.awaitingAi;

        ui.badge.hidden = state.unread === 0 || state.open;
        ui.badge.textContent = state.unread > 9 ? '9+' : String(state.unread);
    }

    function renderMessages(messages, scroll) {
        if (state.messages.length === 0 && messages.length === 0 && ui.list.childElementCount === 0) {
            ui.list.appendChild(bubble({ sender_type: 'ai', body: state.config.welcome || 'Hi! How can we help?' }));
        }
        messages.forEach(function (m) { ui.list.appendChild(bubble(m)); });
        if (scroll !== false) ui.list.scrollTop = ui.list.scrollHeight;
    }

    function bubble(m) {
        if (m.sender_type === 'system') {
            return el('div', { class: 'system', text: m.body });
        }
        var mine = m.sender_type === 'visitor';
        var children = [];
        if (!mine && m.sender_name) children.push(el('div', { class: 'who', text: m.sender_name }));
        children.push(el('div', { class: 'body', text: m.body }));
        return el('div', { class: 'msg ' + (mine ? 'mine' : 'theirs') + (m.pending ? ' pending' : '') }, children);
    }

    function notice(text, ms) {
        ui.notice.textContent = text;
        ui.notice.hidden = false;
        clearTimeout(ui.noticeTimer);
        ui.noticeTimer = setTimeout(function () { ui.notice.hidden = true; }, ms || 4000);
    }

    // ---------- behaviour ----------
    function toggle() {
        state.open = !state.open;
        ui.panel.hidden = !state.open;
        ui.button.setAttribute('aria-label', state.open ? 'Close chat' : 'Open chat');
        if (state.open) {
            state.unread = 0;
            if (state.conversation && state.conversation.status) api('POST', convPath('/read')).catch(function () {});
            setTimeout(function () { (ui.intro.hidden ? ui.input : ui.nameInput).focus(); }, 50);
            ui.list.scrollTop = ui.list.scrollHeight;
        }
        render();
        schedule();
    }

    function toggleMenu() {
        ui.menu.hidden = !ui.menu.hidden;
    }

    function submitIntro(e) {
        e.preventDefault();
        ui.input.focus();
        send();
    }

    function ingest(json) {
        if (json.conversation) state.conversation = json.conversation;
        var fresh = (json.messages || []).filter(function (m) { return m.id > state.lastId; });
        if (fresh.length) {
            state.lastId = fresh[fresh.length - 1].id;
            ui.list.querySelectorAll('.pending').forEach(function (n) { n.remove(); });
            state.messages = state.messages.concat(fresh);
            renderMessages(fresh);
            var replies = fresh.filter(function (m) { return m.sender_type !== 'visitor'; });
            if (replies.length) state.awaitingAi = false;
            if (!state.open) state.unread += replies.filter(function (m) { return m.sender_type !== 'system'; }).length;
        }
        if (state.conversation && state.conversation.mode !== 'ai') state.awaitingAi = false;
        render();
    }

    function send() {
        var body = ui.input.value.trim();
        if (!body || state.busy) return;

        if (!ui.intro.hidden && ui.intro.reportValidity && !ui.intro.reportValidity()) return;

        state.busy = true;
        ui.input.value = '';
        autosize();
        ui.list.hidden = false;
        ui.intro.hidden = true;
        ui.list.appendChild(bubble({ sender_type: 'visitor', body: body, pending: true }));
        ui.list.scrollTop = ui.list.scrollHeight;

        var request;
        if (!state.conversation || !state.conversation.status) {
            var meta = {};
            try { meta = JSON.parse(ds.meta || '{}'); } catch (e) {}
            request = api('POST', '/conversations', {
                name: ui.nameInput.value || ds.name || null,
                email: ui.emailInput.value || ds.email || null,
                message: body,
                meta: meta,
                identity: ds.identity || null,
            }).then(function (json) {
                state.token = json.token;
                state.conversation = json.conversation;
                save();
                ingest(json);
            });
        } else {
            request = api('POST', convPath('/messages'), { body: body }).then(function () { return poll(); });
        }

        request.then(function () {
            state.awaitingAi = state.config.ai && state.conversation && state.conversation.mode === 'ai';
            render();
        }).catch(function (err) {
            ui.list.querySelectorAll('.pending').forEach(function (n) { n.remove(); });
            ui.input.value = body;
            notice(err.message || 'Message not sent. Please try again.');
        }).then(function () {
            state.busy = false;
            schedule();
        });
    }

    function requestHuman() {
        ui.menu.hidden = true;
        api('POST', convPath('/human')).then(poll).catch(function (e) { notice(e.message); });
    }

    function endChat() {
        ui.menu.hidden = true;
        if (!confirm('End this chat?')) return;
        api('POST', convPath('/close')).then(poll).then(function () {
            state.conversation = null;
            state.token = null;
            state.messages = [];
            state.lastId = 0;
            state.awaitingAi = false;
            save();
            ui.list.innerHTML = '';
            renderMessages([]);
            render();
        }).catch(function (e) { notice(e.message); });
    }

    function showTranscriptForm() {
        ui.menu.hidden = true;
        var email = (state.conversation && state.conversation.visitor_email) || '';
        email = prompt('Send the transcript to:', email);
        if (!email) return;
        api('POST', convPath('/transcript'), { email: email })
            .then(function (r) { notice('Transcript sent to ' + r.email); })
            .catch(function (e) { notice(e.message); });
    }

    function poll() {
        if (!state.conversation || !state.token) return Promise.resolve();
        return api('GET', convPath('/messages?after=' + state.lastId)).then(ingest).catch(function (err) {
            if (err.status === 404) {
                state.conversation = null;
                state.token = null;
                save();
            }
        });
    }

    function schedule() {
        clearTimeout(state.timer);
        if (!state.conversation || !state.token) return;
        var base = state.config.poll_interval || 3000;
        var delay = state.open ? base : base * 5;
        if (document.hidden) delay = base * 10;
        state.timer = setTimeout(function () { poll().then(schedule); }, delay);
    }

    function resume() {
        if (!state.conversation || !state.token) {
            renderMessages([]);
            return Promise.resolve();
        }
        return api('GET', convPath()).then(function (json) {
            state.conversation = json.conversation;
            if (json.conversation.status === 'closed') {
                state.conversation = null;
                state.token = null;
                save();
                renderMessages([]);
                return;
            }
            ingest(json);
            state.unread = 0;
        }).catch(function () {
            state.conversation = null;
            state.token = null;
            save();
            renderMessages([]);
        });
    }

    // ---------- icons & styles ----------
    function svg(path) {
        var ns = 'http://www.w3.org/2000/svg';
        var s = document.createElementNS(ns, 'svg');
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS(ns, 'path');
        p.setAttribute('d', path);
        s.appendChild(p);
        return s;
    }
    function iconChat() { return svg('M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z'); }
    function iconClose() { return svg('M6 6l12 12M18 6L6 18'); }
    function iconSend() { return svg('M4 12l16-8-6 16-2-6-8-2z'); }
    function iconDots() { return svg('M5 12h.01M12 12h.01M19 12h.01'); }

    function css(color, side) {
        return [
            ':host{all:initial}',
            '*{box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}',
            'button{font:inherit;cursor:pointer}',
            '[hidden]{display:none!important}',
            'svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}',
            '.launcher{position:fixed;bottom:20px;' + side + ':20px;z-index:2147483000;width:58px;height:58px;border-radius:50%;border:0;background:' + color + ';color:#fff;display:grid;place-items:center;box-shadow:0 8px 24px rgba(0,0,0,.18);transition:transform .15s}',
            '.launcher:hover{transform:scale(1.06)}',
            '.launcher svg{width:26px;height:26px}',
            '.badge{position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#ef4444;color:#fff;font-size:12px;font-weight:600;line-height:20px;text-align:center}',
            '.panel{position:fixed;bottom:90px;' + side + ':20px;z-index:2147483000;width:370px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 120px);background:#fff;color:#111827;border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,.2);display:flex;flex-direction:column;overflow:hidden}',
            '@media (max-width:480px){.panel{inset:0;width:100vw;max-width:none;height:100%;max-height:none;border-radius:0}}',
            '.header{position:relative;display:flex;justify-content:space-between;align-items:center;gap:8px;padding:14px 16px;background:' + color + ';color:#fff}',
            '.title{font-weight:600;font-size:16px}',
            '.status{font-size:12px;opacity:.85;margin-top:2px}',
            '.actions{display:flex;gap:4px}',
            '.icon{background:transparent;border:0;color:#fff;padding:6px;border-radius:8px;display:grid;place-items:center}',
            '.icon:hover{background:rgba(255,255,255,.15)}',
            '.icon svg{width:18px;height:18px}',
            '.menu{position:absolute;top:52px;right:12px;background:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:6px;display:flex;flex-direction:column;min-width:190px;z-index:2}',
            '.menu button{background:none;border:0;text-align:left;padding:9px 12px;border-radius:6px;color:#111827;font-size:14px}',
            '.menu button:hover{background:#f3f4f6}',
            '.intro{flex:1;padding:20px 16px;display:flex;flex-direction:column;gap:10px}',
            '.intro p{margin:0 0 6px;font-size:15px;line-height:1.45}',
            'input,textarea{font:inherit;font-size:14px;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;outline:none;color:#111827;background:#fff;width:100%}',
            'input:focus,textarea:focus{border-color:' + color + ';box-shadow:0 0 0 3px ' + color + '22}',
            '.list{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;background:#f9fafb}',
            '.msg{max-width:82%;display:flex;flex-direction:column}',
            '.msg .body{padding:9px 13px;border-radius:14px;font-size:14px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}',
            '.who{font-size:11px;color:#6b7280;margin:0 0 3px 4px}',
            '.mine{align-self:flex-end}',
            '.mine .body{background:' + color + ';color:#fff;border-bottom-right-radius:4px}',
            '.theirs{align-self:flex-start}',
            '.theirs .body{background:#fff;border:1px solid #e5e7eb;border-bottom-left-radius:4px}',
            '.pending{opacity:.6}',
            '.system{align-self:center;font-size:12px;color:#6b7280;text-align:center;padding:2px 8px}',
            '.typing{display:flex;gap:4px;padding:0 20px 10px;background:#f9fafb}',
            '.typing span{width:7px;height:7px;border-radius:50%;background:#9ca3af;animation:b 1.2s infinite}',
            '.typing span:nth-child(2){animation-delay:.15s}.typing span:nth-child(3){animation-delay:.3s}',
            '@keyframes b{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-4px);opacity:1}}',
            '.notice{font-size:12px;color:#991b1b;background:#fef2f2;padding:6px 16px}',
            '.composer{display:flex;gap:8px;align-items:flex-end;padding:10px 12px;border-top:1px solid #e5e7eb;background:#fff}',
            '.composer textarea{resize:none;max-height:120px}',
            '.send{flex:none;width:40px;height:40px;border-radius:50%;border:0;background:' + color + ';color:#fff;display:grid;place-items:center}',
            '.send svg{width:18px;height:18px}',
        ].join('\n');
    }

    // ---------- boot ----------
    function boot() {
        load();
        api('GET', '/config').then(function (config) {
            state.config = config;
            build();
            return resume();
        }).then(function () {
            render();
            schedule();
        }).catch(function (e) {
            if (window.console) console.warn('[hybrid-chat] failed to start', e);
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) poll().then(schedule);
        });
    }

    window.HybridChat = {
        open: function () { if (!state.open) toggle(); },
        close: function () { if (state.open) toggle(); },
        toggle: function () { toggle(); },
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
