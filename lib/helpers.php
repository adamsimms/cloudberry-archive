<?php

declare(strict_types=1);

/** Escape HTML text or attribute values. */
function pinchard_h(?string $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Minimal branded unavailable page (503 by default) with a quiet CSS fade-in.
 * Used when gallery/config cannot serve the requested surface.
 */
function pinchard_unavailable_page(string $message, int $status = 503): never
{
	http_response_code($status);
	header('Content-Type: text/html; charset=utf-8');
	$title = pinchard_h('Cloudberry — Unavailable');
	$body = pinchard_h($message);
	echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{$title}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "DM Sans", sans-serif;
            background: #0a0a0a;
            color: rgba(255, 255, 255, 0.88);
            text-align: center;
            padding: 24px;
        }
        .pinchard-empty-state {
            max-width: 28rem;
            opacity: 0;
            animation: pinchard-empty-in 0.7s ease forwards;
        }
        .pinchard-empty-state a {
            color: #F05F40;
            text-decoration: none;
        }
        .pinchard-empty-state a:hover,
        .pinchard-empty-state a:focus {
            text-decoration: underline;
        }
        .pinchard-empty-brand {
            display: block;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 1rem;
            color: #fff;
        }
        .pinchard-empty-message {
            font-size: 1.05rem;
            line-height: 1.55;
            margin: 0 0 1.25rem;
        }
        @keyframes pinchard-empty-in {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .pinchard-empty-state {
                animation: none;
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="pinchard-empty-state">
        <span class="pinchard-empty-brand">Cloudberry</span>
        <p class="pinchard-empty-message">{$body}</p>
        <p><a href="/index.php">Back to photographs</a></p>
    </div>
</body>
</html>
HTML;
	exit;
}

/** Google Fonts link tags for DM Sans (body + headings). */
function pinchard_fonts_head_html(): string
{
	return implode("\n", [
		'    <link rel="preconnect" href="https://fonts.googleapis.com">',
		'    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
		'    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">',
	]);
}

function pinchard_mapbox_gl_version(): string
{
	return '3.25.0';
}

function pinchard_mapbox_gl_css(): string
{
	$version = pinchard_mapbox_gl_version();

	return '    <link href="https://api.mapbox.com/mapbox-gl-js/v' . $version . '/mapbox-gl.css" rel="stylesheet">';
}

function pinchard_mapbox_gl_js(): string
{
	$version = pinchard_mapbox_gl_version();

	return '    <script src="https://api.mapbox.com/mapbox-gl-js/v' . $version . '/mapbox-gl.js"></script>';
}

/** Short display date for gallery captions and timeline (e.g. MAR · 18:09). */
function pinchard_show_date(DateTime $dt): string
{
	return strtoupper($dt->format('M')) . ' · ' . $dt->format('H:i');
}

/** Long display date for the photo detail drawer (e.g. Thursday, March 1st, 2018 @ 6:07 PM). */
function pinchard_format_photo_long_date(DateTime $dt): string
{
	return $dt->format('l, F jS, Y @ g:i A');
}

/** @return DateTime|null */
function pinchard_parse_stored_photo_datetime(string $datetime): ?DateTime
{
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $datetime);

	return $dt instanceof DateTime ? $dt : null;
}

/**
 * Resolve capture time for display — always prefer EXIF DateTimeOriginal.
 *
 * EXIF is the source of truth for when the image was taken. S3 keys often carry
 * a download/upload stamp (`...T18:09:20.000Z_...`), especially near the end of
 * the archive where many keys share the same bulk-download minute.
 *
 * @param array<string, mixed> $exif
 */
function pinchard_photo_capture_datetime(string $filenameDatetime, array $exif): ?DateTime
{
	$exifRaw = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
	if (is_string($exifRaw) && $exifRaw !== '') {
		$fromExif = DateTime::createFromFormat('Y:m:d H:i:s', $exifRaw);
		if ($fromExif instanceof DateTime) {
			return $fromExif;
		}
	}

	return pinchard_parse_stored_photo_datetime($filenameDatetime);
}

/** Time-only label for compact gallery overlays (e.g. 18:09). */
function pinchard_show_time(string $date): string
{
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $date);
	if ($dt === false) {
		return '';
	}

	return $dt->format('H:i');
}

/** Scheme + host for absolute URLs (e.g. https://art.adamsimms.xyz). */
function pinchard_site_origin(): string
{
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'art.adamsimms.xyz';

	return $scheme . '://' . $host;
}

/**
 * @param array<string, scalar|null> $query
 */
function pinchard_absolute_url(string $path, array $query = []): string
{
	$url = pinchard_site_origin() . $path;
	$filtered = array_filter($query, static fn ($value) => $value !== null && $value !== '');
	if ($filtered === []) {
		return $url;
	}

	return $url . '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
}

/** Canonical URL for the current request (preserves photo filename on index.php). */
function pinchard_canonical_url(): string
{
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	$path = strtok($uri, '?') ?: '/';
	$query = [];

	if (basename($path) === 'index.php' && isset($_GET['filename']) && $_GET['filename'] !== '') {
		$query['filename'] = (string) $_GET['filename'];
	}

	return pinchard_absolute_url($path, $query);
}

/** Default Open Graph preview image for site pages. */
function pinchard_default_og_image(): string
{
	return 'https://art.adamsimms.xyz/cloudberry/archive/images/info/pano.jpg';
}

/**
 * Shared meta description, Open Graph, Twitter Card, and canonical tags.
 *
 * @param array{
 *   og_image?: string,
 *   og_type?: string,
 *   canonical_url?: string,
 *   robots?: string,
 *   og_site_name?: string,
 *   author?: string,
 * } $options
 */
function pinchard_seo_head_markup(string $title, string $description, array $options = []): string
{
	$ogImage = $options['og_image'] ?? pinchard_default_og_image();
	$ogType = $options['og_type'] ?? 'website';
	$canonical = $options['canonical_url'] ?? pinchard_canonical_url();
	$ogSiteName = $options['og_site_name'] ?? "Pinchard's Island";
	$author = $options['author'] ?? 'Adam Simms & Angela Gabereaux';
	$robots = $options['robots'] ?? null;

	$t = pinchard_h($title);
	$d = pinchard_h($description);
	$og = pinchard_h($ogImage);
	$canonicalEsc = pinchard_h($canonical);
	$authorEsc = pinchard_h($author);
	$siteNameEsc = pinchard_h($ogSiteName);

	$lines = [
		'    <meta name="description" content="' . $d . '">',
		'    <meta name="author" content="' . $authorEsc . '">',
	];
	if ($robots !== null && $robots !== '') {
		$lines[] = '    <meta name="robots" content="' . pinchard_h($robots) . '">';
	}
	$lines = array_merge($lines, [
		'    <meta property="og:title" content="' . $t . '">',
		'    <meta property="og:description" content="' . $d . '">',
		'    <meta property="og:image" content="' . $og . '">',
		'    <meta property="og:type" content="' . pinchard_h($ogType) . '">',
		'    <meta property="og:url" content="' . $canonicalEsc . '">',
		'    <meta property="og:site_name" content="' . $siteNameEsc . '">',
		'    <meta name="twitter:card" content="summary_large_image">',
		'    <meta name="twitter:title" content="' . $t . '">',
		'    <meta name="twitter:description" content="' . $d . '">',
		'    <meta name="twitter:image" content="' . $og . '">',
		'    <link rel="canonical" href="' . $canonicalEsc . '">',
	]);

	return implode("\n", $lines) . "\n";
}

/** Accessible alt text for a photograph from its archive datetime string. */
function pinchard_photo_alt_text(string $datetime): string
{
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $datetime);
	if ($dt === false) {
		return "Photograph of Pinchard's Island, Newfoundland";
	}

	return "Photograph of Pinchard's Island, Newfoundland — " . $dt->format('F j, Y \a\t g:i A');
}

/**
 * @param list<array<string, mixed>> $nodes Schema.org entities (without @context).
 */
function pinchard_json_ld_script(array $nodes): string
{
	if ($nodes === []) {
		return '';
	}

	$payload = [
		'@context' => 'https://schema.org',
		'@graph' => $nodes,
	];
	$json = json_encode(
		$payload,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	if ($json === false) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Datetime used for gallery day/month columns — EXIF capture when cached, else filename.
 *
 * @param array{filename?: string, date?: string, capture_date?: string} $photo
 */
function pinchard_photo_grouping_datetime(array $photo): ?DateTime
{
	$raw = $photo['capture_date'] ?? $photo['date'] ?? '';
	if (!is_string($raw) || $raw === '') {
		return null;
	}

	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $raw);

	return $dt instanceof DateTime ? $dt : null;
}

/**
 * Sort key for archive order — EXIF capture when cached, else filename stamp.
 *
 * @param array{date?: string, capture_date?: string} $photo
 */
function pinchard_photo_sort_key(array $photo): string
{
	$raw = $photo['capture_date'] ?? $photo['date'] ?? '';

	return is_string($raw) ? $raw : '';
}

/**
 * Group photos chronologically by Y-m month key.
 *
 * @param list<array{filename: string, date: string, show_date?: string, capture_date?: string}> $photos
 * @return array<string, array{label: string, photos: list<array{filename: string, date: string, show_date?: string, capture_date?: string}>}>
 */
function pinchard_group_photos_by_month(array $photos): array
{
	$photosByMonth = [];
	foreach ($photos as $photo) {
		$dt = pinchard_photo_grouping_datetime($photo);
		if ($dt === null) {
			continue;
		}
		$monthKey = $dt->format('Y-m');
		if (!isset($photosByMonth[$monthKey])) {
			$photosByMonth[$monthKey] = [
				'label' => $dt->format('F Y'),
				'photos' => [],
			];
		}
		$photosByMonth[$monthKey]['photos'][] = $photo;
	}

	return $photosByMonth;
}

/**
 * Group photos chronologically by calendar day (Y-m-d).
 *
 * @param list<array{filename: string, date: string, show_date?: string, capture_date?: string}> $photos
 * @return array<string, array{label: string, feed_label: string, long_label: string, month_key: string, photos: list<array{filename: string, date: string, show_date?: string, capture_date?: string}>}>
 */
function pinchard_group_photos_by_day(array $photos): array
{
	$photosByDay = [];
	foreach ($photos as $photo) {
		$dt = pinchard_photo_grouping_datetime($photo);
		if ($dt === null) {
			continue;
		}
		$dayKey = $dt->format('Y-m-d');
		if (!isset($photosByDay[$dayKey])) {
			$photosByDay[$dayKey] = [
				'label' => $dt->format('M j'),
				'feed_label' => strtoupper($dt->format('F')) . ' ' . $dt->format('j') . ' • ' . $dt->format('Y'),
				'day_number' => $dt->format('j'),
				'long_label' => $dt->format('F j, Y'),
				'month_key' => $dt->format('Y-m'),
				'photos' => [],
			];
		}
		$photosByDay[$dayKey]['photos'][] = $photo;
	}

	return $photosByDay;
}

/**
 * @return array{month_key: string, label: string, gallery_url: string}|null
 */
function pinchard_gallery_context_for_photo(string $date): ?array
{
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $date);
	if ($dt === false) {
		return null;
	}
	$monthKey = $dt->format('Y-m');
	$dayKey = $dt->format('Y-m-d');

	return [
		'month_key' => $monthKey,
		'label' => $dt->format('F j, Y'),
		'gallery_url' => 'gallery.php#day-' . $dayKey,
	];
}

/**
 * Timeline entries for the index photo viewer scrubber.
 *
 * Uses the full archive when it is small enough to embed; otherwise scopes to the
 * photograph's month so drag/click can resolve to the nearest image client-side.
 *
 * @param list<array{filename: string, date: string, show_date?: string}> $photos
 * @return array{
 *   scope: 'archive'|'month',
 *   label: string,
 *   entries: list<array{f: string, d: string}>,
 *   index: int,
 * }|null
 */
function pinchard_viewer_timeline(array $photos, string $currentFilename, ?array $galleryContext): ?array
{
	if ($photos === []) {
		return null;
	}

	$archiveLimit = 2500;
	$scope = count($photos) <= $archiveLimit ? 'archive' : 'month';
	$monthKey = $galleryContext['month_key'] ?? null;
	$label = $scope === 'archive' ? 'Full archive' : ($galleryContext['label'] ?? 'This month');

	$entries = [];
	foreach ($photos as $photo) {
		if ($scope === 'month') {
			if ($monthKey === null) {
				continue;
			}
			$dt = DateTime::createFromFormat('Y/m/d H:i:s', $photo['date']);
			if ($dt === false || $dt->format('Y-m') !== $monthKey) {
				continue;
			}
		}
		$entries[] = [
			'f' => $photo['filename'],
			'd' => $photo['show_date'] ?? $photo['date'],
		];
	}

	if (count($entries) < 2) {
		return null;
	}

	$index = 0;
	foreach ($entries as $i => $entry) {
		if ($entry['f'] === $currentFilename) {
			$index = $i;
			break;
		}
	}

	return [
		'scope' => $scope,
		'label' => $label,
		'entries' => $entries,
		'index' => $index,
	];
}

/**
 * Timeline entries for the slideshow scrubber (always the full archive).
 *
 * @param list<array{filename: string, date: string, show_date?: string}> $photos
 * @return array{
 *   scope: 'archive',
 *   label: string,
 *   entries: list<array{f: string, d: string}>,
 *   index: int,
 * }|null
 */
function pinchard_slideshow_timeline(array $photos): ?array
{
	if (count($photos) < 2) {
		return null;
	}

	$entries = [];
	foreach ($photos as $photo) {
		$entries[] = [
			'f' => $photo['filename'],
			'd' => $photo['show_date'] ?? $photo['date'],
		];
	}

	return [
		'scope' => 'archive',
		'label' => 'Full archive',
		'entries' => $entries,
		'index' => 0,
	];
}

/** Compact label for gallery timeline scrubber (e.g. Aug 2017). */
function pinchard_month_timeline_label(string $monthKey): string
{
	$dt = DateTime::createFromFormat('Y-m', $monthKey);
	if ($dt === false) {
		return $monthKey;
	}

	return $dt->format('M Y');
}

/** Full month label for day gallery separators (e.g. August 2017). */
function pinchard_month_full_label(string $monthKey): string
{
	$dt = DateTime::createFromFormat('Y-m', $monthKey);
	if ($dt === false) {
		return $monthKey;
	}

	return $dt->format('F Y');
}

/**
 * @param list<array{filename: string, date: string, show_date?: string}> $photos
 */
function pinchard_latest_photo(array $photos): ?array
{
	if ($photos === []) {
		return null;
	}

	return $photos[count($photos) - 1];
}

/** Default meta description for Cloudberry (completed project). */
function pinchard_cloudberry_site_description(): string
{
	return "Cloudberry — an off-the-grid, solar-powered long-term photography project that documented Pinchard's Island, Newfoundland.";
}

/**
 * @param list<array{filename: string, date: string, show_date?: string}> $photos
 * @return array{start: string, end: string}|null
 */
function pinchard_cloudberry_archive_span(array $photos): ?array
{
	if ($photos === []) {
		return null;
	}

	$startDt = DateTime::createFromFormat('Y/m/d H:i:s', $photos[0]['date']);
	$endDt = DateTime::createFromFormat('Y/m/d H:i:s', $photos[count($photos) - 1]['date']);
	if ($startDt === false || $endDt === false) {
		return null;
	}

	return [
		'start' => $startDt->format('F j, Y'),
		'end' => $endDt->format('F j, Y'),
		'range_compact' => $startDt->format('F Y') . '–' . $endDt->format('F Y'),
	];
}

/** About-page meta description including archive dates when available. */
function pinchard_cloudberry_info_description(?array $archiveSpan): string
{
	$base = 'Cloudberry: solar-powered hourly photographs of Pinchard\'s Island, Newfoundland';
	if ($archiveSpan !== null && isset($archiveSpan['range_compact'])) {
		return $base . ' (' . $archiveSpan['range_compact'] . ').';
	}

	return $base . '.';
}

/** Gallery meta description including archive dates when available. */
function pinchard_cloudberry_gallery_description(?array $archiveSpan): string
{
	$range = ($archiveSpan !== null && isset($archiveSpan['range_compact']))
		? ' (' . $archiveSpan['range_compact'] . ')'
		: '';

	return 'Browse the Cloudberry archive' . $range . ' — hourly photographs of Pinchard\'s Island, Newfoundland, one column per day.';
}

/** Slideshow meta description including archive dates when available. */
function pinchard_cloudberry_slideshow_description(?array $archiveSpan): string
{
	$range = ($archiveSpan !== null && isset($archiveSpan['range_compact']))
		? ' (' . $archiveSpan['range_compact'] . ')'
		: '';

	return 'Browse the Cloudberry archive in sequence' . $range . ' — hourly views from Precious Memories cabin on Pinchard\'s Island.';
}

/** Convert an EXIF GPS rational string (or number) to float. */
function pinchard_gps_rational_to_float(mixed $coordPart): float
{
	if (is_int($coordPart) || is_float($coordPart)) {
		return (float) $coordPart;
	}
	if (!is_string($coordPart)) {
		return 0.0;
	}
	$parts = explode('/', $coordPart);
	if (count($parts) === 1) {
		return (float) $parts[0];
	}
	if (count($parts) >= 2 && (float) $parts[1] !== 0.0) {
		return (float) $parts[0] / (float) $parts[1];
	}

	return 0.0;
}

/** @param list<mixed> $exifCoord */
function pinchard_gps_to_decimal(array $exifCoord, ?string $hemi): ?float
{
	if ($hemi === null || $hemi === '') {
		return null;
	}
	$degrees = count($exifCoord) > 0 ? pinchard_gps_rational_to_float($exifCoord[0]) : 0.0;
	$minutes = count($exifCoord) > 1 ? pinchard_gps_rational_to_float($exifCoord[1]) : 0.0;
	$seconds = count($exifCoord) > 2 ? pinchard_gps_rational_to_float($exifCoord[2]) : 0.0;
	$flip = ($hemi === 'W' || $hemi === 'S') ? -1 : 1;

	return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

/** Today's date for citation access lines (Chicago-style, Newfoundland local time). */
function pinchard_citation_access_date(): string
{
	return (new DateTime('now', new DateTimeZone('America/St_Johns')))->format('F j, Y');
}

/** Suggested citation for the Cloudberry archive as a whole. */
function pinchard_citation_archive(): string
{
	$url = pinchard_absolute_url('/');

	return 'Cloudberry (Pinchard\'s Island Photography Archive). '
		. $url
		. '. Accessed ' . pinchard_citation_access_date() . '.';
}

/** Template for citing an individual Cloudberry photograph (replace bracketed fields). */
function pinchard_citation_photo_template(): string
{
	return 'Cloudberry. Automated photograph, [Month Day, Year, Time]; photo ID [number] ([FILENAME].JPG). '
		. pinchard_site_origin()
		. '/index.php?filename=[FILENAME].JPG. Accessed ' . pinchard_citation_access_date() . '.';
}

/** Suggested citation for an individual Cloudberry photograph. */
function pinchard_citation_photo(string $filename, string $datetime): string
{
	$url = pinchard_absolute_url('/index.php', ['filename' => $filename]);
	$photoId = pinchard_photo_title($filename);
	$dt = DateTime::createFromFormat('Y/m/d H:i:s', $datetime);
	$timestamp = $dt !== false ? $dt->format('F j, Y, g:i A') : $datetime;

	return 'Cloudberry. Automated photograph, '
		. $timestamp
		. '; photo ID ' . $photoId
		. ' (' . $filename . '). '
		. $url
		. '. Accessed ' . pinchard_citation_access_date() . '.';
}

/** Cabin location defaults when GPS EXIF is missing. */
function pinchard_cloudberry_gps_defaults(): array
{
	return [
		'latitude_degree' => '49',
		'latitude_min' => '12',
		'latitude_sec' => '9.14',
		'longitude_degree' => '53',
		'longitude_min' => '29',
		'longitude_sec' => '9.11',
		'altitude' => '5.27/1',
	];
}

/** Precious Memories cabin — canonical map center for Cloudberry photo viewer. */
function pinchard_cloudberry_cabin_coords(): array
{
	return [
		'lat' => 49.2025694,
		'lon' => -53.48586388888953,
	];
}

/** Default view for /maps/ — island and Bonavista Bay context. */
function pinchard_pinchards_island_satellite_view(): array
{
	return [
		'lat' => 49.1998,
		'lon' => -53.4865,
		'zoom' => 13.5,
	];
}

/** Display title for a gallery photo (GoPro: digits after GOPR). */
function pinchard_photo_title(string $filename): string
{
	$basename = pathinfo($filename, PATHINFO_FILENAME);
	if (preg_match('/GOPR(\d+)/i', $basename, $matches)) {
		return $matches[1];
	}

	return $basename;
}

/**
 * Resolve a gallery photo by filename (allowlist). Unknown fn falls back to latest photo.
 *
 * @param list<array{filename: string, date: string, show_date?: string}> $photos
 * @return array{photo: array, prev_filename: ?string, next_filename: ?string}
 */
function pinchard_resolve_gallery_photo(array $photos, ?string $requested): array
{
	if ($photos === []) {
		throw new RuntimeException('Photo gallery is empty.');
	}

	if ($requested !== null && $requested !== '') {
		foreach ($photos as $i => $photo) {
			if ($photo['filename'] === $requested) {
				return [
					'photo' => $photo,
					'prev_filename' => $i > 0 ? $photos[$i - 1]['filename'] : null,
					'next_filename' => $i < count($photos) - 1 ? $photos[$i + 1]['filename'] : null,
				];
			}
		}
	}

	$i = count($photos) - 1;

	return [
		'photo' => $photos[$i],
		'prev_filename' => $i > 0 ? $photos[$i - 1]['filename'] : null,
		'next_filename' => null,
	];
}

/** Simple per-IP rate limit for lightweight JSON proxies. */
function pinchard_rate_limit(string $bucket, int $maxRequests, int $windowSeconds = 3600): void
{
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$file = sys_get_temp_dir() . '/pinchard_rl_' . $bucket . '_' . md5($ip) . '.json';
	$now = time();
	$data = ['count' => 0, 'start' => $now];

	if (is_readable($file)) {
		$decoded = json_decode((string) file_get_contents($file), true);
		if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
			$data = $decoded;
		}
	}

	if ($now - (int) $data['start'] >= $windowSeconds) {
		$data = ['count' => 0, 'start' => $now];
	}

	$data['count'] = (int) $data['count'] + 1;
	file_put_contents($file, json_encode($data), LOCK_EX);

	if ($data['count'] > $maxRequests) {
		http_response_code(429);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'Rate limit exceeded. Try again later.']);
		exit;
	}
}
