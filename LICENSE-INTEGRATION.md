# LL Simple Booking - License Integration

This plugin now includes a secure license layer.

## What is already implemented

- Admin page: Simple Booking > License
- Purchase code activation/deactivation handlers
- Encrypted local storage of purchase code
- Signed license state to detect tampering
- Periodic background recheck (WP-Cron)
- Frontend and AJAX feature gating when license is invalid
- Self-hosted license + update client class integrated with WordPress updater

## Self-hosted manager client (new)

The plugin now includes `Self_Hosted_Manager` that automatically:

- validates license against `/llshlm/v1/validate`
- checks updates against `/llshlm/v1/plugin-info`
- injects update package into native WordPress update system

Default endpoints assume your license manager is installed on the same WordPress site.

You can override with filters:

```php
add_filter('llsba_self_hosted_rest_base', static function () {
  return 'https://licenses.example.com/wp-json/llshlm/v1';
});

add_filter('llsba_self_hosted_product_slug', static function () {
  return 'll-simple-booking';
});
```

## Required for production (ThemeForest/Envato)

You must connect a license validation endpoint.

The plugin sends JSON payloads with:

- action: activate | check | deactivate
- purchase_code
- site_url
- domain
- instance_id
- plugin
- version
- platform

Expected JSON response:

```json
{
  "success": true,
  "message": "License valid",
  "data": {
    "license_key": "abc-123",
    "customer": "Buyer Name",
    "source": "envato",
    "valid_until": "2030-12-31"
  }
}
```

## Hook options

### Option 1: Remote API URL

Use filter `llsba_license_api_url` and return your endpoint URL.

### Option 2: Full custom validator

Use filter `llsba_license_validate_payload` and return an array in the response format above.

## Example bootstrap in mu-plugin

Create `wp-content/mu-plugins/llsba-license-bridge.php`:

```php
<?php
add_filter('llsba_license_api_url', static function () {
    return 'https://your-license-server.com/api/license/validate';
});
```

## Security notes

- Never trust only local checks for marketplace licensing.
- Server should verify ThemeForest purchase code against Envato APIs.
- Rate-limit requests and log failed attempts.
- Return minimal data to avoid leaking customer information.
