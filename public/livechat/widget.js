/**
 * FusterAI Live Chat Widget
 *
 * Embed: <script src="/livechat/widget.js"></script>
 * Config: window.FusterAIChat = { workspaceId: 1, wsKey: 'your-key', wsHost: 'localhost', wsPort: 6001 }
 */
(function () {
    'use strict';

    // -- Config ----------------------------------------------------------------

    var config = window.FusterAIChat || {};
    var workspaceId = config.workspaceId || null;
    var wsKey       = config.wsKey       || '';
    var wsHost      = config.wsHost      || window.location.hostname;
    var wsPort      = config.wsPort      || 6001;
    var wsScheme    = config.wsScheme    || (window.location.protocol === 'https:' ? 'https' : 'http');
    var apiBase     = config.apiBase     || '';

    if (!workspaceId) {
        console.warn('[FusterAI Chat] No workspaceId configured. Set window.FusterAIChat.workspaceId.');
    }

    // -- Branding (loaded from workspace settings) -----------------------------

    var brandColor    = '#7c3aed';
    var brandGreeting = 'Hi there! How can we help?';
    var brandPosition = 'bottom-right';
    var brandLauncher = 'Chat with us';

    function applyBranding() {
        // Button color
        btn.style.background = brandColor;

        // Header color
        var header = document.getElementById('fusterai-chat-header');
        if (header) header.style.background = brandColor;

        // Header text
        if (header) header.textContent = brandGreeting;

        // Send button color
        var sendButton = document.getElementById('fusterai-chat-send');
        if (sendButton) sendButton.style.background = brandColor;

        // Input focus color via CSS var not easily settable; skip for now

        // Position
        var isLeft = brandPosition === 'bottom-left';
        btn.style.right  = isLeft ? ''   : '24px';
        btn.style.left   = isLeft ? '24px' : '';
        panel.style.right = isLeft ? ''   : '24px';
        panel.style.left  = isLeft ? '24px' : '';

        // Launcher text is shown on the button only when panel is not a plain icon
        // (icon-only button, so we skip text injection here)
    }

    function fetchBranding() {
        if (!workspaceId || !apiBase) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiBase + '/api/livechat/config?workspace_id=' + workspaceId, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var cfg = JSON.parse(xhr.responseText);
                    if (cfg.color)         brandColor    = cfg.color;
                    if (cfg.greeting)      brandGreeting = cfg.greeting;
                    if (cfg.position)      brandPosition = cfg.position;
                    if (cfg.launcher_text) brandLauncher = cfg.launcher_text;
                    applyBranding();
                } catch (e) {}
            }
        };
        xhr.send();
    }

    // -- Visitor Identity ------------------------------------------------------

    var LS_KEY_ID    = 'fusterai_visitor_id';
    var LS_KEY_NAME  = 'fusterai_visitor_name';
    var LS_KEY_EMAIL = 'fusterai_visitor_email';
    var LS_KEY_CONV  = 'fusterai_conversation_id';

    function generateId() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        // Fallback for older browsers
        var buf = new Uint8Array(16);
        window.crypto.getRandomValues(buf);
        buf[6] = (buf[6] & 0x0f) | 0x40;
        buf[8] = (buf[8] & 0x3f) | 0x80;
        return Array.from(buf).map(function (b, i) {
            var hex = b.toString(16).padStart(2, '0');
            return [4, 6, 8, 10].indexOf(i) !== -1 ? '-' + hex : hex;
        }).join('');
    }

    var visitorId    = localStorage.getItem(LS_KEY_ID)    || generateId();
    var visitorName  = localStorage.getItem(LS_KEY_NAME)  || 'Visitor';
    var visitorEmail = localStorage.getItem(LS_KEY_EMAIL) || null;
    var conversationId = localStorage.getItem(LS_KEY_CONV) || null;

    localStorage.setItem(LS_KEY_ID, visitorId);

    // -- Styles ----------------------------------------------------------------

    var css = [
        '#fusterai-chat-btn {',
        '  position: fixed; bottom: 24px; right: 24px; z-index: 9999;',
        '  width: 56px; height: 56px; border-radius: 50%;',
        '  background: #4F46E5; border: none; cursor: pointer;',
        '  display: flex; align-items: center; justify-content: center;',
        '  box-shadow: 0 4px 14px rgba(0,0,0,0.25);',
        '  transition: transform 0.2s; outline: none;',
        '}',
        '#fusterai-chat-btn:hover { transform: scale(1.1); }',
        '#fusterai-chat-btn svg { width: 26px; height: 26px; fill: #fff; }',
        '#fusterai-chat-panel {',
        '  position: fixed; bottom: 92px; right: 24px; z-index: 9998;',
        '  width: 340px; max-height: 520px;',
        '  background: #fff; border-radius: 12px;',
        '  box-shadow: 0 8px 30px rgba(0,0,0,0.18);',
        '  display: flex; flex-direction: column; overflow: hidden;',
        '  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;',
        '  font-size: 14px;',
        '}',
        '#fusterai-chat-panel.hidden { display: none; }',
        '#fusterai-chat-header {',
        '  background: #4F46E5; color: #fff; padding: 16px;',
        '  font-size: 16px; font-weight: 600;',
        '}',
        '#fusterai-chat-messages {',
        '  flex: 1; overflow-y: auto; padding: 12px;',
        '  display: flex; flex-direction: column; gap: 8px;',
        '}',
        '.fusterai-msg {',
        '  max-width: 80%; padding: 8px 12px; border-radius: 10px;',
        '  line-height: 1.4; word-wrap: break-word;',
        '}',
        '.fusterai-msg-visitor {',
        '  align-self: flex-end; background: #4F46E5; color: #fff;',
        '  border-bottom-right-radius: 2px;',
        '}',
        '.fusterai-msg-agent {',
        '  align-self: flex-start; background: #F3F4F6; color: #111;',
        '  border-bottom-left-radius: 2px;',
        '}',
        '.fusterai-msg-meta {',
        '  font-size: 11px; opacity: 0.65; margin-top: 2px;',
        '}',
        '#fusterai-chat-footer {',
        '  border-top: 1px solid #E5E7EB; padding: 10px 12px;',
        '  display: flex; gap: 8px; align-items: flex-end;',
        '}',
        '#fusterai-chat-input {',
        '  flex: 1; resize: none; border: 1px solid #D1D5DB; border-radius: 8px;',
        '  padding: 8px 10px; font-size: 14px; outline: none;',
        '  font-family: inherit; max-height: 100px;',
        '}',
        '#fusterai-chat-input:focus { border-color: #4F46E5; }',
        '#fusterai-chat-send {',
        '  background: #4F46E5; color: #fff; border: none;',
        '  border-radius: 8px; padding: 8px 14px; cursor: pointer;',
        '  font-size: 14px; white-space: nowrap;',
        '}',
        '#fusterai-chat-send:hover { background: #4338CA; }',
        '#fusterai-chat-send:disabled { opacity: 0.5; cursor: default; }',
        '#fusterai-name-prompt {',
        '  padding: 16px; display: flex; flex-direction: column; gap: 10px;',
        '}',
        '#fusterai-name-prompt input {',
        '  border: 1px solid #D1D5DB; border-radius: 8px; padding: 8px 10px;',
        '  font-size: 14px; outline: none; font-family: inherit;',
        '}',
        '#fusterai-name-prompt input:focus { border-color: #4F46E5; }',
        '#fusterai-name-prompt button {',
        '  background: #4F46E5; color: #fff; border: none; border-radius: 8px;',
        '  padding: 9px; cursor: pointer; font-size: 14px;',
        '}',
    ].join('\n');

    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    // -- DOM -------------------------------------------------------------------

    // Toggle button
    var btn = document.createElement('button');
    btn.id = 'fusterai-chat-btn';
    btn.setAttribute('aria-label', 'Open chat');
    btn.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
        + '<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>'
        + '</svg>';
    document.body.appendChild(btn);

    // Unread badge on the launcher button
    var badge = document.createElement('span');
    badge.id = 'fusterai-unread-badge';
    badge.style.cssText = [
        'position:absolute;top:-4px;right:-4px;',
        'background:#EF4444;color:#fff;',
        'font-size:11px;font-weight:700;line-height:1;',
        'min-width:18px;height:18px;border-radius:9px;',
        'display:none;align-items:center;justify-content:center;',
        'padding:0 4px;box-shadow:0 0 0 2px #fff;',
        'pointer-events:none;',
    ].join('');
    btn.appendChild(badge);

    var unreadCount = 0;

    function incrementUnread() {
        unreadCount++;
        badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
        badge.style.display = 'flex';
    }

    function clearUnread() {
        unreadCount = 0;
        badge.style.display = 'none';
    }

    function openPanel() {
        panel.classList.remove('hidden');
        clearUnread();
        if (conversationId && !livechatChannel) {
            loadHistory(conversationId);
            subscribeToConversation(conversationId);
        }
        if (!localStorage.getItem(LS_KEY_NAME) || !localStorage.getItem(LS_KEY_EMAIL)) {
            showNamePrompt();
        }
        inputEl.focus();
    }

    // Chat panel
    var panel = document.createElement('div');
    panel.id = 'fusterai-chat-panel';
    panel.className = 'hidden';
    panel.innerHTML = [
        '<div id="fusterai-chat-header">Chat with us</div>',
        '<div id="fusterai-chat-messages"></div>',
        '<div id="fusterai-chat-footer">',
        '  <textarea id="fusterai-chat-input" placeholder="Type a message..." rows="1"></textarea>',
        '  <button id="fusterai-chat-send">Send</button>',
        '</div>',
    ].join('');
    document.body.appendChild(panel);

    var messagesEl = document.getElementById('fusterai-chat-messages');
    var inputEl    = document.getElementById('fusterai-chat-input');
    var sendBtn    = document.getElementById('fusterai-chat-send');

    // Fetch workspace branding and apply it
    fetchBranding();

    // -- Name prompt (shown the first time) ------------------------------------

    function showNamePrompt() {
        var footer = document.getElementById('fusterai-chat-footer');
        footer.style.display = 'none';

        var prompt = document.createElement('div');
        prompt.id = 'fusterai-name-prompt';
        prompt.innerHTML = [
            '<div style="font-weight:600;color:#374151">Before we start...</div>',
            '<input id="fusterai-name-input" type="text" placeholder="Your name" maxlength="100" value="' + (localStorage.getItem(LS_KEY_NAME) !== 'Visitor' ? (localStorage.getItem(LS_KEY_NAME) || '') : '') + '"/>',
            '<input id="fusterai-email-input" type="email" placeholder="Your email address" maxlength="255" value="' + (localStorage.getItem(LS_KEY_EMAIL) || '') + '"/>',
            '<button id="fusterai-name-submit">Start chat</button>',
        ].join('');
        panel.appendChild(prompt);

        document.getElementById('fusterai-name-submit').addEventListener('click', function () {
            var name  = (document.getElementById('fusterai-name-input').value  || '').trim();
            var email = (document.getElementById('fusterai-email-input').value || '').trim();

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('fusterai-email-input').style.borderColor = '#EF4444';
                return;
            }

            visitorName  = name || 'Visitor';
            visitorEmail = email;
            localStorage.setItem(LS_KEY_NAME,  visitorName);
            localStorage.setItem(LS_KEY_EMAIL, visitorEmail);
            panel.removeChild(prompt);
            footer.style.display = '';
            inputEl.focus();
        });
    }

    // -- Message rendering -----------------------------------------------------

    function appendMessage(text, side, authorLabel) {
        var wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.flexDirection = 'column';
        wrap.style.alignItems = side === 'visitor' ? 'flex-end' : 'flex-start';

        var bubble = document.createElement('div');
        bubble.className = 'fusterai-msg fusterai-msg-' + side;
        bubble.textContent = text;

        var meta = document.createElement('div');
        meta.className = 'fusterai-msg-meta';
        meta.textContent = authorLabel || (side === 'visitor' ? 'You' : 'Support');

        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // -- Typing indicator (agent → visitor) -----------------------------------

    var typingEl = null;
    var agentTypingTimer = null;

    function showAgentTyping(agentName) {
        if (!typingEl) {
            typingEl = document.createElement('div');
            typingEl.id = 'fusterai-typing';
            typingEl.style.cssText = 'display:flex;flex-direction:column;align-items:flex-start;';
            typingEl.innerHTML = [
                '<div style="display:flex;align-items:center;gap:3px;background:#F3F4F6;padding:8px 12px;border-radius:10px;border-bottom-left-radius:2px;">',
                '  <span class="fusterai-dot"></span>',
                '  <span class="fusterai-dot"></span>',
                '  <span class="fusterai-dot"></span>',
                '</div>',
                '<div class="fusterai-msg-meta" id="fusterai-typing-label"></div>',
            ].join('');

            // Inject dot animation CSS once
            if (!document.getElementById('fusterai-dot-style')) {
                var dotStyle = document.createElement('style');
                dotStyle.id = 'fusterai-dot-style';
                dotStyle.textContent = [
                    '.fusterai-dot {',
                    '  width:6px;height:6px;border-radius:50%;background:#9CA3AF;',
                    '  animation:fusterai-bounce 1s infinite;display:inline-block;',
                    '}',
                    '.fusterai-dot:nth-child(2){animation-delay:0.15s;}',
                    '.fusterai-dot:nth-child(3){animation-delay:0.3s;}',
                    '@keyframes fusterai-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-5px)}}',
                ].join('\n');
                document.head.appendChild(dotStyle);
            }
        }

        document.getElementById('fusterai-typing-label').textContent = (agentName || 'Agent') + ' is typing…';

        if (!typingEl.parentNode) {
            messagesEl.appendChild(typingEl);
        }

        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (agentTypingTimer) clearTimeout(agentTypingTimer);
        agentTypingTimer = setTimeout(hideAgentTyping, 3000);
    }

    function hideAgentTyping() {
        if (typingEl && typingEl.parentNode) {
            typingEl.parentNode.removeChild(typingEl);
        }
        agentTypingTimer = null;
    }

    // -- Visitor typing broadcast (visitor → agent) ---------------------------

    var visitorTypingTimer = null;
    var visitorTypingPending = false;

    function sendVisitorTyping() {
        if (!conversationId || !apiBase) return;
        if (visitorTypingPending) return; // debounce: send at most once per 2s

        visitorTypingPending = true;
        visitorTypingTimer = setTimeout(function () { visitorTypingPending = false; }, 2000);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', apiBase + '/api/livechat/typing', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(JSON.stringify({ conversation_id: conversationId }));
    }

    // -- Pusher / Reverb subscription -----------------------------------------

    var echoInstance = null;
    var livechatChannel = null;

    function loadPusherJs(callback) {
        if (window.Pusher) {
            callback();
            return;
        }
        var s = document.createElement('script');
        s.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
        s.onload = callback;
        document.head.appendChild(s);
    }

    function subscribeToConversation(convId) {
        if (livechatChannel) {
            return; // already subscribed
        }

        loadPusherJs(function () {
            var pusher = new window.Pusher(wsKey, {
                wsHost:            wsHost,
                wsPort:            wsPort,
                wssPort:           wsPort,
                forceTLS:          wsScheme === 'https',
                disableStats:      true,
                enabledTransports: ['ws', 'wss'],
                cluster:           'mt1', // required by Pusher.js but ignored by Reverb
            });

            livechatChannel = pusher.subscribe('livechat.' + convId);

            livechatChannel.bind('thread.created', function (data) {
                if (!data || !data.thread) return;
                var thread = data.thread;
                // Only show agent replies (not our own visitor messages)
                if (thread.customer_id) return;
                var name = (thread.user && thread.user.name) ? thread.user.name : 'Support';

                hideAgentTyping();

                var isPanelOpen = !panel.classList.contains('hidden');
                if (isPanelOpen) {
                    appendMessage(thread.body, 'agent', name);
                } else {
                    // Auto-pop the panel open so the visitor sees the reply
                    openPanel();
                    appendMessage(thread.body, 'agent', name);
                    incrementUnread();
                }
            });

            livechatChannel.bind('agent.typing', function (data) {
                var name = (data && data.agent_name) ? data.agent_name : 'Agent';
                if (!panel.classList.contains('hidden')) {
                    showAgentTyping(name);
                }
            });
        });
    }

    // -- Load message history --------------------------------------------------

    function loadHistory(convId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiBase + '/api/livechat/messages?conversation_id=' + convId + '&visitor_id=' + encodeURIComponent(visitorId), true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.threads && resp.threads.length) {
                        messagesEl.innerHTML = '';
                        resp.threads.forEach(function (thread) {
                            var isVisitor = !!thread.customer_id;
                            var side      = isVisitor ? 'visitor' : 'agent';
                            var label     = isVisitor
                                ? (thread.customer && thread.customer.name ? thread.customer.name : visitorName)
                                : (thread.user && thread.user.name ? thread.user.name : 'Support');
                            appendMessage(thread.body, side, label);
                        });
                    }
                } catch (e) {}
            }
        };
        xhr.send();
    }

    // -- Sending a message -----------------------------------------------------

    function sendMessage() {
        var text = inputEl.value.trim();
        if (!text) return;

        inputEl.value = '';
        inputEl.style.height = '';
        sendBtn.disabled = true;

        appendMessage(text, 'visitor', visitorName);

        var body = JSON.stringify({
            workspace_id:  workspaceId,
            visitor_id:    visitorId,
            visitor_name:  visitorName,
            visitor_email: visitorEmail,
            message:       text,
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', apiBase + '/api/livechat/message', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Attach CSRF token if present in meta
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfMeta.getAttribute('content'));
        }

        xhr.onload = function () {
            sendBtn.disabled = false;
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.conversation_id && !conversationId) {
                        conversationId = resp.conversation_id;
                        localStorage.setItem(LS_KEY_CONV, conversationId);
                        subscribeToConversation(conversationId);
                    }
                } catch (e) {}
            } else {
                appendMessage('Sorry, failed to send your message. Please try again.', 'agent', 'System');
            }
        };

        xhr.onerror = function () {
            sendBtn.disabled = false;
            appendMessage('Network error. Please check your connection.', 'agent', 'System');
        };

        xhr.send(body);
    }

    // -- Event listeners -------------------------------------------------------

    btn.addEventListener('click', function () {
        if (panel.classList.contains('hidden')) {
            openPanel();
        } else {
            panel.classList.add('hidden');
            clearUnread();
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-grow textarea + broadcast typing indicator
    inputEl.addEventListener('input', function () {
        inputEl.style.height = '';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 100) + 'px';
        sendVisitorTyping();
    });

}());
