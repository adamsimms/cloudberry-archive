(function() {
    "use strict";

    var ROUTE_LEAVE_MS = 240;
    var MAIN_ROUTES = {
        'index.php': true,
        'gallery.php': true,
        'info.php': true,
        'gallery': true,
        'info': true,
        'jam': true
    };

    var motion = window.pinchardMotion;

    function prefersReducedMotion() {
        if (motion && typeof motion.prefersReducedMotion === 'function') {
            return motion.prefersReducedMotion();
        }
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function routePathname(pathname) {
        var segments = pathname.split('/').filter(Boolean);
        if (!segments.length) {
            return 'index.php';
        }
        var base = segments[segments.length - 1];
        // Clean archive URLs: /cloudberry/archive or /cloudberry/archive/gallery
        if (base === 'archive') {
            return 'index.php';
        }
        if (base.indexOf('.') === -1) {
            return base;
        }
        return base;
    }

    function isMainRouteHref(href) {
        try {
            var url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) {
                return false;
            }
            var base = routePathname(url.pathname);
            return MAIN_ROUTES[base] === true;
        } catch (err) {
            return false;
        }
    }

    function isMainNavLink(link) {
        return !!(link && link.closest && link.closest('#mainNav') && isMainRouteHref(link.href));
    }

    function navigateWithTransition(href) {
        if (prefersReducedMotion() || !href) {
            window.location.href = href;
            return;
        }
        if (motion && motion.available && typeof motion.routeLeave === 'function') {
            motion.routeLeave(href);
            return;
        }
        document.body.classList.add('pinchard-route-leave');
        document.body.classList.remove('pinchard-route-enter', 'is-ready');
        window.setTimeout(function() {
            window.location.href = href;
        }, ROUTE_LEAVE_MS);
    }

    window.pinchardNavigate = navigateWithTransition;

    if (!prefersReducedMotion()) {
        var handledEnter = motion && motion.available && typeof motion.routeEnter === 'function'
            ? motion.routeEnter()
            : false;

        if (!handledEnter) {
            var skipRouteEnter = document.body.classList.contains('maps-satellite-page')
                || document.body.classList.contains('maps-embed-page');

            if (!skipRouteEnter) {
                document.body.classList.add('pinchard-route-enter');
                window.requestAnimationFrame(function() {
                    window.requestAnimationFrame(function() {
                        document.body.classList.add('is-ready');
                    });
                });
            }
        }

        document.addEventListener('click', function(event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            var link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download') || !isMainNavLink(link)) {
                return;
            }
            var nextUrl = new URL(link.href, window.location.href);
            if (nextUrl.href === window.location.href) {
                return;
            }
            event.preventDefault();
            navigateWithTransition(link.href);
        });
    }

    document.addEventListener('click', function(event) {
        var link = event.target.closest('a.page-scroll');
        if (!link) {
            return;
        }
        var target = document.querySelector(link.getAttribute('href'));
        if (!target) {
            return;
        }
        event.preventDefault();
        target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    });

    var mainNav = document.getElementById('mainNav');
    if (mainNav) {
        var affixOffset = 100;
        var wasAffixed = mainNav.classList.contains('affix');
        function updateAffix() {
            var affixed = window.scrollY > affixOffset;
            if (affixed === wasAffixed) {
                return;
            }
            wasAffixed = affixed;
            mainNav.classList.toggle('affix', affixed);
            if (motion && typeof motion.setNavAffix === 'function') {
                motion.setNavAffix(mainNav, affixed);
            }
        }
        window.addEventListener('scroll', updateAffix, { passive: true });
        updateAffix();
    }

    function closeMapsDropdowns() {
        document.querySelectorAll('.maps-nav-dropdown.is-open').forEach(function(dropdown) {
            if (motion && typeof motion.closeMapsDropdown === 'function') {
                motion.closeMapsDropdown(dropdown);
            } else {
                dropdown.classList.remove('is-open');
                var trigger = dropdown.querySelector('.maps-nav-dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    var mapsDropdownCloseTimer = null;
    var mapsDropdownHover = window.matchMedia('(hover: hover) and (pointer: fine)');

    document.querySelectorAll('.maps-nav-dropdown').forEach(function(dropdown) {
        dropdown.addEventListener('mouseenter', function() {
            if (!mapsDropdownHover.matches) {
                return;
            }
            window.clearTimeout(mapsDropdownCloseTimer);
            closeMapsDropdowns();
            if (motion && typeof motion.openMapsDropdown === 'function') {
                motion.openMapsDropdown(dropdown);
            } else {
                dropdown.classList.add('is-open');
                var trigger = dropdown.querySelector('.maps-nav-dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'true');
                }
            }
        });
        dropdown.addEventListener('mouseleave', function() {
            if (!mapsDropdownHover.matches) {
                return;
            }
            window.clearTimeout(mapsDropdownCloseTimer);
            mapsDropdownCloseTimer = window.setTimeout(function() {
                if (motion && typeof motion.closeMapsDropdown === 'function') {
                    motion.closeMapsDropdown(dropdown);
                } else {
                    dropdown.classList.remove('is-open');
                    var trigger = dropdown.querySelector('.maps-nav-dropdown-trigger');
                    if (trigger) {
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                }
            }, 120);
        });
    });

    document.addEventListener('click', function(event) {
        var trigger = event.target.closest('.maps-nav-dropdown-trigger');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            var dropdown = trigger.closest('.maps-nav-dropdown');
            var isOpen = dropdown.classList.contains('is-open');
            closeMapsDropdowns();
            if (!isOpen) {
                if (motion && typeof motion.openMapsDropdown === 'function') {
                    motion.openMapsDropdown(dropdown);
                } else {
                    dropdown.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
            }
            return;
        }
        if (!event.target.closest('.maps-nav-dropdown')) {
            closeMapsDropdowns();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeMapsDropdowns();
        }
    });

    document.addEventListener('click', function(event) {
        var btn = event.target.closest('.citation-copy-btn');
        if (!btn) {
            return;
        }
        var text = btn.getAttribute('data-citation') || '';
        if (!text) {
            return;
        }

        function markCopied() {
            var original = btn.dataset.copyLabel || btn.textContent || 'Copy';
            btn.dataset.copyLabel = original;
            btn.classList.add('is-copied');
            btn.textContent = 'Copied';
            if (motion && typeof motion.pulseCitation === 'function') {
                motion.pulseCitation(btn);
            }
            window.setTimeout(function() {
                btn.classList.remove('is-copied');
                btn.textContent = original;
            }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(markCopied).catch(function() {
                fallbackCopy(text, markCopied);
            });
            return;
        }

        fallbackCopy(text, markCopied);
    });

    function fallbackCopy(text, onSuccess) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            if (document.execCommand('copy')) {
                onSuccess();
            }
        } finally {
            document.body.removeChild(textarea);
        }
    }

    // Assemble mailto in the browser so Cloudflare email obfuscation cannot
    // rewrite a static mailto into a broken /cdn-cgi/l/email-protection link.
    document.querySelectorAll('[data-email-mailto]').forEach(function (node) {
        if (!(node instanceof HTMLAnchorElement)) return;
        var address = atob('aGVsbG9AYWRhbXNpbW1zLnh5eg==');
        node.href = 'mailto:' + address;
        if (!node.hasAttribute('data-email-keep-label')) {
            node.textContent = address;
        }
    });
})();
