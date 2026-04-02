# OHMS Extension Guide

This guide explains how to build and install:

- Custom themes
- Custom modules
- Payment gateways
- Server managers
- Addon modules

Bug reports and support: <https://discord.gg/p6Sz3X3YFe>

## 1) Custom Theme

### Folder structure

Create a new folder under `themes/`:

```text
themes/mytheme/
  manifest.json
  html/
    layout_default.latte
    ...
  assets/
    css/
    js/
  config/
    settings.html
    settings_data.json
```

### Required files

- `manifest.json`: theme metadata
- `html/`: Latte templates used by client/public pages
- `config/settings.html`: admin theme settings form
- `config/settings_data.json`: default preset values

### Install

1. Copy your theme folder to `themes/mytheme`.
2. In admin panel, go to theme selection and switch to your theme.
3. If needed, clear template cache in `data/cache/latte`.

## 2) Custom Module

Use `modules/Example` as the base template.

### Folder structure

```text
modules/MyModule/
  manifest.json
  Service.php
  Api/
    Guest.php
    Client.php
    Admin.php
  Controller/
    Client.php
    Admin.php
  html_client/
  html_admin/
  html_email/   (optional)
```

### `manifest.json` example

```json
{
  "id": "mymodule",
  "type": "mod",
  "name": "My Module",
  "description": "Custom OHMS module",
  "icon_url": "icon.png",
  "version": "1.0.0"
}
```

### Install

1. Copy module to `modules/MyModule`.
2. Go to admin panel and activate/install the module.
3. Access routes from your `Controller/*` registration.

## 3) Payment Gateway

Payment adapters are PHP classes in `library/Payment/Adapter/`.

### Create adapter

1. Create file: `library/Payment/Adapter/MyGateway.php`
2. Class name pattern: `Payment_Adapter_MyGateway`
3. Implement methods similar to existing adapters (`Custom.php`, `Stripe.php`):
   - `public static function getConfig()`
   - `public function getHtml($api_admin, $invoice_id, $subscription)`
   - `public function process($tx)`

### Install

1. Place file in `library/Payment/Adapter/`.
2. Go to admin panel -> payment gateways.
3. Enable and configure your new gateway.

## 4) Server Manager

Server managers are classes in `library/Server/Manager/`.

### Create manager

1. Create file: `library/Server/Manager/MyServer.php`
2. Class name pattern: `Server_Manager_MyServer`
3. Extend `Server_Manager`
4. Implement required methods (see `library/Server/Manager/Custom.php`):
   - `init()`
   - `getForm()`
   - `testConnection()`
   - `createAccount()`, `suspendAccount()`, `unsuspendAccount()`, `cancelAccount()`
   - `changeAccountPackage()` and other account mutators as needed

### Install

1. Place file in `library/Server/Manager/`.
2. Open admin panel -> hosting plans and servers.
3. Create/select server using your manager class.

## 5) Addon Module

An addon module is usually a regular module focused on extending existing flows (hooks/events, UI blocks, API helpers).

### Typical use cases

- Add extra product/order logic
- Add admin utilities
- Add client dashboard widgets
- Add service integrations

### Build approach

1. Copy `modules/Example` as a starter.
2. Rename namespace, manifest ID, class names.
3. Register admin/client routes and API methods.
4. Add templates in `html_admin` / `html_client`.

### Install

1. Copy to `modules/MyAddon`.
2. Activate in admin extensions/module area.

## 6) Packaging and Deployment

### Best practices

- Keep extension IDs unique.theme
- Use semantic versioning.
- Avoid editing core files when possible.
- Document required PHP extensions and external services.

### Recommended release format

- Zip the top-level extension folder:
  - `themes/mytheme.zip` or `modules/MyModule.zip`

## 7) Troubleshooting

- Template errors: clear `data/cache/latte`.
- New classes not detected: verify filename/class naming patterns.
- API not found: verify URL format `/api/{role}/{class}/{method}`.
- Permission issues: check file permissions and web server user access.

Bug reports and support: <https://discord.gg/p6Sz3X3YFe>
