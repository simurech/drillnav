# Reply to WP.org Plugin Review
**Review ID:** AUTOPREREVIEW ❗OWN-LIC drillnav-drilldown-navigation/simurech/29May26/T1

---

Hi,

thank you for the detailed review. I have addressed all points below.

---

## Account / Ownership

The plugin was accidentally submitted under my secondary account **simurech**. My primary WordPress.org developer account is **simon61** (https://profiles.wordpress.org/simon61/), which I use for all my plugins.

Could you please transfer the plugin submission to the account **simon61**? I will not resubmit under the new account, as your instructions ask me to request a transfer instead.

The `Contributors` field in `readme.txt` already lists `simon61`, which will match correctly once the transfer is done.

---

## Trialware / Locked Features (Guideline 5)

I have resolved this by using Freemius's proper mechanism for separating free and premium code:

**1. `@fs_premium_only` annotation** added to the plugin header in `drillnav-drilldown-navigation.php`. This tells Freemius's deployment tool to exclude the listed files entirely from the free (WordPress.org) build:

```
* @fs_premium_only /includes/integrations/class-woocommerce.php, /includes/admin/class-itemmeta.php, /includes/integrations/class-taxonomy.php, /includes/integrations/class-menu.php
```

These files are completely absent from the free ZIP.

**2. Replaced `can_use_premium_code__premium_only()` with `is__premium_only()`** throughout all plugin files. `is__premium_only()` is Freemius's standard method for marking premium build code — it returns `true` only when the premium version of the plugin is running, and has no relation to license key checks. In the free version, it always returns `false`. I used this same pattern in my other plugin (libre-bite), which was accepted on WordPress.org.

The build script for the WP.org submission now also explicitly excludes the Pro-only files via `rsync --exclude` to ensure they are absent regardless of how the ZIP is built.

---

## Inline `<script>` Tag (class-admin.php)

Fixed. The JavaScript for dismissing the onboarding notice has been moved to `assets/js/onboarding.js`. It is now registered and enqueued via `wp_enqueue_script()` on all admin pages (only when the notice has not been dismissed yet), with the AJAX URL and nonce passed via `wp_localize_script()`. The raw `<script>` tag has been removed.

---

## REST API `permission_callback`

The `/children` endpoint is intentionally public — it returns child pages of public hierarchical post types, which is the same data accessible to any visitor on the front end.

However, I have tightened the validation: the endpoint now checks `$pto->public` in addition to `$pto->hierarchical`, ensuring that non-public custom post types cannot be queried. This addresses the AI-identified concern.

---

## Sanitization for `register_setting()`

This is already correctly implemented. The third argument already includes a `sanitize_callback`:

```php
register_setting(
    'drillnav_settings_group',
    'drillnav_settings',
    array(
        'sanitize_callback' => array( $this->settings, 'sanitize' ),
        'default'           => Settings::defaults(),
    )
);
```

No change was needed here.

---

## GPL / Licensing

All plugin code is GPL-2.0-or-later. The Freemius SDK is GPL-compatible. No third-party code with conflicting licenses is included.

---

I have tested the updated plugin and confirmed it activates without errors. Please let me know if you need any clarification.

Best regards,
Simon Urech (simon61)
simon@urech.dev
