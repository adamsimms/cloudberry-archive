(function() {
    'use strict';

    var cfg = window.pinchardViewer;
    if (!cfg || !cfg.cdnUrl || !Array.isArray(cfg.filenames) || cfg.filenames.length === 0) {
        return;
    }

    var motion = window.pinchardMotion;
    var BROWSE_FADE_MS = cfg.fadeMs != null ? cfg.fadeMs : 1000;
    var INTRO_FADE_MS = cfg.introFadeMs != null ? cfg.introFadeMs : 700;
    var PLAY_FADE_MS = cfg.playFadeMs != null ? cfg.playFadeMs : 8000;
    var PLAY_DISPLAY_MS = cfg.playDisplayMs != null ? cfg.playDisplayMs : 0;
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducedMotion) {
        BROWSE_FADE_MS = 0;
        INTRO_FADE_MS = 0;
        PLAY_FADE_MS = 0;
    }

    var placeholder = document.getElementById('preview_image');
    var viewer = document.getElementById('photoViewer');
    var drawer = document.getElementById('detailDrawer');
    var toggle = document.getElementById('detailToggle');
    var prevLink = document.querySelector('.viewer-photo-prev');
    var nextLink = document.querySelector('.viewer-photo-next');
    var playToggle = document.getElementById('viewerPlayToggle');
    var fullscreenToggle = document.getElementById('viewerFullscreenToggle');
    var timelineRange = document.getElementById('viewerTimelineRange');
    var timelinePosition = document.getElementById('viewerTimelinePosition');
    var titleEl = document.getElementById('viewerPhotoTitle');
    var dateEl = document.getElementById('viewerPhotoDate');
    var cameraEl = document.getElementById('viewerCameraLines');
    var gpsEl = document.getElementById('viewerGpsLines');
    var weatherArea = document.getElementById('viewerWeatherArea');
    var weatherEl = document.getElementById('viewerWeatherLines');
    var citationBtn = document.querySelector('.detail-citation-copy');
    var hiddenTitle = document.querySelector('.viewer-page h1.visually-hidden');

    if (!placeholder || !viewer) {
        return;
    }

    var currentMainImg = null;
    var transitioning = false;
    var navGeneration = 0;
    var navDirection = 1;
    var metadataRequest = null;
    var prefetchCache = Object.create(null);
    var playing = false;
    var scrubbing = false;
    var advanceTimer = null;
    var kioskActive = document.body.classList.contains('viewer-page--kiosk');

    cfg.currentIndex = typeof cfg.currentIndex === 'number' ? cfg.currentIndex : 0;
    cfg.currentFilename = cfg.filenames[cfg.currentIndex] || cfg.currentFilename || '';

    function activeFadeMs() {
        return playing ? PLAY_FADE_MS : BROWSE_FADE_MS;
    }

    var catalogByFilename = null;
    var catalogPromise = null;

    function pageBase() {
        if (typeof cfg.basePath === 'string' && cfg.basePath !== '') {
            return cfg.basePath.replace(/\/$/, '');
        }
        return '';
    }

    function photoPageUrl(filename) {
        var params = new URLSearchParams();
        params.set('filename', filename);
        if (playing) {
            params.set('play', '1');
        }
        if (kioskActive) {
            params.set('kiosk', '1');
        }
        if (cfg.playDisplayFromUrl && cfg.playDisplayMs != null) {
            params.set('display', String(cfg.playDisplayMs / 1000));
        }
        if (cfg.playFadeFromUrl && cfg.playFadeMs != null) {
            params.set('fade', String(cfg.playFadeMs / 1000));
        }
        var base = pageBase();
        if (base) {
            return base + '/?' + params.toString();
        }
        return 'index.php?' + params.toString();
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatTempC(temp) {
        if (typeof temp !== 'number') {
            return '';
        }
        var rounded = Math.round(temp * 10) / 10;
        var text = String(rounded);
        if (text.indexOf('.') === -1) {
            text += '.0';
        }
        return (rounded < 0 ? '\u2212' + text.slice(1) : text) + ' \u00b0C';
    }

    function formatSpeed(kmh) {
        if (typeof kmh !== 'number') {
            return '';
        }
        return String(Math.round(kmh * 10) / 10);
    }

    function cameraLinesHtmlFromRecord(camera) {
        if (!camera) {
            return 'Make:<br>Model:<br>Focal Length:<br>Exposure:<br>Image Size:<br>Resolution:';
        }
        var lines = [];
        lines.push(camera.make ? 'Make: ' + escapeHtml(camera.make) : 'Make:');
        lines.push(camera.model ? 'Model: ' + escapeHtml(camera.model) : 'Model:');
        lines.push(
            typeof camera.focalLengthMm === 'number'
                ? 'Focal Length: ' + camera.focalLengthMm.toFixed(2) + ' mm'
                : 'Focal Length:'
        );
        if (camera.exposureDisplay && typeof camera.fNumber === 'number' && camera.iso != null) {
            lines.push(
                'Exposure: ' + escapeHtml(camera.exposureDisplay) + ' sec, f/' +
                camera.fNumber.toFixed(1) + '; ISO ' + escapeHtml(String(camera.iso))
            );
        } else {
            lines.push('Exposure:');
        }
        if (camera.width && camera.height) {
            lines.push('Image Size: ' + camera.width + ' x ' + camera.height);
        } else {
            lines.push('Image Size:');
        }
        lines.push(
            typeof camera.resolutionPpi === 'number'
                ? 'Resolution: ' + camera.resolutionPpi.toFixed(2) + ' pixels per inch'
                : 'Resolution:'
        );
        return lines.join('<br>');
    }

    function gpsHtmlFromRecord(gps) {
        if (!gps) {
            return '';
        }
        var latD = gps.latitudeDegree != null ? gps.latitudeDegree : '';
        var latM = gps.latitudeMin != null ? gps.latitudeMin : '';
        var latS = gps.latitudeSec != null ? gps.latitudeSec : '';
        var lonD = gps.longitudeDegree != null ? gps.longitudeDegree : '';
        var lonM = gps.longitudeMin != null ? gps.longitudeMin : '';
        var lonS = gps.longitudeSec != null ? gps.longitudeSec : '';
        var alt = typeof gps.altitudeM === 'number'
            ? 'Altitude: ' + gps.altitudeM.toFixed(2) + ' m'
            : 'Altitude:';
        return 'Position: ' + latD + '&deg; ' + latM + '&acute; ' + latS + '&quot; N, '
            + lonD + '&deg; ' + lonM + '&acute; ' + lonS + '&quot; W<br>' + alt;
    }

    function weatherHtmlFromRecord(weather) {
        if (!weather || typeof weather.temperatureC !== 'number' || typeof weather.windSpeedKmh !== 'number') {
            return '';
        }
        var lines = [];
        lines.push(
            'Conditions: ' + escapeHtml(formatTempC(weather.temperatureC)) +
            ' · ' + escapeHtml(weather.conditionsLabel || 'Unknown')
        );
        var wind = 'Wind: ' + escapeHtml(weather.windCompass || '') + ' '
            + escapeHtml(formatSpeed(weather.windSpeedKmh)) + ' km/h';
        if (typeof weather.windGustsKmh === 'number') {
            wind += ' · gusts ' + escapeHtml(formatSpeed(weather.windGustsKmh)) + ' km/h';
        }
        lines.push(wind);
        var precipParts = [];
        if (typeof weather.rainMm === 'number' && weather.rainMm > 0) {
            precipParts.push(weather.rainMm + ' mm rain');
        }
        if (typeof weather.snowfallCm === 'number' && weather.snowfallCm > 0) {
            precipParts.push(weather.snowfallCm + ' cm snow');
        }
        if (precipParts.length === 0 && typeof weather.precipitationMm === 'number' && weather.precipitationMm > 0) {
            precipParts.push(weather.precipitationMm + ' mm');
        }
        if (precipParts.length) {
            lines.push('Precipitation: ' + precipParts.join(' · '));
        }
        return lines.join('<br>');
    }

    function citationFromRecord(photo) {
        if (!photo) {
            return '';
        }
        var origin = typeof cfg.siteOrigin === 'string' && cfg.siteOrigin
            ? cfg.siteOrigin.replace(/\/$/, '')
            : (window.location.origin || '');
        var path = photo.citationPath || (pageBase() + '/?filename=' + encodeURIComponent(photo.filename));
        var when = photo.convertedDate || photo.date || '';
        return 'Cloudberry. Automated photograph, ' + when
            + '; photo ID ' + (photo.title || '')
            + ' (' + photo.filename + '). '
            + origin + path
            + '. Accessed ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + '.';
    }

    function payloadFromCatalogPhoto(photo, index) {
        var gps = photo.gps || {};
        return {
            filename: photo.filename,
            imageUrl: photo.imageUrl || imageUrl(photo.filename),
            prevFilename: adjacentFilename(-1),
            nextFilename: adjacentFilename(1),
            index: index,
            photoTitle: photo.title || '',
            photoAlt: 'Photograph from Pinchard\'s Island',
            convertedDate: photo.convertedDate || photo.date || '',
            showDate: photo.showDate || '',
            cameraLinesHtml: cameraLinesHtmlFromRecord(photo.camera),
            gpsHtml: gpsHtmlFromRecord(gps),
            weatherHtml: weatherHtmlFromRecord(photo.weather),
            citation: citationFromRecord(photo),
            mapLat: gps.lat,
            mapLon: gps.lon,
            hasGps: !!gps.hasGps,
            timelineIndex: index,
            archiveDate: photo.date,
            captureDateIso: photo.captureDateIso,
            ogDescription: photo.convertedDate
                ? 'Photograph from Pinchard\'s Island — ' + photo.convertedDate + '.'
                : 'Photograph from Pinchard\'s Island.'
        };
    }

    function ensureCatalog() {
        if (catalogByFilename) {
            return Promise.resolve(catalogByFilename);
        }
        if (catalogPromise) {
            return catalogPromise;
        }
        if (cfg.catalog && Array.isArray(cfg.catalog.photos)) {
            catalogByFilename = Object.create(null);
            cfg.catalog.photos.forEach(function(photo) {
                catalogByFilename[photo.filename] = photo;
            });
            return Promise.resolve(catalogByFilename);
        }
        if (!cfg.catalogUrl) {
            return Promise.resolve(null);
        }
        catalogPromise = fetch(cfg.catalogUrl)
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('Catalog request failed');
                }
                return res.json();
            })
            .then(function(catalog) {
                catalogByFilename = Object.create(null);
                (catalog.photos || []).forEach(function(photo) {
                    catalogByFilename[photo.filename] = photo;
                });
                cfg.catalog = catalog;
                return catalogByFilename;
            })
            .catch(function() {
                catalogByFilename = null;
                return null;
            });
        return catalogPromise;
    }

    function imageUrl(filename) {
        var viewBase = typeof cfg.cdnViewUrl === 'string' && cfg.cdnViewUrl
            ? cfg.cdnViewUrl
            : (cfg.cdnUrl + 'view/');
        return viewBase + filename;
    }

    function thumbUrl(filename) {
        if (typeof cfg.cdnThumbUrl === 'string' && cfg.cdnThumbUrl) {
            return cfg.cdnThumbUrl + filename;
        }
        return imageUrl(filename);
    }

    function fullImageUrl(filename) {
        return cfg.cdnUrl + filename;
    }

    function resolveDisplayUrl(filename) {
        // Prefer 1280w view derivative; fall back to thumb (never the 4MB master).
        return imageUrl(filename);
    }

    function setPlaceholderUnderlay(url) {
        if (!url) {
            return;
        }
        placeholder.style.backgroundImage = 'url("' + url.replace(/"/g, '\\"') + '")';
        placeholder.style.backgroundSize = 'cover';
        placeholder.style.backgroundPosition = 'center';
    }

    function filenameFromIndex(index) {
        return cfg.filenames[index] || null;
    }

    function adjacentFilename(offset) {
        var len = cfg.filenames.length;
        if (len === 0) {
            return null;
        }
        if (playing) {
            return cfg.filenames[(cfg.currentIndex + offset + len) % len];
        }
        var nextIndex = cfg.currentIndex + offset;
        if (nextIndex < 0 || nextIndex >= len) {
            return null;
        }
        return cfg.filenames[nextIndex];
    }

    function indexFromFilename(filename) {
        return cfg.filenames.indexOf(filename);
    }

    function preloadUrl(url) {
        if (prefetchCache[url]) {
            return prefetchCache[url];
        }
        prefetchCache[url] = new Promise(function(resolve, reject) {
            var img = new Image();
            img.onload = function() { resolve(img); };
            img.onerror = reject;
            img.src = url;
        });
        return prefetchCache[url];
    }

    function prefetchAdjacent() {
        var prev = adjacentFilename(-1);
        var next = adjacentFilename(1);
        if (prev && prev !== cfg.currentFilename) {
            preloadUrl(imageUrl(prev));
        }
        if (next && next !== cfg.currentFilename) {
            preloadUrl(imageUrl(next));
        }
    }

    function setNavLinkState(link, filename) {
        if (!link) {
            return;
        }
        if (!filename) {
            link.classList.add('is-hidden');
            link.setAttribute('aria-hidden', 'true');
            link.setAttribute('tabindex', '-1');
            link.setAttribute('href', photoPageUrl(''));
            return;
        }
        link.classList.remove('is-hidden');
        link.removeAttribute('aria-hidden');
        link.removeAttribute('tabindex');
        link.setAttribute('href', photoPageUrl(filename));
    }

    function updateNavLinks() {
        var prev = adjacentFilename(-1);
        var next = adjacentFilename(1);
        if (playing && cfg.filenames.length < 2) {
            prev = null;
            next = null;
        }
        cfg.prevUrl = prev ? photoPageUrl(prev) : '';
        cfg.nextUrl = next ? photoPageUrl(next) : '';
        setNavLinkState(prevLink, prev);
        setNavLinkState(nextLink, next);
        prefetchAdjacent();
    }

    function timelineEntry(idx) {
        if (!cfg.timeline || !cfg.timeline.entries || !cfg.timeline.entries.length) {
            return null;
        }
        return cfg.timeline.entries[Math.max(0, Math.min(cfg.timeline.entries.length - 1, idx))];
    }

    function updateTimelineUi(idx, options) {
        options = options || {};
        // Autoplay metadata must not yank the scrubber while the user is dragging it.
        if (scrubbing && !options.fromScrub) {
            return;
        }
        if (!timelineRange || !cfg.timeline || !cfg.timeline.entries) {
            return;
        }
        var entry = timelineEntry(idx);
        if (!entry) {
            return;
        }
        var position = idx + 1;
        var count = cfg.timeline.entries.length;
        timelineRange.value = String(idx);
        timelineRange.setAttribute('aria-valuenow', String(position));
        timelineRange.setAttribute('aria-valuetext', 'Photograph ' + position + ' of ' + count + ', ' + entry.d);
        if (timelinePosition) {
            if (motion && typeof motion.fadeText === 'function') {
                motion.fadeText(timelinePosition, entry.d);
            } else {
                timelinePosition.textContent = entry.d;
            }
        }
    }

    function syncTimelineToFilename(filename) {
        if (!cfg.timeline || !cfg.timeline.entries) {
            return;
        }
        for (var i = 0; i < cfg.timeline.entries.length; i++) {
            if (cfg.timeline.entries[i].f === filename) {
                updateTimelineUi(i);
                return;
            }
        }
    }

    function updateDocumentTitle(title) {
        document.title = 'Cloudberry — ' + title;
        if (hiddenTitle) {
            hiddenTitle.textContent = 'Cloudberry — ' + title;
        }
    }

    function updateMap(payload) {
        var mapState = window.pinchardPhotoMap;
        if (!mapState || !mapState.map || !mapState.marker) {
            return;
        }
        mapState.marker.setLngLat([payload.mapLon, payload.mapLat]);
        mapState.map.easeTo({
            center: [payload.mapLon, payload.mapLat],
            duration: activeFadeMs()
        });
    }

    function applyMetadata(payload) {
        if (payload.filename !== cfg.currentFilename) {
            return;
        }
        var canFade = motion && motion.available;
        if (titleEl) {
            if (canFade && typeof motion.fadeText === 'function') {
                motion.fadeText(titleEl, payload.photoTitle);
            } else {
                titleEl.textContent = payload.photoTitle;
            }
        }
        if (dateEl) {
            if (canFade && typeof motion.fadeHtml === 'function') {
                motion.fadeHtml(dateEl, payload.convertedDate);
            } else {
                dateEl.innerHTML = payload.convertedDate;
            }
        }
        if (cameraEl) {
            if (canFade && typeof motion.fadeHtml === 'function') {
                motion.fadeHtml(cameraEl, payload.cameraLinesHtml);
            } else {
                cameraEl.innerHTML = payload.cameraLinesHtml;
            }
        }
        if (gpsEl) {
            if (canFade && typeof motion.fadeHtml === 'function') {
                motion.fadeHtml(gpsEl, payload.gpsHtml);
            } else {
                gpsEl.innerHTML = payload.gpsHtml;
            }
        }
        if (weatherEl && weatherArea) {
            var weatherHtml = typeof payload.weatherHtml === 'string' ? payload.weatherHtml : '';
            if (weatherHtml === '') {
                weatherArea.classList.add('is-hidden');
                weatherEl.innerHTML = '';
            } else {
                weatherArea.classList.remove('is-hidden');
                if (canFade && typeof motion.fadeHtml === 'function') {
                    motion.fadeHtml(weatherEl, weatherHtml);
                } else {
                    weatherEl.innerHTML = weatherHtml;
                }
            }
        }
        if (citationBtn) {
            citationBtn.setAttribute('data-citation', payload.citation);
        }
        updateDocumentTitle(payload.photoTitle);
        updateMap(payload);
        if (typeof payload.timelineIndex === 'number') {
            updateTimelineUi(payload.timelineIndex);
            if (payload.showDate && cfg.timeline && cfg.timeline.entries && cfg.timeline.entries[payload.timelineIndex]) {
                cfg.timeline.entries[payload.timelineIndex].d = payload.showDate;
                if (timelinePosition) {
                    if (canFade && typeof motion.fadeText === 'function') {
                        motion.fadeText(timelinePosition, payload.showDate);
                    } else {
                        timelinePosition.textContent = payload.showDate;
                    }
                }
            }
        } else if (payload.showDate && timelinePosition) {
            if (canFade && typeof motion.fadeText === 'function') {
                motion.fadeText(timelinePosition, payload.showDate);
            } else {
                timelinePosition.textContent = payload.showDate;
            }
        }
    }

    function fetchMetadata(filename) {
        if (metadataRequest) {
            metadataRequest.abort();
            metadataRequest = null;
        }

        if (cfg.catalogUrl || (cfg.catalog && Array.isArray(cfg.catalog.photos))) {
            return ensureCatalog().then(function(byName) {
                if (!byName || !byName[filename]) {
                    return null;
                }
                var index = indexFromFilename(filename);
                var payload = payloadFromCatalogPhoto(byName[filename], index >= 0 ? index : 0);
                applyMetadata(payload);
                return payload;
            });
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var url = 'viewer-photo.php?filename=' + encodeURIComponent(filename);
        var options = controller ? { signal: controller.signal } : {};
        metadataRequest = controller;

        return fetch(url, options)
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('Metadata request failed');
                }
                return res.json();
            })
            .then(function(payload) {
                applyMetadata(payload);
                return payload;
            })
            .catch(function(err) {
                if (err && err.name === 'AbortError') {
                    return null;
                }
                return null;
            })
            .finally(function() {
                metadataRequest = null;
            });
    }

    function waitForImagePaint(img) {
        if (!img) {
            return Promise.resolve();
        }
        if (typeof img.decode === 'function') {
            return img.decode().catch(function() {
                return null;
            });
        }
        if (img.complete && img.naturalWidth > 0) {
            return Promise.resolve();
        }
        return new Promise(function(resolve) {
            img.onload = function() { resolve(); };
            img.onerror = function() { resolve(); };
        });
    }

    function crossfadeTo(url, alt, generation) {
        var fadeMs = activeFadeMs();
        var expectedGeneration = generation == null ? navGeneration : generation;
        return preloadUrl(url).then(function() {
            if (expectedGeneration !== navGeneration) {
                return;
            }
            var old = currentMainImg;

            // Pin the current photo (and underlay) before the next layer appears,
            // so the white placeholder never shows through the dissolve.
            if (old && old.src) {
                setPlaceholderUnderlay(old.src);
                if (motion && motion.available && motion.gsap) {
                    motion.gsap.killTweensOf(old);
                }
                old.classList.add('loaded');
                old.style.opacity = '1';
                old.style.zIndex = '1';
            }

            var newImg = document.createElement('img');
            newImg.src = url;
            newImg.alt = alt;
            newImg.className = 'viewer-photo-main';
            newImg.style.opacity = '0';
            newImg.style.zIndex = '2';
            placeholder.appendChild(newImg);

            return waitForImagePaint(newImg).then(function() {
                if (expectedGeneration !== navGeneration) {
                    if (newImg.parentNode) {
                        newImg.parentNode.removeChild(newImg);
                    }
                    return;
                }
                var useMotion = motion && motion.available && typeof motion.crossfadeViewer === 'function'
                    && old && fadeMs > 0;

                if (useMotion) {
                    return motion.crossfadeViewer(old, newImg, navDirection, fadeMs).then(function() {
                        if (expectedGeneration !== navGeneration) {
                            return;
                        }
                        currentMainImg = newImg;
                        placeholder.dataset.large = url;
                    });
                }

                return new Promise(function(resolve) {
                    if (expectedGeneration !== navGeneration) {
                        if (newImg.parentNode) {
                            newImg.parentNode.removeChild(newImg);
                        }
                        resolve();
                        return;
                    }
                    if (old) {
                        old.style.opacity = '1';
                        old.style.zIndex = '1';
                    }

                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            var finished = false;

                            function finish() {
                                if (finished) {
                                    return;
                                }
                                finished = true;
                                if (expectedGeneration !== navGeneration) {
                                    if (newImg.parentNode) {
                                        newImg.parentNode.removeChild(newImg);
                                    }
                                    resolve();
                                    return;
                                }
                                if (old && old.parentNode) {
                                    old.parentNode.removeChild(old);
                                }
                                newImg.classList.remove('is-fading-in');
                                newImg.classList.add('loaded');
                                newImg.style.transition = 'none';
                                newImg.style.opacity = '1';
                                newImg.style.zIndex = '2';
                                setPlaceholderUnderlay(url);
                                currentMainImg = newImg;
                                placeholder.dataset.large = url;
                                resolve();
                            }

                            if (old && fadeMs > 0) {
                                newImg.classList.add('loaded');
                                newImg.classList.add('is-fading-in');
                                // Clear the pre-insert hide so the CSS opacity transition can run.
                                newImg.style.transition = 'opacity ' + (fadeMs / 1000) + 's ease-in-out';
                                newImg.style.opacity = '1';
                                newImg.addEventListener('transitionend', function(e) {
                                    if (e.propertyName === 'opacity') {
                                        finish();
                                    }
                                }, { once: true });
                                window.setTimeout(finish, fadeMs + 80);
                            } else {
                                newImg.classList.add('loaded');
                                newImg.style.opacity = '1';
                                finish();
                            }
                        });
                    });
                });
            });
        });
    }

    function updateHistory(filename, mode) {
        var url = photoPageUrl(filename);
        var state = { filename: filename, play: playing, kiosk: kioskActive };
        if (mode === 'replace') {
            history.replaceState(state, '', url);
        } else if (mode === 'push') {
            history.pushState(state, '', url);
        }
    }

    function clearAdvanceTimer() {
        if (advanceTimer !== null) {
            clearTimeout(advanceTimer);
            advanceTimer = null;
        }
    }

    function scheduleAdvance() {
        clearAdvanceTimer();
        if (!playing || scrubbing || transitioning || cfg.filenames.length < 2) {
            return;
        }
        advanceTimer = setTimeout(function() {
            advanceAutoplay();
        }, PLAY_DISPLAY_MS);
    }

    function advanceAutoplay() {
        if (!playing || scrubbing || transitioning) {
            return;
        }
        navigateByOffset(1, { history: 'replace' });
    }

    function setPlaying(nextPlaying, options) {
        options = options || {};
        playing = !!nextPlaying;
        clearAdvanceTimer();
        if (playToggle) {
            playToggle.classList.toggle('is-paused', !playing);
            playToggle.setAttribute('aria-label', playing ? 'Pause slideshow' : 'Play slideshow');
        }
        updateNavLinks();
        if (options.syncUrl !== false) {
            updateHistory(cfg.currentFilename, 'replace');
        }
        if (playing && !scrubbing && !transitioning) {
            scheduleAdvance();
        }
    }

    function cancelInFlightVisualTransition() {
        var imgs = Array.prototype.slice.call(
            placeholder.querySelectorAll('img.viewer-photo-main')
        );
        if (motion && motion.gsap) {
            imgs.forEach(function(img) {
                motion.gsap.killTweensOf(img);
            });
        }
        var keep = null;
        if (currentMainImg && currentMainImg.parentNode === placeholder) {
            keep = currentMainImg;
        } else if (imgs.length) {
            keep = imgs[imgs.length - 1];
        }
        imgs.forEach(function(img) {
            if (img !== keep && img.parentNode) {
                img.parentNode.removeChild(img);
            }
        });
        if (keep) {
            keep.style.transition = 'none';
            keep.style.opacity = '1';
            keep.style.zIndex = '2';
            keep.classList.add('loaded');
            currentMainImg = keep;
            if (keep.src) {
                setPlaceholderUnderlay(keep.src);
            }
        }
        transitioning = false;
    }

    function navigateToFilename(filename, options) {
        options = options || {};
        var index = indexFromFilename(filename);
        if (index < 0) {
            return Promise.resolve(false);
        }
        if (filename === cfg.currentFilename && !options.force) {
            return Promise.resolve(false);
        }
        // During long play-mode fades, block overlapping arrow nav unless interrupt
        // (timeline scrub / forced seek).
        if (transitioning && !options.interrupt) {
            return Promise.resolve(false);
        }

        clearAdvanceTimer();
        // Invalidate any in-flight fade before visual cancel so its callbacks no-op.
        var myGeneration = ++navGeneration;
        if (transitioning && options.interrupt) {
            cancelInFlightVisualTransition();
        }
        transitioning = true;
        navDirection = index >= cfg.currentIndex ? 1 : -1;
        // Wraparound can move from last→first; keep direction forward when playing.
        if (playing && options.wrapDir) {
            navDirection = options.wrapDir;
        }
        cfg.currentIndex = index;
        cfg.currentFilename = filename;
        updateNavLinks();

        if (options.history === 'push') {
            updateHistory(filename, 'push');
        } else if (options.history === 'replace') {
            updateHistory(filename, 'replace');
        } else if (options.history === 'none') {
            // URL already matches browser history.
        }

        syncTimelineToFilename(filename);

        var metaPromise = fetchMetadata(filename);
        var fadePromise = crossfadeTo(imageUrl(filename), '', myGeneration);

        return Promise.all([metaPromise, fadePromise]).then(function(results) {
            if (myGeneration !== navGeneration) {
                return false;
            }
            var payload = results[0];
            if (payload && payload.photoAlt && currentMainImg) {
                currentMainImg.alt = payload.photoAlt;
            }
            return true;
        }).catch(function() {
            if (myGeneration !== navGeneration) {
                return false;
            }
            if (options.history === 'push') {
                history.back();
            }
            return false;
        }).finally(function() {
            if (myGeneration !== navGeneration) {
                return;
            }
            transitioning = false;
            if (playing && !scrubbing) {
                scheduleAdvance();
            }
        });
    }

    function navigateByOffset(offset, options) {
        var target = adjacentFilename(offset);
        if (!target) {
            return Promise.resolve(false);
        }
        options = options || {};
        if (!options.history) {
            options.history = 'push';
        }
        if (playing) {
            options.wrapDir = offset >= 0 ? 1 : -1;
        }
        return navigateToFilename(target, options);
    }

    function initInitialPhoto() {
        var existing = placeholder.querySelector('img.viewer-photo-main');
        var largeUrl = placeholder.dataset.large || (cfg.currentFilename ? resolveDisplayUrl(cfg.currentFilename) : '');
        var alt = placeholder.dataset.alt || '';

        if (existing) {
            currentMainImg = existing;
            if (!existing.getAttribute('src') && largeUrl) {
                existing.src = largeUrl;
            }
            existing.classList.add('loaded');
            existing.style.opacity = '1';
            setPlaceholderUnderlay(existing.currentSrc || existing.src || largeUrl);
            prefetchAdjacent();
            // If the view derivative 404s, fall back to thumb (not the 4MB master).
            existing.addEventListener('error', function onViewError() {
                existing.removeEventListener('error', onViewError);
                var thumb = placeholder.dataset.thumb || (cfg.currentFilename ? thumbUrl(cfg.currentFilename) : '');
                if (thumb && existing.src !== thumb) {
                    existing.src = thumb;
                }
            });
            return;
        }

        if (!largeUrl) {
            return;
        }

        // Start on white; fade the display image in once it is ready.
        placeholder.style.backgroundImage = '';
        preloadUrl(largeUrl).then(function() {
            var imgLarge = document.createElement('img');
            imgLarge.src = largeUrl;
            imgLarge.alt = alt;
            imgLarge.className = 'viewer-photo-main';
            imgLarge.style.opacity = '0';
            placeholder.appendChild(imgLarge);

            function show() {
                imgLarge.classList.add('loaded');
                if (motion && motion.available && motion.gsap && INTRO_FADE_MS > 0) {
                    motion.gsap.killTweensOf(imgLarge);
                    motion.gsap.fromTo(imgLarge, { opacity: 0 }, {
                        opacity: 1,
                        duration: INTRO_FADE_MS / 1000,
                        ease: 'sine.out',
                        onComplete: function () {
                            imgLarge.style.opacity = '1';
                            setPlaceholderUnderlay(largeUrl);
                        }
                    });
                } else {
                    imgLarge.style.opacity = '1';
                    setPlaceholderUnderlay(largeUrl);
                }
            }

            waitForImagePaint(imgLarge).then(show);
            currentMainImg = imgLarge;
            prefetchAdjacent();
        }).catch(function() {
            var thumb = placeholder.dataset.thumb || (cfg.currentFilename ? thumbUrl(cfg.currentFilename) : '');
            if (!thumb) return;
            var img = document.createElement('img');
            img.src = thumb;
            img.alt = alt;
            img.className = 'viewer-photo-main loaded';
            img.style.opacity = '1';
            placeholder.appendChild(img);
            currentMainImg = img;
            setPlaceholderUnderlay(thumb);
        });
    }

    function setDrawerOpen(open) {
        if (!drawer || !toggle) {
            return;
        }
        if (motion && typeof motion.setDetailDrawerOpen === 'function') {
            motion.setDetailDrawerOpen(drawer, toggle, open);
            return;
        }
        drawer.classList.toggle('open', open);
        toggle.classList.toggle('down_arrow', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Hide photograph details' : 'Show photograph details');
    }

    function requestBrowserFullscreen() {
        var el = document.documentElement;
        var req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
        if (req) {
            try {
                var result = req.call(el);
                if (result && typeof result.catch === 'function') {
                    result.catch(function() { /* user denied or unavailable */ });
                }
            } catch (err) {
                /* ignore */
            }
        }
    }

    function exitBrowserFullscreen() {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            return;
        }
        var exit = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
        if (exit) {
            try {
                var result = exit.call(document);
                if (result && typeof result.catch === 'function') {
                    result.catch(function() { /* ignore */ });
                }
            } catch (err) {
                /* ignore */
            }
        }
    }

    function updateFullscreenToggle() {
        if (!fullscreenToggle) {
            return;
        }
        fullscreenToggle.classList.toggle('is-active', kioskActive);
        fullscreenToggle.setAttribute('aria-pressed', kioskActive ? 'true' : 'false');
        fullscreenToggle.setAttribute('aria-label', kioskActive ? 'Exit fullscreen' : 'Enter fullscreen');
    }

    function setKiosk(active, options) {
        options = options || {};
        if (active === kioskActive) {
            return;
        }
        kioskActive = active;
        document.body.classList.toggle('viewer-page--kiosk', active);
        updateFullscreenToggle();
        if (active) {
            setDrawerOpen(false);
        }
        if (options.syncUrl !== false) {
            updateHistory(cfg.currentFilename, 'replace');
        }
        if (options.browserFullscreen !== false) {
            if (active) {
                requestBrowserFullscreen();
            } else {
                exitBrowserFullscreen();
            }
        }
    }

    function toggleKiosk() {
        setKiosk(!kioskActive);
    }

    if (drawer && toggle) {
        toggle.addEventListener('click', function() {
            setDrawerOpen(!drawer.classList.contains('open'));
        });
    }

    if (playToggle) {
        playToggle.addEventListener('click', function() {
            setPlaying(!playing);
        });
    }

    if (fullscreenToggle) {
        fullscreenToggle.addEventListener('click', function() {
            toggleKiosk();
        });
    }

    document.addEventListener('fullscreenchange', function() {
        if (!document.fullscreenElement && kioskActive) {
            setKiosk(false, { browserFullscreen: false });
        }
    });
    document.addEventListener('webkitfullscreenchange', function() {
        if (!document.webkitFullscreenElement && kioskActive) {
            setKiosk(false, { browserFullscreen: false });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.isContentEditable)) {
            return;
        }

        if (e.key === 'Escape') {
            if (kioskActive) {
                e.preventDefault();
                setKiosk(false);
            }
            return;
        }

        if (e.key === 'f' || e.key === 'F') {
            e.preventDefault();
            toggleKiosk();
            return;
        }

        if (e.key === ' ' || e.key === 'Spacebar') {
            e.preventDefault();
            setPlaying(!playing);
            return;
        }

        if (e.key === 'ArrowLeft') {
            if (!adjacentFilename(-1)) {
                return;
            }
            e.preventDefault();
            navigateByOffset(-1);
        } else if (e.key === 'ArrowRight') {
            if (!adjacentFilename(1)) {
                return;
            }
            e.preventDefault();
            navigateByOffset(1);
        } else if (e.key === 'ArrowUp' && drawer && toggle && !drawer.classList.contains('open') && !kioskActive) {
            e.preventDefault();
            setDrawerOpen(true);
        } else if (e.key === 'ArrowDown' && drawer && toggle && drawer.classList.contains('open')) {
            e.preventDefault();
            setDrawerOpen(false);
        }
    });

    if (timelineRange && cfg.timeline && cfg.timeline.entries && cfg.timeline.entries.length > 1) {
        timelineRange.addEventListener('input', function() {
            updateTimelineUi(parseInt(timelineRange.value, 10), { fromScrub: true });
        });

        timelineRange.addEventListener('change', function() {
            var entry = timelineEntry(parseInt(timelineRange.value, 10));
            if (!entry || entry.f === cfg.currentFilename) {
                return;
            }
            // Interrupt long play-mode crossfades so scrub always seeks.
            navigateToFilename(entry.f, { history: 'push', interrupt: true });
        });

        timelineRange.addEventListener('pointerdown', function(e) {
            e.stopPropagation();
            scrubbing = true;
            clearAdvanceTimer();
        });

        timelineRange.addEventListener('pointerup', function() {
            scrubbing = false;
            if (playing && !transitioning) {
                scheduleAdvance();
            }
        });

        timelineRange.addEventListener('pointercancel', function() {
            scrubbing = false;
            if (playing && !transitioning) {
                scheduleAdvance();
            }
        });

        timelineRange.addEventListener('touchstart', function(e) {
            e.stopPropagation();
            scrubbing = true;
            clearAdvanceTimer();
        }, { passive: true });

        timelineRange.addEventListener('touchend', function(e) {
            e.stopPropagation();
            scrubbing = false;
            if (playing && !transitioning) {
                scheduleAdvance();
            }
        }, { passive: true });

        var timelineNav = document.getElementById('viewerTimeline');
        if (timelineNav) {
            timelineNav.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }

    viewer.addEventListener('click', function(e) {
        var link = e.target.closest('.viewer-photo-prev, .viewer-photo-next');
        if (!link || link.classList.contains('is-hidden')) {
            return;
        }
        e.preventDefault();
        if (link.classList.contains('viewer-photo-prev')) {
            navigateByOffset(-1);
        } else {
            navigateByOffset(1);
        }
    });

    var touchStartX = 0;
    viewer.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    viewer.addEventListener('touchend', function(e) {
        var dx = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(dx) < 50) {
            return;
        }
        if (dx > 0) {
            if (playing || cfg.currentIndex > 0) {
                navigateByOffset(-1);
            }
        } else if (playing || cfg.currentIndex < cfg.filenames.length - 1) {
            navigateByOffset(1);
        }
    }, { passive: true });

    window.addEventListener('popstate', function() {
        var params = new URLSearchParams(window.location.search);
        var filename = params.get('filename');
        if (!filename) {
            return;
        }
        var nextPlay = params.get('play') === '1';
        var nextKiosk = params.get('kiosk') === '1';
        if (nextPlay !== playing) {
            setPlaying(nextPlay, { syncUrl: false });
        }
        if (nextKiosk !== kioskActive) {
            setKiosk(nextKiosk, { syncUrl: false, browserFullscreen: false });
        }
        if (filename === cfg.currentFilename) {
            return;
        }
        navigateToFilename(filename, { history: 'none' });
    });

    if (Array.isArray(cfg.prefetch)) {
        cfg.prefetch.forEach(function(url) {
            preloadUrl(url);
        });
    }

    // Static archive ships one index.html (default = last photo). Honor ?filename=
    // deep links from the gallery before replaceState locks the URL to boot state.
    var bootParams = new URLSearchParams(window.location.search);
    var urlFilename = bootParams.get('filename');
    var deepLinkIndex = urlFilename ? indexFromFilename(urlFilename) : -1;

    if (deepLinkIndex >= 0 && urlFilename !== cfg.currentFilename) {
        navigateToFilename(urlFilename, { history: 'none', force: true });
    } else {
        initInitialPhoto();
        updateNavLinks();
    }
    updateFullscreenToggle();
    updateHistory(cfg.currentFilename, 'replace');

    if (cfg.playOnLoad) {
        setPlaying(true, { syncUrl: false });
    }

    if (cfg.kioskOnLoad && kioskActive) {
        // PHP already applied body class; request browser fullscreen after gesture-friendly load.
        requestBrowserFullscreen();
    }
})();
