<?php

declare(strict_types=1);

/**
 * Legacy PHP runtime settings (pre–static archive). Production gallery CDN hosts are
 * Cloudflare R2 via catalog `cdn.full` / `cdn.thumb` (see scripts/build-catalog.php).
 */
return [
	's3_bucket_full' => 'shutter-island',
	's3_bucket_thumbnails' => 'shutter-island-thumbnails',
	'cdn_url_full' => 'https://d3kq73uimqeic8.cloudfront.net/',
	'cdn_url_thumbnails' => 'https://d35wkpjsrmtk40.cloudfront.net/',
];
