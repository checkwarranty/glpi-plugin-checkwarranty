#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
while IFS= read -r -d '' source; do
    php -l "$source"
done < <(find . -path './vendor' -prune -o -name '*.php' -print0)
