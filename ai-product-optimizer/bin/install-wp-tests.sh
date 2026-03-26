#!/usr/bin/env bash
# ===========================================================================
# install-wp-tests.sh
#
# Installs the WordPress test suite and a test database for integration tests.
#
# Usage:
#   bin/install-wp-tests.sh <db_name> <db_user> <db_pass> [db_host] [wp_version]
#
# Example:
#   bin/install-wp-tests.sh wp_tests root '' localhost latest
#
# Adapted from the WP Plugin Boilerplate install script.
# ===========================================================================

set -euo pipefail

DB_NAME="${1:-wp_tests}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

download() {
    if [ "$(which curl)" ]; then
        curl -s "$1" > "$2"
    elif [ "$(which wget)" ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ "$WP_VERSION" == "latest" ]]; then
    local_version_url="https://api.wordpress.org/core/version-check/1.7/"
    WP_VERSION=$(download "$local_version_url" - | grep -o '"version":"[^"]*"' | head -1 | sed 's/"version":"\(.*\)"/\1/')
    echo "Latest WordPress version: $WP_VERSION"
fi

WP_TESTS_TAG="tags/$WP_VERSION"

# ---------------------------------------------------------------------------
# Download WordPress core (test runner needs it)
# ---------------------------------------------------------------------------

if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
fi

# ---------------------------------------------------------------------------
# Download WordPress test suite
# ---------------------------------------------------------------------------

if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
    svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
fi

# ---------------------------------------------------------------------------
# wp-tests-config.php
# ---------------------------------------------------------------------------

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/youremptytestdbnamehere/$DB_NAME/"      "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourusernamehere/$DB_USER/"             "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourpasswordhere/$DB_PASS/"             "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|$DB_HOST|"                    "$WP_TESTS_DIR/wp-tests-config.php"
fi

# ---------------------------------------------------------------------------
# Create test database
# ---------------------------------------------------------------------------

EXTRA=""
if [ -n "$DB_PASS" ]; then
    EXTRA="-p$DB_PASS"
fi

mysqladmin create "$DB_NAME" --user="$DB_USER" $EXTRA --host="$DB_HOST" 2>/dev/null || true

echo "WordPress test suite installed at $WP_TESTS_DIR"
echo "Test database '$DB_NAME' ready on $DB_HOST"
