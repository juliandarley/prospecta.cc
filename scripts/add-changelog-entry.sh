#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/.."

CHANGELOG_FILE="CHANGELOG.md"
[ -f "$CHANGELOG_FILE" ] || {
  echo "# Changelog for \`Prōspecta.cc\`" > "$CHANGELOG_FILE"
  echo "All notable changes to this project will be documented here." >> "$CHANGELOG_FILE"
  echo >> "$CHANGELOG_FILE"
}

HASH=$(git rev-parse --short HEAD)
BRANCH=$(git rev-parse --abbrev-ref HEAD)
STAMP=$(git log -1 --format=%aI)
TITLE=$(git log -1 --format=%s)
BODY=$(git log -1 --format=%b)

TZNAME=$(date -d "$STAMP" '+%Z')
OFFRAW=$(date -d "$STAMP" '+%z')
OFFFMT="${OFFRAW:0:3}:${OFFRAW:3:2}"
DATESTR=$(date -d "$STAMP" '+%Y-%m-%d %H:%M')

ENTRY="#### $DATESTR $TZNAME ($OFFFMT) [$HASH] [$BRANCH]

##### $TITLE

$BODY

"

awk -v entry="$ENTRY" 'NR==1{print;printed=1;next} printed{print ""; print entry; printed=0} {print}' "$CHANGELOG_FILE" > "$CHANGELOG_FILE.tmp"
mv "$CHANGELOG_FILE.tmp" "$CHANGELOG_FILE"




