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
})();
