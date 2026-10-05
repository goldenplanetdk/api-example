<?php
/**
 * Volume discount examples for the OBB REST API.
 *
 * A volume discount is a cart-level rule ("Mængderabat"): once the basket reaches a quantity
 * or a subtotal, a discount line is added. It is not a product price, so nothing here touches
 * the catalogue and nothing here is a job - these are plain CRUD calls that answer immediately.
 * Demonstrates:
 *   1. creating a discount with graduations
 *   2. listing them, and the paginated envelope the list comes back in
 *   3. reading one back
 *   4. replacing one, and what PUT does to the fields you leave out
 *   5. narrowing a discount to categories and customer groups
 *   6. the four ways a request gets refused
 *   7. deleting it again
 *
 * See volume_discounts.md for the reference documentation.
 *
 * These endpoints only exist on v3, while get_token_guzzle.php points $apiClient at /api/v2/,
 * so every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps
 * the host.
 *
 * This script creates a discount and deletes it again at the end, so it is safe to re-run.
 * It does not modify anything that already exists in your shop.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$categoryId      = 12; // replace with a category id from your shop
$customerGroupId = 3;  // replace with a customer group id from your shop

/**
 * POST/PUT a body and return [decoded, statusCode], tolerating the 400s we provoke on purpose.
 */
function send($apiClient, $method, $path, array $body) {
	$response = $apiClient->request($method, $path, ['json' => $body, 'http_errors' => false]);
	return [json_decode($response->getBody(), true), $response->getStatusCode()];
}

/**
 * Print the first validation message per field, so the refusals below are readable.
 *
 * Messages come back in the SHOP's language, not English - a Danish shop answers in Danish.
 * Branch on the field name and the status code, never on the message text.
 */
function printErrors($label, $status, $body) {
	echo str_pad($label, 34) . 'HTTP ' . $status;
	foreach (($body['errors'] ?? $body) as $field => $messages) {
		if (is_array($messages)) {
			echo '  ' . $field . ': ' . reset($messages);
		}
	}
	echo "\n";
}


/* ---------------------------------------------------------------------------
 * 1. Create a volume discount
 *
 *    A graduation fires when the cart meets min_quantity AND min_amount - both,
 *    not either. Leave one at 0 to key the tier off the other alone. The two
 *    below therefore mean "10+ items" and "2500+ in subtotal".
 *
 *    "discount" is either a percentage ("5%") or a fixed amount in the default
 *    currency ("200"). It is always a reduction, so it carries no sign: "-5%"
 *    is rejected, not treated as a discount.
 *
 *    Overlapping graduations are fine. The customer is given the best matching
 *    one, so you do not have to keep the tiers disjoint.
 * ------------------------------------------------------------------------- */

list($created, $status) = send($apiClient, 'POST', '/api/v3/volumediscounts.json', [
	'title' => 'API quantity discount',
	'is_enabled' => true,
	'priority' => 0,
	'graduations' => [
		['min_quantity' => 10, 'min_amount' => 0, 'discount' => '5%'],
		['min_quantity' => 0, 'min_amount' => 2500, 'discount' => '200'],
	],
	'exclude_options' => ['special', 'tier'],
]);

$discountId = $created['id'];
echo "created discount $discountId (HTTP $status), graduations: "
	. count($created['graduations']) . "\n";

// Always send is_enabled explicitly. It is a checkbox: omitting it is indistinguishable
// from sending false, so a POST without it creates a DISABLED discount even though a
// discount created in the admin defaults to enabled.


/* ---------------------------------------------------------------------------
 * 2. List them
 *
 *    The list is NOT a bare array. It is a paginated envelope, and the discounts
 *    are under _embedded.resources:
 *
 *      {"total": 3, "page": 1, "limit": 100, "pages": 1,
 *       "_links": {...}, "_embedded": {"resources": [ ... ]}}
 *
 *    Iterating the response directly gets you the four counters, not your data.
 * ------------------------------------------------------------------------- */

$list = json_decode($apiClient->get('/api/v3/volumediscounts.json?limit=100')->getBody(), true);

echo 'list: ' . $list['total'] . " total, page {$list['page']} of {$list['pages']}\n";
foreach ($list['_embedded']['resources'] as $discount) {
	echo '  ' . $discount['id'] . '  ' . $discount['title']
		. '  (' . count($discount['graduations']) . " graduations)\n";
}


/* ---------------------------------------------------------------------------
 * 3. Read one back
 *
 *    The targeting is returned as id arrays - allowed_category_ids and friends -
 *    not as embedded objects, and those are the same names you send.
 *
 *    An EMPTY array means "applies to everything", not "applies to nothing".
 * ------------------------------------------------------------------------- */

$one = json_decode(
	$apiClient->get('/api/v3/volumediscounts/' . $discountId . '.json')->getBody(),
	true
);

echo "read back {$one['id']}: {$one['title']}, "
	. 'categories: ' . (count($one['allowed_category_ids']) ?: 'all') . "\n";


/* ---------------------------------------------------------------------------
 * 4. Replace it - and mind what you leave out
 *
 *    PUT is a full replace, including the collections. Every field you omit is
 *    reset, not preserved:
 *      - omit graduations  -> 400, at least one is required
 *      - omit a targeting array -> that restriction is CLEARED, so the discount
 *        widens to everything rather than keeping what it had
 *
 *    There is no PATCH. To change one field, GET the resource, change that field,
 *    and PUT the whole thing back.
 * ------------------------------------------------------------------------- */

list($replaced) = send($apiClient, 'PUT', '/api/v3/volumediscounts/' . $discountId . '.json', [
	'title' => 'API quantity discount v2',
	'is_enabled' => true,
	'priority' => 0,
	'graduations' => [
		['min_quantity' => 25, 'min_amount' => 0, 'discount' => '8%'],
	],
	// exclude_options and every allowed_*_ids are omitted on purpose - watch them clear.
]);

echo "replaced: graduations " . count($replaced['graduations'])
	. ', exclude_options ' . count($replaced['exclude_options'])
	. " (was 2 - omitting the field cleared it)\n";

// Send priority explicitly too. On releases before the empty_data fix, omitting it stored
// NULL rather than 0, and discounts are ordered by priority DESC with NULLs last - so a PUT
// that just forgot the field silently demoted the discount below every other one.


/* ---------------------------------------------------------------------------
 * 5. Narrow it to categories and customer groups
 *
 *    Send ids; unknown ones are refused rather than skipped.
 *
 *    Do NOT send is_all_categories_allowed and the other is_all_* flags - they
 *    are not part of the payload. The server derives them from whether the list
 *    is empty, which is what keeps "empty means everything" true.
 *
 *    is_exclude_categories flips the list from an allow-list to a deny-list.
 * ------------------------------------------------------------------------- */

list($targeted) = send($apiClient, 'PUT', '/api/v3/volumediscounts/' . $discountId . '.json', [
	'title' => 'API quantity discount v2',
	'is_enabled' => true,
	'priority' => 0,
	'graduations' => [
		['min_quantity' => 25, 'min_amount' => 0, 'discount' => '8%'],
	],
	'allowed_category_ids' => [$categoryId],
	'allowed_customer_group_ids' => [$customerGroupId],
	'is_exclude_categories' => false,
]);

echo 'narrowed to categories ' . json_encode($targeted['allowed_category_ids'])
	. ', groups ' . json_encode($targeted['allowed_customer_group_ids']) . "\n";


/* ---------------------------------------------------------------------------
 * 6. The four refusals
 *
 *    All are 400, all name the offending field, and none of them create anything.
 * ------------------------------------------------------------------------- */

list($body, $status) = send($apiClient, 'POST', '/api/v3/volumediscounts.json', [
	'title' => 'No graduations',
	'graduations' => [],
]);
printErrors('empty graduations', $status, $body);

list($body, $status) = send($apiClient, 'POST', '/api/v3/volumediscounts.json', [
	'title' => 'Bad discount',
	'graduations' => [['min_quantity' => 10, 'discount' => '-5%']],
]);
printErrors('signed discount "-5%"', $status, $body);

list($body, $status) = send($apiClient, 'POST', '/api/v3/volumediscounts.json', [
	'title' => 'Unknown category',
	'graduations' => [['min_quantity' => 10, 'discount' => '5%']],
	'allowed_category_ids' => [987654321],
]);
printErrors('unknown category id', $status, $body);

list($body, $status) = send($apiClient, 'POST', '/api/v3/volumediscounts.json', [
	'title' => 'Extra field',
	'graduations' => [['min_quantity' => 10, 'discount' => '5%']],
	'nonsense' => true,
]);
printErrors('unknown field', $status, $body);


/* ---------------------------------------------------------------------------
 * 7. Delete it
 *
 *    204, empty body. Orders that already used the discount are unaffected -
 *    order lines keep their own copy of what was charged.
 * ------------------------------------------------------------------------- */

$response = $apiClient->delete('/api/v3/volumediscounts/' . $discountId . '.json');
echo 'deleted ' . $discountId . ': HTTP ' . $response->getStatusCode() . "\n";
