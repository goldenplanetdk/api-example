# Media: banner, campaign and design images

Put an image on the shop — a banner, a campaign photo, a background, a logo — and get back a
path you can use anywhere on the storefront: in category data, in a text or HTML block, in a Layout
Builder image element or as a Style Editor background. v3 only. Product images have their own
endpoint, see [product_image.md](product_image.md).

> Requires a shop release that includes GPP-15444. On older releases these URLs answer `404`.

| What | Endpoint |
|---|---|
| Upload a file, import a URL or send base64 | `POST /api/v3/media.json` |
| List the images | `GET /api/v3/media.json?folder=&page=&limit=` |
| Delete one | `DELETE /api/v3/media.json?path=/images/media/…&confirm=1` |

Scopes: `media_write` to upload and delete, `media_read` to list. The generic `write` / `read`
scopes include them, so a client that can already change products can upload images too.

## Uploading

Send **exactly one** of three things. All three are validated the same way and give the same
answer.

**A file** (`multipart/form-data`, the part is called `file`):

```bash
curl -XPOST 'http://SHOP_DOMAIN/api/v3/media.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -F 'file=@hero.jpg' -F 'folder=campaigns'
```

**A URL** for the shop to download (JSON `src`):

```bash
curl -XPOST 'http://SHOP_DOMAIN/api/v3/media.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' -H 'Content-Type: application/json' \
  -d '{"src": "https://studio.example.com/hero.jpg", "folder": "campaigns"}'
```

**Base64** (JSON `attachment`, plain base64 or a `data:` URI):

```bash
curl -XPOST 'http://SHOP_DOMAIN/api/v3/media.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' -H 'Content-Type: application/json' \
  -d '{"attachment": "data:image/png;base64,iVBORw0KGgo…", "filename": "hero.png"}'
```

Base64 is about a third larger on the wire and must be built in memory, so prefer a file or a URL
for photos. It is handy for small images an agent already holds as text.

Optional fields, for every method:

| Field | Meaning |
|---|---|
| `filename` | The readable part of the stored name. Defaults to the uploaded file's name or the URL's last segment. |
| `folder` | One folder level, `^[a-z0-9][a-z0-9-]{0,39}$`, e.g. `campaigns`. Created when needed. |

### The answer

```json
HTTP/1.1 201 Created

{
  "path": "/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg",
  "url": "https://SHOP_DOMAIN/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg",
  "folder": "campaigns",
  "mime": "image/jpeg",
  "bytes": 184233,
  "width": 1600,
  "height": 1067,
  "modified_at": "2026-10-13T12:00:00+02:00"
}
```

- `201` means the file is stored in full and is already served at `url`.
- `width` / `height` are the size the image is **displayed** at (a JPEG that its EXIF data says is
  rotated reports its rotated size). Use them for a Layout Builder image element, so the image box
  has the right proportions and nothing is cropped.
- `folder` is `""` for images in the root.

### Names never change meaning

The stored name is `<filename as a slug>-<first 12 hex chars of the file's sha256>.<type>`. The type
extension comes from the image itself, never from the name you send.

- **Retrying is safe.** The same bytes under the same `filename` answer `200` with the stored image
  and write nothing. A timeout or a lost answer can simply be repeated.
- **Nothing is ever overwritten.** Different bytes always get a different path, so an image a page
  already shows can never be replaced behind its back, and browsers never show a stale copy.
- To "replace" an image, upload the new one and point the page at the new path.

### Using the path

Use `path` as it is — it starts at the shop root:

| Where | How |
|---|---|
| Category / page data, block HTML | `<img src="/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg" alt="…">` |
| Layout Builder image element (`obb-image`) | `file` = the path, `width` / `height` = the values from the answer |
| Style Editor background | `url('/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg')` |

The file is served **exactly as uploaded**: no resizing, no recompression, no metadata stripping.
Resize photos before uploading (a full-width hero is 1920–2560 px wide and 150–500 KB), and
**remove GPS and other EXIF data** you do not want published.

## What is accepted

| Rule | Refused with |
|---|---|
| exactly one of `file`, `src`, `attachment` | `400 Send exactly one of: file (multipart), src, attachment` |
| not empty | `400 Image is empty (0 bytes)` |
| at most **2 MB** (2,097,152 bytes) of image data, for every method | `413 Image is 4.8 MB (5,033,165 bytes); the limit is 2 MB (2,097,152 bytes). Resize to max 2560 px wide and/or recompress (JPEG/WebP q80), then retry.` |
| JPEG, PNG, GIF or WebP, recognised from the content | `415 Detected text/plain; allowed: image/jpeg, image/png, image/gif, image/webp` |
| at most 10000 px per side and 40 megapixels | `400 Image is 12000x9000 px; allowed up to 10000 px per side and 40 megapixels` |
| the whole image decodes (no truncated file) | `400 Image data is corrupt or truncated: …` |
| a valid `folder` | `400 folder must match ^[a-z0-9][a-z0-9-]{0,39}$` |

- The type is read from the bytes. The file name and the `Content-Type` you send are ignored.
- **SVG is refused**: it can carry scripts.
- A refused upload stores nothing.
- Every message says what to change. Branch on the status code, not on the text.
- A request body far beyond the limit is stopped by the web server first, which answers `413`
  with its own HTML page instead of this JSON.

## Importing a URL

The shop downloads `src` itself, so it only goes where any visitor's browser could go:

- `http` or `https`, on port 80 or 443, without `user:password@`;
- an ASCII host name or an IP address — send international domain names in punycode;
- every address the host resolves to must be on the public internet: loopback, private networks,
  link-local addresses (cloud metadata services) and the like are refused, also when a public URL
  **redirects** there;
- at most 3 redirects, 5 s to connect, 20 s in total, and the download stops at 2 MB.

| Refused because | Message |
|---|---|
| scheme, port or credentials | `400 src must be an http(s) URL on port 80 or 443 without credentials` |
| host name not ASCII | `400 src host must be an ASCII host name or an IP address` |
| host resolves to a private address | `400 src host resolves to a non-public address` |
| host does not resolve | `400 src host does not resolve` |
| the server answered something else than 200 | `400 src returned HTTP 404` |
| too slow | `400 src timed out` |

The downloaded file is then checked exactly like an uploaded one.

On development and test shops whose own host name resolves to a private address, importing a URL
**of the same shop** is refused. A file that is already on the shop needs no import — use its path.

## Listing

```bash
curl 'http://SHOP_DOMAIN/api/v3/media.json?folder=campaigns' -H 'Authorization: Bearer ACCESS_TOKEN'
```

```json
{
  "page": 1, "limit": 100, "pages": 1, "total": 1,
  "_links": {"self": {"href": "/api/v3/media.json?folder=campaigns&page=1&limit=100"}, "…": "…"},
  "_embedded": {
    "resources": [
      {"path": "/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg", "url": "https://SHOP_DOMAIN/…",
       "folder": "campaigns", "mime": "image/jpeg", "bytes": 184233, "width": 1600, "height": 1067,
       "modified_at": "2026-10-13T12:00:00+02:00"}
    ]
  }
}
```

- Newest first. Without `folder`, the root and every folder are listed.
- The images are under `_embedded.resources`, as in every v3 list. `limit` is at most 100.
- Images a merchant put into `/images/media` with the admin Media Manager are listed too.

## Deleting

```bash
curl -XDELETE 'http://SHOP_DOMAIN/api/v3/media.json?path=/images/media/campaigns/hero-banner-3f1cf32c4ac1.jpg&confirm=1' \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

- `204` = deleted. `404` = no such file. `400` = the path is not an image of `/images/media`
  (nothing outside it can be deleted through this API).
- **Put another parameter after `path`** (`&confirm=1`). Some shops sit behind a cache that treats
  a URL ending in `.jpg` or `.png` as a static file — even when the ending is in the query string —
  and answers the DELETE with a cached page while nothing is deleted.
- Deleting does **not** check whether a category, block or layout still shows the image. A page
  that still references it shows a broken image.

## Errors

| Status | When |
|---|---|
| 400 | no or several sources, bad base64, bad `folder`, corrupt image, refused or failed `src`, bad `path` |
| 403 | missing scope — the message names it: `Missing scope: media_write` |
| 404 | `DELETE` of a file that does not exist |
| 409 | a different image already has this exact name (practically never: the name holds a hash) |
| 413 | over 2 MB |
| 415 | not a JPEG, PNG, GIF or WebP image |

## Example

[media.php](media.php) — uploads one image as a file, uploads it again (200, same path), sends it as
base64, shows a refused text file and a refused internal URL, optionally imports a public URL,
lists the folder and deletes the image.
