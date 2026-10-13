<?php
/**
 * Editing Layout Builder page layouts through the OBB REST API.
 *
 * The admin Layout Builder (LB) keeps every page section as HTML presets; one preset per section is
 * active, and "publishing" compiles the active presets into the storefront's templates. The API
 * works on those same presets, so the LB canvas always shows what an API client did.
 *
 * Demonstrates:
 *   1. listing the pages and reading one, with the hash of every preset
 *   2. creating a DRAFT preset with a new text block ("new:<label>" + translations)
 *   3. what an off-rails draft looks like (400 with a list of errors, nothing written)
 *   4. a stale If-Match (412) and a correct one (200)
 *   5. publishing the draft as the active preset - only when $publish is true
 *   6. cleaning up: switching back and deleting the draft
 *
 * See layouts.md for the reference documentation.
 *
 * The client needs the theme_read and theme_write scopes; the generic read/write scopes do NOT
 * include them (tick them for the client at /admin/api-token).
 *
 * These endpoints only exist on v3, while get_token_guzzle.php points $apiClient at /api/v2/, so
 * every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps the host.
 *
 * Safe to re-run as it is: without $publish the storefront never changes, and the draft is deleted
 * at the end. With $publish = true the HOME PAGE OF THE SHOP CHANGES for every visitor until step 6
 * switches it back - use a test shop.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$page            = 'home';
$section         = 'main';
$defaultLanguage = 'da'; // the shop's default language: its text is required for a new text block
$publish         = false; // set to true to make the draft live on the storefront (and back again)

/**
 * Send a JSON request that is allowed to fail, and return [decoded body, status code].
 *
 * A body is always sent - even {} - because a POST without one is rejected by the proxy in front
 * of the shop with an empty 400, before the API runs.
 */
function send($apiClient, $method, $path, $body = null, array $headers = []) {
	$options = ['http_errors' => false, 'headers' => $headers];
	if ($body !== null) {
		$options['json'] = $body;
	}
	$response = $apiClient->request($method, $path, $options);
	return [json_decode((string)$response->getBody(), true), $response->getStatusCode()];
}

/**
 * Find a preset of a section in a page as GET /layouts/{page} returns it.
 * Ids are numbers for core/user presets but names for theme presets: compare as strings.
 */
function findPreset(array $pageLayout, $section, $id) {
	foreach ($pageLayout['sections'][$section]['presets'] as $preset) {
		if ((string)$preset['id'] === (string)$id) {
			return $preset;
		}
	}
	return null;
}


/* ---------------------------------------------------------------------------
 * 1. List the pages, then read one
 *
 *    A New Backend shop answers 409 with a "use" hint - its layouts are edited elsewhere.
 *    A locked page can still get drafts, but publishing it answers 409.
 * ------------------------------------------------------------------------- */

list($pages, $status) = send($apiClient, 'GET', '/api/v3/layouts.json');
if ($status !== 200) {
	exit("cannot list layouts: HTTP $status " . json_encode($pages) . "\n");
}

foreach ($pages as $item) {
	echo sprintf("%-12s locked=%s unpublished=%s active main=%s\n",
		$item['page'],
		$item['locked'] ? 'yes' : 'no',
		$item['has_unpublished_changes'] ? 'yes' : 'no',
		isset($item['sections']['main']['active_preset_id']) ? $item['sections']['main']['active_preset_id'] : '-'
	);
}

list($layout, $status) = send($apiClient, 'GET', "/api/v3/layouts/$page.json");

$previousActiveId = $layout['sections'][$section]['active_preset_id'];
$activePreset     = findPreset($layout, $section, $previousActiveId);

echo "\n$page/$section presets:\n";
foreach ($layout['sections'][$section]['presets'] as $preset) {
	echo sprintf("  %-14s source=%-5s %s hash=%s…\n",
		$preset['id'], $preset['source'], $preset['active'] ? 'ACTIVE' : '      ', substr($preset['hash'], 0, 12));
}


/* ---------------------------------------------------------------------------
 * 2. Create a draft: the active preset plus one new row of text
 *
 *    The HTML is LB's own: a container, a row, a col-md-24 column (the grid has 24 columns),
 *    and a custom-text component whose settings sit in its first child <script class="params">.
 *
 *    "new:usp1" asks the shop to create a text block. Its text comes from translations, the
 *    default language being mandatory. The response maps the label to the block's real number,
 *    which the stored HTML now references.
 * ------------------------------------------------------------------------- */

$newRow = '<div class="container"><div class="row"><div class="col-md-24">'
	. '<div class="custom-text">'
	. '<script class="params" type="application/json">{"block_id": "new:usp1"}</script>'
	. '<p>Free shipping on orders over 500 kr.</p>'
	. '</div>'
	. '</div></div></div>';

$translations = [
	'new:usp1' => [
		$defaultLanguage => '<p>Fri fragt ved køb over 500 kr.</p>',
		'en'             => '<p>Free shipping on orders over 500 kr.</p>',
	],
];

list($draft, $status) = send($apiClient, 'POST', "/api/v3/layouts/$page/$section/presets.json",
	['html' => $activePreset['html'] . $newRow, 'translations' => $translations]);

if ($status === 400 && !empty($draft['errors'])) {
	// A hand-made element the merchant once put into the active preset is tolerated when you PUT
	// over that preset, but a POST has no previous version to compare with, so the copy is refused.
	// Show why, and fall back to a draft holding only the new row.
	echo "copy of the active preset refused:\n  - " . implode("\n  - ", $draft['errors']) . "\n";
	list($draft, $status) = send($apiClient, 'POST', "/api/v3/layouts/$page/$section/presets.json",
		['html' => $newRow, 'translations' => $translations]);
}

if ($status !== 201) {
	// e.g. "Text block "new:usp1" needs its text in the default language "sv"" - fix $defaultLanguage
	exit("draft refused: HTTP $status " . json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

$draftId = $draft['id'];
echo "\ncreated draft preset $draftId, text block " . json_encode($draft['block_ids']) . "\n";
foreach ($draft['warnings'] as $warning) {
	echo "  warning: $warning\n"; // advice only - the draft was saved
}


/* ---------------------------------------------------------------------------
 * 3. An off-rails draft is refused, and nothing is written
 *
 *    col-sm-6 is not an LB column (only col-md-1..24 are). The 400 lists every problem it found,
 *    worded for a human or an agent to act on - do not parse the text.
 * ------------------------------------------------------------------------- */

list($refusal, $status) = send($apiClient, 'POST', "/api/v3/layouts/$page/$section/presets.json", [
	'html' => '<div class="container"><div class="row"><div class="col-sm-6"></div></div></div>',
]);

echo "\noff-rails draft: HTTP $status\n";
foreach (isset($refusal['errors']) ? $refusal['errors'] : [] as $error) {
	echo "  - $error\n";
}


/* ---------------------------------------------------------------------------
 * 4. Change the draft - with If-Match
 *
 *    The hash from the last read says which version you edited. If the preset changed since
 *    (the merchant saved LB, another client wrote it), the shop answers 412 and writes nothing:
 *    read it again and redo the change on the current HTML.
 *
 *    The text of an existing block cannot be changed here - a new text means a new "new:" block.
 * ------------------------------------------------------------------------- */

$changedHtml = str_replace('<div class="container"><div class="row"><div class="col-md-24">',
	'<div class="container"><div class="row"><div class="col-md-24 text-center">', $draft['html']);

list($stale, $status) = send($apiClient, 'PUT', "/api/v3/layouts/$page/$section/presets/$draftId.json",
	['html' => $changedHtml], ['If-Match' => 'a-hash-from-an-older-read']);
echo "\nPUT with a stale If-Match: HTTP $status " . (isset($stale['message']) ? $stale['message'] : '') . "\n";

list($draft, $status) = send($apiClient, 'PUT', "/api/v3/layouts/$page/$section/presets/$draftId.json",
	['html' => $changedHtml], ['If-Match' => $draft['hash']]);
echo "PUT with the current hash: HTTP $status, new hash " . substr($draft['hash'], 0, 12) . "…\n";


/* ---------------------------------------------------------------------------
 * 5. Publish
 *
 *    "active" picks the preset per section; sections left out keep theirs. The shop backs up,
 *    writes, compiles and clears caches exactly like LB's Save - the storefront changes at once.
 *    A section that would not compile is a 400 and nothing is published; a locked page is 409.
 * ------------------------------------------------------------------------- */

if ($publish) {
	list($published, $status) = send($apiClient, 'POST', "/api/v3/layouts/$page/publish.json",
		['active' => [$section => $draftId]]);
	echo "\npublish: HTTP $status, active $section preset "
		. $published['sections'][$section]['active_preset_id']
		. ', unpublished changes: ' . ($published['has_unpublished_changes'] ? 'yes' : 'no') . "\n";
	echo "open the storefront's $page page now to see the new row\n";
} else {
	echo "\nnot publishing (\$publish = false): the storefront is unchanged, LB's canvas shows the draft\n";
}


/* ---------------------------------------------------------------------------
 * 6. Clean up
 *
 *    The active preset cannot be deleted (409), so if the draft went live, publish the previous
 *    preset as active first. Deleting a draft that was never published needs no publish at all.
 * ------------------------------------------------------------------------- */

if ($publish) {
	list($body, $status) = send($apiClient, 'POST', "/api/v3/layouts/$page/publish.json",
		['active' => [$section => $previousActiveId]]);
	echo "\nswitched $section back to preset $previousActiveId: HTTP $status\n";
}

list($body, $status) = send($apiClient, 'DELETE', "/api/v3/layouts/$page/$section/presets/$draftId.json");
echo "deleted draft $draftId: HTTP $status\n"; // 204

// The text block created in step 2 stays in the shop, unused - LB leaves such blocks behind too.
