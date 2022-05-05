#!/usr/bin/env bash

set -euo pipefail

export -n GITHUB_TOKEN

tmp=$(mktemp)
cleanup() { rm -f "$tmp"; }
trap cleanup EXIT

issues_found=0
if ! phan --output-mode json --output "$tmp"; then
	# Phan exits with error status on successful runs with issues found
	issues_found=1
fi

if [[ ! -s "$tmp" ]]; then
	echo "Phan did not run successfully; output file is empty" >&2
	exit 1
fi

export GITHUB_TOKEN
exec php /submit.php "$tmp"
