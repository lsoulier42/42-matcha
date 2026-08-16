/**
 * Matcha — JS applicatif.
 *  - menu mobile
 *  - localisation : consentement GPS explicite (page profil)
 *  - autocomplétion des tags existants (page profil)
 *  - polling temps réel (badges, chat, notifications) — phase 6
 */
(function () {
    'use strict';

    /* ---------- Menu mobile ---------- */
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---------- Géolocalisation (consentement explicite) ---------- */
    const btnGps = document.getElementById('btn-gps');
    if (btnGps) {
        btnGps.addEventListener('click', function () {
            if (!('geolocation' in navigator)) {
                alert('La géolocalisation n\'est pas supportée par ce navigateur. Saisissez votre ville manuellement.');
                return;
            }
            btnGps.disabled = true;
            btnGps.textContent = 'Localisation en cours…';

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    document.getElementById('lat').value = position.coords.latitude;
                    document.getElementById('lng').value = position.coords.longitude;
                    document.getElementById('gps_consent').value = '1';
                    btnGps.textContent = '📍 Position GPS obtenue ✓';
                    btnGps.classList.add('btn-success');
                    const ville = document.getElementById('ville');
                    if (ville && ville.value.trim() === '') {
                        ville.placeholder = 'Votre ville / quartier (optionnel avec le GPS)';
                    }
                },
                function () {
                    alert('Position refusée ou indisponible. Vous pouvez saisir votre ville manuellement : elle est alors obligatoire pour le matching.');
                    btnGps.disabled = false;
                    btnGps.textContent = '📍 Utiliser ma position GPS';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    /* ---------- Autocomplétion des tags existants ---------- */
    const tagInput = document.getElementById('tag-input');
    const tagSuggestions = document.getElementById('tag-suggestions');

    if (tagInput && tagSuggestions) {
        let debounce = null;

        tagInput.addEventListener('input', function () {
            clearTimeout(debounce);
            const q = tagInput.value.trim();
            if (q === '') {
                tagSuggestions.hidden = true;
                tagSuggestions.innerHTML = '';
                return;
            }
            debounce = setTimeout(function () {
                fetch('/api/tags?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (res) { return res.json(); })
                    .then(function (tags) {
                        tagSuggestions.innerHTML = '';
                        if (tags.length === 0) {
                            tagSuggestions.hidden = true;
                            return;
                        }
                        tags.forEach(function (name) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = '#' + name;
                            btn.addEventListener('click', function () {
                                tagInput.value = name;
                                tagSuggestions.hidden = true;
                                tagSuggestions.innerHTML = '';
                                tagInput.focus();
                            });
                            tagSuggestions.appendChild(btn);
                        });
                        tagSuggestions.hidden = false;
                    })
                    .catch(function () { /* réseau : silencieux, aucune erreur console */ });
            }, 200);
        });

        document.addEventListener('click', function (event) {
            if (!tagSuggestions.contains(event.target)) {
                tagSuggestions.hidden = true;
            }
        });
    }

    /* ---------- Temps réel : badges globaux (polling 5 s) ---------- */
    const POLL_INTERVAL = 5000;
    const badgeMessages = document.querySelector('[data-badge="messages"]');
    const badgeNotifs = document.querySelector('[data-badge="notifs"]');

    function updateBadges() {
        fetch('/api/poll', { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (badgeMessages) {
                    badgeMessages.textContent = data.unread_messages > 0 ? data.unread_messages : '';
                }
                if (badgeNotifs) {
                    badgeNotifs.textContent = data.unread_notifs > 0 ? data.unread_notifs : '';
                }
            })
            .catch(function () { /* réseau : silencieux */ });
    }

    if (badgeMessages || badgeNotifs) {
        updateBadges();
        setInterval(updateBadges, POLL_INTERVAL);
    }

    /* ---------- Chat temps réel (fil ouvert, polling 5 s) ---------- */
    const chat = document.querySelector('.chat');

    if (chat) {
        const thread = document.getElementById('chat-thread');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input');
        const otherId = chat.dataset.chatUser;
        const csrf = chat.dataset.csrf;
        const baseUrl = chat.dataset.baseUrl;
        let lastId = parseInt(chat.dataset.lastId || '0', 10);
        let focused = true;

        function scrollToBottom() {
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
        }

        function appendMessage(msg) {
            const wrap = document.createElement('div');
            const mine = String(msg.from_user_id) === String(otherId) ? 'theirs' : 'mine';
            wrap.className = 'chat-message ' + mine;
            let label = '';
            if (typeof msg.ts === 'number') {
                const time = new Date(msg.ts * 1000);
                label = String(time.getHours()).padStart(2, '0') + ':' + String(time.getMinutes()).padStart(2, '0');
            }
            wrap.innerHTML = '<div class="chat-bubble"></div><time class="chat-time">' + label + '</time>';
            wrap.querySelector('.chat-bubble').textContent = msg.content;
            thread.appendChild(wrap);
            if (msg.id > lastId) {
                lastId = msg.id;
                chat.dataset.lastId = String(lastId);
            }
        }

        function pollThread() {
            fetch(baseUrl + '/api/messages/' + otherId + '?after=' + lastId, {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data)) {
                        let changed = false;
                        data.forEach(function (msg) {
                            appendMessage(msg);
                            changed = true;
                        });
                        if (changed) {
                            scrollToBottom();
                            updateBadges();
                        }
                    }
                })
                .catch(function () { /* réseau : silencieux */ });
        }

        if (form && thread) {
            scrollToBottom();

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const content = input.value.trim();
                if (content === '') {
                    return;
                }

                const body = new FormData();
                body.append('content', content);

                fetch(baseUrl + '/messages/' + otherId, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: body
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            input.value = '';
                            input.focus();
                        } else {
                            alert('Message non envoyé : la conversation n\'est plus active.');
                            window.location.href = '/messages';
                        }
                    })
                    .catch(function () {
                        // Réseau indisponible : soumission classique en secours.
                        form.submit();
                    });
            });

            // Le fil n'est pollé que si la page est visible.
            document.addEventListener('visibilitychange', function () {
                focused = !document.hidden;
                if (focused) {
                    pollThread();
                }
            });

            setInterval(function () {
                if (focused) {
                    pollThread();
                }
            }, POLL_INTERVAL);
        }
    }
})();
