# OHMS API + Standalone Integration Guide

This guide shows how to:

- Use OHMS API endpoints
- Use API keys (tokens)
- Fetch product/category data for a standalone site
- Auto-build "Buy" flow with cart + checkout links

Bug reports and support: <https://discord.gg/p6Sz3X3YFe>

## 1) API URL format

OHMS API endpoint format:

```text
https://your-ohms-domain.com/index.php?_url=/api/{role}/{class}/{method}
```

Examples:

- Guest products list: `/api/guest/product/get_list`
- Guest categories list: `/api/guest/product/category_get_list`
- Guest cart add item: `/api/guest/cart/add_item`
- Guest cart view: `/api/guest/cart/get`

## 2) Authentication

### Guest API

- No API key required.
- Use for public data like products/categories.

### Client/Admin API key auth

OHMS supports HTTP Basic auth with token login:

- Username: `client` (or `admin`)
- Password: API token

Header format:

```text
Authorization: Basic base64("client:YOUR_API_TOKEN")
```

### Getting client API key

- Client area -> Profile -> API key tab
- Or API methods:
  - `client/client/api_key_get`
  - `client/client/api_key_reset`

## 3) Fetch categories and products

### Categories

```bash
curl "https://your-ohms-domain.com/index.php?_url=/api/guest/product/category_get_list&per_page=100"
```

### Products

```bash
curl "https://your-ohms-domain.com/index.php?_url=/api/guest/product/get_list&per_page=100"
```

### Single product by ID

```bash
curl -X POST "https://your-ohms-domain.com/index.php?_url=/api/guest/product/get" \
  -d "id=1"
```

### Response pattern

API response envelope:

```json
{
  "result": { },
  "error": null
}
```

Always read from `result`.

## 4) Standalone site product cards (example)

```html
<div id="products"></div>
<script>
async function loadProducts() {
  const url = "https://your-ohms-domain.com/index.php?_url=/api/guest/product/get_list&per_page=50";
  const res = await fetch(url);
  const json = await res.json();
  const products = (json.result && json.result.list) || [];

  const root = document.getElementById("products");
  root.innerHTML = products.map(p => `
    <article>
      <h3>${p.title}</h3>
      <p>${p.description || ""}</p>
      <a href="https://your-ohms-domain.com/order/${p.slug}">Buy Now</a>
    </article>
  `).join("");
}
loadProducts();
</script>
```

## 5) Cart flow from standalone site

## Option A (simple/recommended): link to OHMS order page

Use:

```text
https://your-ohms-domain.com/order/{product-slug}
```

Customer configures options in OHMS and proceeds to cart/checkout.

## Option B (auto-add by API): backend bridge

For cross-domain standalone sites, do the cart-add API call from your backend (not browser), then redirect user to:

```text
https://your-ohms-domain.com/cart
```

Use endpoint:

```text
POST /api/guest/cart/add_item
```

Important payload fields:

- `id` (required product ID)
- `period` (optional billing period)
- `quantity` (optional)
- `config_options[...]` (optional)
- `addons[...]` (optional)
- `multiple=1` (optional, keep existing cart items)

Example POST form-data:

```text
id=12
period=1M
quantity=1
multiple=1
```

## 6) Pterodactyl product config notes

For Pterodactyl products, configuration options typically include:

- `config_options[location_id]`
- `config_options[nest_id]`
- `config_options[egg_id]`
- `config_options[hostname]`

Ensure your product is configured with selectable options in admin and that API credentials for ServicePterodactyl are valid.

## 7) Common issues

- `error.message` returned: inspect API class/method and input names.
- Empty product lists: verify product/category status is enabled.
- Cart not persisting across domains: use backend bridge or keep storefront on same domain/session context.
- Auth failures with token: verify Basic auth username is `client` or `admin`.

Bug reports and support: <https://discord.gg/p6Sz3X3YFe>
