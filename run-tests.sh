#!/usr/bin/env bash
#
# Run this repo's checks on a machine with no PHP.
#
# usage:
#   ./run-tests.sh                 # everything CI can refuse a push on
#   ./run-tests.sh docs            # no document names a command or path that is gone
#   ./run-tests.sh comments        # the comment convention, on this push's commits
#   ./run-tests.sh secrets         # no credential file in the tree, no retired name
#   ./run-tests.sh gitleaks        # the secret scan that hard-blocks the publish
#   ./run-tests.sh phpunit [args]  # the suite only, arguments passed through
#
# WHY THIS FILE EXISTS. The workflow has five things that can refuse a push and
# this repo had no runner at all, so four of them were reachable only by pushing
# and reading the result, and the fifth only by knowing to type it. That is the
# shape the secret scan was found in elsewhere: a step no local tier runs, whose
# first sign of a refusal is a red publish.
#
# It reproduces the workflow rather than inventing a second way to check
# anything. The three guards are the SAME scripts CI calls, and the scan is the
# same invocation: a local restatement of what a guard looks for is a second
# rule set, free to drift from the one that actually blocks the push.
#
# tests/CiStepsRunnableTest.php derives the guard list from the workflow, so a
# step added there tomorrow reds on the day it lands.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT" || exit 1

if [[ -t 1 ]]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
else
    RED='' GREEN='' CYAN='' BOLD='' NC=''
fi
info() { echo -e "${CYAN}→${NC} $*"; }
ok()   { echo -e "${GREEN}✓${NC} $*"; }
die()  { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

FAILED=()

# The suite needs PHP with intl, which Filament's form components require and
# the stock image lacks. A host PHP is used when there is one; otherwise the
# image is built once and cached, so a machine with only docker can still run
# the tests. The version tracks the upper end of the workflow matrix.
IMAGE="${CONNECTOR_SCHEMA_TEST_IMAGE:-cerase-connector-schema-test-php}"

php_run() {
    if command -v php >/dev/null 2>&1; then
        php "$@"
        return $?
    fi
    command -v docker >/dev/null 2>&1 \
        || die "neither php nor docker is available, so the suite cannot run here"
    if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
        info "building the test runner image ($IMAGE)…"
        docker build -q -t "$IMAGE" - >/dev/null <<'DOCKERFILE'
FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
 && docker-php-ext-install intl zip \
 && rm -rf /var/lib/apt/lists/*
DOCKERFILE
    fi
    docker run --rm -v "$ROOT":/app -w /app "$IMAGE" php "$@"
}

run_docs_parity() {
    info "docs parity"
    if bash "$ROOT/scripts/docs-parity.sh"; then
        ok "docs parity clean"
    else
        FAILED+=("docs-parity")
    fi
}

# The comment convention, against the range this machine is about to push.
#
# CI passes the push range explicitly. Locally the script defaults to the
# merge-base with the default branch, and this repo pushes to main — so the
# merge-base IS HEAD, the diff is empty, and the check reports success for
# having looked at nothing. The upstream range is exactly what a push sends; no
# upstream falls back to the script's own default rather than inventing one.
run_comment_check() {
    info "comment convention"
    local base
    base="$(git -C "$ROOT" rev-parse --verify --quiet '@{upstream}' 2>/dev/null || true)"
    local rc=0
    if [[ -n "$base" ]]; then
        bash "$ROOT/scripts/comment-check.sh" --base "$base" || rc=1
    else
        bash "$ROOT/scripts/comment-check.sh" || rc=1
    fi
    if [[ "$rc" -eq 0 ]]; then
        ok "comment convention clean"
    else
        FAILED+=("comment-check")
    fi
}

run_secrets_guard() {
    info "secrets guard"
    if bash "$ROOT/scripts/secrets-guard.sh"; then
        ok "secrets guard clean"
    else
        FAILED+=("secrets-guard")
    fi
}

# The gate that hard-blocks every push, runnable here.
#
# An absent scanner is a failure with install instructions, never a skip. A
# suite that reports success for having looked at nothing is the same
# invisibility that let a fixture reach history in a sibling repo, in a better
# colour. The version matches the one the workflow installs, so a local verdict
# and CI's are the same claim rather than two.
run_gitleaks() {
    info "gitleaks"
    if ! command -v gitleaks >/dev/null 2>&1; then
        echo "gitleaks is NOT INSTALLED — this machine cannot see the scan that blocks every push." >&2
        echo "  install:  curl -fsSL https://github.com/gitleaks/gitleaks/releases/download/v8.21.2/gitleaks_8.21.2_linux_x64.tar.gz | sudo tar -xz -C /usr/local/bin gitleaks" >&2
        FAILED+=("gitleaks (not installed)")
        return 0
    fi
    if gitleaks detect --source "$ROOT" --redact --verbose --no-banner --exit-code 1; then
        ok "gitleaks clean"
    else
        echo "the history trips the secret scanner, so CI will refuse the push." >&2
        echo "  Fix the code, then acknowledge the published finding by fingerprint in .gitleaksignore." >&2
        echo "  Never relax a rule in a config: that switches the pattern off everywhere and for ever." >&2
        FAILED+=("gitleaks")
    fi
}

run_phpunit() {
    info "phpunit"
    [[ -d "$ROOT/vendor" ]] || die "no vendor/ — run composer install first"
    if php_run vendor/bin/phpunit "$@"; then
        ok "suite green"
    else
        FAILED+=("phpunit")
    fi
}

case "${1:-all}" in
    docs)     run_docs_parity ;;
    comments) run_comment_check ;;
    secrets)  run_secrets_guard ;;
    gitleaks) run_gitleaks ;;
    phpunit)  shift; run_phpunit "$@" ;;
    all)      run_docs_parity; run_comment_check; run_secrets_guard; run_gitleaks; run_phpunit ;;
    *)        die "unknown tier '$1' — see the header of this file" ;;
esac

echo
if [[ ${#FAILED[@]} -eq 0 ]]; then
    echo -e "${GREEN}${BOLD}all green${NC}"
    exit 0
fi
echo -e "${RED}${BOLD}FAILED:${NC} ${FAILED[*]}"
exit 1
