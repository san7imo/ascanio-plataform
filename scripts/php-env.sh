# Source inside this Git checkout; optional local extensions are for PHP 8.4.
# Preserve the PHP binary's compiled scan directory instead of assuming /etc.
if ascanio_php_root=$(git rev-parse --show-toplevel 2>/dev/null) && [ -d "$ascanio_php_root/.local/php-conf.d" ]; then
    ascanio_php_binary=${ASCANIO_PHP_BINARY:-php8.4}
    if [ "$(env -u PHP_INI_SCAN_DIR "$ascanio_php_binary" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" != '8.4' ]; then
        echo 'Local extensions require PHP 8.4; use system extensions for other versions.' >&2
        return 1
    fi
    ascanio_php_scan=$(env -u PHP_INI_SCAN_DIR "$ascanio_php_binary" -r 'echo PHP_CONFIG_FILE_SCAN_DIR;') || return 1
    export PHP_INI_SCAN_DIR="$ascanio_php_scan:$ascanio_php_root/.local/php-conf.d"
fi
unset ascanio_php_root ascanio_php_binary ascanio_php_scan
