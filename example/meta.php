<?php
/**
 * Product meta field examples for the OBB REST API. Demonstrates:
 *   1. reading a product's meta fields
 *   2. setting one field while keeping the others
 *   3. removing one field
 *
 * A product PUT with "meta" replaces the whole meta list, so changing one field means sending
 * every other field back as well. setMeta() and removeMeta() below do that read-modify-write.
 *
 * See meta.md for the reference documentation.
 *
 * get_token_guzzle.php points $apiClient at /api/v2/, so every call below uses an absolute
 * /api/v3/... path - Guzzle replaces the path and keeps the host.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$productId = 1; // replace with a product id from your shop

/**
 * Read a product's meta fields as a flat list of namespace/key/value entries - the shape a PUT
 * expects. The GET answer groups them by namespace under _embedded.meta.
 */
function getMeta($apiClient, $productId) {
	$product = json_decode($apiClient->get('/api/v3/products/' . $productId . '.json')->getBody(), true);
	$grouped = isset($product['_embedded']['meta']) ? $product['_embedded']['meta'] : [];

	$meta = [];
	foreach ($grouped as $namespace => $fields) {
		foreach ($fields as $key => $value) {
			$meta[] = ['namespace' => (string)$namespace, 'key' => (string)$key, 'value' => $value];
		}
	}
	return $meta;
}

/**
 * Set one meta field, keeping every other field of the product.
 * Namespace and key: lowercase latin letters and digits only.
 */
function setMeta($apiClient, $productId, $namespace, $key, $value) {
	$meta = [];
	foreach (getMeta($apiClient, $productId) as $field) {
		if ($field['namespace'] !== $namespace || $field['key'] !== $key) {
			$meta[] = $field;
		}
	}
	$meta[] = ['namespace' => $namespace, 'key' => $key, 'value' => $value];

	$apiClient->put('/api/v3/products/' . $productId . '.json', ['json' => ['meta' => $meta]]);
}

/**
 * Remove one meta field, keeping every other field of the product.
 */
function removeMeta($apiClient, $productId, $namespace, $key) {
	$meta = [];
	foreach (getMeta($apiClient, $productId) as $field) {
		if ($field['namespace'] !== $namespace || $field['key'] !== $key) {
			$meta[] = $field;
		}
	}

	$apiClient->put('/api/v3/products/' . $productId . '.json', ['json' => ['meta' => $meta]]);
}

function showMeta($apiClient, $productId, $label) {
	echo str_pad($label, 30) . json_encode(getMeta($apiClient, $productId)) . "\n";
}


/* ---------------------------------------------------------------------------
 * 1. Read the current meta fields
 * ------------------------------------------------------------------------- */

showMeta($apiClient, $productId, 'before');


/* ---------------------------------------------------------------------------
 * 2. Set one field, keeping the rest
 *    The storefront shows the new value within a few seconds - no admin save
 *    needed.
 * ------------------------------------------------------------------------- */

setMeta($apiClient, $productId, 'price', 'volholduntil', '2026-12-31');
showMeta($apiClient, $productId, 'after set');


/* ---------------------------------------------------------------------------
 * 3. Remove that field again
 * ------------------------------------------------------------------------- */

removeMeta($apiClient, $productId, 'price', 'volholduntil');
showMeta($apiClient, $productId, 'after remove');
