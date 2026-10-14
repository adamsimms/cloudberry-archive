(function (window, document) {
    'use strict';

    var gsap = window.gsap;
    var ScrollTrigger = window.ScrollTrigger;
    var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var available = !!(gsap && !reduced);

    if (gsap && ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);
    }

    if (available) {
        document.documentElement.classList.add('js-gsap');
    }

    function prefersReducedMotion() {
        return reduced;
    }

    function fadeText(el, nextText, duration) {
        if (!el) {
            return;
        }
        var text = nextText == null ? '' : String(nextText);
        if (!available || el.textContent === text) {
            el.textContent = text;
            return;
        }
        gsap.killTweensOf(el);
        gsap.to(el, {
            opacity: 0,
            y: -4,
            duration: (duration || 0.22) * 0.45,
            ease: 'power1.in',
            onComplete: function () {
                el.textContent = text;
                gsap.fromTo(el, { opacity: 0, y: 4 }, {
                    opacity: 1,
                    y: 0,
                    duration: (duration || 0.22) * 0.55,
                    ease: 'power2.out'
                });
            }
        });
    }

    function fadeHtml(el, nextHtml, duration) {
        if (!el) {
            return;
        }
        var html = nextHtml == null ? '' : String(nextHtml);
        if (!available || el.innerHTML === html) {
            el.innerHTML = html;
            return;
        }
        gsap.killTweensOf(el);
        gsap.to(el, {
            opacity: 0,
            y: -4,
            duration: (duration || 0.22) * 0.45,
            ease: 'power1.in',
            onComplete: function () {
                el.innerHTML = html;
                gsap.fromTo(el, { opacity: 0, y: 4 }, {
                    opacity: 1,
                    y: 0,
                    duration: (duration || 0.22) * 0.55,
                    ease: 'power2.out'
                });
            }
        });
    }

    function setNavAffix(nav, affixed) {
        if (!nav || !available) {
            return;
        }
        var icons = nav.querySelectorAll('.nav_gallery, .nav_info, .nav_maps');
        gsap.killTweensOf(nav);
        // Box-shadow + icon scale only — never animate layout props (minHeight) while scrolling.
        gsap.to(nav, {
            boxShadow: affixed ? '0 6px 18px rgba(0, 0, 0, 0.07)' : '0 0 0 rgba(0, 0, 0, 0)',
            duration: 0.35,
            ease: 'power2.out',
            overwrite: true
        });
        if (icons.length) {
            gsap.to(icons, {
                scale: affixed ? 0.92 : 1,
                duration: 0.3,
                ease: 'power2.out',
                transformOrigin: '50% 50%',
                overwrite: true
            });
        }
    }

    function fadeInElement(el, duration) {
        if (!el) {
            return;
        }
        if (!available) {
            el.style.opacity = '1';
            return;
        }
        gsap.fromTo(el, { opacity: 0 }, {
            opacity: 1,
            duration: duration || 0.65,
            ease: 'power2.out'
        });
    }

    function pulseCitation(btn) {
        if (!btn || !available) {
            return;
        }
        gsap.fromTo(btn, { scale: 1 }, {
            scale: 1.04,
            duration: 0.16,
            yoyo: true,
            repeat: 1,
            ease: 'power2.out',
            clearProps: 'transform'
        });
    }

    function openMapsDropdown(dropdown) {
        if (!dropdown) {
            return;
        }
        dropdown.classList.add('is-open');
        var trigger = dropdown.querySelector('.maps-nav-dropdown-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
        if (!available) {
            return;
        }
        var panel = dropdown.querySelector('.maps-nav-dropdown-panel');
        var items = dropdown.querySelectorAll('.maps-nav-dropdown-item');
        if (!panel) {
            return;
        }
        gsap.killTweensOf([panel].concat(Array.prototype.slice.call(items)));
        gsap.fromTo(panel, { opacity: 0, y: -6, scale: 0.98 }, {
            opacity: 1,
            y: 0,
            scale: 1,
            duration: 0.22,
            ease: 'power2.out',
            clearProps: 'transform'
        });
        if (items.length) {
            gsap.fromTo(items, { opacity: 0, y: -4 }, {
                opacity: 1,
                y: 0,
                duration: 0.2,
                stagger: 0.04,
                ease: 'power2.out',
                clearProps: 'all'
            });
        }
    }

    function closeMapsDropdown(dropdown) {
        if (!dropdown) {
            return;
        }
        var trigger = dropdown.querySelector('.maps-nav-dropdown-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
        if (!available) {
            dropdown.classList.remove('is-open');
            return;
        }
        var panel = dropdown.querySelector('.maps-nav-dropdown-panel');
        if (!panel) {
            dropdown.classList.remove('is-open');
            return;
        }
        gsap.killTweensOf(panel);
        gsap.to(panel, {
            opacity: 0,
            y: -4,
            scale: 0.98,
            duration: 0.14,
            ease: 'power1.in',
            onComplete: function () {
                dropdown.classList.remove('is-open');
                gsap.set(panel, { clearProps: 'all' });
            }
        });
    }

    function routeEnter() {
        if (!available) {
            return false;
        }
        var skip = document.body.classList.contains('maps-satellite-page')
            || document.body.classList.contains('maps-embed-page');
        if (skip) {
            return false;
        }
        document.body.classList.add('pinchard-route-enter');
        gsap.fromTo(document.body, { opacity: 0 }, {
            opacity: 1,
            duration: 0.36,
            ease: 'power2.out',
            onComplete: function () {
                document.body.classList.add('is-ready');
                gsap.set(document.body, { clearProps: 'opacity' });
            }
        });
        var chrome = document.getElementById('mainNav');
        if (chrome) {
            gsap.fromTo(chrome, { opacity: 0, y: -6 }, {
                opacity: 1,
                y: 0,
                duration: 0.4,
                ease: 'power2.out',
                clearProps: 'all'
            });
        }
        return true;
    }

    function routeLeave(href, done) {
        if (!available) {
            if (typeof done === 'function') {
                done();
            }
            return 0;
        }
        document.body.classList.add('pinchard-route-leave');
        document.body.classList.remove('pinchard-route-enter', 'is-ready');
        var tl = gsap.timeline({
            onComplete: function () {
                if (typeof done === 'function') {
                    done();
                } else if (href) {
                    window.location.href = href;
                }
            }
        });
        tl.to(document.body, {
            opacity: 0,
            duration: 0.24,
            ease: 'power1.in'
        }, 0);
        var chrome = document.getElementById('mainNav');
        if (chrome) {
            tl.to(chrome, {
                opacity: 0,
                y: -4,
                duration: 0.2,
                ease: 'power1.in'
            }, 0);
        }
        return 240;
    }

    function setViewerUnderlay(parent, src) {
        if (!parent || !src) {
            return;
        }
        parent.style.backgroundImage = 'url("' + String(src).replace(/"/g, '\\"') + '")';
        parent.style.backgroundSize = 'cover';
        parent.style.backgroundPosition = 'center';
        parent.style.backgroundRepeat = 'no-repeat';
    }

    function crossfadeViewer(oldImg, newImg, direction, durationMs) {
        var duration = Math.max(0, (durationMs || 1000) / 1000);
        var parent = newImg && newImg.parentNode;

        if (!available || duration === 0) {
            if (newImg) {
                newImg.style.opacity = '1';
                newImg.classList.add('loaded');
            }
            if (oldImg && oldImg.parentNode) {
                oldImg.parentNode.removeChild(oldImg);
            }
            if (parent && newImg && newImg.src) {
                setViewerUnderlay(parent, newImg.src);
            }
            return Promise.resolve();
        }

        // Overlay dissolve only: outgoing stays fully opaque underneath.
        // Never fade both at once — that lets the white placeholder flash through.
        if (parent && oldImg && oldImg.src) {
            setViewerUnderlay(parent, oldImg.src);
        }

        if (oldImg) {
            gsap.killTweensOf(oldImg);
            gsap.set(oldImg, { opacity: 1, zIndex: 1, visibility: 'visible' });
            oldImg.classList.add('loaded');
            oldImg.style.opacity = '1';
        }

        gsap.killTweensOf(newImg);
        newImg.classList.add('loaded');
        newImg.style.zIndex = '2';

        return new Promise(function (resolve) {
            gsap.fromTo(newImg, { opacity: 0 }, {
                opacity: 1,
                duration: duration,
                ease: 'power1.inOut',
                onComplete: function () {
                    if (oldImg && oldImg.parentNode) {
                        oldImg.parentNode.removeChild(oldImg);
                    }
                    if (parent && newImg.src) {
                        setViewerUnderlay(parent, newImg.src);
                    }
                    // Keep inline opacity:1 — clearing it re-triggers CSS and flashes.
                    newImg.style.opacity = '1';
                    newImg.style.zIndex = '2';
                    resolve();
                }
            });
        });
    }

    function setDetailDrawerOpen(drawer, toggle, open) {
        if (!drawer) {
            return;
        }
        drawer.classList.toggle('open', open);
        if (toggle) {
            toggle.classList.toggle('down_arrow', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Hide photograph details' : 'Show photograph details');
        }
        if (!available || !open) {
            return;
        }

        var rows = drawer.querySelectorAll('.detail_content_view > div');
        var map = drawer.querySelector('.mapcontainer');
        if (rows.length) {
            gsap.fromTo(rows, { opacity: 0, y: 14 }, {
                opacity: 1,
                y: 0,
                duration: 0.4,
                stagger: 0.05,
                delay: 0.12,
                ease: 'power2.out',
                clearProps: 'transform'
            });
        }
        if (map) {
            gsap.fromTo(map, { opacity: 0, y: 18 }, {
                opacity: 1,
                y: 0,
                duration: 0.45,
                delay: 0.28,
                ease: 'power2.out',
                clearProps: 'all'
            });
        }
    }

    function initGalleryMotion() {
        if (!available) {
            return;
        }
        var scrollEl = document.getElementById('galleryDaysScroll');
        var track = document.getElementById('galleryDaysTrack');
        if (!scrollEl || !track) {
            return;
        }

        // (4) First paint — soft fade of the whole filmstrip/feed.
        gsap.fromTo(track, { opacity: 0 }, {
            opacity: 1,
            duration: 0.4,
            ease: 'power2.out'
        });

        // (1) Thumb reveal when each lazy image finishes loading.
        function revealGalleryThumb(img) {
            if (!img || img.dataset.gsapRevealed === '1') {
                return;
            }
            img.dataset.gsapRevealed = '1';
            gsap.fromTo(img, { opacity: 0 }, {
                opacity: 1,
                duration: 0.28,
                ease: 'power2.out'
            });
        }

        scrollEl.addEventListener('load', function (e) {
            var t = e.target;
            if (t && t.classList && t.classList.contains('gallery-photo')) {
                revealGalleryThumb(t);
            }
        }, true);

        scrollEl.querySelectorAll('.gallery-photo').forEach(function (img) {
            if (img.complete && img.naturalWidth > 0 && !img.getAttribute('data-src')) {
                revealGalleryThumb(img);
            }
        });

        // (2) Day label settle — horizontal on filmstrip, vertical on mobile feed.
        if (!ScrollTrigger) {
            return;
        }

        var filmstrip = window.matchMedia('(min-width: 768px)').matches;
        var columns = scrollEl.querySelectorAll('.gallery-day-column');
        columns.forEach(function (col) {
            var label = col.querySelector('.gallery-day-label');
            if (!label) {
                return;
            }
            gsap.set(label, { opacity: 0, y: 6 });
            ScrollTrigger.create({
                scroller: scrollEl,
                trigger: col,
                horizontal: filmstrip,
                start: filmstrip ? 'left 92%' : 'top 90%',
                once: true,
                onEnter: function () {
                    gsap.to(label, {
                        opacity: 1,
                        y: 0,
                        duration: 0.4,
                        ease: 'power2.out',
                        clearProps: 'transform'
                    });
                }
            });
        });

        scrollEl.addEventListener('scroll', function () {
            ScrollTrigger.update();
        }, { passive: true });
        ScrollTrigger.refresh();
    }

    function initInfoMotion() {
        initHardwareAccordion();

        var hero = document.querySelector('.info-hero img');
        if (hero) {
            revealInfoImage(hero, { hero: true });
        }

        document.querySelectorAll('.info-main img.info_img, .info-main .people-list-photo').forEach(function (img) {
            revealInfoImage(img, { hero: false });
        });

        initInfoMapReveal();

        if (!available) {
            return;
        }

        // Copy/people autoAlpha reveals removed — they hid targets so TOC
        // hash links landed on blank space. Keep image fades + TOC marker.
        initTocMarker();
    }

    function revealInfoImage(img, options) {
        options = options || {};
        if (!img) {
            return;
        }

        img.classList.add('info-reveal-img', 'is-pending');

        function playReveal() {
            if (img.classList.contains('is-ready')) {
                return;
            }
            img.classList.remove('is-pending');
            img.classList.add('is-ready');
            if (!available) {
                img.style.opacity = '1';
                return;
            }
            gsap.fromTo(img, { opacity: 0 }, {
                opacity: 1,
                duration: options.hero ? 0.35 : 0.25,
                ease: 'power2.out'
            });
        }

        if (img.complete && img.naturalWidth > 0) {
            playReveal();
        } else {
            img.addEventListener('load', playReveal, { once: true });
            img.addEventListener('error', playReveal, { once: true });
        }
    }

    function initInfoMapReveal() {
        var map = document.querySelector('.info-map');
        if (!map) {
            return;
        }
        var iframe = map.querySelector('iframe');
        var settled = false;

        function settle() {
            if (settled) {
                return;
            }
            settled = true;
            map.classList.add('is-ready');
            if (!available) {
                return;
            }
            gsap.fromTo(map, { opacity: 0 }, {
                opacity: 1,
                duration: 0.55,
                ease: 'power2.out'
            });
        }

        if (iframe) {
            iframe.addEventListener('load', settle, { once: true });
            window.setTimeout(settle, 2500);
        } else {
            settle();
        }
    }

    function initInfoCopyReveal() {
        // One opacity-only trigger per section. Avoid y/blur/refresh — those caused scroll jank.
        document.querySelectorAll('.info-main .how_section, .info-main .who_section, .info-main .contact_section').forEach(function (section) {
            var els = section.querySelectorAll('h3, h4, p, .citation-block, .hardware-accordion');
            if (!els.length) {
                return;
            }
            gsap.set(els, { autoAlpha: 0 });
            gsap.to(els, {
                autoAlpha: 1,
                duration: 0.4,
                stagger: 0.03,
                ease: 'power1.out',
                overwrite: true,
                scrollTrigger: {
                    trigger: section,
                    start: 'top 90%',
                    once: true,
                    fastScrollEnd: true
                }
            });
        });
    }

    function initInfoPeopleStagger() {
        var items = document.querySelectorAll('.who_section .people-list-item');
        if (!items.length) {
            return;
        }
        gsap.set(items, { autoAlpha: 0 });
        gsap.to(items, {
            autoAlpha: 1,
            duration: 0.45,
            stagger: 0.08,
            ease: 'power1.out',
            overwrite: true,
            scrollTrigger: {
                trigger: '.who_section .people-list',
                start: 'top 90%',
                once: true,
                fastScrollEnd: true
            }
        });
    }

    function initTocMarker() {
        var nav = document.querySelector('.info-toc nav');
        if (!nav || !available) {
            return;
        }
        var mobileToc = window.matchMedia('(max-width: 991px)');
        // Desktop uses a CSS left border; the sliding marker is mobile-only.
        if (!mobileToc.matches) {
            mobileToc.addEventListener('change', function () {
                if (mobileToc.matches) {
                    initTocMarker();
                }
            });
            return;
        }
        if (nav.querySelector('.info-toc-marker')) {
            return;
        }
        var marker = document.createElement('span');
        marker.className = 'info-toc-marker';
        marker.setAttribute('aria-hidden', 'true');
        nav.appendChild(marker);

        function moveMarker(active) {
            if (!mobileToc.matches) {
                gsap.set(marker, { opacity: 0 });
                return;
            }
            if (!active) {
                gsap.to(marker, { opacity: 0, duration: 0.2 });
                return;
            }
            gsap.to(marker, {
                opacity: 1,
                x: active.offsetLeft,
                y: 0,
                width: active.offsetWidth,
                height: 2,
                duration: 0.28,
                ease: 'power2.out'
            });
        }

        var initial = nav.querySelector('a.is-active') || nav.querySelector('a');
        if (initial) {
            gsap.set(marker, {
                x: initial.offsetLeft,
                y: 0,
                width: initial.offsetWidth,
                height: 2,
                opacity: 1
            });
        }

        var observer = new MutationObserver(function () {
            moveMarker(nav.querySelector('a.is-active'));
        });
        nav.querySelectorAll('a').forEach(function (link) {
            observer.observe(link, { attributes: true, attributeFilter: ['class'] });
        });
        mobileToc.addEventListener('change', function () {
            moveMarker(nav.querySelector('a.is-active'));
        });

        window.pinchardMotion.moveTocMarker = moveMarker;
    }

    function initHardwareAccordion() {
        var items = Array.prototype.slice.call(document.querySelectorAll('.hardware-details'));
        if (!items.length) {
            return;
        }

        if (!available) {
            items.forEach(function (details) {
                details.addEventListener('toggle', function () {
                    if (!details.open) {
                        return;
                    }
                    items.forEach(function (other) {
                        if (other !== details) {
                            other.open = false;
                        }
                    });
                });
            });
            return;
        }

        var openEase = 'power2.out';
        var closeEase = 'power2.inOut';
        var openDur = 0.42;
        var closeDur = 0.34;
        var bodyPadY = 16;

        function closeDetails(details) {
            var body = details.querySelector('.hardware-details-body');
            if (!body || !details.open) {
                return;
            }
            gsap.killTweensOf(body);
            gsap.to(body, {
                height: 0,
                paddingTop: 0,
                paddingBottom: 0,
                opacity: 0,
                overflow: 'hidden',
                duration: closeDur,
                ease: closeEase,
                onComplete: function () {
                    details.open = false;
                    gsap.set(body, { clearProps: 'height,paddingTop,paddingBottom,opacity,overflow' });
                }
            });
        }

        function openDetails(details) {
            var body = details.querySelector('.hardware-details-body');
            if (!body) {
                return;
            }
            gsap.killTweensOf(body);
            details.open = true;
            gsap.fromTo(
                body,
                {
                    height: 0,
                    paddingTop: 0,
                    paddingBottom: 0,
                    opacity: 0,
                    overflow: 'hidden'
                },
                {
                    height: 'auto',
                    paddingTop: bodyPadY,
                    paddingBottom: bodyPadY,
                    opacity: 1,
                    duration: openDur,
                    ease: openEase,
                    onComplete: function () {
                        gsap.set(body, { clearProps: 'height,paddingTop,paddingBottom,opacity,overflow' });
                    }
                }
            );
        }

        items.forEach(function (details) {
            var summary = details.querySelector('summary');
            if (!summary) {
                return;
            }

            summary.addEventListener('click', function (event) {
                event.preventDefault();
                var opening = !details.open;
                if (opening) {
                    items.forEach(function (other) {
                        if (other !== details) {
                            closeDetails(other);
                        }
                    });
                    openDetails(details);
                } else {
                    closeDetails(details);
                }
            });
        });
    }

    function flyInMap(map, view) {
        if (!map || !view) {
            return;
        }
        var payload = {
            center: view.center,
            zoom: view.zoom,
            bearing: view.bearing || 0,
            pitch: view.pitch || 0
        };
        if (reduced) {
            map.jumpTo(payload);
            return;
        }
        map.easeTo({
            center: payload.center,
            zoom: payload.zoom,
            bearing: payload.bearing,
            pitch: payload.pitch,
            duration: 2200,
            easing: function (t) {
                return 1 - Math.pow(1 - t, 3);
            }
        });
    }

    function boot() {
        if (!available) {
            var emptyFallback = document.querySelector('.pinchard-empty-state, .maps-embed-shell');
            if (emptyFallback) {
                emptyFallback.style.opacity = '1';
            }
            return;
        }
        if (document.body.classList.contains('gallery-page')) {
            initGalleryMotion();
        }
        if (document.body.classList.contains('info-page')) {
            initInfoMotion();
        }
        if (document.body.classList.contains('maps-embed-page')) {
            var embedShell = document.querySelector('.maps-embed-shell');
            if (embedShell) {
                gsap.fromTo(embedShell, { opacity: 0 }, {
                    opacity: 1,
                    duration: 0.75,
                    ease: 'power2.out'
                });
            }
        }
        document.querySelectorAll('.pinchard-empty-state').forEach(function (el) {
            fadeInElement(el, 0.7);
        });
    }

    window.pinchardMotion = {
        available: available,
        reduced: reduced,
        prefersReducedMotion: prefersReducedMotion,
        gsap: gsap,
        ScrollTrigger: ScrollTrigger,
        fadeText: fadeText,
        fadeHtml: fadeHtml,
        fadeInElement: fadeInElement,
        setNavAffix: setNavAffix,
        pulseCitation: pulseCitation,
        openMapsDropdown: openMapsDropdown,
        closeMapsDropdown: closeMapsDropdown,
        routeEnter: routeEnter,
        routeLeave: routeLeave,
        crossfadeViewer: crossfadeViewer,
        setDetailDrawerOpen: setDetailDrawerOpen,
        flyInMap: flyInMap,
        initGalleryMotion: initGalleryMotion,
        initInfoMotion: initInfoMotion
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
