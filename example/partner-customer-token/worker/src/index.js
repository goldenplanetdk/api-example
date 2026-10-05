/**
 * Mock of a partner's AI backend for the GP partner-token integration.
 *
 * It does the real work a partner has to do:
 *   - answers the CORS preflight the storefront's fetch triggers
 *   - verifies the ticket's HMAC with the shop's API client secret
 *   - checks exp, shop and client_id, not just the signature
 *   - requires an active newsletter subscription
 *   - counts generations per (shop, customer_id)
 *
 * It does not generate anything — it returns a placeholder image, so the
 * storefront end of the chain can be exercised before the real service exists.
 *
 * Secrets (wrangler secret put): GP_CLIENT_SECRET
 * Vars (wrangler.jsonc):         GP_SHOP, GP_CLIENT_ID, ALLOWED_ORIGIN, DAILY_LIMIT
 * Optional binding:              QUOTA (KV) — without it the quota is not enforced
 */

const CLOCK_SKEW_SECONDS = 60;

export default {
	async fetch(request, env, ctx) {
		const origin = env.ALLOWED_ORIGIN || '*';

		if (request.method === 'OPTIONS') {
			return new Response(null, { status: 204, headers: corsHeaders(origin) });
		}
		if (request.method !== 'POST') {
			return json({ error: 'method_not_allowed' }, 405, origin);
		}

		const ticket = bearer(request.headers.get('authorization'));
		if (!ticket) {
			return json({ error: 'missing_ticket' }, 401, origin);
		}

		let claims;
		try {
			claims = await readTicket(ticket, env.GP_CLIENT_SECRET, env.GP_SHOP, env.GP_CLIENT_ID);
		} catch (err) {
			console.log(JSON.stringify({ event: 'verify_failed', reason: String(err) }));
			return json({ error: 'invalid_ticket' }, 401, origin);
		}
		if (!claims) {
			return json({ error: 'invalid_ticket' }, 401, origin);
		}

		if (claims.newsletter !== true) {
			return json({ error: 'newsletter_required', status: claims.newsletter_status }, 403, origin);
		}

		const limit = Number(env.DAILY_LIMIT || 0);
		if (env.QUOTA && limit > 0) {
			const key = `${claims.shop}:${claims.customer_id}:${new Date().toISOString().slice(0, 10)}`;
			const used = Number((await env.QUOTA.get(key)) || 0);
			if (used >= limit) {
				return json({ error: 'limit_reached', used, limit }, 429, origin);
			}
			ctx.waitUntil(env.QUOTA.put(key, String(used + 1), { expirationTtl: 172800 }));
		}

		let body = {};
		try {
			body = await request.json();
		} catch (err) {
			// A malformed body is not fatal for a mock — the identity part is the point.
		}

		console.log(JSON.stringify({
			event: 'generate',
			shop: claims.shop,
			customer_id: claims.customer_id,
			colour: body && body.colour,
		}));

		return json({
			image_url: 'https://picsum.photos/seed/' + encodeURIComponent(String((body && body.article) || 'yarn')) + '/600/600',
			echo: {
				customer_id: claims.customer_id,
				email: claims.email,
				newsletter_status: claims.newsletter_status,
				colour: body && body.colour,
				expires_in: claims.exp - Math.floor(Date.now() / 1000),
			},
		}, 200, origin);
	},
};

/**
 * Verifies the signature over the payload exactly as it arrived, then the
 * claims. Returns null for anything that does not check out.
 */
async function readTicket(ticket, secret, expectedShop, expectedClientId) {
	const parts = ticket.split('.');
	if (parts.length !== 2) {
		return null;
	}
	const [payload, signature] = parts;

	const key = await crypto.subtle.importKey(
		'raw',
		new TextEncoder().encode(secret),
		{ name: 'HMAC', hash: 'SHA-256' },
		false,
		['sign']
	);
	const mac = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(payload));
	const expected = new TextEncoder().encode(base64url(new Uint8Array(mac)));
	const given = new TextEncoder().encode(signature);

	if (expected.byteLength !== given.byteLength) {
		return null;
	}
	if (!crypto.subtle.timingSafeEqual(expected, given)) {
		return null;
	}

	const claims = JSON.parse(new TextDecoder().decode(base64urlDecode(payload)));

	const now = Math.floor(Date.now() / 1000);
	if (typeof claims.exp !== 'number' || claims.exp + CLOCK_SKEW_SECONDS < now) {
		return null;
	}
	if (claims.shop !== expectedShop) {
		return null;
	}
	if (claims.client_id !== expectedClientId) {
		return null;
	}
	if (!Number.isInteger(claims.customer_id)) {
		return null;
	}

	return claims;
}

function bearer(header) {
	if (!header) {
		return null;
	}
	const m = /^Bearer\s+(.+)$/i.exec(header.trim());
	return m ? m[1] : null;
}

function base64url(bytes) {
	let binary = '';
	for (const b of bytes) {
		binary += String.fromCharCode(b);
	}
	return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function base64urlDecode(s) {
	const padded = s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (s.length % 4)) % 4);
	const binary = atob(padded);
	const out = new Uint8Array(binary.length);
	for (let i = 0; i < binary.length; i++) {
		out[i] = binary.charCodeAt(i);
	}
	return out;
}

function corsHeaders(origin) {
	return {
		'Access-Control-Allow-Origin': origin,
		'Access-Control-Allow-Methods': 'POST, OPTIONS',
		'Access-Control-Allow-Headers': 'authorization, content-type',
		'Access-Control-Max-Age': '86400',
		'Vary': 'Origin',
	};
}

function json(body, status, origin) {
	return new Response(JSON.stringify(body), {
		status,
		headers: { 'Content-Type': 'application/json', ...corsHeaders(origin) },
	});
}
