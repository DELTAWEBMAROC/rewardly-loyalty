#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
run_wp() {
    if [ -n "${NODEVORA_WP_CLI_PHAR:-}" ]; then
        "${NODEVORA_PHP:-php}" "$NODEVORA_WP_CLI_PHAR" "$@"
    else
        wp "$@"
    fi
}
run_wp i18n make-pot . languages/nodevora-customer-rewards-for-woocommerce.pot --domain=nodevora-customer-rewards-for-woocommerce --exclude=tests,docs,tools,.git --skip-audit
msgmerge --update --no-fuzzy-matching --backup=none languages/nodevora-customer-rewards-for-woocommerce-fr_FR.po languages/nodevora-customer-rewards-for-woocommerce.pot
msgfmt --check --check-format --statistics -o languages/nodevora-customer-rewards-for-woocommerce-fr_FR.mo languages/nodevora-customer-rewards-for-woocommerce-fr_FR.po
run_wp i18n make-json languages --no-purge
