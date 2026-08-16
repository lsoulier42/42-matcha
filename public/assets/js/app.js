/**
 * Matcha — JS applicatif.
 * Phase 0 : menu mobile. Le polling temps réel (badges + chat) arrive en phase 6.
 */
(function () {
    'use strict';

    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
})();
