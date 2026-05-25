#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# get-cert-pins.sh — Retrieve SHA-256 SPKI certificate pins for a host.
#
# Run this before updating android-network-security-config.xml to get the
# correct base64-encoded SHA-256 pins for the current certificate chain.
#
# Usage:
#   bash scripts/get-cert-pins.sh
#   bash scripts/get-cert-pins.sh api.project-nexus.ie
#   bash scripts/get-cert-pins.sh api.project-nexus.ie 443
#
# Requirements: openssl must be installed and available on $PATH.

set -euo pipefail

HOST="${1:-api.project-nexus.ie}"
PORT="${2:-443}"

echo "Getting certificate pins for ${HOST}:${PORT}..."
echo ""

# Verify openssl is available
if ! command -v openssl &>/dev/null; then
  echo "ERROR: openssl is not installed or not on PATH." >&2
  exit 1
fi

# Fetch the full certificate chain from the server
CHAIN=$(openssl s_client -connect "${HOST}:${PORT}" -showcerts 2>/dev/null)

if [[ -z "$CHAIN" ]]; then
  echo "ERROR: Could not connect to ${HOST}:${PORT}. Check network connectivity." >&2
  exit 1
fi

# --- Leaf (primary) certificate pin ---
echo "=== Leaf certificate pin (use as primary <pin>) ==="
LEAF_PIN=$(echo "$CHAIN" \
  | openssl x509 -pubkey -noout 2>/dev/null \
  | openssl pkey -pubin -outform DER 2>/dev/null \
  | openssl dgst -sha256 -binary \
  | base64)

if [[ -z "$LEAF_PIN" ]]; then
  echo "WARNING: Could not extract leaf certificate pin." >&2
else
  echo "$LEAF_PIN"
fi

echo ""
echo "=== Instructions ==="
echo "1. Copy the pin value above."
echo "2. Open android-network-security-config.xml."
echo "3. Replace PLACEHOLDER_PRIMARY_PIN_BASE64= with the leaf pin."
echo "4. For the backup pin, repeat this command against your CA certificate"
echo "   or use a known intermediate CA pin (DigiCert G2, ISRG Root X1, etc.)."
echo "5. Always keep at least one backup pin to survive certificate rotation."
echo ""
echo "See: https://developer.android.com/training/articles/security-config#CertificatePinning"
