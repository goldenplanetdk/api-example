<?php
/**
 * Uploading banner, campaign and design images through the OBB REST API.
 *
 * POST /api/v3/media.json stores an image under /images/media/ and answers with a path that works
 * as-is in category data, block HTML, a Layout Builder image element or a Style Editor background.
 * The image can arrive as a multipart file, as a URL the shop downloads, or as base64 in JSON.
 *
 * Demonstrates:
 *   1. uploading a file (multipart) into a folder
 *   2. uploading the same image again: 200 and the same path, nothing written
 *   3. sending it as base64 (a data: URI) - same validation, same answer
 *   4. refusals: a text file (415) and an internal URL (400, never fetched)
 *   5. importing a public URL - only when $imageUrl is set
 *   6. listing the folder and deleting the image
 *
 * See media.md for the reference documentation.
 *
 * Requires a shop release that includes GPP-15444, and a client with the media_write and
 * media_read scopes (the generic write/read scopes include them).
 *
 * This endpoint only exists on v3, while get_token_guzzle.php points $apiClient at /api/v2/, so
 * every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps the host.
 *
 * Safe to re-run: the image lands in its own folder and is deleted at the end.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$folder   = 'api-example';
$imageUrl = null; // e.g. 'https://example.org/banner.jpg' - any public JPEG/PNG/GIF/WebP up to 2 MB

// A 16x9 PNG, embedded so the example needs no image file. Any JPEG/PNG/GIF/WebP up to 2 MB works.
$imageBytes = base64_decode(
	'iVBORw0KGgoAAAANSUhEUgAAABAAAAAJCAIAAAC0SDtlAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAJ0lEQVQY02OUi1rAQApgYiAR'
	. 'kKyBBULdWRBFUKlKwjK6OIlx8IUSAMESBUN1ZyzRAAAAAElFTkSuQmCC'
);

/**
 * Send a request that is allowed to fail, and return [decoded body, status code].
 */
function send($apiClient, $method, $path, array $options = []) {
	$response = $apiClient->request($method, $path, $options + ['http_errors' => false]);
	return [json_decode((string)$response->getBody(), true), $response->getStatusCode()];
}

/**
 * One line per answer: the status and either the stored image or the error message.
 */
function show($label, $body, $status) {
	if (isset($body['path'])) {
		echo sprintf("%-28s HTTP %d  %s  %dx%d  %s, %d bytes\n",
			$label, $status, $body['path'], $body['width'], $body['height'], $body['mime'], $body['bytes']);
	} else {
		echo sprintf("%-28s HTTP %d  %s\n", $label, $status, isset($body['message']) ? $body['message'] : json_encode($body));
	}
}


/* ---------------------------------------------------------------------------
 * 1. Upload a file
 *
 *    multipart/form-data with the image in the part "file". "filename" is the readable part of
 *    the stored name; the extension always comes from the detected type.
 *
 *    201 = stored in full and already served at "url". width/height are the displayed size:
 *    pass them on to a Layout Builder image element so nothing is cropped.
 * ------------------------------------------------------------------------- */

list($image, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
	'multipart' => [
		['name' => 'file', 'contents' => $imageBytes, 'filename' => 'Hero Banner.png'],
		['name' => 'folder', 'contents' => $folder],
	],
]);
show('upload (multipart)', $image, $status);

if (!isset($image['path'])) {
	exit("upload refused - nothing else to show\n");
}

echo "use it as: <img src=\"{$image['path']}\" width=\"{$image['width']}\" height=\"{$image['height']}\">\n";
echo "served at: {$image['url']}\n\n";


/* ---------------------------------------------------------------------------
 * 2. The same image again
 *
 *    The stored name contains a hash of the content, so the same bytes under the same filename
 *    answer 200 with the image already stored. A timeout can therefore simply be retried. Other
 *    bytes would get another path: nothing is ever overwritten.
 * ------------------------------------------------------------------------- */

list($again, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
	'multipart' => [
		['name' => 'file', 'contents' => $imageBytes, 'filename' => 'Hero Banner.png'],
		['name' => 'folder', 'contents' => $folder],
	],
]);
show('same image again', $again, $status);


/* ---------------------------------------------------------------------------
 * 3. The same image as base64
 *
 *    JSON "attachment", plain base64 or a data: URI (its media type label is ignored - the type is
 *    read from the bytes). Same validation and same answer as a file: here 200 and the same path,
 *    because the bytes and the filename are the same.
 * ------------------------------------------------------------------------- */

list($b64, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
	'json' => [
		'attachment' => 'data:image/png;base64,' . base64_encode($imageBytes),
		'filename'   => 'Hero Banner.png',
		'folder'     => $folder,
	],
]);
show('same image as base64', $b64, $status);


/* ---------------------------------------------------------------------------
 * 4. Refusals
 *
 *    Every refusal stores nothing and says what to change. SVG, text, truncated files and anything
 *    over 2 MB are refused; URL imports never reach internal addresses (here the cloud metadata
 *    address - refused before any connection is made).
 * ------------------------------------------------------------------------- */

list($body, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
	'json' => ['attachment' => base64_encode("This is not an image.\n"), 'folder' => $folder],
]);
show('a text file', $body, $status); // 415 Detected text/plain; allowed: ...

list($body, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
	'json' => ['src' => 'http://169.254.169.254/latest/meta-data/', 'folder' => $folder],
]);
show('an internal URL', $body, $status); // 400 src host resolves to a non-public address


/* ---------------------------------------------------------------------------
 * 5. Import a URL
 *
 *    The shop downloads the image (http/https on 80/443, public addresses only, at most 3
 *    redirects, 20 s, 2 MB) and checks it like an upload. Without "filename" the name comes from
 *    the URL's last path segment.
 * ------------------------------------------------------------------------- */

$imported = null;
if ($imageUrl) {
	list($imported, $status) = send($apiClient, 'POST', '/api/v3/media.json', [
		'json' => ['src' => $imageUrl, 'folder' => $folder],
	]);
	show('URL import', $imported, $status);
} else {
	echo "URL import skipped (set \$imageUrl to a public image URL)\n";
}


/* ---------------------------------------------------------------------------
 * 6. List the folder, then delete what this script stored
 *
 *    The list is a v3 page: the images are under _embedded.resources, newest first.
 *
 *    DELETE takes the path in the query. The extra "confirm=1" AFTER it keeps the URL from ending
 *    in ".png": some shops sit behind a cache that would otherwise take the request for a static
 *    file and answer it from the cache without deleting anything. 204 = deleted, 404 = not there.
 * ------------------------------------------------------------------------- */

list($list, $status) = send($apiClient, 'GET', '/api/v3/media.json?folder=' . $folder);
echo "\n{$list['total']} image(s) in /images/media/$folder:\n";
foreach ($list['_embedded']['resources'] as $item) {
	echo "  {$item['path']}  {$item['width']}x{$item['height']}  {$item['modified_at']}\n";
}

$paths = [$image['path']];
if (isset($imported['path'])) {
	$paths[] = $imported['path'];
}

foreach ($paths as $path) {
	list($body, $status) = send($apiClient, 'DELETE', '/api/v3/media.json?path=' . rawurlencode($path) . '&confirm=1');
	echo "delete $path: HTTP $status\n"; // 204
}

list($body, $status) = send($apiClient, 'DELETE', '/api/v3/media.json?path=' . rawurlencode($image['path']) . '&confirm=1');
show('delete it again', $body, $status); // 404 ... does not exist

// Deleting does not check whether a page still shows the image: remove references first.
