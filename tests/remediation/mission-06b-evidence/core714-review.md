# Scoped WordPress 7.1.3 → 7.1.4 review

Official release/package verification is retained in `wordpress-release-check.json`; no second release lookup or unofficial package was used. Compared actual isolated core files, preserving both installations. Full 49-file inventory is in that JSON; relevant PHP diff is in `core714-relevant.diff`.

- KSES and Interactivity API now prevent bindings from replacing restricted attribute values, especially object data/type. Nodevora renders escaped HTML and registered WooCommerce Store API blocks; it does not depend on object bindings or remove these checks. Its actual Blocks rendering/integration requires the scoped smoke below rather than inferring compatibility from this review alone.
- PHP and JavaScript block parsers narrow comment delimiters to HTML whitespace. Standard serialized WooCommerce/Nodevora blocks use that whitespace; no custom serialization/parser override exists in Nodevora.
- REST comments controller requires edit permission for editorial notes. Nodevora review rewards use product reviews and WordPress comment hooks, not editorial-note permission bypasses; existing capabilities remain required.
- Core updater uses unpredictable unpack directories, preserves index.php and excludes wp-config.php from core copying. Nodevora has no custom updater and this mission did not update the primary core.
- Other inventory changes concern users/privacy lists, sign-up, customizer widgets, navigation/image/category/playlist rendering, connector UI, package hashes/router assets and CA roots. No financial journal schema/identity API is changed by these files. Network delivery is blocked in fixtures, so outbound CA/provider compatibility is not certified.

No Nodevora code change was needed solely for these core differences. Runtime compatibility is limited to the actual scoped tests and environments reported in the final report; this review is not a full core/security audit.
