/* PGCO Sale - countdown timer, animated clock, slider, dismissible bar (plain JS) */
(function () {
    'use strict';

    var DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    var fa = function (n) {
        return String(Math.max(0, n)).padStart(2, '0').replace(/\d/g, function (d) { return DIGITS[d]; });
    };
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timers = [];
    var clocks = [];

    /* ── timer: based on server time, not the user's clock ── */
    function initTimers() {
        document.querySelectorAll('[data-pgs-timer]').forEach(function (el) {
            var end = parseInt(el.dataset.end, 10) * 1000;
            var offset = parseInt(el.dataset.now, 10) * 1000 - Date.now();
            if (!end) return;
            timers.push({
                el: el,
                end: end,
                offset: offset,
                nums: {
                    d: el.querySelector('[data-pgs-unit="d"]'),
                    h: el.querySelector('[data-pgs-unit="h"]'),
                    m: el.querySelector('[data-pgs-unit="m"]'),
                    s: el.querySelector('[data-pgs-unit="s"]')
                }
            });
        });
    }

    function setNum(node, value) {
        var txt = fa(value);
        if (!node || node.textContent === txt) return;
        var first = node.textContent === '۰۰' && !node.dataset.ready;
        node.textContent = txt;
        node.dataset.ready = '1';
        if (!first && !reduced) {
            node.classList.remove('is-flip');
            void node.offsetWidth;
            node.classList.add('is-flip');
        }
    }

    function tickTimers() {
        timers.forEach(function (t) {
            var left = Math.floor((t.end - (Date.now() + t.offset)) / 1000);
            if (left <= 0) {
                expire();
                return;
            }
            setNum(t.nums.d, Math.floor(left / 86400));
            setNum(t.nums.h, Math.floor(left / 3600) % 24);
            setNum(t.nums.m, Math.floor(left / 60) % 60);
            setNum(t.nums.s, left % 60);
        });
    }

    /* Campaign end: the section and the bar are both hidden (even if the page is cached) */
    function expire() {
        document.querySelectorAll('[data-pgs-hide-on-end]').forEach(function (n) { n.hidden = true; });
    }

    /* ── analog clock: hands follow the site time, smooth second hand ── */
    function initClocks() {
        document.querySelectorAll('[data-pgs-clock]').forEach(function (el) {
            var c = {
                el: el,
                base: parseInt(el.dataset.secs, 10) || 0,
                t0: Date.now(),
                h: el.querySelector('[data-hand="h"]'),
                m: el.querySelector('[data-hand="m"]'),
                s: el.querySelector('[data-hand="s"]'),
                visible: true
            };
            clocks.push(c);
        });
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    clocks.forEach(function (c) { if (c.el === e.target) c.visible = e.isIntersecting; });
                });
            });
            clocks.forEach(function (c) { io.observe(c.el); });
        }
    }

    function drawClock(c) {
        var ms = (c.base * 1000 + (Date.now() - c.t0)) % 86400000;
        var sec = ms / 1000;
        var s = reduced ? Math.floor(sec % 60) : sec % 60;
        var rot = function (node, deg) { if (node) node.setAttribute('transform', 'rotate(' + deg.toFixed(2) + ' 100 100)'); };
        rot(c.s, s * 6);
        rot(c.m, (sec / 60 % 60) * 6);
        rot(c.h, (sec / 3600 % 12) * 30);
    }

    function frame() {
        clocks.forEach(function (c) { if (c.visible) drawClock(c); });
        window.requestAnimationFrame(frame);
    }

    /* ── slider: scroll-snap + buttons (directions are physical and work correctly in RTL too) ── */
    function initSliders() {
        document.querySelectorAll('[data-pgs-slider]').forEach(function (root) {
            var vp = root.querySelector('[data-pgs-viewport]');
            var prev = root.querySelector('[data-pgs-prev]');
            var next = root.querySelector('[data-pgs-next]');
            if (!vp) return;

            function update() {
                var max = vp.scrollWidth - vp.clientWidth;
                var pos = Math.abs(vp.scrollLeft);
                root.classList.toggle('is-scrollable', max > 4);
                if (prev) prev.disabled = pos <= 2;            // 'previous' = right side (start)
                if (next) next.disabled = pos >= max - 2;      // 'next' = left side
            }
            function step(dir) {
                var slide = vp.querySelector('.pgs-slide');
                var w = slide ? slide.getBoundingClientRect().width + 24 : vp.clientWidth;
                vp.scrollBy({ left: dir * w, behavior: reduced ? 'auto' : 'smooth' });
            }
            if (prev) prev.addEventListener('click', function () { step(1); });
            if (next) next.addEventListener('click', function () { step(-1); });
            vp.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
            vp.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') { step(-1); e.preventDefault(); }
                if (e.key === 'ArrowRight') { step(1); e.preventDefault(); }
            });
            window.addEventListener('resize', update);
            update();
            initAutoplay(root, vp, step);
        });
    }

    /* ── autoplay: pauses on mouse/focus/touch, off-screen, hidden tab or reduced motion ── */
    function initAutoplay(root, vp, step) {
        var ms = parseInt(root.dataset.autoplay, 10) || 0;
        if (!ms || reduced) return;
        var timer = null, hold = false, visible = true;

        function go() {
            var max = vp.scrollWidth - vp.clientWidth;
            if (max <= 4) return;
            if (Math.abs(vp.scrollLeft) >= max - 2) vp.scrollTo({ left: 0, behavior: 'smooth' }); // end: back to the start
            else step(-1);
        }
        function start() { stop(); if (!hold && visible && !document.hidden) timer = window.setInterval(go, ms); }
        function stop() { if (timer) { window.clearInterval(timer); timer = null; } }
        function pause() { hold = true; stop(); }
        function resume() { hold = false; start(); }

        root.addEventListener('mouseenter', pause);
        root.addEventListener('mouseleave', resume);
        root.addEventListener('focusin', pause);
        root.addEventListener('focusout', resume);
        root.addEventListener('touchstart', pause, { passive: true });
        root.addEventListener('touchend', function () { window.setTimeout(resume, ms); }, { passive: true });
        document.addEventListener('visibilitychange', start);
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; start(); }).observe(root);
        }
        start();
    }

    /* ── bar: close (until the session ends) and smooth scroll to the section ── */
    function initBar() {
        document.querySelectorAll('[data-pgs-bar]').forEach(function (bar) {
            var close = bar.querySelector('[data-pgs-close]');
            if (close) {
                close.addEventListener('click', function () {
                    bar.hidden = true;
                    try { sessionStorage.setItem('pgsBarClosed:' + (bar.dataset.pgsKey || ''), '1'); } catch (e) { /* ignore */ }
                });
            }
        });
        document.querySelectorAll('[data-pgs-goto]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var href = a.getAttribute('href') || '';
                var hash = href.charAt(0) === '#' ? href : (a.hash && a.pathname === location.pathname && a.host === location.host ? a.hash : '');
                var target = hash.length > 1 ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;
                if (!target) return; // another page's URL: normal navigation
                e.preventDefault();
                target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
                if (history.replaceState) history.replaceState(null, '', hash);
            });
        });
    }

    function init() {
        initTimers();
        initClocks();
        initSliders();
        initBar();
        tickTimers();
        window.setInterval(tickTimers, 1000);
        if (clocks.length) {
            clocks.forEach(drawClock);
            if (reduced) {
                window.setInterval(function () { clocks.forEach(drawClock); }, 1000);
            } else {
                window.requestAnimationFrame(frame);
            }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
