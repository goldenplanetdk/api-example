<?php
/**
 * Product feature (product level attribute) examples for the OBB REST API.
 *
 * Features are managed purely by title - no attribute or value ids anywhere. Demonstrates:
 *   1. reading the feature list
 *   2. replacing the whole list
 *   3. adding one feature without touching the others
 *   4. removing one feature, and every value of an attribute
 *   5. what ordering does and does not do
 *   6. storefront ordering, which lives on the attribute
 *
 * See features.md for the reference documentation.
 *
 * The endpoint only exists on v3, while get_token_guzzle.php points $apiClient at /api/v2/,
 * so every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps
 * the host.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$productId = 1; // replace with a product id from your shop

/**
 * Print the current feature list of a product and return it decoded.
 */
function showFeatures($apiClient, $productId, $label) {
	$response = $apiClient->get('/api/v3/products/' . $productId . '/features.json')->getBody();
	echo str_pad($label, 52) . $response . "\n";
	return json_decode($response, true);
}


/* ---------------------------------------------------------------------------
 * 1. Read the current features
 * ------------------------------------------------------------------------- */

showFeatures($apiClient, $productId, 'before');


/* ---------------------------------------------------------------------------
 * 2. Replace the whole list
 *    The resulting set is exactly what you send. "Material" and "Origin" are
 *    created on the fly if the shop does not have them yet.
 * ------------------------------------------------------------------------- */

$apiClient->put('/api/v3/products/' . $productId . '/features.json', [
	'json' => [
		['Material' => 'Cotton'],
		['Origin' => 'Denmark'],
	],
]);
showFeatures($apiClient, $productId, 'after replace');


/* ---------------------------------------------------------------------------
 * 3. Add one feature, keeping the existing ones
 *    Adding a feature that is already there is a no-op, so this is safe to retry.
 * ------------------------------------------------------------------------- */

$apiClient->post('/api/v3/products/' . $productId . '/features.json', [
	'json' => ['Warranty' => '2 years'],
]);
showFeatures($apiClient, $productId, 'after add');

$apiClient->post('/api/v3/products/' . $productId . '/features.json', [
	'json' => ['Warranty' => '2 years'],
]);
showFeatures($apiClient, $productId, 'after adding the same one again');


/* ---------------------------------------------------------------------------
 * 4. Remove features
 *    The pairs go in the request body, so titles containing a slash need no
 *    encoding. A null value removes every value of that attribute.
 * ------------------------------------------------------------------------- */

$apiClient->delete('/api/v3/products/' . $productId . '/features.json', [
	'json' => [
		['Origin' => 'Denmark'],
	],
]);
showFeatures($apiClient, $productId, 'after delete');

$apiClient->delete('/api/v3/products/' . $productId . '/features.json', [
	'json' => [
		['Material' => null], // every Material value, whatever they are
	],
]);
showFeatures($apiClient, $productId, 'after deleting all Material values');


/* ---------------------------------------------------------------------------
 * 5. Ordering
 *
 *    Reordering the array on a normal PUT does nothing: a feature that survives
 *    the request keeps its database row, and therefore its position. That keeps
 *    feature ids stable for external systems.
 *
 *    Pass reorder=1 to rebuild the rows in payload order. Every feature id
 *    changes as a result. The rebuild is transactional, so a rejected request
 *    leaves the previous features in place rather than wiping them.
 * ------------------------------------------------------------------------- */

// Clear first, so the baseline really is payload order rather than whatever survived the
// steps above - a feature that survives a PUT keeps its row, and therefore its position.
$apiClient->put('/api/v3/products/' . $productId . '/features.json', ['json' => []]);
$apiClient->put('/api/v3/products/' . $productId . '/features.json', [
	'json' => [
		['Material' => 'Cotton'],
		['Warranty' => '2 years'],
	],
]);
showFeatures($apiClient, $productId, 'baseline order');

$apiClient->put('/api/v3/products/' . $productId . '/features.json', [
	'json' => [
		['Warranty' => '2 years'],
		['Material' => 'Cotton'],
	],
]);
showFeatures($apiClient, $productId, 'reversed payload, plain PUT - unchanged');

$apiClient->put('/api/v3/products/' . $productId . '/features.json?reorder=1', [
	'json' => [
		['Warranty' => '2 years'],
		['Material' => 'Cotton'],
	],
]);
showFeatures($apiClient, $productId, 'reversed payload, reorder=1 - applied');


/* ---------------------------------------------------------------------------
 * 6. Storefront order
 *    The shop sorts features by the attribute's own sorting, not by anything
 *    above. It is a property of the attribute, so it reorders that feature on
 *    every product using it.
 * ------------------------------------------------------------------------- */

$product = json_decode($apiClient->get('/api/v3/products/' . $productId . '.json')->getBody(), true);
$sorting = 10;
foreach ($product['_embedded']['features'] as $feature) {
	$attribute = $feature['value']['_links']['attribute'];
	echo 'attribute ' . $attribute['id'] . ' "' . $attribute['title'] . '" -> sorting ' . $sorting . "\n";

	$apiClient->put('/api/v3/attributes/' . $attribute['id'] . '.json', [
		'json' => ['sorting' => $sorting],
	]);
	$sorting += 10;
}
