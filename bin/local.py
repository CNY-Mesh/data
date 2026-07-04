#!/usr/bin/env python3
import argparse
import sys
import time
from typing import Optional

import requests
from google.protobuf.json_format import MessageToDict

# Protobufs from the Meshtastic Python package
# pip install meshtastic google.protobuf requests
from meshtastic.protobuf import mesh_pb2  # type: ignore


def fetch_fromradio_once(
    session: requests.Session,
    base_url: str,
    timeout: float = 30.0,
) -> Optional[mesh_pb2.FromRadio]:
    """
    Do a single GET to /api/v1/fromradio and return a parsed FromRadio, or None if no content.
    """
    url = f"{base_url.rstrip('/')}/api/v1/fromradio"
    headers = {"Accept": "application/x-protobuf"}
    r = session.get(url, headers=headers, timeout=timeout)
    if r.status_code == 204 or not r.content:
        return None  # nothing available right now
    r.raise_for_status()

    fr = mesh_pb2.FromRadio()
    fr.ParseFromString(r.content)  # the spec says one protobuf per request by default
    return fr


def main():
    ap = argparse.ArgumentParser(
        description="Tail decrypted data from a Meshtastic node over HTTP(S) and print to console."
    )
    ap.add_argument(
        "address",
        nargs="?",
        default="192.168.200.145",
        help="Host or URL of the device. Examples: 192.168.1.23, meshtastic.local, http://meshtastic.local, https://10.0.0.5 (default: 192.168.200.145)",
    )
    ap.add_argument(
        "--http",
        action="store_true",
        help="Force http:// scheme instead of https:// (ignored if address already has a scheme).",
    )
    ap.add_argument(
        "--secure",
        action="store_true",
        help="Enable TLS certificate verification (default is to skip verification).",
    )
    ap.add_argument(
        "--interval",
        type=float,
        default=0.3,
        help="Seconds to wait between polls when no data is available (default: 0.3).",
    )
    ap.add_argument(
        "--timeout",
        type=float,
        default=30.0,
        help="HTTP request timeout seconds (default: 30).",
    )
    args = ap.parse_args()

    # Normalize base URL
    addr = args.address.strip()
    if addr.startswith("http://") or addr.startswith("https://"):
        base = addr
    else:
        # Default to HTTPS unless --http is specified
        base = f"http://{addr}" if args.http else f"https://{addr}"

    # Default to insecure (skip verification) unless --secure is specified
    verify_tls = args.secure

    s = requests.Session()
    s.verify = verify_tls

    print(f"# Connecting to {base} ...", file=sys.stderr)
    print(f"# TLS verification: {'enabled' if verify_tls else 'disabled'}", file=sys.stderr)
    print("# Press Ctrl+C to stop.", file=sys.stderr)

    # Simple, resilient poll loop
    while True:
        try:
            fr = fetch_fromradio_once(s, base, timeout=args.timeout)
            if fr is None:
                time.sleep(args.interval)
                continue

            # Convert to a friendly dict for console printing.
            # This includes decrypted contents when present (MeshPacket.decoded.*).
            obj = MessageToDict(
                fr,
                preserving_proto_field_name=True,
                including_default_value_fields=False,
            )

            # Optional: make common decoded text easy to spot
            try:
                pkt = fr.packet
                if pkt.HasField("decoded"):
                    decoded = pkt.decoded
                    # Print a concise line before the full JSON
                    if decoded.HasField("data") and decoded.data.text:
                        print(f"[text] {decoded.data.text}")
            except Exception:
                pass

            # Full JSON for everything we received
            print(obj, flush=True)

        except requests.exceptions.SSLError as e:
            print(f"[TLS error] {e} (TLS verification is disabled by default; try --http for HTTP)", file=sys.stderr)
            sys.exit(1)
        except requests.exceptions.RequestException as e:
            # Network hiccup -> log and retry
            print(f"[HTTP error] {e}; retrying in {args.interval}s ...", file=sys.stderr)
            time.sleep(args.interval)
        except KeyboardInterrupt:
            print("\n# Stopping.", file=sys.stderr)
            break


if __name__ == "__main__":
    main()
