=== Syvo Business Directory ===
Contributors: syvo
Requires at least: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
Text Domain: syvo-business-directory

Production-ready local business directory and local search engine for Syvo.

== Description ==

Syvo Business Directory adds a standalone business directory with:

* Frontend account and business registration.
* Hierarchical categories and services.
* Province / county / city location database.
* Map selection and reverse geocoding abstraction.
* Business owner dashboard and object-level authorization.
* Search and local relevance ordering.
* Automatic Category + City virtual SEO pages under /hiper/.
* Async AI classification, page SEO copy and image ALT assistance.
* Rank Math/Elementor compatibility without hard dependencies.
* Security, privacy and diagnostics tooling.

== Shortcodes ==

[syvo_business_register]
[syvo_business_login]
[syvo_business_dashboard]
[syvo_business_search]
[syvo_directory]
[syvo_business_profile]
[syvo_directory_landing]

== AI ==

On WordPress 7.0+, the plugin uses the WordPress AI Client when a provider is configured by the site owner. The plugin never stores consumer ChatGPT/Claude login sessions and never exposes API credentials in frontend JavaScript.

== Data provenance ==

The included location manifest pins a 1402 administrative snapshot whose provenance identifies the Statistical Center of Iran as the underlying source. The dataset is imported to the local Syvo table so frontend requests do not depend on an external location API.

== Uninstall ==

Real business data is retained by default. Destructive cleanup requires the explicit plugin option `syvo_bd_delete_data_on_uninstall` to be enabled.
