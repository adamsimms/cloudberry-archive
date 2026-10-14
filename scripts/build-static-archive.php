#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build a static Cloudberry archive tree for assemble-into art.adamsimms.xyz.
 *
 * Output (default): dist-archive/  →  copy to art …/dist/cloudberry/archive/
 *
 * Usage:
 *   php scripts/build-static-archive.php
 *   php scripts/build-static-archive.php --out=/tmp/foo
 *   php scripts/build-static-archive.php --allow-partial
 */

$REPO = dirname(__DIR__);
$BASE = '/cloudberry/archive';
$SITE = 'https://art.adamsimms.xyz';
$EXPECTED_COUNT = 1955;
$OUT = $REPO . '/dist-archive';
$ALLOW_PARTIAL = false;

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--out=')) {
		$OUT = rtrim(substr($arg, 6), '/');
		if ($OUT === '') {
			fwrite(STDERR, "Empty --out path\n");
			exit(1);
		}
		if ($OUT[0] !== '/') {
			$OUT = getcwd() . '/' . $OUT;
		}
	} elseif ($arg === '--allow-partial') {
		$ALLOW_PARTIAL = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		echo "Usage: php scripts/build-static-archive.php [--out=DIR] [--allow-partial]\n";
		exit(0);
	} else {
		fwrite(STDERR, "Unknown argument: {$arg}\n");
		exit(1);
	}
}

require_once $REPO . '/lib/env.php';

$mapboxToken = pinchard_env_non_empty('MAPBOX_ACCESS_TOKEN');
$hasMapbox = is_string($mapboxToken) && str_starts_with($mapboxToken, 'pk.');
$mapboxVersion = '3.25.0';

$catalogPath = $REPO . '/data/catalog.json';
if (!is_readable($catalogPath)) {
	fwrite(STDERR, "Missing required catalog: data/catalog.json\n");
	exit(1);
}

$catalogRaw = file_get_contents($catalogPath);
if ($catalogRaw === false) {
	fwrite(STDERR, "Could not read data/catalog.json\n");
	exit(1);
}

/** @var array<string, mixed>|null $catalog */
$catalog = json_decode($catalogRaw, true);
if (!is_array($catalog) || !isset($catalog['photos']) || !is_array($catalog['photos'])) {
	fwrite(STDERR, "Invalid catalog.json: expected { photos: [...] }\n");
	exit(1);
}

/** @var list<array<string, mixed>> $photos */
$photos = array_values($catalog['photos']);
$photoCount = count($photos);
$reportedCount = isset($catalog['count']) ? (int) $catalog['count'] : $photoCount;

if (!$ALLOW_PARTIAL && ($photoCount !== $EXPECTED_COUNT || $reportedCount !== $EXPECTED_COUNT)) {
	fwrite(STDERR, "Catalog count mismatch: expected {$EXPECTED_COUNT}, got photos={$photoCount} count={$reportedCount}. Pass --allow-partial to continue.\n");
	exit(1);
}

if ($photos === []) {
	fwrite(STDERR, "Catalog has no photos\n");
	exit(1);
}

$cdnFull = (string) ($catalog['cdn']['full'] ?? 'https://cloudberry-images.adamsimms.xyz/');
$cdnThumbs = (string) ($catalog['cdn']['thumbs'] ?? 'https://cloudberry-thumbs.adamsimms.xyz/');
if (!str_ends_with($cdnFull, '/')) {
	$cdnFull .= '/';
}
if (!str_ends_with($cdnThumbs, '/')) {
	$cdnThumbs .= '/';
}

$filesWritten = 0;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function h(?string $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function static_asset(string $rel): string
{
	global $BASE, $REPO;
	$rel = ltrim($rel, '/');
	$mtime = @filemtime($REPO . '/' . $rel) ?: 1;

	return $BASE . '/' . $rel . '?v=' . $mtime;
}

function write_file(string $path, string $contents): void
{
	global $filesWritten;
	$dir = dirname($path);
	if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
		throw new RuntimeException("Could not create directory: {$dir}");
	}
	if (file_put_contents($path, $contents) === false) {
		throw new RuntimeException("Could not write: {$path}");
	}
	$filesWritten++;
}

/**
 * Recursively copy $src → $dst, skipping junk / cache trees.
 *
 * @param list<string> $skipNames basename skip list
 */
function copy_tree(string $src, string $dst, array $skipNames = ['.cache', 'tmp', '.DS_Store']): int
{
	if (!is_dir($src)) {
		return 0;
	}
	if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
		throw new RuntimeException("Could not create directory: {$dst}");
	}

	$copied = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	/** @var SplFileInfo $item */
	foreach ($iterator as $item) {
		// Skip anything named or nested under a skipped basename (e.g. images/photo/.cache/…).
		$relative = substr($item->getPathname(), strlen($src) + 1);
		$parts = explode(DIRECTORY_SEPARATOR, str_replace('\\', '/', $relative));
		$skip = false;
		foreach ($parts as $part) {
			if (in_array($part, $skipNames, true)) {
				$skip = true;
				break;
			}
		}
		if ($skip) {
			continue;
		}

		$target = $dst . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
		if ($item->isDir()) {
			if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
				throw new RuntimeException("Could not create directory: {$target}");
			}
			continue;
		}
		$targetDir = dirname($target);
		if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
			throw new RuntimeException("Could not create directory: {$targetDir}");
		}
		if (!copy($item->getPathname(), $target)) {
			throw new RuntimeException("Could not copy {$item->getPathname()} → {$target}");
		}
		$copied++;
	}

	return $copied;
}

function rm_tree(string $dir): void
{
	if (!is_dir($dir)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	/** @var SplFileInfo $item */
	foreach ($iterator as $item) {
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}
	rmdir($dir);
}

function format_temp_c(float $temp): string
{
	$rounded = round($temp, 1);
	$text = number_format(abs($rounded), 1, '.', '');
	$sign = $rounded < 0 ? '−' : '';

	return $sign . $text . ' °C';
}

function format_speed(float $kmh): string
{
	return (string) (round($kmh * 10) / 10);
}

/** @param array<string, mixed>|null $camera */
function camera_lines_html(?array $camera): string
{
	if ($camera === null) {
		return 'Make:<br>Model:<br>Focal Length:<br>Exposure:<br>Image Size:<br>Resolution:';
	}
	$lines = [];
	$lines[] = isset($camera['make']) && $camera['make'] !== '' ? 'Make: ' . h((string) $camera['make']) : 'Make:';
	$lines[] = isset($camera['model']) && $camera['model'] !== '' ? 'Model: ' . h((string) $camera['model']) : 'Model:';
	$lines[] = isset($camera['focalLengthMm']) && is_numeric($camera['focalLengthMm'])
		? 'Focal Length: ' . number_format((float) $camera['focalLengthMm'], 2, '.', '') . ' mm'
		: 'Focal Length:';
	if (
		isset($camera['exposureDisplay'], $camera['fNumber'], $camera['iso'])
		&& $camera['exposureDisplay'] !== ''
		&& is_numeric($camera['fNumber'])
	) {
		$lines[] = 'Exposure: ' . h((string) $camera['exposureDisplay']) . ' sec, f/'
			. number_format((float) $camera['fNumber'], 1, '.', '') . '; ISO ' . h((string) $camera['iso']);
	} else {
		$lines[] = 'Exposure:';
	}
	if (isset($camera['width'], $camera['height'])) {
		$lines[] = 'Image Size: ' . (int) $camera['width'] . ' x ' . (int) $camera['height'];
	} else {
		$lines[] = 'Image Size:';
	}
	$lines[] = isset($camera['resolutionPpi']) && is_numeric($camera['resolutionPpi'])
		? 'Resolution: ' . number_format((float) $camera['resolutionPpi'], 2, '.', '') . ' pixels per inch'
		: 'Resolution:';

	return implode('<br>', $lines);
}

/** @param array<string, mixed>|null $gps */
function gps_lines_html(?array $gps): string
{
	if ($gps === null) {
		return '';
	}
	$latD = (string) ($gps['latitudeDegree'] ?? '');
	$latM = (string) ($gps['latitudeMin'] ?? '');
	$latS = (string) ($gps['latitudeSec'] ?? '');
	$lonD = (string) ($gps['longitudeDegree'] ?? '');
	$lonM = (string) ($gps['longitudeMin'] ?? '');
	$lonS = (string) ($gps['longitudeSec'] ?? '');
	$alt = isset($gps['altitudeM']) && is_numeric($gps['altitudeM'])
		? 'Altitude: ' . number_format((float) $gps['altitudeM'], 2, '.', '') . ' m'
		: 'Altitude:';

	return 'Position: ' . $latD . '&deg; ' . $latM . '&acute; ' . $latS . '&quot; N, '
		. $lonD . '&deg; ' . $lonM . '&acute; ' . $lonS . '&quot; W<br>' . $alt;
}

/** @param array<string, mixed>|null $weather */
function weather_lines_html(?array $weather): string
{
	if (
		$weather === null
		|| !isset($weather['temperatureC'], $weather['windSpeedKmh'])
		|| !is_numeric($weather['temperatureC'])
		|| !is_numeric($weather['windSpeedKmh'])
	) {
		return '';
	}
	$lines = [];
	$lines[] = 'Conditions: ' . h(format_temp_c((float) $weather['temperatureC']))
		. ' · ' . h((string) ($weather['conditionsLabel'] ?? 'Unknown'));
	$wind = 'Wind: ' . h((string) ($weather['windCompass'] ?? '')) . ' '
		. h(format_speed((float) $weather['windSpeedKmh'])) . ' km/h';
	if (isset($weather['windGustsKmh']) && is_numeric($weather['windGustsKmh'])) {
		$wind .= ' · gusts ' . h(format_speed((float) $weather['windGustsKmh'])) . ' km/h';
	}
	$lines[] = $wind;
	$precip = [];
	if (isset($weather['rainMm']) && is_numeric($weather['rainMm']) && (float) $weather['rainMm'] > 0) {
		$precip[] = $weather['rainMm'] . ' mm rain';
	}
	if (isset($weather['snowfallCm']) && is_numeric($weather['snowfallCm']) && (float) $weather['snowfallCm'] > 0) {
		$precip[] = $weather['snowfallCm'] . ' cm snow';
	}
	if (
		$precip === []
		&& isset($weather['precipitationMm'])
		&& is_numeric($weather['precipitationMm'])
		&& (float) $weather['precipitationMm'] > 0
	) {
		$precip[] = $weather['precipitationMm'] . ' mm';
	}
	if ($precip !== []) {
		$lines[] = 'Precipitation: ' . implode(' · ', $precip);
	}

	return implode('<br>', $lines);
}

/** @param array<string, mixed> $photo */
function citation_for_photo(array $photo): string
{
	global $SITE, $BASE;
	$origin = rtrim($SITE, '/');
	$path = (string) ($photo['citationPath'] ?? ($BASE . '/?filename=' . rawurlencode((string) ($photo['filename'] ?? ''))));
	$when = (string) ($photo['convertedDate'] ?? $photo['date'] ?? '');
	$access = (new DateTime('now', new DateTimeZone('America/St_Johns')))->format('F j, Y');

	return 'Cloudberry. Automated photograph, ' . $when
		. '; photo ID ' . (string) ($photo['title'] ?? '')
		. ' (' . (string) ($photo['filename'] ?? '') . '). '
		. $origin . $path
		. '. Accessed ' . $access . '.';
}

/** @param array<string, mixed> $photo */
function gallery_time_label(array $photo): string
{
	$date = (string) ($photo['date'] ?? '');
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $date);
	if ($dt instanceof DateTime) {
		return $dt->format('H:i');
	}

	return '';
}

/**
 * @param list<array<string, mixed>> $photos
 * @return array<string, array{label: string, feed_label: string, long_label: string, photos: list<array<string, mixed>>}>
 */
function group_photos_by_day(array $photos): array
{
	$byDay = [];
	foreach ($photos as $photo) {
		$date = (string) ($photo['date'] ?? '');
		$dt = DateTime::createFromFormat('Y/m/d H:i:s', $date);
		if (!$dt instanceof DateTime) {
			$iso = (string) ($photo['captureDateIso'] ?? '');
			try {
				$dt = $iso !== '' ? new DateTime($iso) : null;
			} catch (Exception) {
				$dt = null;
			}
		}
		if (!$dt instanceof DateTime) {
			continue;
		}
		$key = $dt->format('Y-m-d');
		if (!isset($byDay[$key])) {
			$byDay[$key] = [
				'label' => $dt->format('M j'),
				'feed_label' => strtoupper($dt->format('F')) . ' ' . $dt->format('j') . ' • ' . $dt->format('Y'),
				'long_label' => $dt->format('F j, Y'),
				'photos' => [],
			];
		}
		$byDay[$key]['photos'][] = $photo;
	}

	return $byDay;
}

/**
 * @param list<array<string, mixed>> $photos
 * @return array{start: string, end: string, range_compact: string}|null
 */
function archive_span(array $photos): ?array
{
	$first = $photos[0] ?? null;
	$last = $photos[count($photos) - 1] ?? null;
	if (!is_array($first) || !is_array($last)) {
		return null;
	}
	$startDt = DateTime::createFromFormat('Y/m/d H:i:s', (string) ($first['date'] ?? ''));
	$endDt = DateTime::createFromFormat('Y/m/d H:i:s', (string) ($last['date'] ?? ''));
	if (!$startDt instanceof DateTime || !$endDt instanceof DateTime) {
		return null;
	}

	return [
		'start' => $startDt->format('F j, Y'),
		'end' => $endDt->format('F j, Y'),
		'range_compact' => $startDt->format('F Y') . '–' . $endDt->format('F Y'),
	];
}

/**
 * Shared HTML head for archive pages.
 *
 * @param array{
 *   description?: string,
 *   og_image?: string,
 *   og_type?: string,
 *   canonical?: string,
 *   robots?: string,
 *   body_class?: string,
 *   extra_head?: string,
 * } $options
 */
function page_head(string $title, array $options = []): string
{
	global $BASE, $SITE;
	$description = $options['description'] ?? "Cloudberry — an off-the-grid, solar-powered long-term photography project that documented Pinchard's Island, Newfoundland.";
	$ogImage = $options['og_image'] ?? ($SITE . $BASE . '/images/info/pano.jpg');
	$ogType = $options['og_type'] ?? 'website';
	$canonical = $options['canonical'] ?? ($SITE . $BASE . '/');
	$robots = $options['robots'] ?? null;
	$bodyClass = $options['body_class'] ?? '';
	$extraHead = $options['extra_head'] ?? '';

	$bootstrap = h(static_asset('vendor/bootstrap/css/bootstrap.css'));
	$css = h(static_asset('css/pinchard.css'));
	$fav = static fn (string $file): string => h(static_asset('favicon/' . $file));

	$html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
	$html .= "    <meta charset=\"utf-8\">\n";
	$html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
	$html .= '    <meta name="description" content="' . h($description) . "\">\n";
	$html .= "    <meta name=\"author\" content=\"Adam Simms & Angela Gabereaux\">\n";
	if ($robots !== null && $robots !== '') {
		$html .= '    <meta name="robots" content="' . h($robots) . "\">\n";
	}
	$html .= '    <meta property="og:title" content="' . h($title) . "\">\n";
	$html .= '    <meta property="og:description" content="' . h($description) . "\">\n";
	$html .= '    <meta property="og:image" content="' . h($ogImage) . "\">\n";
	$html .= '    <meta property="og:type" content="' . h($ogType) . "\">\n";
	$html .= '    <meta property="og:url" content="' . h($canonical) . "\">\n";
	$html .= "    <meta property=\"og:site_name\" content=\"Pinchard's Island\">\n";
	$html .= "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
	$html .= '    <meta name="twitter:title" content="' . h($title) . "\">\n";
	$html .= '    <meta name="twitter:description" content="' . h($description) . "\">\n";
	$html .= '    <meta name="twitter:image" content="' . h($ogImage) . "\">\n";
	$html .= '    <link rel="canonical" href="' . h($canonical) . "\">\n";
	$html .= '    <title>' . h($title) . "</title>\n";
	$html .= "    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
	$html .= "    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
	$html .= "    <link rel=\"preconnect\" href=\"https://cloudberry-images.adamsimms.xyz\" crossorigin>\n";
	$html .= "    <link rel=\"preconnect\" href=\"https://cloudberry-thumbs.adamsimms.xyz\" crossorigin>\n";
	$html .= "    <link href=\"https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap\" rel=\"stylesheet\">\n";
	$html .= '    <link href="' . $bootstrap . "\" rel=\"stylesheet\">\n";
	$html .= '    <link href="' . $css . "\" rel=\"stylesheet\">\n";
	$html .= '    <link rel="apple-touch-icon" sizes="180x180" href="' . $fav('apple-touch-icon.png') . "\">\n";
	$html .= '    <link rel="icon" type="image/png" sizes="32x32" href="' . $fav('favicon-32x32.png') . "\">\n";
	$html .= '    <link rel="icon" type="image/png" sizes="16x16" href="' . $fav('favicon-16x16.png') . "\">\n";
	$html .= '    <link rel="manifest" href="' . $fav('manifest.json') . "\">\n";
	$html .= '    <link rel="mask-icon" href="' . $fav('safari-pinned-tab.svg') . "\" color=\"#5bbad5\">\n";
	$html .= '    <link rel="shortcut icon" href="' . $fav('favicon.ico') . "\">\n";
	$html .= '    <meta name="msapplication-config" content="' . $fav('browserconfig.xml') . "\">\n";
	$html .= "    <meta name=\"theme-color\" content=\"#ffffff\">\n";
	if ($extraHead !== '') {
		$html .= $extraHead;
		if (!str_ends_with($extraHead, "\n")) {
			$html .= "\n";
		}
	}
	$html .= "</head>\n";
	$bodyOpen = '<body id="page-top"';
	if ($bodyClass !== '') {
		$bodyOpen .= ' class="' . h($bodyClass) . '"';
	}
	$bodyOpen .= ">\n";
	$html .= $bodyOpen;

	return $html;
}

/**
 * Archive nav (maps point at art hub paths, not under archive).
 *
 * @param 'index'|'gallery'|'info'|null $active
 */
function page_nav(?string $active = null): string
{
	global $BASE;
	$indexHref = $BASE . '/';
	$galleryHref = $BASE . '/gallery/';
	$infoHref = $BASE . '/info/';

	$galleryClass = 'link-to-gallery nav_gallery' . ($active === 'gallery' ? ' active' : '');
	$infoClass = 'nav_info' . ($active === 'info' ? ' active' : '');

	$mapItems = [
		['href' => '/maps', 'title' => 'Satellite'],
		['href' => '/maps/trees', 'title' => '53 Trees'],
		['href' => '/maps/resettled', 'title' => 'Resettled'],
	];

	$html = <<<HTML
    <nav id="mainNav" class="navbar navbar-default fixed-top" aria-label="Site">
        <div class="nav-bar-inner">
            <div class="nav-bar-start">
                <a href="{$indexHref}" class="title-brand"
HTML;
	if ($active === 'index') {
		$html .= ' aria-current="page"';
	}
	$html .= <<<HTML
>
                    <span class="title-brand-mark" aria-hidden="true"></span>
                    <span class="title-brand-text">Cloudberry</span>
                </a>
            </div>
            <div class="nav-bar-end">
                <a href="{$galleryHref}" class="{$galleryClass}" aria-label="Browse photo gallery"></a>
                <div class="maps-nav-dropdown">
                    <button type="button" class="maps-nav-dropdown-trigger nav_maps" aria-expanded="false" aria-haspopup="true" aria-controls="mapsNavDropdownMenu" id="mapsNavDropdownTrigger" aria-label="Maps"></button>
                    <div class="maps-nav-dropdown-menu" id="mapsNavDropdownMenu" role="menu" aria-labelledby="mapsNavDropdownTrigger">
                        <div class="maps-nav-dropdown-panel">

HTML;
	foreach ($mapItems as $item) {
		$html .= '                            <a href="' . h($item['href']) . '" class="maps-nav-dropdown-item" role="menuitem">' . h($item['title']) . "</a>\n";
	}
	$html .= <<<HTML
                        </div>
                    </div>
                </div>
                <a class="{$infoClass}" href="{$infoHref}" aria-label="About Cloudberry"></a>
            </div>
        </div>
    </nav>

HTML;

	return $html;
}

/**
 * @param array{extra_scripts?: string, include_pinchard_js?: bool} $options
 */
function page_footer(array $options = []): string
{
	$includePinchardJs = $options['include_pinchard_js'] ?? true;
	$extra = $options['extra_scripts'] ?? '';
	$html = '';
	$html .= '    <script src="' . h(static_asset('vendor/gsap/gsap.min.js')) . "\"></script>\n";
	$html .= '    <script src="' . h(static_asset('vendor/gsap/ScrollTrigger.min.js')) . "\"></script>\n";
	$html .= '    <script src="' . h(static_asset('js/gsap-motion.js')) . "\"></script>\n";
	if ($includePinchardJs) {
		$html .= '    <script src="' . h(static_asset('js/pinchard.js')) . "\"></script>\n";
	}
	if ($extra !== '') {
		$html .= $extra;
		if (!str_ends_with($extra, "\n")) {
			$html .= "\n";
		}
	}
	$html .= "</body>\n</html>\n";

	return $html;
}

$je = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// ---------------------------------------------------------------------------
// Prepare output directory + copy assets
// ---------------------------------------------------------------------------

echo "Building static Cloudberry archive → {$OUT}\n";
echo "  photos: {$photoCount}\n";
echo "  base:   {$BASE}\n";

if (is_dir($OUT)) {
	rm_tree($OUT);
}
if (!mkdir($OUT, 0755, true) && !is_dir($OUT)) {
	fwrite(STDERR, "Could not create output directory: {$OUT}\n");
	exit(1);
}

$bootstrapCss = $REPO . '/vendor/bootstrap/css/bootstrap.css';
$gsapJs = $REPO . '/vendor/gsap/gsap.min.js';
if (!is_readable($bootstrapCss) || !is_readable($gsapJs)) {
	fwrite(STDERR, "Missing vendor assets (run: npm ci && npm run vendor:frontend).\n");
	fwrite(STDERR, "  expected: vendor/bootstrap/css/bootstrap.css\n");
	fwrite(STDERR, "  expected: vendor/gsap/gsap.min.js\n");
	exit(1);
}

$assetCopied = 0;
$assetCopied += copy_tree($REPO . '/css', $OUT . '/css');
$assetCopied += copy_tree($REPO . '/js', $OUT . '/js');
$assetCopied += copy_tree($REPO . '/images', $OUT . '/images');
$assetCopied += copy_tree($REPO . '/favicon', $OUT . '/favicon');
$assetCopied += copy_tree($REPO . '/vendor/bootstrap', $OUT . '/vendor/bootstrap');
$assetCopied += copy_tree($REPO . '/vendor/gsap', $OUT . '/vendor/gsap');

if (!is_readable($OUT . '/vendor/bootstrap/css/bootstrap.css') || !is_readable($OUT . '/vendor/gsap/gsap.min.js')) {
	fwrite(STDERR, "Vendor copy failed — Bootstrap/GSAP missing from output.\n");
	exit(1);
}

if (!is_dir($OUT . '/data') && !mkdir($OUT . '/data', 0755, true)) {
	fwrite(STDERR, "Could not create data/\n");
	exit(1);
}
if (!copy($catalogPath, $OUT . '/data/catalog.json')) {
	fwrite(STDERR, "Could not copy catalog.json\n");
	exit(1);
}
$filesWritten++;

// ---------------------------------------------------------------------------
// Viewer index.html (default = last photo)
// ---------------------------------------------------------------------------

$currentIndex = $photoCount - 1;
$current = $photos[$currentIndex];
$filename = (string) $current['filename'];
$photoTitle = (string) ($current['title'] ?? '');
$convertedDate = (string) ($current['convertedDate'] ?? $current['date'] ?? '');
$showDate = (string) ($current['showDate'] ?? '');
$imageUrl = (string) ($current['imageUrl'] ?? ($cdnFull . $filename));
$photoAlt = 'Photograph from Pinchard\'s Island' . ($convertedDate !== '' ? ' — ' . $convertedDate : '');
$cameraHtml = camera_lines_html(isset($current['camera']) && is_array($current['camera']) ? $current['camera'] : null);
$gpsHtml = gps_lines_html(isset($current['gps']) && is_array($current['gps']) ? $current['gps'] : null);
$weatherHtml = weather_lines_html(isset($current['weather']) && is_array($current['weather']) ? $current['weather'] : null);
$citation = citation_for_photo($current);
$mapLat = isset($current['gps']['lat']) && is_numeric($current['gps']['lat']) ? (float) $current['gps']['lat'] : 49.2025694;
$mapLon = isset($current['gps']['lon']) && is_numeric($current['gps']['lon']) ? (float) $current['gps']['lon'] : -53.48586388888953;
$hasGps = !empty($current['gps']['hasGps']);

$filenames = array_map(static fn (array $p): string => (string) $p['filename'], $photos);
$timelineEntries = [];
foreach ($photos as $p) {
	$timelineEntries[] = [
		'f' => (string) $p['filename'],
		'd' => (string) ($p['showDate'] ?? $p['date'] ?? ''),
	];
}
$timeline = [
	'scope' => 'archive',
	'label' => 'Full archive',
	'entries' => $timelineEntries,
	'index' => $currentIndex,
];

$prevFilename = $currentIndex > 0 ? $filenames[$currentIndex - 1] : null;
$nextFilename = $currentIndex < $photoCount - 1 ? $filenames[$currentIndex + 1] : null;
$prevUrl = $prevFilename !== null ? $BASE . '/?filename=' . rawurlencode($prevFilename) : '';
$nextUrl = $nextFilename !== null ? $BASE . '/?filename=' . rawurlencode($nextFilename) : '';

// One indexable URL for the archive; ?filename= states stay shareable but consolidate.
$archiveDescription = 'Cloudberry — hourly photographs of Pinchard\'s Island, Newfoundland, from a solar-powered off-grid camera.';

$viewUrl = $cdnFull . 'view/' . $filename;
$thumbUrl = (string) ($current['thumbUrl'] ?? ($cdnThumbs . $filename));

$extraHead = '';
// LCP: preload the 1280w view derivative (not the 4MB master). Mapbox CSS loads on drawer open.
$extraHead .= '    <link rel="preload" as="image" href="' . h($viewUrl) . "\" fetchpriority=\"high\">\n";
if ($prevFilename !== null) {
	$extraHead .= '    <link rel="prefetch" href="' . h($cdnFull . 'view/' . $prevFilename) . "\" as=\"image\">\n";
}
if ($nextFilename !== null) {
	$extraHead .= '    <link rel="prefetch" href="' . h($cdnFull . 'view/' . $nextFilename) . "\" as=\"image\">\n";
}

$viewerHtml = page_head('Cloudberry — ' . $photoTitle, [
	'description' => $archiveDescription,
	'og_image' => $imageUrl,
	'og_type' => 'website',
	'canonical' => $SITE . $BASE . '/',
	'body_class' => 'viewer-page',
	'extra_head' => $extraHead,
]);
$viewerHtml .= page_nav('index');

$timelineCount = count($timelineEntries);
$timelinePosition = $currentIndex + 1;
$timelineAria = 'Photograph ' . $timelinePosition . ' of ' . $timelineCount . ', ' . $showDate;
$weatherHidden = $weatherHtml === '' ? ' is-hidden' : '';
$prevHidden = $prevFilename === null ? ' is-hidden' : '';
$nextHidden = $nextFilename === null ? ' is-hidden' : '';
$prevAria = $prevFilename === null ? ' aria-hidden="true" tabindex="-1"' : '';
$nextAria = $nextFilename === null ? ' aria-hidden="true" tabindex="-1"' : '';
$imgIcon = static fn (string $name): string => h($BASE . '/images/' . $name);

$imageUrlEsc = h($imageUrl);
$viewUrlEsc = h($viewUrl);
$thumbUrlEsc = h($thumbUrl);
$photoAltEsc = h($photoAlt);
$prevUrlEsc = h($prevUrl);
$nextUrlEsc = h($nextUrl);
$showDateEsc = h($showDate);
$timelineMax = $timelineCount - 1;
$timelineAriaEsc = h($timelineAria);
$photoTitleEsc = h($photoTitle);
$convertedDateEsc = h($convertedDate);
$citationEsc = h($citation);
$iconNumber = $imgIcon('icon-number.svg');
$iconDate = $imgIcon('icon-date.svg');
$iconGopro = $imgIcon('icon-gopro.svg');
$iconPi = $imgIcon('icon-raspberry.svg');
$iconGeo = $imgIcon('icon-geolocation.svg');
$iconWeather = $imgIcon('icon-weather.svg');

$viewerHtml .= '    <h1 class="visually-hidden">' . h('Cloudberry — ' . $photoTitle) . "</h1>\n";
$viewerHtml .= <<<HTML
    <div class="preview" id="photoViewer" tabindex="0" aria-label="Photograph viewer. Use arrow keys or swipe to browse. Space plays or pauses autoplay. F toggles fullscreen. Timeline scrubber jumps through the archive.">
        <div class="photo-placeholder" data-large="{$viewUrlEsc}" data-full="{$imageUrlEsc}" data-thumb="{$thumbUrlEsc}" data-alt="{$photoAltEsc}" id="preview_image">
            <div style="padding-bottom: 66.6%;"></div>
            <img class="viewer-photo-main loaded" src="{$viewUrlEsc}" alt="{$photoAltEsc}" width="1280" height="960" decoding="async" fetchpriority="high">
        </div>

        <div class="detail_view has-timeline" id="detailDrawer">
            <div class="detail_view-bar">
                <a href="{$prevUrlEsc}" class="viewer-photo-prev{$prevHidden}" aria-label="Previous photograph"{$prevAria}>
                    <span class="arrow left" aria-hidden="true"></span>
                </a>
                <button type="button" class="viewer-play-toggle is-paused" id="viewerPlayToggle" aria-label="Play slideshow">
                    <span class="viewer-play-icons" aria-hidden="true">
                        <span class="viewer-play-icon viewer-play-icon--pause"></span>
                        <span class="viewer-play-icon viewer-play-icon--play"></span>
                    </span>
                </button>
                <a href="{$nextUrlEsc}" class="viewer-photo-next{$nextHidden}" aria-label="Next photograph"{$nextAria}>
                    <span class="arrow right" aria-hidden="true"></span>
                </a>
                <nav class="viewer-timeline" id="viewerTimeline" aria-label="Photograph timeline">
                    <span class="viewer-timeline-date" id="viewerTimelinePosition" aria-live="polite">{$showDateEsc}</span>
                    <div class="viewer-timeline-track">
                        <input
                            type="range"
                            class="viewer-timeline-range"
                            id="viewerTimelineRange"
                            min="0"
                            max="{$timelineMax}"
                            value="{$currentIndex}"
                            step="1"
                            aria-label="{$timelineAriaEsc}"
                            aria-valuemin="1"
                            aria-valuemax="{$timelineCount}"
                            aria-valuenow="{$timelinePosition}"
                            aria-valuetext="{$timelineAriaEsc}"
                        >
                    </div>
                </nav>
                <button type="button" class="viewer-fullscreen-toggle" id="viewerFullscreenToggle" aria-label="Enter fullscreen" aria-pressed="false"></button>
                <button type="button" class="btn_arrow" id="detailToggle" aria-expanded="false" aria-controls="detailDrawerContent" aria-label="Show photograph details"></button>
            </div>
            <hr class="detail_view-divider" aria-hidden="true">
            <div class="detail_drawer-inner" id="detailDrawerContent">
            <div class="row g-0">
            <div class="col-md-5 detail_container">
                <div class="detail_content_view">
                    <div>
                        <div class="detail_rect title_rect"><img src="{$iconNumber}" alt="" /></div>
                        <div class="title" id="viewerPhotoTitle">{$photoTitleEsc}</div>
                    </div>
                    <div class="datetime_area">
                        <div class="detail_rect"><img src="{$iconDate}" alt="" /></div>
                        <div class="inner_data" id="viewerPhotoDate">{$convertedDateEsc}</div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="{$iconGopro}" alt="" /></div>
                        <div class="inner_data" id="viewerCameraLines">{$cameraHtml}</div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="{$iconPi}" alt="" /></div>
                        <div class="inner_data">Photographer: Raspberry Pi 3 Model B</div>
                    </div>
                    <div class="inner_area">
                        <div class="detail_rect"><img src="{$iconGeo}" alt="" /></div>
                        <div class="inner_data" id="viewerGpsLines">{$gpsHtml}</div>
                    </div>
                    <div class="inner_area{$weatherHidden}" id="viewerWeatherArea">
                        <div class="detail_rect"><img src="{$iconWeather}" alt="" /></div>
                        <div class="inner_data" id="viewerWeatherLines">{$weatherHtml}</div>
                    </div>
                    <div class="inner_area">
                        <button type="button" class="citation-copy-btn detail-citation-copy" data-citation="{$citationEsc}" aria-label="Copy citation to clipboard">Copy citation</button>
                    </div>
                </div>
            </div>
            <div class="col-md-7 mapcontainer">

HTML;

if ($hasMapbox) {
	$viewerHtml .= '                <div id="photoMap" role="img" aria-label="Map showing photograph location"></div>' . "\n";
} elseif ($hasGps) {
	$viewerHtml .= '                <p class="text-muted pinchard-empty-state">Map unavailable.</p>' . "\n";
}

$viewerHtml .= <<<HTML
            </div>
            </div>
            </div>
        </div>
    </div>

HTML;

$prefetch = [];
if ($prevFilename !== null) {
	$prefetch[] = $cdnFull . 'view/' . $prevFilename;
}
if ($nextFilename !== null) {
	$prefetch[] = $cdnFull . 'view/' . $nextFilename;
}

$viewerBoot = [
	'basePath' => $BASE,
	'catalogUrl' => $BASE . '/data/catalog.json',
	'siteOrigin' => $SITE,
	'cdnUrl' => $cdnFull,
	'cdnViewUrl' => $cdnFull . 'view/',
	'cdnThumbUrl' => $cdnThumbs,
	'filenames' => $filenames,
	'currentIndex' => $currentIndex,
	'currentFilename' => $filename,
	'fadeMs' => 1000,
	'introFadeMs' => 700,
	'playDisplayMs' => 1400,
	'playFadeMs' => 1000,
	'playDisplayFromUrl' => false,
	'playFadeFromUrl' => false,
	'playOnLoad' => false,
	'kioskOnLoad' => false,
	'prevUrl' => $prevUrl,
	'nextUrl' => $nextUrl,
	'prefetch' => $prefetch,
	'timeline' => $timeline,
];

$bootJson = json_encode($viewerBoot, $je);
if ($bootJson === false) {
	fwrite(STDERR, "Failed to encode viewer boot config\n");
	exit(1);
}

$footerScripts = "    <script>\n        window.pinchardViewer = {$bootJson};\n    </script>\n";
$footerScripts .= '    <script src="' . h(static_asset('js/viewer.js')) . "\"></script>\n";

if ($hasMapbox) {
	$tokenJson = json_encode($mapboxToken, $je);
	$lonJson = json_encode($mapLon, $je);
	$latJson = json_encode($mapLat, $je);
	$mapboxCss = json_encode('https://api.mapbox.com/mapbox-gl-js/v' . $mapboxVersion . '/mapbox-gl.css', $je);
	$mapboxJs = json_encode('https://api.mapbox.com/mapbox-gl-js/v' . $mapboxVersion . '/mapbox-gl.js', $je);
	$footerScripts .= <<<JS
    <script>
        (function() {
            var loaded = false;
            var mapboxCss = {$mapboxCss};
            var mapboxJs = {$mapboxJs};
            function loadMapbox() {
                if (loaded) return;
                loaded = true;
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = mapboxCss;
                document.head.appendChild(link);
                var script = document.createElement('script');
                script.src = mapboxJs;
                script.onload = function() {
                    if (!window.mapboxgl || !document.getElementById('photoMap')) return;
                    var map = new mapboxgl.Map({
                        accessToken: {$tokenJson},
                        container: 'photoMap',
                        style: 'mapbox://styles/mapbox/satellite-v9',
                        center: [{$lonJson}, {$latJson}],
                        zoom: 14
                    });
                    var marker = new mapboxgl.Marker().setLngLat([{$lonJson}, {$latJson}]).addTo(map);
                    window.pinchardPhotoMap = { map: map, marker: marker };
                    if (map.resize) map.resize();
                };
                document.head.appendChild(script);
            }
            window.pinchardLoadPhotoMap = loadMapbox;
            var toggle = document.getElementById('detailToggle');
            var drawer = document.getElementById('detailDrawer');
            if (toggle) {
                toggle.addEventListener('click', function() {
                    // Drawer opens on this click; load map as soon as details are requested.
                    loadMapbox();
                }, { once: true });
            }
            // If details start open for any reason, still load.
            if (drawer && drawer.classList.contains('open')) {
                loadMapbox();
            }
        })();
    </script>

JS;
}

$viewerHtml .= page_footer(['extra_scripts' => $footerScripts]);
write_file($OUT . '/index.html', $viewerHtml);

// ---------------------------------------------------------------------------
// gallery/index.html
// ---------------------------------------------------------------------------

$photosByDay = group_photos_by_day($photos);
$galleryMaxPhotosPerDay = 12;
$maxPhotosPerDay = 1;
foreach ($photosByDay as $dayGroup) {
	$maxPhotosPerDay = max($maxPhotosPerDay, count($dayGroup['photos']));
}
$maxPhotosPerDay = min($galleryMaxPhotosPerDay, $maxPhotosPerDay);

$span = archive_span($photos);
$galleryDescription = 'Browse the Cloudberry archive'
	. ($span !== null ? ' (' . $span['range_compact'] . ')' : '')
	. ' — hourly photographs of Pinchard\'s Island, Newfoundland, one column per day.';

$galleryHtml = page_head('Cloudberry — Photo Gallery', [
	'description' => $galleryDescription,
	'canonical' => $SITE . $BASE . '/gallery/',
	'body_class' => 'gallery-page',
]);
$galleryHtml .= page_nav('gallery');
$galleryHtml .= "    <h1 class=\"visually-hidden\">Cloudberry Photo Gallery</h1>\n";
$galleryHtml .= '    <div class="gallery-days-layout" style="--gallery-days-max-photos: ' . (int) $maxPhotosPerDay . "\">\n";

$initialFeedLabel = '';
foreach ($photosByDay as $dayGroup) {
	$initialFeedLabel = $dayGroup['feed_label'];
	break;
}
$galleryHtml .= '        <div class="gallery-feed-date" id="galleryFeedDate" aria-live="polite">' . h($initialFeedLabel) . "</div>\n";
$galleryHtml .= '        <div class="gallery-days-scroll" id="galleryDaysScroll" tabindex="0" aria-label="Photo gallery. On phones, scroll vertically by day. On larger screens, drag or scroll horizontally across days. Arrow keys move between photographs.">' . "\n";
$galleryHtml .= '            <div class="gallery-days-track" id="galleryDaysTrack">' . "\n";

$galleryEagerBudget = 12;
$galleryEagerUsed = 0;
foreach ($photosByDay as $dayKey => $dayGroup) {
	$galleryHtml .= '                <section class="gallery-day-column" id="day-' . h($dayKey) . '" aria-label="' . h($dayGroup['long_label']) . '" data-feed-label="' . h($dayGroup['feed_label']) . "\">\n";
	$galleryHtml .= '                    <div class="gallery-day-label" title="' . h($dayGroup['long_label']) . "\">\n";
	$galleryHtml .= '                        <span class="gallery-day-label-compact">' . h($dayGroup['label']) . "</span>\n";
	$galleryHtml .= '                        <span class="gallery-day-label-feed">' . h($dayGroup['feed_label']) . "</span>\n";
	$galleryHtml .= "                    </div>\n";
	$galleryHtml .= "                    <div class=\"gallery-day-stack\">\n";
	foreach ($dayGroup['photos'] as $photo) {
		$fn = (string) $photo['filename'];
		$thumb = (string) ($photo['thumbUrl'] ?? ($cdnThumbs . $fn));
		$href = $BASE . '/?filename=' . rawurlencode($fn);
		$alt = 'Photograph of Pinchard\'s Island, Newfoundland';
		$date = (string) ($photo['date'] ?? '');
		$dt = DateTime::createFromFormat('Y/m/d H:i:s', $date);
		if ($dt instanceof DateTime) {
			$alt .= ' — ' . $dt->format('F j, Y \a\t g:i A');
		}
		$timeLabel = gallery_time_label($photo);
		$galleryHtml .= '                        <a href="' . h($href) . "\" class=\"gallery-day-photo photoBox\">\n";
		$eager = $galleryEagerUsed < $galleryEagerBudget;
		if ($eager) {
			$galleryEagerUsed++;
			$priority = $galleryEagerUsed <= 4 ? ' fetchpriority="high"' : '';
			$galleryHtml .= '                            <img class="gallery-photo img-fluid" src="' . h($thumb) . '" alt="' . h($alt) . "\" width=\"288\" height=\"224\" decoding=\"async\" loading=\"eager\"{$priority}>\n";
		} else {
			$galleryHtml .= '                            <img class="gallery-photo img-fluid" data-src="' . h($thumb) . '" alt="' . h($alt) . "\" width=\"288\" height=\"224\" decoding=\"async\" loading=\"lazy\">\n";
		}
		$galleryHtml .= "                            <div class=\"photo-box-caption\">\n";
		$galleryHtml .= '                                <div class="photo-box-caption-content">' . h($timeLabel) . "</div>\n";
		$galleryHtml .= "                            </div>\n";
		$galleryHtml .= "                        </a>\n";
	}
	$galleryHtml .= "                    </div>\n";
	$galleryHtml .= "                </section>\n";
}

$galleryHtml .= "            </div>\n        </div>\n    </div>\n\n";
$galleryHtml .= page_footer([
	'extra_scripts' => '    <script src="' . h(static_asset('js/gallery.js')) . "\"></script>\n",
]);
write_file($OUT . '/gallery/index.html', $galleryHtml);

// ---------------------------------------------------------------------------
// info/index.html
// ---------------------------------------------------------------------------

$infoDescription = 'Cloudberry: solar-powered hourly photographs of Pinchard\'s Island, Newfoundland'
	. ($span !== null ? ' (' . $span['range_compact'] . ').' : '.');
$imgInfo = static fn (string $file): string => h($BASE . '/images/info/' . $file);
$imgPeople = static fn (string $file): string => h($BASE . '/images/people/' . $file);
$copyrightYear = (int) date('Y');
$accessDate = (new DateTime('now', new DateTimeZone('America/St_Johns')))->format('F j, Y');
$archiveCitation = 'Cloudberry (Pinchard\'s Island Photography Archive). ' . $SITE . $BASE . '/. Accessed ' . $accessDate . '.';
$photoCitationTpl = 'Cloudberry. Automated photograph, [Month Day, Year, Time]; photo ID [number] ([FILENAME].JPG). '
	. $SITE . $BASE . '/?filename=[FILENAME].JPG. Accessed ' . $accessDate . '.';

$spanParagraph = '';
if ($span !== null) {
	$spanParagraph = '                <p>Cloudberry operated from ' . h($span['start']) . ' through ' . h($span['end'])
		. '. The camera system eventually failed—likely from cold, weathering, too little sun, or some combination. This website is the archive and documentation of what it captured.</p>';
}

/**
 * Match lib/partials/citation.php markup so .citation-block-text gets monospace.
 *
 * @param array{text: string, label?: string, hint?: string, class?: string} $options
 */
function citation_block_html(array $options): string
{
	$text = $options['text'];
	$label = $options['label'] ?? 'Suggested citation';
	$hint = $options['hint'] ?? 'Update the access date if you retrieved this material on a different day.';
	$class = $options['class'] ?? '';
	$blockClass = 'citation-block' . ($class !== '' ? ' ' . $class : '');
	$html = '<div class="' . h($blockClass) . "\">\n";
	$html .= "    <div class=\"citation-block-header\">\n";
	$html .= '        <span class="citation-block-label">' . h($label) . "</span>\n";
	$html .= '        <button type="button" class="citation-copy-btn" data-citation="' . h($text) . '" aria-label="Copy citation to clipboard">Copy</button>' . "\n";
	$html .= "    </div>\n";
	$html .= '    <p class="citation-block-text" tabindex="0">' . h($text) . "</p>\n";
	if ($hint !== '') {
		$html .= '    <p class="citation-block-hint">' . h($hint) . "</p>\n";
	}
	$html .= "</div>\n";

	return $html;
}

$archiveCitationBlock = citation_block_html([
	'text' => $archiveCitation,
	'label' => 'Archive citation',
	'hint' => 'The access date reflects the day you loaded this page.',
	'class' => 'citation-block--spaced-below',
]);
$photoCitationBlock = citation_block_html([
	'text' => $photoCitationTpl,
	'label' => 'Individual photograph template',
	'hint' => 'Replace bracketed fields with values from the photograph you are citing. The access date reflects the day you loaded this page.',
]);

$infoHtml = page_head('Cloudberry — About', [
	'description' => $infoDescription,
	'canonical' => $SITE . $BASE . '/info/',
	'body_class' => 'info-page',
]);
$infoHtml .= page_nav('info');
$infoHtml .= <<<HTML
    <h1 class="visually-hidden">About Cloudberry</h1>
    <div class="info-hero">
        <img src="{$imgInfo('pano.jpg')}" class="img-fluid info_img" alt="View from Precious Memories cabin on Pinchard's Island" fetchpriority="high" decoding="async">
    </div>

    <div class="info-layout">
        <aside class="info-toc" aria-label="Table of contents">
            <nav>
                <a href="#about" class="is-active" aria-current="true">About</a>
                <a href="#how">How</a>
                <a href="#who">Who</a>
                <a href="#more">More</a>
                <a href="#contact">Contact</a>
            </nav>
        </aside>

        <main class="info-main">
    <div class="how_section" id="about">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>About</h2>

                <p><strong>Cloudberry</strong> was an off-the-grid, solar-powered, long-term photography project. Using a GoPro, a Raspberry Pi, and a USB cellular modem, the system we designed took one photograph per hour between 8 AM and 8 PM each day and uploaded the images via a cellular network to this website.</p>

                <p>The photographs depict a view of Pinchard's Island from a small, family-owned cabin named "Precious Memories." The island, only accessible by boat for a few weeks of the year, is home to a few cabins that resettled residents use while picking bake apples (the local term for cloudberries) during the summer months.</p>

                <img src="{$imgInfo('precious-moments.jpg')}" class="img-fluid info_img" alt="Precious Memories cabin" width="1500" height="1000" loading="lazy" decoding="async">

                <p>The view was static—in the sense that the camera always captured the same frame; however, the lighting of the frame could vary drastically from one image to another. They extended the habit of glancing out the cabin window at the surrounding landscape.</p>

{$spanParagraph}

                <h2>Okay, but why Pinchard's Island?</h2>

                <p><a href="https://art.adamsimms.xyz/" target="_blank" rel="noopener noreferrer">Adam</a> has been photographing <a href="https://art.adamsimms.xyz/pinchards-island" target="_blank" rel="noopener noreferrer">Pinchard's Island</a> and its previous residents for several years. The harsh weather conditions and the extreme remoteness of the island made it difficult to access the island year round and take images over long periods of time. Cloudberry grew from the desire to be able to photograph the island throughout the year from anywhere via the internet.</p>

                <img src="{$imgInfo('pinchards-island-sisters.jpg')}" class="img-fluid info_img" alt="Pinchard's Island Sisters" width="1000" height="667" loading="lazy" decoding="async">

                <p>Shortly after Newfoundland joined Canada as its 10th province, Pinchard's Island was <a href="https://art.adamsimms.xyz/resettlement" target="_blank" rel="noopener noreferrer">resettled</a> in an attempt to modernize the province. Adam has been documenting the return of his grandmother, along with her brothers and sisters, to this island each summer in an attempt to write the future of resettlement by reviving traditions and creating new ones.</p>

                <h2>Where is Pinchard's Island?</h2>
                <p>Pinchard's Island is situated at the northern edge of Bonavista Bay, Newfoundland, Canada. It was one of the first settled sites in Bonavista Bay but is no longer inhabited.</p>

                <div class="info-map">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d20855.569930035457!2d-53.48462861918841!3d49.20157937004537!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4b75e272b66fd9bf%3A0xe011372b0d414175!2sPinchards+Island%2C+New-Wes-Valley%2C+NL+A0G+3L0%2C+Canada!5e0!3m2!1sen!2sgr!4v1503767433902" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map of Pinchard's Island"></iframe>
                </div>
            </div></div>
        </div>
    </div>

    <div class="how_section" id="how">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>How?</h2>

                <p>Adam and Angela met early May 2017 to briefly discuss the possibility of collaborating together. The idea was loose, but the goal was to take photos of the island remotely, upload the images via the cellular network and access them from anywhere. We both shared connections to Newfoundland, and a passion for art and technology, so we set out to see what was possible.</p>

                <p>Our initial research showed that there were a lot of possibilities as to how we could approach the project, but it was clear from the beginning that every decision would result in many constraints that would affect every decision we made. Power, temperature, weather, sunlight, data limits, storage, remoteness, were all components that constantly determined decision making and the methods in which we worked.</p>

                <p>We used <a href="https://trello.com/" target="_blank" rel="noopener noreferrer">Trello</a> to plan every aspect of the project, communicate, and document our research:</p>

                <a href="https://trello.com/b/eYzSO4qQ/shutter-island" target="_blank" rel="noopener noreferrer"><img src="{$imgInfo('trello.jpg')}" class="img-fluid info_img" alt="Trello project board" width="1408" height="706" loading="lazy" decoding="async"></a>

                <p>Designing a system that worked was only the start of the project. Every slight adjustment that we made to the system, such as moving from electricity to solar power, putting the USB cellular modem in a case or using different USB cables introduced new problems that we had to constantly monitor and resolve. Once we felt confident in our system, we had to be realistic that once the system was installed on the island, we would not be able to physically be there to troubleshoot any problem that might arise. This forced us to evaluate the entire solution and implement different components to help reduce the risk factor of the project.</p>

                <img src="{$imgInfo('notebook.jpg')}" class="img-fluid info_img" alt="Project notebook" width="1400" height="933" loading="lazy" decoding="async">

                <p>The entire system took us approximately 3 months to build. This includes the initial idea, research, system design, installation, and final production code. Below is a system diagram and an outline of all the hardware and software used to create Cloudberry. The Raspberry Pi field software is open source as <a href="https://github.com/adamsimms/cloudberry" target="_blank" rel="noopener noreferrer">Cloudberry</a>.</p>

                <h2>The Cloudberry System</h2>

                <a href="https://www.figma.com/file/GvUAbr6vcpJ2Ruk1T1q4e20Z/Shutter-Island?node-id=35%3A116" target="_blank" rel="noopener noreferrer"><img src="{$imgInfo('cloudberry-system.jpg')}" class="img-fluid info_img info-system-diagram" alt="Cloudberry system diagram" width="1600" height="957" loading="lazy" decoding="async"></a>

                <h2>Hardware</h2>

                <div class="hardware-accordion">
                    <details class="hardware-details">
                        <summary>GoPro HERO4 Black with 16gb micro SD Card</summary>
                        <div class="hardware-details-body">
                                <p>We initially wanted to use the GoPro HERO5, but the Cam Do enclosure did not support the GoPro HERO5 at the time. Creating a DIY weatherproof enclosure didn't add any benefit since the image quality between GoPro HERO4 and GoPro HERO5 is the same; therefore, we made the decision to go with the GoPro HERO4.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Cam Do Blink Interval Timer</summary>
                        <div class="hardware-details-body">
                                <p>The interval timer turned on the GoPro every hour between 8 AM and 8 PM. The GoPro was modified with Cam Do Pro-csiController firmware and ran a custom <code>autoexec</code> script to take a photo, turn on the GoPro WiFi, and then put the camera in standby mode to conserve power.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Raspberry Pi 3 Model B</summary>
                        <div class="hardware-details-body">
                                <p>Standard Raspberry Pi with a 16GB Noobs SD Card running Raspbian OS.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>WittyPi 2</summary>
                        <div class="hardware-details-body">
                                <p>A real time clock (RTC) that turned on the Raspberry Pi at night for 30 minutes to download images from the GoPro, upload them to cloud storage, and shut down to conserve power.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>130 Watt Solar Power System</summary>
                        <div class="hardware-details-body">
                                <p>Solar panel, charge controller, inverter, and deep-cycle batteries designed for intermittent power in harsh winter conditions on the island.</p>
                        </div>
                    </details>
                    <details class="hardware-details">
                        <summary>Huawei LTE USB Cellular Modem</summary>
                        <div class="hardware-details-body">
                                <p>Connected to the Raspberry Pi with a Bell Canada SIM. At ±6 MB per photo and 13 photos per day, the system used roughly 2.3 GB per month.</p>
                        </div>
                    </details>
                </div>

                <h2>Installation</h2>
                <p>During the second week of August, we embarked on our journey to install Cloudberry. The first task was to bring all of the solar power components to the island, which was a task that required four people to load the housing unit, batteries, and solar panel. It took approximately two days for Roger and Adam to install the entire system with constant readjustments.</p>

                <img src="{$imgInfo('solar-install.jpg')}" class="img-fluid info_img" alt="Solar power installation" width="1000" height="1000" loading="lazy" decoding="async">

                <p>The second step was to choose the frame of the photograph and install the Cam Do enclosure. Pointing the camera towards the north avoided sun blasting and offers views of both the landscape and the ocean.</p>

                <img src="{$imgInfo('cam-do.jpg')}" class="img-fluid info_img" alt="Cam Do enclosure" width="1400" height="934" loading="lazy" decoding="async">

                <p>Once the entire system was in place, we monitored everything for a full day cycle before locking up all of the cases and leaving the island.</p>

                <img src="{$imgInfo('pi.jpg')}" class="img-fluid info_img" alt="Raspberry Pi assembly" width="1400" height="933" loading="lazy" decoding="async">
            </div></div>
        </div>
    </div>

    <div class="who_section" id="who">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <img src="{$imgInfo('yay.jpg')}" class="img-fluid info_img" alt="Cloudberry creators" width="3000" height="2250" loading="lazy" decoding="async">
                <h2>Who made Cloudberry?</h2>
                <ul class="people-list">
                    <li class="people-list-item">
                        <img class="people-list-photo" src="{$imgPeople('adam-simms.jpg')}" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Adam Simms</div>
                            <div class="people-list-role">Photographer, designer, developer</div>
                            <a href="https://art.adamsimms.xyz/" target="_blank" rel="noopener noreferrer" class="link">www</a>
                        </div>
                    </li>
                    <li class="people-list-item">
                        <img class="people-list-photo" src="{$imgPeople('angela-gabereaux.jpg')}" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Angela Gabereaux</div>
                            <div class="people-list-role">Software developer, systems engineer</div>
                            <a href="https://www.ada-x.org/en/participants/angela-gabereau-2/" target="_blank" rel="noopener noreferrer" class="link">www</a>
                        </div>
                    </li>
                    <li class="people-list-item">
                        <img class="people-list-photo" src="{$imgPeople('roger-knight.jpg')}" alt="" width="72" height="72" loading="lazy" decoding="async">
                        <div class="people-list-body">
                            <div class="people-list-name">Roger Knight</div>
                            <div class="people-list-role">Equipment operator, carpenter</div>
                        </div>
                    </li>
                </ul>
            </div></div>
        </div>
    </div>

    <div class="how_section" id="more">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>Citations</h2>
                <p>Researchers and publications are welcome to use Cloudberry photographs with attribution. The archive is complete and no longer receiving new images.</p>
                <p>The suggested format below follows the <strong>Chicago Manual of Style, Author-Date</strong> system, adapted for a born-digital photograph archive (similar to citing a website or online collection).</p>
                <h3>Citing the entire archive</h3>
                <p>Use this when referring to the project or website as a whole.</p>
{$archiveCitationBlock}
                <h3>Citing a specific photograph</h3>
                <p>Open the image on the site, expand the details panel, and note the <strong>date and time</strong>, <strong>photo number</strong> (shown as the large title), and <strong>filename</strong> from the page URL (<code>?filename=…</code>). Substitute those values into the template below.</p>
{$photoCitationBlock}

                <h2>Keyboard shortcuts</h2>
                <ul class="keyboard-shortcuts">
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>←</kbd> <kbd>→</kbd></span>
                        <span class="keyboard-shortcuts-action">Previous / next photograph</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>↑</kbd> <kbd>↓</kbd></span>
                        <span class="keyboard-shortcuts-action">Open / close details</span>
                    </li>
                    <li>
                        <span class="keyboard-shortcuts-keys"><kbd>Space</kbd></span>
                        <span class="keyboard-shortcuts-action">Pause / resume</span>
                    </li>
                </ul>

                <h2>Source</h2>
                <ul class="source-list">
                    <li>
                        <a href="https://github.com/adamsimms/cloudberry" target="_blank" rel="noopener noreferrer" class="link">Cloudberry</a>
                        <span class="source-list-desc">Raspberry Pi field software — capture, upload, and power management</span>
                    </li>
                    <li>
                        <a href="https://github.com/adamsimms/pinchards.is" target="_blank" rel="noopener noreferrer" class="link">pinchards.is</a>
                        <span class="source-list-desc">This website and photograph archive</span>
                    </li>
                </ul>
            </div></div>
        </div>
    </div>

    <div class="contact_section" id="contact">
        <div class="container">
            <div class="row justify-content-center"><div class="col-12 col-md-10 col-lg-8">
                <h2>Contact</h2>
                <p><!--email_off--><a href="#" class="link" data-email-mailto>Email</a><!--/email_off--></p>
                <img src="{$imgInfo('boat.jpg')}" class="img-fluid info_img" alt="Boat approaching Pinchard's Island" width="1400" height="918" loading="lazy" decoding="async">
                <div class="copyright">
                    Copyright &copy; 2017&ndash;{$copyrightYear}
                </div>
            </div></div>
        </div>
    </div>

        </main>
    </div>

HTML;

$infoTocJs = <<<'JS'
    <script>
        (function() {
            var toc = document.querySelector('.info-toc');
            var hero = document.querySelector('.info-hero');
            var sectionIds = ['about', 'how', 'who', 'more', 'contact'];
            var links = document.querySelectorAll('.info-toc nav a');
            var sections = sectionIds.map(function(id) {
                return document.getElementById(id);
            }).filter(Boolean);
            var mobileToc = window.matchMedia('(max-width: 991px)');

            function setActive(id) {
                links.forEach(function(link) {
                    var match = (link.getAttribute('href') || '') === '#' + id;
                    link.classList.toggle('is-active', match);
                    if (match) {
                        link.setAttribute('aria-current', 'true');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            }

            function setMobileTocVisible(visible) {
                if (!toc) return;
                if (!mobileToc.matches) {
                    toc.classList.remove('is-visible');
                    return;
                }
                toc.classList.toggle('is-visible', visible);
            }

            if (toc && hero && 'IntersectionObserver' in window) {
                new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        setMobileTocVisible(!entry.isIntersecting);
                    });
                }, { rootMargin: '-50px 0px 0px 0px', threshold: 0 }).observe(hero);
            }

            if (sections.length && 'IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    var visible = entries.filter(function(e) { return e.isIntersecting; })
                        .sort(function(a, b) {
                            return Math.abs(a.boundingClientRect.top) - Math.abs(b.boundingClientRect.top);
                        });
                    if (visible.length) {
                        setActive(visible[0].target.id);
                    }
                }, { rootMargin: '-20% 0px -55% 0px', threshold: 0 });
                sections.forEach(function(section) { observer.observe(section); });
            }

            links.forEach(function(link) {
                link.addEventListener('click', function(event) {
                    var href = link.getAttribute('href') || '';
                    if (href.charAt(0) !== '#') return;
                    var target = document.getElementById(href.slice(1));
                    if (!target) return;
                    event.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    setActive(href.slice(1));
                    if (window.history && window.history.pushState) {
                        window.history.pushState(null, '', href);
                    }
                });
            });
        })();
    </script>
JS;

$infoHtml .= page_footer(['extra_scripts' => $infoTocJs]);
write_file($OUT . '/info/index.html', $infoHtml);

// ---------------------------------------------------------------------------
// jam/index.html — kiosk slideshow
// ---------------------------------------------------------------------------

$jamImages = [];
foreach ($photos as $p) {
	$jamImages[] = [
		'filename' => (string) $p['filename'],
		'date' => (string) ($p['date'] ?? ''),
		'show_date' => (string) ($p['showDate'] ?? ''),
	];
}

$jamPayload = [
	'display' => 10, // ms (matches PHP: display * 1000 with default 0.01s)
	'fade' => 6000,
	'images' => $jamImages,
	'timeline' => null,
	'cdnurl' => $cdnFull,
	'startIndex' => 0,
];
$jamJson = json_encode($jamPayload, $je);
if ($jamJson === false) {
	fwrite(STDERR, "Failed to encode jam slideshow config\n");
	exit(1);
}

$jamHtml = page_head('Cloudberry Jam', [
	'description' => 'Fullscreen exhibition slideshow from the Cloudberry archive — for projection and direct-link playback only.',
	'canonical' => $SITE . $BASE . '/jam/',
	'robots' => 'noindex, nofollow',
	'body_class' => 'jam-page jam-page--fill',
]);
// No site nav for jam kiosk
$jamHtml .= <<<HTML
    <div class="slideshow-shell jam-slideshow-shell">
        <div class="slideshow-viewport" id="slideshow" style="--slideshow-fade: 6s;" aria-live="polite" aria-label="Exhibition slideshow"></div>
    </div>

HTML;
$jamScripts = '    <script>window.pinchardSlideshow = ' . $jamJson . ";</script>\n"
	. '    <script src="' . h(static_asset('js/slideshow.js')) . "\"></script>\n";
$jamHtml .= page_footer([
	'include_pinchard_js' => false,
	'extra_scripts' => $jamScripts,
]);
write_file($OUT . '/jam/index.html', $jamHtml);

// ---------------------------------------------------------------------------
// Redirects fragment for art repo to merge into public/_redirects
// ---------------------------------------------------------------------------

$redirects = <<<'TXT'
# Cloudflare Pages redirects fragment for Cloudberry archive
# Merge into art.adamsimms.xyz public/_redirects (or Pages redirects config).
# Paths assume the archive is served at /cloudberry/archive/.
/cloudberry/archive/gallery.php /cloudberry/archive/gallery/ 301
/cloudberry/archive/info.php /cloudberry/archive/info/ 301
/cloudberry/archive/slideshow.php /cloudberry/archive/?play=1 301
/cloudberry/archive/slider.php /cloudberry/archive/?play=1 301
/cloudberry/archive/index.php /cloudberry/archive/ 301
/cloudberry/archive/gallery /cloudberry/archive/gallery/ 308
/cloudberry/archive/info /cloudberry/archive/info/ 308
/cloudberry/archive/jam /cloudberry/archive/jam/ 308

TXT;
write_file($OUT . '/_redirects.fragment', $redirects);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$totalOutFiles = 0;
$outIterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($OUT, FilesystemIterator::SKIP_DOTS)
);
foreach ($outIterator as $item) {
	if ($item->isFile()) {
		$totalOutFiles++;
	}
}

$photoCacheCopied = is_dir($OUT . '/images/photo/.cache');
echo "\nDone.\n";
echo "  HTML pages written: index, gallery, info, jam\n";
echo "  Asset files copied: {$assetCopied}\n";
echo "  Files written by builder: {$filesWritten}\n";
echo "  Total files in output: {$totalOutFiles}\n";
echo "  Photo count: {$photoCount}\n";
echo "  Mapbox: " . ($hasMapbox ? 'pk token embedded' : 'not available') . "\n";
echo "  images/photo/.cache present in output: " . ($photoCacheCopied ? 'YES (bug)' : 'no') . "\n";
echo "  Output: {$OUT}\n";

if ($photoCacheCopied) {
	fwrite(STDERR, "ERROR: images/photo/.cache was copied; fix skip logic.\n");
	exit(1);
}

exit(0);
