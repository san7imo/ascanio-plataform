#!/usr/bin/env bash
set -euo pipefail

# Optional no-sudo fallback for Ubuntu 22.04 amd64 with the Ondrej PHP PPA.
# apt verifies downloads against metadata from the configured signed repository.
ascanio_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
ascanio_php=${ASCANIO_PHP_BINARY:-php8.4}
ascanio_package_version='8.4.26-1+ubuntu22.04.1+deb.sury.org+1'

if [[ $(env -u PHP_INI_SCAN_DIR "$ascanio_php" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') != '8.4' ]]; then
    echo 'This optional fallback requires PHP 8.4; use system extensions for other versions.' >&2
    exit 1
fi
if [[ $(dpkg --print-architecture) != 'amd64' ]] || ! grep -q '^VERSION_ID="22.04"' /etc/os-release; then
    echo 'This fallback is verified only on Ubuntu 22.04 amd64; use system extensions elsewhere.' >&2
    exit 1
fi

ascanio_extension_dir=$(env -u PHP_INI_SCAN_DIR "$ascanio_php" -r 'echo ini_get("extension_dir");')
ascanio_api=$(basename -- "$ascanio_extension_dir")
if [[ $ascanio_api != '20240924' ]]; then
    echo 'Unsupported PHP extension ABI; no files changed.' >&2
    exit 1
fi
mkdir -p "$ascanio_root/.local/php-packages" "$ascanio_root/.local/php-extensions" "$ascanio_root/.local/php-conf.d"
for ascanio_extension in curl mbstring zip; do
    ascanio_ini="$ascanio_root/.local/php-conf.d/20-$ascanio_extension.ini"
    if env -u PHP_INI_SCAN_DIR "$ascanio_php" -r "exit(extension_loaded('$ascanio_extension') ? 0 : 1);"; then
        # System installations take precedence over an earlier local fallback.
        if [[ -f $ascanio_ini ]]; then
            : > "$ascanio_ini"
        fi
        continue
    fi
    (
        cd "$ascanio_root/.local/php-packages"
        apt download "php8.4-$ascanio_extension=$ascanio_package_version"
    )
    dpkg-deb -x "$ascanio_root/.local/php-packages/php8.4-${ascanio_extension}_${ascanio_package_version}_amd64.deb" "$ascanio_root/.local/php-extensions"
    ascanio_so="$ascanio_root/.local/php-extensions/usr/lib/php/$ascanio_api/$ascanio_extension.so"
    env -u PHP_INI_SCAN_DIR "$ascanio_php" -d "extension=$ascanio_so" -r "exit(extension_loaded('$ascanio_extension') ? 0 : 1);"
    printf 'extension=%s\n' "$ascanio_so" > "$ascanio_ini"
done
printf 'Local PHP extensions ready. Run: source scripts/php-env.sh\n'
