<?php

declare(strict_types=1);

/**
 * Pinchard photo viewer — single image with metadata drawer and optional autoplay.
 *
 * Query params:
 *   filename=…   current photograph
 *   play=1       start autoplay (loops the archive)
 *   display=SEC  hold time after each exhibition crossfade
 *   fade=SEC     exhibition crossfade duration (default 8)
 *   kiosk=1      start chrome-hidden / fullscreen mode (toggle with F)
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/partials/layout.php';

if (isset($_GET['fn']) && $_GET['fn'] !== '') {
	$filenameParam = isset($_GET['filename']) && $_GET['filename'] !== ''
		? (string) $_GET['filename']
		: (string) $_GET['fn'];
	header('Location: ' . pinchard_absolute_url('/index.php', ['filename' => $filenameParam]), true, 301);
	exit;
}

$playDisplay = 0.0;
$playFade = 8.0;
$playDisplayFromUrl = false;
$playFadeFromUrl = false;
if (isset($_GET['display']) && $_GET['display'] !== '') {
	$playDisplay = max(0.1, min(600.0, (float) $_GET['display']));
	$playDisplayFromUrl = true;
}
if (isset($_GET['fade']) && $_GET['fade'] !== '') {
	$playFade = max(0.0, min(60.0, (float) $_GET['fade']));
	$playFadeFromUrl = true;
}
$playOnLoad = isset($_GET['play']) && $_GET['play'] !== '' && $_GET['play'] !== '0';
$kiosk = isset($_GET['kiosk']) && $_GET['kiosk'] !== '' && $_GET['kiosk'] !== '0';

try {
	$cfg = pinchard_config();

	$requestedFn = null;
	if (isset($_GET['filename']) && $_GET['filename'] !== '') {
		$requestedFn = (string) $_GET['filename'];
	}

	$array = getObjectList($cfg['s3_bucket_thumbnails']);
	usort($array, fn ($a, $b) => $a['date'] <=> $b['date']);

	if ($array === []) {
		pinchard_unavailable_page('Photo gallery is empty or temporarily unavailable.');
	}

	$cdnFull = $cfg['cdn_url_full'];
	$resolved = pinchard_resolve_gallery_photo($array, $requestedFn);
	$filename = $resolved['photo']['filename'];
	$datetime = $resolved['photo']['date'];
	$galleryContext = pinchard_gallery_context_for_photo($datetime);
	$viewerTimeline = pinchard_viewer_timeline($array, $filename, $galleryContext);
	$payload = pinchard_viewer_photo_payload($array, $filename, $galleryContext, $cdnFull, $viewerTimeline);

	$filename = $payload['filename'];
	$prev_filename = $payload['prevFilename'];
	$next_filename = $payload['nextFilename'];
	$viewerCurrentIndex = $payload['index'];
	$viewerFilenames = array_column($array, 'filename');
	$imageUrl = $payload['imageUrl'];
	$photoAlt = $payload['photoAlt'];
	$converted_date = $payload['convertedDate'];
	$timelineDateLabel = $payload['showDate'];
	$cameraLinesHtml = $payload['cameraLinesHtml'];
	$gpsHtml = $payload['gpsHtml'];
	$weatherHtml = $payload['weatherHtml'];
	$photoCitation = $payload['citation'];
	$mapLat = $payload['mapLat'];
	$mapLon = $payload['mapLon'];
	$hasGps = $payload['hasGps'];
	$ogDescription = $payload['ogDescription'];

	$pinchardMapboxToken = pinchard_env_non_empty('MAPBOX_ACCESS_TOKEN');
	$mapJe = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	$jsonLd = [
		[
			'@type' => 'WebSite',
			'name' => 'Cloudberry',
			'url' => pinchard_absolute_url('/index.php'),
			'description' => pinchard_cloudberry_site_description(),
		],
	];
	$imageObject = [
		'@type' => 'ImageObject',
		'name' => 'Cloudberry — ' . $payload['photoTitle'],
		'description' => $ogDescription,
		'contentUrl' => $imageUrl,
		'dateCreated' => $payload['captureDateIso'],
		'creator' => [
			'@type' => 'Person',
			'name' => 'Adam Simms',
		],
		'copyrightHolder' => [
			'@type' => 'Person',
			'name' => 'Adam Simms',
		],
	];
	if ($hasGps) {
		$imageObject['contentLocation'] = [
			'@type' => 'Place',
			'name' => "Pinchard's Island, Newfoundland",
			'geo' => [
				'@type' => 'GeoCoordinates',
				'latitude' => $mapLat,
				'longitude' => $mapLon,
			],
		];
	}
	$jsonLd[] = $imageObject;

	$extraHead = pinchard_mapbox_gl_css() . "\n";
	if ($prev_filename !== null && $prev_filename !== '') {
		$extraHead .= '    <link rel="prefetch" href="' . pinchard_h($cdnFull . $prev_filename) . '" as="image">' . "\n";
	}
	if ($next_filename !== null && $next_filename !== '') {
		$extraHead .= '    <link rel="prefetch" href="' . pinchard_h($cdnFull . $next_filename) . '" as="image">' . "\n";
	}

	$bodyClass = 'viewer-page' . ($kiosk ? ' viewer-page--kiosk' : '');

	pinchard_layout_head('Cloudberry — ' . $payload['photoTitle'], [
		'description' => $ogDescription,
		'og_image' => $imageUrl,
		'og_type' => 'article',
		'body_class' => $bodyClass,
		'extra_head' => $extraHead,
		'json_ld' => $jsonLd,
	]);

	pinchard_layout_nav([
		'active' => 'index',
	]);
} catch (Throwable $e) {
	if (pinchard_env_non_empty('PINCHARD_DEBUG') === '1') {
		http_response_code($e instanceof \Aws\Exception\AwsException || $e instanceof RuntimeException ? 503 : 500);
		header('Content-Type: text/plain; charset=utf-8');
		exit($e->getMessage());
	}
	pinchard_unavailable_page(
		'Photo viewer is temporarily unavailable.',
		$e instanceof \Aws\Exception\AwsException || $e instanceof RuntimeException ? 503 : 500
	);
}
?>
    <h1 class="visually-hidden"><?= pinchard_h('Cloudberry — ' . $payload['photoTitle']) ?></h1>
    <div class="preview" id="photoViewer" tabindex="0" aria-label="Photograph viewer. Use arrow keys or swipe to browse. Space plays or pauses autoplay. F toggles fullscreen. Timeline scrubber jumps through the archive.">
        <div class="photo-placeholder" data-large="<?= pinchard_h($imageUrl) ?>" data-alt="<?= pinchard_h($photoAlt) ?>" id="preview_image">
            <div style="padding-bottom: 66.6%;"></div>
        </div>

        <div class="detail_view<?= $viewerTimeline !== null ? ' has-timeline' : '' ?>" id="detailDrawer">
            <div class="detail_view-bar">
                <a href="index.php?filename=<?= pinchard_h($prev_filename ?? '') ?>" class="viewer-photo-prev<?= ($prev_filename === null || $prev_filename === '') ? ' is-hidden' : '' ?>" aria-label="Previous photograph"<?= ($prev_filename === null || $prev_filename === '') ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
                    <span class="arrow left" aria-hidden="true"></span>
                </a>
                <button type="button" class="viewer-play-toggle is-paused" id="viewerPlayToggle" aria-label="Play slideshow">
                    <span class="viewer-play-icons" aria-hidden="true">
                        <span class="viewer-play-icon viewer-play-icon--pause"></span>
                        <span class="viewer-play-icon viewer-play-icon--play"></span>
                    </span>
                </button>
                <a href="index.php?filename=<?= pinchard_h($next_filename ?? '') ?>" class="viewer-photo-next<?= ($next_filename === null || $next_filename === '') ? ' is-hidden' : '' ?>" aria-label="Next photograph"<?= ($next_filename === null || $next_filename === '') ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
                    <span class="arrow right" aria-hidden="true"></span>
                </a>
<?php if ($viewerTimeline !== null): ?>
<?php
	$timelineCount = count($viewerTimeline['entries']);
	$timelinePosition = $viewerTimeline['index'] + 1;
	$timelineDate = $timelineDateLabel;
	$viewerTimeline['entries'][$viewerTimeline['index']]['d'] = $timelineDate;
	$timelineAria = pinchard_h('Photograph ' . $timelinePosition . ' of ' . $timelineCount . ', ' . $timelineDate);
?>
                <nav class="viewer-timeline" id="viewerTimeline" aria-label="Photograph timeline">
                    <span class="viewer-timeline-date" id="viewerTimelinePosition" aria-live="polite"><?= pinchard_h($timelineDate) ?></span>
                    <div class="viewer-timeline-track">
                        <input
                            type="range"
                            class="viewer-timeline-range"
                            id="viewerTimelineRange"
                            min="0"
                            max="<?= $timelineCount - 1 ?>"
                            value="<?= $viewerTimeline['index'] ?>"
                            step="1"
                            aria-label="<?= $timelineAria ?>"
                            aria-valuemin="1"
                            aria-valuemax="<?= $timelineCount ?>"
                            aria-valuenow="<?= $timelinePosition ?>"
                            aria-valuetext="<?= $timelineAria ?>"
                        >
                    </div>
                </nav>
<?php endif; ?>
                <button type="button" class="viewer-fullscreen-toggle" id="viewerFullscreenToggle" aria-label="Enter fullscreen" aria-pressed="false"></button>
                <button type="button" class="btn_arrow" id="detailToggle" aria-expanded="false" aria-controls="detailDrawerContent" aria-label="Show photograph details"></button>
            </div>
            <hr class="detail_view-divider" aria-hidden="true">
            <div class="detail_drawer-inner" id="detailDrawerContent">
            <div class="row g-0">
            <div class="col-md-5 detail_container">
                <div class="detail_content_view">
                    <div>
                        <div class="detail_rect title_rect"><img src="images/icon-number.svg" alt="" /></div>
                        <div class="title" id="viewerPhotoTitle"><?= pinchard_h($payload['photoTitle']) ?></div>
                    </div>
                    <div class="datetime_area">
                        <div class="detail_rect"><img src="images/icon-date.svg" alt="" /></div>
                        <div class="inner_data" id="viewerPhotoDate"><?= $converted_date ?></div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="images/icon-gopro.svg" alt="" /></div>
                        <div class="inner_data" id="viewerCameraLines"><?= $cameraLinesHtml ?></div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="images/icon-raspberry.svg" alt="" /></div>
                        <div class="inner_data">Photographer: Raspberry Pi 3 Model B</div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="images/icon-geolocation.svg" alt="" /></div>
                        <div class="inner_data" id="viewerGpsLines"><?= $gpsHtml ?></div>
                    </div>
                    <div class="inner_area<?= $weatherHtml === '' ? ' is-hidden' : '' ?>" id="viewerWeatherArea">
                        <div class="detail_rect"><img src="images/icon-weather.svg" alt="" /></div>
                        <div class="inner_data" id="viewerWeatherLines"><?= $weatherHtml ?></div>
                    </div>
                    <div class="inner_area">
                        <button type="button" class="citation-copy-btn detail-citation-copy" data-citation="<?= pinchard_h($photoCitation) ?>" aria-label="Copy citation to clipboard">Copy citation</button>
                    </div>
                </div>
            </div>
            <div class="col-md-7 mapcontainer">
<?php if ($pinchardMapboxToken !== null && str_starts_with($pinchardMapboxToken, 'pk.')): ?>
                <div id="photoMap" role="img" aria-label="Map showing photograph location"></div>
<?php elseif ($hasGps): ?>
                <p class="text-muted pinchard-empty-state">Map unavailable.</p>
<?php endif; ?>
            </div>
            </div>
            </div>
        </div>
    </div>

<?php
$footerScripts = <<<'JS'
    <script>
        window.pinchardViewer = {
            cdnUrl: CDN_URL,
            filenames: VIEWER_FILENAMES,
            currentIndex: VIEWER_CURRENT_INDEX,
            fadeMs: 1000,
            introFadeMs: 700,
            playDisplayMs: PLAY_DISPLAY_MS,
            playFadeMs: PLAY_FADE_MS,
            playDisplayFromUrl: PLAY_DISPLAY_FROM_URL,
            playFadeFromUrl: PLAY_FADE_FROM_URL,
            playOnLoad: PLAY_ON_LOAD,
            kioskOnLoad: KIOSK_ON_LOAD,
            prevUrl: PREV_URL,
            nextUrl: NEXT_URL,
            prefetch: PRELOAD_URLS,
            currentFilename: CURRENT_FILENAME,
            timeline: TIMELINE_DATA
        };
    </script>
    <script src="VIEWER_JS_SRC"></script>
JS;

$preloadUrls = [];
if ($prev_filename !== null && $prev_filename !== '') {
	$preloadUrls[] = $cdnFull . $prev_filename;
}
if ($next_filename !== null && $next_filename !== '') {
	$preloadUrls[] = $cdnFull . $next_filename;
}

$prevUrl = ($prev_filename !== null && $prev_filename !== '') ? 'index.php?filename=' . rawurlencode($prev_filename) : '';
$nextUrl = ($next_filename !== null && $next_filename !== '') ? 'index.php?filename=' . rawurlencode($next_filename) : '';

$footerScripts = str_replace('VIEWER_JS_SRC', pinchard_h(pinchard_page_asset_url('js/viewer.js')), $footerScripts);

$footerScripts = str_replace('CDN_URL', json_encode($cdnFull, $mapJe), $footerScripts);
$footerScripts = str_replace('VIEWER_FILENAMES', json_encode($viewerFilenames, $mapJe), $footerScripts);
$footerScripts = str_replace('VIEWER_CURRENT_INDEX', json_encode($viewerCurrentIndex, $mapJe), $footerScripts);
$footerScripts = str_replace('PLAY_DISPLAY_MS', json_encode((int) round($playDisplay * 1000), $mapJe), $footerScripts);
$footerScripts = str_replace('PLAY_FADE_MS', json_encode((int) round($playFade * 1000), $mapJe), $footerScripts);
$footerScripts = str_replace('PLAY_DISPLAY_FROM_URL', json_encode($playDisplayFromUrl, $mapJe), $footerScripts);
$footerScripts = str_replace('PLAY_FADE_FROM_URL', json_encode($playFadeFromUrl, $mapJe), $footerScripts);
$footerScripts = str_replace('PLAY_ON_LOAD', json_encode($playOnLoad, $mapJe), $footerScripts);
$footerScripts = str_replace('KIOSK_ON_LOAD', json_encode($kiosk, $mapJe), $footerScripts);
$footerScripts = str_replace('PREV_URL', json_encode($prevUrl, $mapJe), $footerScripts);
$footerScripts = str_replace('NEXT_URL', json_encode($nextUrl, $mapJe), $footerScripts);
$footerScripts = str_replace('PRELOAD_URLS', json_encode($preloadUrls, $mapJe), $footerScripts);
$footerScripts = str_replace('CURRENT_FILENAME', json_encode($filename, $mapJe), $footerScripts);
$footerScripts = str_replace('TIMELINE_DATA', json_encode($viewerTimeline ?? null, $mapJe), $footerScripts);

if ($pinchardMapboxToken !== null && str_starts_with($pinchardMapboxToken, 'pk.')) {
	$footerScripts .= "\n    " . pinchard_mapbox_gl_js() . "\n";
	$footerScripts .= "    <script>\n        document.addEventListener('DOMContentLoaded', function() {\n";
	$footerScripts .= "            var map = new mapboxgl.Map({\n";
	$footerScripts .= '                accessToken: ' . json_encode($pinchardMapboxToken, $mapJe) . ",\n";
	$footerScripts .= "                container: 'photoMap',\n";
	$footerScripts .= "                style: 'mapbox://styles/mapbox/satellite-v9',\n";
	$footerScripts .= '                center: [' . json_encode($mapLon, $mapJe) . ', ' . json_encode($mapLat, $mapJe) . "],\n";
	$footerScripts .= "                zoom: 14\n";
	$footerScripts .= "            });\n";
	$footerScripts .= '            var marker = new mapboxgl.Marker().setLngLat([' . json_encode($mapLon, $mapJe) . ', ' . json_encode($mapLat, $mapJe) . "]).addTo(map);\n";
	$footerScripts .= "            window.pinchardPhotoMap = { map: map, marker: marker };\n";
	$footerScripts .= "        });\n    </script>\n";
}

pinchard_layout_footer(['extra_scripts' => $footerScripts]);
