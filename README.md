# MS Collection storefront

Custom WordPress/WooCommerce source and bundled design assets for MS Collection.
This repository is a snapshot of the local `wp-content` workspace, not a live-site or database backup.

## Included

- `plugins/ms-core`: storefront glue, multilingual account interface and WooCommerce integration.
- `plugins/ms-secret`: age verification and private access-code functionality using Stripe Identity.
- `plugins/sm-reference-finish`: storefront styling, layout refinements, revisions and image assets.

## Setup

Install WordPress, WooCommerce, Astra, Elementor, Elementor Pro (with an appropriate license), Polylang and WooCommerce Stripe Gateway separately. Copy the included plugins into `wp-content/plugins` and activate the required dependencies first.

Page layouts, menus, translations, product records, prices, shipping zones, tax configuration and Additional CSS stored in the WordPress database must be imported or configured separately. Activation code may apply project-specific revisions; review it on staging before activating against an existing store.

Configure API credentials and mail transport privately in the WordPress admin. Credentials, customer data, email logs, database dumps, backups and third-party plugin packages are deliberately excluded.

The live UPS integration was configured separately through Shipping Live Rates and Access Points for UPS for WooCommerce. Its credentials and settings are not included here. This repository does not establish that live payments, email delivery or shipping packaging are fully configured.
