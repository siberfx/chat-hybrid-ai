<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Chat inbox') }} · {{ config('app.name') }}</title>
    <style>
        :root {
            --accent: {{ config('chat-hybrid-ai.widget.color', '#4f46e5') }};
            --bg: #f6f7f9; --panel: #fff; --text: #111827; --muted: #6b7280; --line: #e5e7eb; --soft: #f3f4f6;
            --warn: #b45309; --warn-bg: #fef3c7; --ok: #047857; --ok-bg: #d1fae5; --ai: #4338ca; --ai-bg: #e0e7ff;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b0d12; --panel: #151821; --text: #e5e7eb; --muted: #9ca3af; --line: #262a36; --soft: #1d212c;
                --warn: #fbbf24; --warn-bg: #3a2a07; --ok: #34d399; --ok-bg: #06301f; --ai: #a5b4fc; --ai-bg: #1e1b4b; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        button { font: inherit; cursor: pointer; }
        [hidden] { display: none !important; }
        .app { display: grid; grid-template-columns: 210px 340px 1fr; height: 100vh; }
        .nav { padding: 18px 12px; border-right: 1px solid var(--line); background: var(--panel); }
        .nav h1 { font-size: 16px; margin: 0 8px 16px; }
        .nav button { display: flex; justify-content: space-between; width: 100%; background: none; border: 0; padding: 8px 10px; border-radius: 8px; color: var(--text); text-align: left; }
        .nav button.active, .nav button:hover { background: var(--soft); }
        .nav button.active { font-weight: 600; }
        .count { color: var(--muted); font-size: 12px; }
        .count.hot { background: var(--accent); color: #fff; border-radius: 10px; padding: 0 7px; }
        .list { border-right: 1px solid var(--line); display: flex; flex-direction: column; min-height: 0; background: var(--panel); }
        .search { padding: 12px; border-bottom: 1px solid var(--line); }
        input, textarea { font: inherit; width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--text); outline: none; }
        input:focus, textarea:focus { border-color: var(--accent); }
        .items { overflow-y: auto; flex: 1; }
        .item { display: block; width: 100%; text-align: left; background: none; border: 0; border-bottom: 1px solid var(--line); padding: 12px 14px; color: var(--text); }
        .item:hover { background: var(--soft); }
        .item.active { background: var(--soft); box-shadow: inset 3px 0 0 var(--accent); }
        .row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .time { color: var(--muted); font-size: 12px; flex: none; }
        .preview { color: var(--muted); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 3px; }
        .unread .name::after { content: ""; display: inline-block; width: 8px; height: 8px; background: var(--accent); border-radius: 50%; margin-left: 6px; vertical-align: middle; }
        .pill { font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: .02em; }
        .pill.ai { background: var(--ai-bg); color: var(--ai); }
        .pill.waiting { background: var(--warn-bg); color: var(--warn); }
        .pill.human { background: var(--ok-bg); color: var(--ok); }
        .pill.closed { background: var(--soft); color: var(--muted); }
        .chat { display: flex; flex-direction: column; min-height: 0; }
        .empty { margin: auto; color: var(--muted); }
        .chat-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--line); background: var(--panel); flex-wrap: wrap; }
        .chat-head .sub { color: var(--muted); font-size: 12px; }
        .tools { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn { border: 1px solid var(--line); background: var(--panel); color: var(--text); padding: 6px 12px; border-radius: 8px; }
        .btn:hover { background: var(--soft); }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn.danger { color: #dc2626; }
        .body { display: grid; grid-template-columns: 1fr 240px; flex: 1; min-height: 0; }
        .messages { overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 8px; }
        .msg { max-width: 72%; }
        .msg .who { font-size: 11px; color: var(--muted); margin: 0 4px 3px; }
        .msg .text { padding: 9px 13px; border-radius: 14px; white-space: pre-wrap; word-wrap: break-word; }
        .msg.visitor { align-self: flex-start; }
        .msg.visitor .text { background: var(--panel); border: 1px solid var(--line); border-bottom-left-radius: 4px; }
        .msg.agent, .msg.ai { align-self: flex-end; }
        .msg.agent .text { background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
        .msg.ai .text { background: var(--ai-bg); color: var(--text); border-bottom-right-radius: 4px; }
        .msg.agent .who, .msg.ai .who { text-align: right; }
        .system { align-self: center; font-size: 12px; color: var(--muted); }
        .msg .tool { font-size: 11px; color: var(--muted); margin: 3px 4px 0; text-align: right; font-family: ui-monospace, monospace; word-break: break-all; }
        .details { border-left: 1px solid var(--line); padding: 16px; overflow-y: auto; background: var(--panel); font-size: 13px; }
        .details dt { color: var(--muted); font-size: 12px; margin-top: 10px; }
        .details dd { margin: 2px 0 0; word-break: break-word; }
        .composer { padding: 12px 18px; border-top: 1px solid var(--line); background: var(--panel); }
        .composer .row { align-items: flex-end; }
        .composer textarea { resize: vertical; min-height: 44px; max-height: 200px; }
        .hint { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--text); color: var(--bg); padding: 8px 16px; border-radius: 8px; }
        @media (max-width: 1100px) { .body { grid-template-columns: 1fr; } .details { display: none; } }
        @media (max-width: 800px) {
            .app { grid-template-columns: 1fr; }
            .nav { display: flex; gap: 4px; overflow-x: auto; padding: 8px; border-right: 0; border-bottom: 1px solid var(--line); }
            .nav h1 { display: none; }
            .nav button { width: auto; white-space: nowrap; gap: 6px; }
            .app.has-chat .list, .app:not(.has-chat) .chat { display: none; }
        }
    </style>
</head>
<body>
<div class="app" id="app">
    <nav class="nav" id="filters">
        <h1>{{ __('Chat inbox') }}</h1>
        <button data-filter="waiting">{{ __('Needs a human') }} <span class="count" data-count="waiting"></span></button>
        <button data-filter="open">{{ __('All open') }} <span class="count" data-count="open"></span></button>
        <button data-filter="mine">{{ __('Mine') }} <span class="count" data-count="human"></span></button>
        @if($aiEnabled)
            <button data-filter="ai">{{ __('With AI') }} <span class="count" data-count="ai"></span></button>
        @endif
        <button data-filter="unread">{{ __('Unread') }} <span class="count" data-count="unread"></span></button>
        <button data-filter="closed">{{ __('Closed') }}</button>
    </nav>

    <section class="list">
        <div class="search"><input id="search" type="search" placeholder="{{ __('Search name, email, message…') }}"></div>
        <div class="items" id="items"></div>
    </section>

    <section class="chat" id="chat">
        <div class="empty" id="empty">{{ __('Select a conversation') }}</div>
        <div class="chat-head" id="head" hidden>
            <div>
                <div class="row" style="justify-content:flex-start"><strong id="c-name"></strong> <span id="c-mode" class="pill"></span></div>
                <div class="sub" id="c-sub"></div>
            </div>
            <div class="tools">
                <button class="btn" id="back" hidden>&larr;</button>
                <button class="btn primary" id="take-over">{{ __('Take over') }}</button>
                @if($aiEnabled)
                    <button class="btn" id="return-ai">{{ __('Hand back to AI') }}</button>
                @endif
                <button class="btn" id="transcript">{{ __('Email transcript') }}</button>
                <button class="btn" id="close">{{ __('Close') }}</button>
                <button class="btn" id="reopen">{{ __('Reopen') }}</button>
                <button class="btn danger" id="delete">{{ __('Delete') }}</button>
            </div>
        </div>
        <div class="body" id="body" hidden>
            <div style="display:flex;flex-direction:column;min-height:0">
                <div class="messages" id="messages"></div>
                <form class="composer" id="composer">
                    <div class="row">
                        <textarea id="reply" placeholder="{{ __('Reply… (Enter to send, Shift+Enter for a new line)') }}"></textarea>
                        <button class="btn primary" type="submit">{{ __('Send') }}</button>
                    </div>
                    <div class="hint" id="hint"></div>
                </form>
            </div>
            <dl class="details" id="details"></dl>
        </div>
    </section>
</div>
<div class="toast" id="toast" hidden></div>

<script>
(function () {
    var BASE = @json(url(config('chat-hybrid-ai.routes.admin.prefix')).'/api');
    var POLL = {{ $pollInterval }};
    var CSRF = document.querySelector('meta[name=csrf-token]').content;
    @php
    $strings = [
        'visitor' => __('Visitor'),
        'aiHint' => __('The AI is answering. Sending a reply takes the conversation over.'),
        'waitingHint' => __('The visitor is waiting for a person.'),
        'closedHint' => __('Closed. Replying reopens the conversation.'),
        'confirmDelete' => __('Delete this conversation permanently?'),
        'sendTo' => __('Send the transcript to (comma separated):'),
        'sent' => __('Transcript sent'),
        'none' => __('No conversations'),
        'modes' => ['ai' => __('AI'), 'waiting' => __('Waiting'), 'human' => __('Human'), 'closed' => __('Closed')],
    ];
    @endphp
    var T = @json($strings);

    var $ = function (id) { return document.getElementById(id); };
    var state = { filter: 'waiting', search: '', items: [], current: null, lastId: 0, timer: null };

    function api(method, path, body) {
        return fetch(BASE + path, {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (r) {
            if (r.status === 204) return null;
            return r.json().then(function (j) {
                if (!r.ok) throw new Error(j.message || r.statusText);
                return j;
            });
        });
    }

    function toast(text) {
        var t = $('toast');
        t.textContent = text;
        t.hidden = false;
        clearTimeout(t._h);
        t._h = setTimeout(function () { t.hidden = true; }, 3000);
    }

    function ago(iso) {
        if (!iso) return '';
        var s = (Date.now() - new Date(iso)) / 1000;
        if (s < 60) return 'now';
        if (s < 3600) return Math.floor(s / 60) + 'm';
        if (s < 86400) return Math.floor(s / 3600) + 'h';
        return new Date(iso).toLocaleDateString();
    }

    function text(tag, cls, value) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (value != null) n.textContent = value;
        return n;
    }

    function modeOf(c) { return c.status === 'closed' ? 'closed' : c.mode; }

    // ---------- list ----------
    function query() {
        var q = { waiting: 'status=open&mode=waiting', open: 'status=open', mine: 'status=open&mine=1', ai: 'status=open&mode=ai', unread: 'status=open&unread=1', closed: 'status=closed' }[state.filter];
        if (state.search) q += '&search=' + encodeURIComponent(state.search);
        return q;
    }

    function loadList() {
        return Promise.all([
            api('GET', '/conversations?' + query()).then(function (r) { state.items = r.data; renderList(); }),
            api('GET', '/stats').then(renderStats),
        ]).catch(function (e) { toast(e.message); });
    }

    function renderStats(s) {
        document.querySelectorAll('[data-count]').forEach(function (n) {
            var v = s[n.dataset.count] || 0;
            n.textContent = v || '';
            n.classList.toggle('hot', n.dataset.count === 'waiting' && v > 0);
        });
        document.title = (s.waiting ? '(' + s.waiting + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
    }

    function renderList() {
        var box = $('items');
        box.innerHTML = '';
        if (!state.items.length) {
            box.appendChild(text('div', 'empty', T.none)).style.padding = '24px';
            return;
        }
        state.items.forEach(function (c) {
            var b = text('button', 'item' + (c.unread ? ' unread' : '') + (state.current && state.current.id === c.id ? ' active' : ''));
            var r1 = text('div', 'row');
            r1.appendChild(text('span', 'name', c.visitor_name || c.visitor_email || T.visitor));
            r1.appendChild(text('span', 'time', ago(c.last_message_at)));
            var r2 = text('div', 'row');
            r2.appendChild(text('span', 'preview', c.latest_message ? c.latest_message.body : ''));
            var m = modeOf(c);
            r2.appendChild(text('span', 'pill ' + m, T.modes[m]));
            b.appendChild(r1);
            b.appendChild(r2);
            b.onclick = function () { location.hash = c.id; };
            box.appendChild(b);
        });
    }

    // ---------- conversation ----------
    function open(id) {
        if (!id) return close();
        state.lastId = 0;
        $('messages').innerHTML = '';
        $('app').classList.add('has-chat');
        api('GET', '/conversations/' + id).then(function (r) {
            state.current = r.conversation;
            $('empty').hidden = true;
            $('head').hidden = false;
            $('body').hidden = false;
            renderHead();
            append(r.messages);
            renderList();
            $('reply').focus();
        }).catch(function (e) { toast(e.message); close(); });
    }

    function close() {
        state.current = null;
        $('app').classList.remove('has-chat');
        $('empty').hidden = false;
        $('head').hidden = true;
        $('body').hidden = true;
        renderList();
    }

    function renderHead() {
        var c = state.current, m = modeOf(c);
        $('c-name').textContent = c.visitor_name || c.visitor_email || T.visitor;
        $('c-mode').className = 'pill ' + m;
        $('c-mode').textContent = T.modes[m] + (c.assignee && c.mode === 'human' ? ' · ' + c.assignee.name : '');
        $('c-sub').textContent = [c.visitor_email, c.subject].filter(Boolean).join(' · ');
        $('take-over').hidden = c.mode === 'human' || c.status === 'closed';
        if ($('return-ai')) $('return-ai').hidden = c.mode === 'ai' || c.status === 'closed';
        $('close').hidden = c.status === 'closed';
        $('reopen').hidden = c.status !== 'closed';
        $('back').hidden = window.innerWidth > 800;
        $('hint').textContent = c.status === 'closed' ? T.closedHint : c.mode === 'ai' ? T.aiHint : c.mode === 'waiting' ? T.waitingHint : '';

        var d = $('details');
        d.innerHTML = '';
        var rows = [['ID', c.id], ['Email', c.visitor_email], ['Signed in as', c.visitor_id ? 'user #' + c.visitor_id : ''], ['Started', new Date(c.created_at).toLocaleString()]];
        Object.keys(c.meta || {}).forEach(function (k) { rows.push([k, c.meta[k]]); });
        rows.forEach(function (r) {
            if (!r[1]) return;
            d.appendChild(text('dt', null, r[0]));
            d.appendChild(text('dd', null, String(r[1])));
        });
    }

    function append(messages) {
        var box = $('messages');
        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        messages.forEach(function (m) {
            if (m.id <= state.lastId) return;
            state.lastId = m.id;
            if (m.sender_type === 'system') {
                box.appendChild(text('div', 'system', m.body));
                return;
            }
            var wrap = text('div', 'msg ' + m.sender_type);
            wrap.appendChild(text('div', 'who', m.sender_name + ' · ' + new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })));
            wrap.appendChild(text('div', 'text', m.body));
            (m.tools || []).forEach(function (t) {
                var args = Object.keys(t.arguments || {}).map(function (k) { return k + ': ' + JSON.stringify(t.arguments[k]); }).join(', ');
                wrap.appendChild(text('div', 'tool', '🔎 ' + t.name + '(' + args + ')'));
            });
            box.appendChild(wrap);
        });
        if (atBottom || messages.length) box.scrollTop = box.scrollHeight;
    }

    function pollCurrent() {
        if (!state.current) return Promise.resolve();
        return api('GET', '/conversations/' + state.current.id + '/messages?after=' + state.lastId).then(function (r) {
            state.current = r.conversation;
            renderHead();
            append(r.messages);
        });
    }

    function action(path, body) {
        return api('POST', '/conversations/' + state.current.id + path, body).then(function () {
            return Promise.all([pollCurrent(), loadList()]);
        }).catch(function (e) { toast(e.message); });
    }

    // ---------- events ----------
    $('composer').addEventListener('submit', function (e) {
        e.preventDefault();
        var body = $('reply').value.trim();
        if (!body || !state.current) return;
        $('reply').value = '';
        action('/messages', { body: body });
    });
    $('reply').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('composer').requestSubmit(); }
    });
    $('take-over').onclick = function () { action('/take-over'); };
    if ($('return-ai')) $('return-ai').onclick = function () { action('/return-to-ai'); };
    $('close').onclick = function () { action('/close'); };
    $('reopen').onclick = function () { action('/reopen'); };
    $('back').onclick = function () { history.pushState('', document.title, location.pathname); close(); };
    $('transcript').onclick = function () {
        var to = prompt(T.sendTo, state.current.visitor_email || '');
        if (!to) return;
        var emails = to.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        api('POST', '/conversations/' + state.current.id + '/transcript', { emails: emails })
            .then(function () { toast(T.sent); }).catch(function (e) { toast(e.message); });
    };
    $('delete').onclick = function () {
        if (!confirm(T.confirmDelete)) return;
        api('DELETE', '/conversations/' + state.current.id).then(function () {
            history.pushState('', document.title, location.pathname);
            close();
            loadList();
        }).catch(function (e) { toast(e.message); });
    };
    document.querySelectorAll('[data-filter]').forEach(function (b) {
        b.onclick = function () {
            state.filter = b.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(function (x) { x.classList.toggle('active', x === b); });
            loadList();
        };
    });
    var searchTimer;
    $('search').addEventListener('input', function (e) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.search = e.target.value.trim(); loadList(); }, 300);
    });
    window.addEventListener('hashchange', function () { open(location.hash.slice(1)); });

    function tick() {
        clearTimeout(state.timer);
        Promise.all([loadList(), pollCurrent()]).catch(function () {}).then(function () {
            state.timer = setTimeout(tick, document.hidden ? POLL * 5 : POLL);
        });
    }

    document.querySelector('[data-filter="' + state.filter + '"]').classList.add('active');
    if (location.hash) open(location.hash.slice(1));
    tick();
})();
</script>
</body>
</html>
