(function() {
            var scrollEl = document.getElementById('galleryDaysScroll');
            var layout = document.querySelector('.gallery-days-layout');
            if (!scrollEl || !layout) {
                return;
            }

            var filmstripQuery = window.matchMedia('(min-width: 768px)');
            var feedDateEl = document.getElementById('galleryFeedDate');
            var photos = scrollEl.querySelectorAll('.gallery-photo[data-src]');
            var photoLinks = scrollEl.querySelectorAll('.gallery-day-photo');
            var columns = scrollEl.querySelectorAll('.gallery-day-column');
            var isDragging = false;
            var dragMoved = false;
            var pendingDrag = null;
            var fitRaf = 0;
            var feedDateRaf = 0;
            var activeFeedLabel = feedDateEl ? feedDateEl.textContent : '';

            function isFilmstrip() {
                return filmstripQuery.matches;
            }

            function updateFeedDate() {
                if (!feedDateEl || isFilmstrip() || !columns.length) {
                    return;
                }
                var scrollTop = scrollEl.scrollTop;
                var active = columns[0];
                var showSticky = false;
                for (var i = 0; i < columns.length; i++) {
                    var label = columns[i].querySelector('.gallery-day-label');
                    var labelHeight = label ? label.offsetHeight : 0;
                    // Once this day's inline label has scrolled away, it owns the sticky bar.
                    if (columns[i].offsetTop + labelHeight <= scrollTop + 1) {
                        active = columns[i];
                        showSticky = true;
                    } else {
                        break;
                    }
                }
                feedDateEl.classList.toggle('is-visible', showSticky);
                if (!showSticky) {
                    return;
                }
                var nextLabel = active.getAttribute('data-feed-label') || '';
                if (nextLabel && nextLabel !== activeFeedLabel) {
                    activeFeedLabel = nextLabel;
                    feedDateEl.textContent = nextLabel;
                }
            }

            function scheduleFeedDateUpdate() {
                if (feedDateRaf) return;
                feedDateRaf = requestAnimationFrame(function() {
                    feedDateRaf = 0;
                    updateFeedDate();
                });
            }

            function fitPhotoHeights() {
                if (!isFilmstrip()) {
                    return;
                }
                var styles = getComputedStyle(layout);
                var maxPhotos = parseInt(styles.getPropertyValue('--gallery-days-max-photos'), 10) || 12;
                var colPad = parseFloat(styles.getPropertyValue('--gallery-days-column-pad')) || 12;
                var dateLine = parseFloat(styles.getPropertyValue('--gallery-days-date-line')) || 11;
                var dateBand = dateLine + colPad + 1;
                var available = scrollEl.clientHeight - colPad - dateBand;
                if (available <= 0 || maxPhotos <= 0) {
                    return;
                }
                var photoHeight = Math.floor(available / maxPhotos);
                if (photoHeight < 20) {
                    return;
                }
                var columnWidth = Math.round(photoHeight * 288 / 224);
                var prevHeight = parseFloat(layout.style.getPropertyValue('--gallery-days-photo-height')) || 0;
                if (Math.abs(photoHeight - prevHeight) < 2 && prevHeight > 0) {
                    return;
                }
                layout.style.setProperty('--gallery-days-photo-height', photoHeight + 'px');
                layout.style.setProperty('--gallery-days-column-width', columnWidth + 'px');
            }

            function scheduleFitPhotoHeights() {
                if (!isFilmstrip()) {
                    return;
                }
                if (fitRaf) return;
                fitRaf = requestAnimationFrame(function() {
                    fitRaf = 0;
                    fitPhotoHeights();
                });
            }

            function clearFilmstripSizing() {
                layout.style.removeProperty('--gallery-days-photo-height');
                layout.style.removeProperty('--gallery-days-column-width');
            }

            function onFilmstripModeChange() {
                if (isFilmstrip()) {
                    fitPhotoHeights();
                } else {
                    clearFilmstripSizing();
                    endDrag();
                    updateFeedDate();
                }
            }

            fitPhotoHeights();
            updateFeedDate();
            window.addEventListener('resize', scheduleFitPhotoHeights);
            scrollEl.addEventListener('scroll', scheduleFeedDateUpdate, { passive: true });
            if ('ResizeObserver' in window) {
                new ResizeObserver(scheduleFitPhotoHeights).observe(scrollEl);
            }
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function() {
                    if (isFilmstrip()) {
                        fitPhotoHeights();
                    }
                });
            }
            if (typeof filmstripQuery.addEventListener === 'function') {
                filmstripQuery.addEventListener('change', onFilmstripModeChange);
            } else if (typeof filmstripQuery.addListener === 'function') {
                filmstripQuery.addListener(onFilmstripModeChange);
            }

            function markLoaded(img) {
                var box = img.closest('.photoBox');
                if (box) {
                    box.classList.add('is-loaded');
                }
            }

            function loadPhoto(img) {
                if (img.dataset.loading) return;
                if (img.getAttribute('src') && !img.getAttribute('data-src')) {
                    markLoaded(img);
                    return;
                }
                img.dataset.loading = '1';
                var src = img.getAttribute('data-src');
                if (!src) return;

                function done() {
                    markLoaded(img);
                    img.removeAttribute('data-src');
                }

                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
                img.src = src;
                if (img.complete) {
                    done();
                }
            }

            if (photos.length && 'IntersectionObserver' in window) {
                var photoObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            loadPhoto(entry.target);
                            photoObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    root: scrollEl,
                    // Prefetch well ahead so 3-up rows are warm before they arrive.
                    rootMargin: isFilmstrip() ? '160px 240px' : '800px 0px'
                });
                photos.forEach(function(img) {
                    photoObserver.observe(img);
                });
                // Eager first-column thumbs already have src — mark them loaded.
                scrollEl.querySelectorAll('.gallery-photo[src]:not([data-src])').forEach(markLoaded);
            } else {
                photos.forEach(loadPhoto);
                scrollEl.querySelectorAll('.gallery-photo[src]:not([data-src])').forEach(markLoaded);
            }

            function endDrag() {
                isDragging = false;
                pendingDrag = null;
                scrollEl.classList.remove('is-dragging');
            }

            // Mouse-only drag-to-scroll on the desktop filmstrip.
            scrollEl.addEventListener('pointerdown', function(e) {
                if (!isFilmstrip() || e.pointerType !== 'mouse' || e.button !== 0) return;
                pendingDrag = {
                    startX: e.clientX,
                    scrollLeft: scrollEl.scrollLeft,
                    pointerId: e.pointerId
                };
                dragMoved = false;
                isDragging = false;
            });

            scrollEl.addEventListener('pointermove', function(e) {
                if (!isFilmstrip() || !pendingDrag || e.pointerId !== pendingDrag.pointerId) return;
                var delta = e.clientX - pendingDrag.startX;
                if (!isDragging) {
                    if (Math.abs(delta) <= 4) return;
                    isDragging = true;
                    dragMoved = true;
                    scrollEl.classList.add('is-dragging');
                    if (scrollEl.setPointerCapture) {
                        scrollEl.setPointerCapture(e.pointerId);
                    }
                }
                scrollEl.scrollLeft = pendingDrag.scrollLeft - delta;
            });

            scrollEl.addEventListener('pointerup', function(e) {
                if (!pendingDrag || e.pointerId !== pendingDrag.pointerId) return;
                endDrag();
                window.setTimeout(function() {
                    dragMoved = false;
                }, 0);
            });
            scrollEl.addEventListener('pointercancel', function(e) {
                if (!pendingDrag || e.pointerId !== pendingDrag.pointerId) return;
                endDrag();
                dragMoved = false;
            });

            photoLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (dragMoved) {
                        e.preventDefault();
                        return;
                    }
                });
                link.addEventListener('dragstart', function(e) {
                    e.preventDefault();
                });
                link.addEventListener('focus', function() {
                    photoLinks.forEach(function(other) {
                        other.classList.toggle('is-keyboard-focus', other === link);
                    });
                });
            });

            scrollEl.addEventListener('wheel', function(e) {
                if (!isFilmstrip()) return;
                var delta = e.deltaX;
                if (e.shiftKey && e.deltaY !== 0) {
                    delta = e.deltaY;
                } else if (delta === 0 && e.deltaY !== 0) {
                    delta = e.deltaY;
                }
                if (delta === 0) return;
                scrollEl.scrollLeft += delta;
                e.preventDefault();
            }, { passive: false });

            photoLinks.forEach(function(link) {
                link.setAttribute('tabindex', '-1');
            });

            function columnPhotos(col) {
                var all = Array.prototype.slice.call(col.querySelectorAll('.gallery-day-photo'));
                if (!isFilmstrip()) {
                    return all;
                }
                var maxPhotos = parseInt(getComputedStyle(layout).getPropertyValue('--gallery-days-max-photos'), 10) || 12;
                return all.slice(0, maxPhotos);
            }

            function focusPhoto(colIndex, photoIndex) {
                if (!columns.length) return;
                colIndex = Math.max(0, Math.min(colIndex, columns.length - 1));
                var col = columns[colIndex];
                var stack = columnPhotos(col);
                if (!stack.length) return;
                photoIndex = Math.max(0, Math.min(photoIndex, stack.length - 1));
                var target = stack[photoIndex];
                photoLinks.forEach(function(link) {
                    link.classList.remove('is-keyboard-focus');
                    link.setAttribute('tabindex', '-1');
                });
                target.classList.add('is-keyboard-focus');
                target.setAttribute('tabindex', '0');
                target.focus({ preventScroll: true });
                if (isFilmstrip()) {
                    var colLeft = col.offsetLeft;
                    var colRight = colLeft + col.offsetWidth;
                    var viewLeft = scrollEl.scrollLeft;
                    var viewRight = viewLeft + scrollEl.clientWidth;
                    if (colLeft < viewLeft) {
                        scrollEl.scrollTo({ left: colLeft, behavior: 'smooth' });
                    } else if (colRight > viewRight) {
                        scrollEl.scrollTo({ left: colRight - scrollEl.clientWidth, behavior: 'smooth' });
                    }
                } else {
                    target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }

            function focusedPosition() {
                var active = document.activeElement;
                if (!active || !active.classList.contains('gallery-day-photo')) {
                    return { col: 0, photo: 0 };
                }
                var col = active.closest('.gallery-day-column');
                var colIndex = Array.prototype.indexOf.call(columns, col);
                var stack = columnPhotos(col);
                return { col: colIndex, photo: stack.indexOf(active) };
            }

            scrollEl.addEventListener('keydown', function(e) {
                if (!isFilmstrip()) {
                    return;
                }
                var pos = focusedPosition();
                if (document.activeElement !== scrollEl && !document.activeElement.classList.contains('gallery-day-photo')) {
                    focusPhoto(0, 0);
                    e.preventDefault();
                    return;
                }
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusPhoto(pos.col - 1, pos.photo);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusPhoto(pos.col + 1, pos.photo);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    focusPhoto(pos.col, pos.photo - 1);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    focusPhoto(pos.col, pos.photo + 1);
                }
            });

            scrollEl.addEventListener('focus', function() {
                if (!isFilmstrip()) {
                    return;
                }
                if (!document.activeElement.classList.contains('gallery-day-photo')) {
                    focusPhoto(0, 0);
                }
            });

            function scrollToDayColumn(target) {
                if (!target) return;
                var dayIndex = Array.prototype.indexOf.call(columns, target);
                requestAnimationFrame(function() {
                    if (isFilmstrip()) {
                        scrollEl.scrollTo({ left: target.offsetLeft, behavior: 'auto' });
                        if (dayIndex >= 0) {
                            focusPhoto(dayIndex, 0);
                        }
                    } else {
                        scrollEl.scrollTop = target.offsetTop;
                        updateFeedDate();
                    }
                });
            }

            var hash = window.location.hash;
            if (hash.indexOf('#day-') === 0) {
                scrollToDayColumn(document.querySelector(hash));
            } else if (hash.indexOf('#month-') === 0) {
                var monthKey = hash.replace('#month-', '');
                scrollToDayColumn(document.querySelector('.gallery-day-column[id^="day-' + monthKey + '"]'));
            }
        })();
