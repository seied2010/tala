#!/usr/bin/env bash
set -euo pipefail
# Isolated local test installations only. See docs/REPRODUCING_TESTS.md first.
for spec in 'gc-site1 site1 83' 'gc-site2 site2 85'; do
    read -r container site version <<< "$spec"
    docker exec "$container" php /project/tests/unit.php
    docker exec "$container" wp --path="/project/runtime/$site" eval-file /project/tests/integration.php --use-include
    docker exec "$container" wp --path="/project/runtime/$site" eval-file /project/tests/security.php --use-include
    docker exec "$container" wp --path="/project/runtime/$site" eval-file /project/tests/extended.php --use-include
done
python3 tests/concurrency.py
node tests/browser.cjs
node tests/accessibility.cjs
python3 tests/zip-install.py
python3 tests/restore.py
