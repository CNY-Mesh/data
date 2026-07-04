#!/usr/bin/env bash
set -euo pipefail
OUT_DIR="$(cd "$(dirname "$0")"/..; pwd)/src/Protos"
mkdir -p "$OUT_DIR"
if [ ! -d /tmp/meshtastic-protobufs ]; then
  git clone https://github.com/meshtastic/protobufs.git /tmp/meshtastic-protobufs
else
  (cd /tmp/meshtastic-protobufs && git pull --ff-only)
fi
cd /tmp/meshtastic-protobufs
# Generate only files without proto3 optional fields
echo "Generating basic protobuf files..."
protoc --php_out="$OUT_DIR" -I . \
  meshtastic/portnums.proto \
  meshtastic/remote_hardware.proto \
  meshtastic/rtttl.proto \
  meshtastic/xmodem.proto

# Try individual files that might work
echo "Attempting additional files..."
for proto in telemetry.proto apponly.proto channel.proto storeforward.proto cannedmessages.proto paxcount.proto powermon.proto; do
  echo "Trying $proto..."
  if protoc --php_out="$OUT_DIR" -I . "meshtastic/$proto" 2>/dev/null; then
    echo "✓ Generated $proto"
  else
    echo "✗ Failed $proto"
  fi
done

echo "Generated PHP protobuf classes into: $OUT_DIR"
