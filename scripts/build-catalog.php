#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build data/catalog.json for the static Cloudberry archive.
 *
 * Sources (local caches + optional ranged EXIF completion from R2 CDN):
 *   - images/photo/.cache/s3-list-*.json
 *   - images/photo/.cache/exif-dates.json
 *   - images/photo/.cache/weather-hours.json
 *   - images/photo/.cache/exif-meta/*.json (filled on miss via Range GET)
 *
 * Usage:
 *   php scripts/build-catalog.php
 *   php scripts/build-catalog.php --limit=50
 *   php scripts/build-catalog.php --skip-exif-fetch   # use only existing meta + defaults
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

const CATALOG_CDN_FULL = 'https://cloudberry-images.adamsimms.xyz/';
const CATALOG_CDN_THUMBS = 'https://cloudberry-thumbs.adamsimms.xyz/';
const CATALOG_EXIF_RANGE_BYTES = 262144;
const CATALOG_EXPECTED_COUNT = 1957;

$limit = null;
$skipExifFetch = false;
foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--limit=')) {
		$limit = max(0, (int) substr($arg, 8));
	} elseif ($arg === '--skip-exif-fetch') {
		$skipExifFetch = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDERR, "Usage: php scripts/build-catalog.php [--limit=N] [--skip-exif-fetch]\n");
		exit(0);
	}
}

$repo = dirname(__DIR__);
$cacheDir = $repo . '/images/photo/.cache';
$outPath = $repo . '/data/catalog.json';

$photos = catalog_load_photo_list($cacheDir);
if ($limit !== null) {
	$photos = array_slice($photos, 0, $limit);
}
$dates = catalog_load_exif_dates($cacheDir);
$weatherHours = catalog_load_weather_hours($cacheDir);
$cabin = pinchard_cloudberry_cabin_coords();

$total = count($photos);
fwrite(STDERR, "Building catalog for {$total} photographs…\n");

$records = [];
$exifHits = 0;
$exifFetches = 0;
$exifFails = 0;
$weatherHits = 0;
$weatherMiss = 0;

foreach ($photos as $i => $photo) {
	$filename = $photo['filename'];
	$n = $i + 1;

	$exif = pinchard_exif_meta_cache_read($filename);
	if ($exif === null && !$skipExifFetch) {
		$exif = catalog_fetch_exif_ranged($filename);
		$exifFetches++;
		if ($exif !== []) {
			try {
				pinchard_exif_meta_cache_write($filename, $exif);
			} catch (Throwable $e) {
				fwrite(STDERR, "[{$n}] meta write failed: {$e->getMessage()}\n");
			}
		}
	}

	if ($exif === null) {
		$exif = [];
	}
	if ($exif !== []) {
		$exifHits++;
	} else {
		$exifFails++;
	}

	$dateFromCache = $dates[$filename] ?? null;
	$captureDt = null;
	if (is_string($dateFromCache) && $dateFromCache !== '') {
		$captureDt = pinchard_parse_stored_photo_datetime($dateFromCache);
	}
	if ($captureDt === null) {
		$captureDt = pinchard_photo_capture_datetime($photo['date'], $exif);
	}

	$camera = catalog_camera_from_exif($exif);
	$gps = catalog_gps_from_exif($exif, $cabin);
	$weather = catalog_weather_for_capture($captureDt, $weatherHours);
	if ($weather !== null) {
		$weatherHits++;
	} else {
		$weatherMiss++;
	}

	$dateStored = $captureDt !== null
		? $captureDt->format('Y/m/d H:i:s')
		: ($dateFromCache ?? $photo['date']);

	$records[] = [
		'filename' => $filename,
		'title' => pinchard_photo_title($filename),
		'date' => $dateStored,
		'showDate' => $captureDt !== null
			? pinchard_show_date($captureDt)
			: ($photo['show_date'] ?? ''),
		'convertedDate' => $captureDt !== null
			? pinchard_format_photo_long_date($captureDt)
			: $dateStored,
		'captureDateIso' => $captureDt !== null ? $captureDt->format(DateTime::ATOM) : null,
		'imageUrl' => CATALOG_CDN_FULL . $filename,
		'thumbUrl' => CATALOG_CDN_THUMBS . $filename,
		'camera' => $camera,
		'gps' => $gps,
		'weather' => $weather,
		'citationPath' => '/cloudberry/archive?filename=' . rawurlencode($filename),
	];

	if ($n % 50 === 0 || $n === $total) {
		fwrite(
			STDERR,
			"[{$n}/{$total}] exif_hits={$exifHits} fetched={$exifFetches} "
			. "exif_empty={$exifFails} weather={$weatherHits} weather_miss={$weatherMiss}\n"
		);
	}
}

if (count($records) !== CATALOG_EXPECTED_COUNT && $limit === null) {
	fwrite(
		STDERR,
		'ERROR: expected ' . CATALOG_EXPECTED_COUNT . ' photos, got ' . count($records) . "\n"
	);
	exit(1);
}

$catalog = [
	'version' => 1,
	'generatedAt' => gmdate('c'),
	'expectedCount' => CATALOG_EXPECTED_COUNT,
	'count' => count($records),
	'cdn' => [
		'full' => CATALOG_CDN_FULL,
		'thumbs' => CATALOG_CDN_THUMBS,
	],
	'cabin' => $cabin,
	'photos' => $records,
];

$payload = json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false) {
	fwrite(STDERR, "ERROR: json_encode failed\n");
	exit(1);
}

if (!is_dir(dirname($outPath)) && !mkdir(dirname($outPath), 0755, true) && !is_dir(dirname($outPath))) {
	fwrite(STDERR, "ERROR: cannot create data/\n");
	exit(1);
}

file_put_contents($outPath, $payload . "\n", LOCK_EX);
$sizeKb = round(strlen($payload) / 1024, 1);
fwrite(STDERR, "Wrote {$outPath} ({$sizeKb} KiB, count=" . count($records) . ")\n");

/**
 * @return list<array{filename: string, date: string, show_date?: string}>
 */
function catalog_load_photo_list(string $cacheDir): array
{
	$files = glob($cacheDir . '/s3-list-*.json') ?: [];
	if ($files === []) {
		throw new RuntimeException('No s3-list-*.json in cache');
	}
	rsort($files);
	$data = json_decode((string) file_get_contents($files[0]), true);
	if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
		throw new RuntimeException('Invalid s3-list cache');
	}
	/** @var list<array{filename: string, date: string, show_date?: string}> $items */
	$items = $data['items'];
	usort($items, static fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

	return $items;
}

/**
 * @return array<string, string>
 */
function catalog_load_exif_dates(string $cacheDir): array
{
	$path = $cacheDir . '/exif-dates.json';
	$data = json_decode((string) file_get_contents($path), true);
	if (!is_array($data) || !isset($data['dates']) || !is_array($data['dates'])) {
		throw new RuntimeException('Invalid exif-dates.json');
	}
	/** @var array<string, string> $dates */
	$dates = $data['dates'];

	return $dates;
}

/**
 * @return array<string, array<string, mixed>>
 */
function catalog_load_weather_hours(string $cacheDir): array
{
	$path = $cacheDir . '/weather-hours.json';
	$data = json_decode((string) file_get_contents($path), true);
	if (!is_array($data) || !isset($data['hours']) || !is_array($data['hours'])) {
		throw new RuntimeException('Invalid weather-hours.json');
	}
	/** @var array<string, array<string, mixed>> $hours */
	$hours = $data['hours'];

	return $hours;
}

/**
 * @return array<string, mixed>
 */
function catalog_fetch_exif_ranged(string $filename): array
{
	if (!function_exists('exif_read_data')) {
		return [];
	}

	$url = CATALOG_CDN_FULL . rawurlencode($filename);
	$tmp = pinchard_exif_tmp_path($filename);
	pinchard_ensure_exif_tmp_dir();

	$ctx = stream_context_create([
		'http' => [
			'method' => 'GET',
			'header' => "Range: bytes=0-" . (CATALOG_EXIF_RANGE_BYTES - 1) . "\r\n"
				. "User-Agent: pinchards.is catalog build\r\n",
			'timeout' => 60,
			'ignore_errors' => true,
		],
		'ssl' => [
			'verify_peer' => true,
			'verify_peer_name' => true,
		],
	]);

	$blob = @file_get_contents($url, false, $ctx);
	if ($blob === false || $blob === '') {
		pinchard_exif_tmp_unlink($filename);

		return [];
	}

	file_put_contents($tmp, $blob);
	$read = @exif_read_data($tmp, null, true);
	pinchard_exif_tmp_unlink($filename);

	return is_array($read) ? $read : [];
}

/**
 * @param array<string, mixed> $exif
 * @return array<string, mixed>
 */
function catalog_camera_from_exif(array $exif): array
{
	$make = trim((string) ($exif['IFD0']['Make'] ?? ''));
	$model = trim((string) ($exif['IFD0']['Model'] ?? ''));
	$focal = $exif['EXIF']['FocalLength'] ?? '';
	$exposure = $exif['EXIF']['ExposureTime'] ?? '';
	$fnumber = $exif['EXIF']['FNumber'] ?? '';
	$iso = $exif['EXIF']['ISOSpeedRatings'] ?? null;
	$width = $exif['COMPUTED']['Width'] ?? $exif['EXIF']['ExifImageWidth'] ?? null;
	$height = $exif['COMPUTED']['Height'] ?? $exif['EXIF']['ExifImageLength'] ?? null;
	$xresolution = $exif['IFD0']['XResolution'] ?? $exif['THUMBNAIL']['XResolution'] ?? '';

	$focalMm = catalog_rational_float($focal);
	$exposureSec = catalog_rational_float($exposure);
	$fNumberVal = catalog_rational_float($fnumber);
	$resolutionPpi = catalog_rational_float($xresolution);

	$exposureDisplay = null;
	if ($exposureSec !== null && $exposureSec > 0) {
		$exposureDisplay = '1/' . (string) (int) round(1 / $exposureSec);
	}

	return [
		'make' => $make !== '' ? $make : null,
		'model' => $model !== '' ? $model : null,
		'focalLengthMm' => $focalMm,
		'exposureSec' => $exposureSec,
		'exposureDisplay' => $exposureDisplay,
		'fNumber' => $fNumberVal,
		'iso' => is_numeric($iso) ? (int) $iso : null,
		'width' => is_numeric($width) ? (int) $width : null,
		'height' => is_numeric($height) ? (int) $height : null,
		'resolutionPpi' => $resolutionPpi,
	];
}

/**
 * @param array<string, mixed> $exif
 * @param array{lat: float, lon: float} $cabin
 * @return array<string, mixed>
 */
function catalog_gps_from_exif(array $exif, array $cabin): array
{
	$hasGps = false;
	$lat = $cabin['lat'];
	$lon = $cabin['lon'];
	$altitudeM = null;

	$defaults = pinchard_cloudberry_gps_defaults();
	$altDefault = catalog_rational_float($defaults['altitude']);

	if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'])
		&& is_array($exif['GPS']['GPSLatitude'])
		&& is_array($exif['GPS']['GPSLongitude'])
	) {
		$latDec = pinchard_gps_to_decimal(
			$exif['GPS']['GPSLatitude'],
			isset($exif['GPS']['GPSLatitudeRef']) ? (string) $exif['GPS']['GPSLatitudeRef'] : null
		);
		$lonDec = pinchard_gps_to_decimal(
			$exif['GPS']['GPSLongitude'],
			isset($exif['GPS']['GPSLongitudeRef']) ? (string) $exif['GPS']['GPSLongitudeRef'] : null
		);
		if ($latDec !== null && $lonDec !== null) {
			$lat = $latDec;
			$lon = $lonDec;
			$hasGps = true;
		}
	}

	if (isset($exif['GPS']['GPSAltitude'])) {
		$altitudeM = catalog_rational_float($exif['GPS']['GPSAltitude']);
	}
	if ($altitudeM === null) {
		$altitudeM = $altDefault;
	}

	return [
		'hasGps' => $hasGps,
		'lat' => $lat,
		'lon' => $lon,
		'altitudeM' => $altitudeM,
		// Drawer-compatible DMS fields (cabin defaults when EXIF GPS absent).
		'latitudeDegree' => $hasGps ? null : $defaults['latitude_degree'],
		'latitudeMin' => $hasGps ? null : $defaults['latitude_min'],
		'latitudeSec' => $hasGps ? null : $defaults['latitude_sec'],
		'longitudeDegree' => $hasGps ? null : $defaults['longitude_degree'],
		'longitudeMin' => $hasGps ? null : $defaults['longitude_min'],
		'longitudeSec' => $hasGps ? null : $defaults['longitude_sec'],
	];
}

/**
 * @param array<string, array<string, mixed>> $hours
 * @return array<string, mixed>|null
 */
function catalog_weather_for_capture(?DateTime $captureDt, array $hours): ?array
{
	$local = pinchard_weather_local_capture($captureDt);
	if ($local === null) {
		return null;
	}
	$key = pinchard_weather_hour_key($local);
	$hour = $hours[$key] ?? null;
	if (!is_array($hour)) {
		return null;
	}

	$temp = isset($hour['temperature_2m']) && is_numeric($hour['temperature_2m'])
		? (float) $hour['temperature_2m'] : null;
	$code = isset($hour['weather_code']) && is_numeric($hour['weather_code'])
		? (int) $hour['weather_code'] : null;
	$windSpeed = isset($hour['wind_speed_10m']) && is_numeric($hour['wind_speed_10m'])
		? (float) $hour['wind_speed_10m'] : null;
	$windDir = isset($hour['wind_direction_10m']) && is_numeric($hour['wind_direction_10m'])
		? (float) $hour['wind_direction_10m'] : null;
	$gusts = isset($hour['wind_gusts_10m']) && is_numeric($hour['wind_gusts_10m'])
		? (float) $hour['wind_gusts_10m'] : null;

	if ($temp === null || $windSpeed === null) {
		return null;
	}

	return [
		'hourKey' => $key,
		'temperatureC' => $temp,
		'weatherCode' => $code,
		'conditionsLabel' => pinchard_weather_wmo_label($code),
		'windSpeedKmh' => $windSpeed,
		'windDirectionDeg' => $windDir,
		'windCompass' => pinchard_weather_compass($windDir),
		'windGustsKmh' => $gusts,
		'precipitationMm' => isset($hour['precipitation']) && is_numeric($hour['precipitation'])
			? (float) $hour['precipitation'] : null,
		'rainMm' => isset($hour['rain']) && is_numeric($hour['rain']) ? (float) $hour['rain'] : null,
		'snowfallCm' => isset($hour['snowfall']) && is_numeric($hour['snowfall'])
			? (float) $hour['snowfall'] : null,
		'cloudCoverPct' => isset($hour['cloud_cover']) && is_numeric($hour['cloud_cover'])
			? (float) $hour['cloud_cover'] : null,
		'relativeHumidityPct' => isset($hour['relative_humidity_2m']) && is_numeric($hour['relative_humidity_2m'])
			? (float) $hour['relative_humidity_2m'] : null,
	];
}

function catalog_rational_float(mixed $value): ?float
{
	if (is_int($value) || is_float($value)) {
		return (float) $value;
	}
	if (!is_string($value) || $value === '') {
		return null;
	}
	if (str_contains($value, '/')) {
		[$a, $b] = array_pad(explode('/', $value, 2), 2, '0');
		$den = (float) $b;
		if ($den === 0.0) {
			return null;
		}

		return (float) $a / $den;
	}
	if (is_numeric($value)) {
		return (float) $value;
	}

	return null;
}
