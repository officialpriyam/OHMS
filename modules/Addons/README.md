# Addons workspace

This module is a small admin workspace for two jobs:

1. Surface product add-ons from the existing Product module in one place.
2. Show the file structure needed for a custom admin addon module.

## Minimal custom addon module structure

```text
modules/
  Myaddon/
    manifest.json
    Controller/
      Admin.php
    html_admin/
      mod_myaddon_settings.latte
```

## Minimal manifest example

```json
{
  "id": "myaddon",
  "type": "mod",
  "name": "My Addon",
  "description": "Custom admin addon example",
  "version": "1.0.0"
}
```

## Notes

- `mod_myaddon_settings.latte` is what makes the module show up as a configurable settings area.
- `Controller/Admin.php` can register routes such as `/myaddon` and add a submenu entry under `system`.
- If the module is not core, activate it from the Extensions screen first.
