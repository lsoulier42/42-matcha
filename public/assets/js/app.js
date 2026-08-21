/**
 * Matcha — JS applicatif.
 *  - localisation : consentement GPS explicite (page profil)
 *  - autocomplétion des tags existants (page profil)
 *  - polling temps réel (badges, chat, notifications) — phase 6
 */
(function () {
    'use strict';

    /* ---------- Géolocalisation (consentement explicite) ---------- */
    const btnGps = document.getElementById('btn-gps');
    if (btnGps) {
        btnGps.addEventListener('click', function () {
            if (!('geolocation' in navigator)) {
                alert('La géolocalisation est indisponible : le site doit être ouvert en HTTPS (ou sur localhost). Vous pouvez saisir votre ville manuellement.');
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
                    if (ville) {
                        // La ville pré-remplie (ex. ville de la fixture) est périmée :
                        // elle sera redétectée à l'enregistrement depuis les coordonnées.
                        ville.value = '';
                        ville.placeholder = 'Détectée automatiquement à l\'enregistrement';
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

    /* ---------- Galerie : drag & drop + édition (bonus) ---------- */
    const dropZone = document.getElementById('drop-zone');

    if (dropZone) {
        const csrf = document.querySelector('input[name="csrf_token"]').value;

        ['dragover', 'dragenter'].forEach(function (eventName) {
            dropZone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropZone.addEventListener(eventName, function () {
                dropZone.classList.remove('dragover');
            });
        });
        dropZone.addEventListener('drop', function (event) {
            event.preventDefault();
            const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
            if (!file) {
                return;
            }
            const body = new FormData();
            body.append('csrf_token', csrf);
            body.append('photo', file);
            fetch('/profile/photo', { method: 'POST', body: body })
                .then(function (res) {
                    window.location.href = res.url || '/profile';
                })
                .catch(function () {
                    window.location.reload();
                });
        });
    }

    /* --- Éditeur d'image (rotation, filtres, recadrage) --- */
    const editor = document.getElementById('photo-editor');
    if (editor) {
        const editorCanvas = document.getElementById('editor-canvas');
        const ctx = editorCanvas.getContext('2d');
        const cropSize = document.getElementById('crop-size');
        const editorClose = document.getElementById('editor-close');
        const editorCropBtn = document.getElementById('editor-crop-btn');
        const rotateForm = document.getElementById('editor-rotate-form');
        const filterForm = document.getElementById('editor-filter-form');
        const canvasCss = getComputedStyle(editorCanvas);
        const cw = parseInt(canvasCss.width, 10) || 320;
        const ch = parseInt(canvasCss.height, 10) || 320;

        let currentPhotoId = 0;
        let sourceImage = null;   // Image() de la photo d'origine
        let scale = 1;
        let offsetX = 0;
        let offsetY = 0;
        let frame = { x: 0, y: 0, size: 200 };
        let dragging = false;
        let dragOffset = { x: 0, y: 0 };

        function draw() {
            ctx.clearRect(0, 0, cw, ch);
            if (!sourceImage) {
                return;
            }
            ctx.drawImage(sourceImage, offsetX, offsetY, sourceImage.width * scale, sourceImage.height * scale);
            // Assombrir l'extérieur du cadre
            ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
            ctx.fillRect(0, 0, cw, frame.y);
            ctx.fillRect(0, frame.y + frame.size, cw, ch - frame.y - frame.size);
            ctx.fillRect(0, frame.y, frame.x, frame.size);
            ctx.fillRect(frame.x + frame.size, frame.y, cw - frame.x - frame.size, frame.size);
            // Bordure du cadre
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.strokeRect(frame.x, frame.y, frame.size, frame.size);
        }

        function openEditor(photoId) {
            currentPhotoId = photoId;
            const img = document.querySelector('[data-photo-id="' + photoId + '"] img');
            if (!img) {
                return;
            }
            const src = img.getAttribute('src');
            sourceImage = new Image();
            sourceImage.crossOrigin = 'anonymous';
            sourceImage.onload = function () {
                // Couverture de l'image dans le canvas (cover)
                scale = Math.max(cw / sourceImage.width, ch / sourceImage.height);
                offsetX = (cw - sourceImage.width * scale) / 2;
                offsetY = (ch - sourceImage.height * scale) / 2;
                const size = Math.round((parseInt(cropSize.value, 10) / 100) * Math.min(cw, ch));
                frame = { x: Math.round((cw - size) / 2), y: Math.round((ch - size) / 2), size: size };
                draw();
            };
            sourceImage.onerror = function () { /* image illisible : silencieux */ };
            sourceImage.src = src;
            editor.hidden = false;
            // Pointer les formulaires d'édition vers la photo courante
            rotateForm.setAttribute('action', '/profile/photo/' + photoId + '/rotate');
            filterForm.setAttribute('action', '/profile/photo/' + photoId + '/filter');
        }

        function closeEditor() {
            editor.hidden = true;
            sourceImage = null;
        }

        document.querySelectorAll('[data-open-editor]').forEach(function (button) {
            button.addEventListener('click', function () {
                openEditor(parseInt(button.dataset.openEditor, 10));
            });
        });

        if (editorClose) {
            editorClose.addEventListener('click', closeEditor);
        }

        if (cropSize) {
            cropSize.addEventListener('input', function () {
                if (!sourceImage) {
                    return;
                }
                const size = Math.round((parseInt(cropSize.value, 10) / 100) * Math.min(cw, ch));
                frame.size = size;
                frame.x = Math.min(Math.max(frame.x, 0), cw - size);
                frame.y = Math.min(Math.max(frame.y, 0), ch - size);
                draw();
            });
        }

        if (editorCanvas) {
            editorCanvas.addEventListener('pointerdown', function (event) {
                if (!sourceImage) {
                    return;
                }
                const rect = editorCanvas.getBoundingClientRect();
                const px = event.clientX - rect.left;
                const py = event.clientY - rect.top;
                if (px >= frame.x && px <= frame.x + frame.size && py >= frame.y && py <= frame.y + frame.size) {
                    dragging = true;
                    dragOffset = { x: px - frame.x, y: py - frame.y };
                    editorCanvas.setPointerCapture(event.pointerId);
                }
            });
            editorCanvas.addEventListener('pointermove', function (event) {
                if (!dragging) {
                    return;
                }
                const rect = editorCanvas.getBoundingClientRect();
                frame.x = Math.min(Math.max(event.clientX - rect.left - dragOffset.x, 0), cw - frame.size);
                frame.y = Math.min(Math.max(event.clientY - rect.top - dragOffset.y, 0), ch - frame.size);
                draw();
            });
            editorCanvas.addEventListener('pointerup', function () {
                dragging = false;
            });
        }

        if (editorCropBtn) {
            editorCropBtn.addEventListener('click', function () {
                if (!sourceImage || currentPhotoId === 0) {
                    return;
                }
                // Conversion des coordonnées canvas → pixels de l'image source
                const sx = Math.round((frame.x - offsetX) / scale);
                const sy = Math.round((frame.y - offsetY) / scale);
                const sw = Math.round(frame.size / scale);
                const sh = Math.round(frame.size / scale);
                const body = new FormData();
                body.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                body.append('x', String(Math.max(0, sx)));
                body.append('y', String(Math.max(0, sy)));
                body.append('width', String(Math.max(50, sw)));
                body.append('height', String(Math.max(50, sh)));
                fetch('/profile/photo/' + currentPhotoId + '/crop', { method: 'POST', body: body })
                    .then(function (res) {
                        window.location.href = res.url || '/profile';
                    })
                    .catch(function () {
                        window.location.reload();
                    });
            });
        }
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

    /* ---------- Profil public : swipe + écran « C'est un match ! » ----------
       Enhancement progressif sur le like/unlike : le profil glisse
       (is-liking / is-noping, tampon LIKE/NOPE), le POST part en fetch JSON ;
       si le serveur signale un match, l'overlay « C'est un match ! » s'ouvre,
       sinon on suit la redirection classique (flash conservés).
       Sans JS ou hors réseau, le formulaire POST fonctionne tel quel. */
    const userShow = document.querySelector('.user-show');

    if (userShow) {
        const matchOverlay = document.getElementById('match-overlay');

        function showMatchOverlay(chatUrl, redirectUrl) {
            if (!matchOverlay) {
                window.location.href = redirectUrl;
                return;
            }
            const chatLink = document.getElementById('match-chat');
            const closeBtn = document.getElementById('match-close');
            if (chatLink) { chatLink.href = chatUrl; }
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    window.location.href = redirectUrl;
                });
            }
            matchOverlay.classList.add('is-open');
            if (chatLink) { chatLink.focus(); }
        }

        userShow.querySelectorAll('.swipe-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                userShow.classList.add(form.dataset.swipe === 'like' ? 'is-liking' : 'is-noping');

                const body = new FormData(form);
                const headers = { 'Accept': 'application/json' };
                const csrfInput = form.querySelector('input[name="csrf_token"]');
                if (csrfInput) { headers['X-CSRF-Token'] = csrfInput.value; }

                fetch(form.action, {
                    method: 'POST',
                    headers: headers,
                    body: body
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        window.setTimeout(function () {
                            if (data && data.ok && data.match && data.chat_url) {
                                showMatchOverlay(data.chat_url, data.redirect);
                            } else if (data && data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                window.location.reload();
                            }
                        }, 360);
                    })
                    .catch(function () {
                        // Réseau indisponible : soumission classique en secours.
                        window.setTimeout(function () { form.submit(); }, 340);
                    });
            });
        });
    }
})();
