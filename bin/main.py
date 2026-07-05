#!/usr/bin/env python3
"""
Meshtastic MQTT Monitor and Decoder
Connects to Meshtastic MQTT server and decodes all messages for manual review
"""

import os
import sys
import json
import base64
import time
import logging
import requests
from datetime import datetime
from typing import Optional, Dict, Any, List
from dataclasses import dataclass

# Third-party imports
import paho.mqtt.client as mqtt
from dotenv import load_dotenv
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend

# Ensure log output is flushed line-by-line when running detached with redirected stdout.
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(line_buffering=True)
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(line_buffering=True)

# Try to import meshtastic protobuf - if not available, we'll decode basic structure
try:
    from meshtastic import mesh_pb2, mqtt_pb2, portnums_pb2, telemetry_pb2
    PROTOBUF_AVAILABLE = True
    print("✓ Meshtastic protobuf libraries found")
    print(f"✓ MapReport available: {hasattr(mqtt_pb2, 'MapReport')}")
except ImportError as e:
    PROTOBUF_AVAILABLE = False
    print(f"⚠ Meshtastic protobuf not available: {e}")
    print("  Install with: pip install meshtastic")

@dataclass
class MQTTConfig:
    """MQTT configuration from .env file"""
    host: str
    port: int
    client_id: str
    username: str
    password: str
    topic: str
    keepalive: int
    clean_session: bool

class ApiClient:
    """Client for sending decoded mesh data to the API"""
    
    def __init__(self, api_url: str = "https://data.cnymesh.org/?r=api&a=mesh_data"):
        self.api_url = api_url
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'User-Agent': 'CNY-Mesh MQTT Monitor/1.0'
        })
        self.batch_messages = []
        self.batch_size = 10  # Send in batches of 10 messages
        self.last_send_time = time.time()
        self.send_interval = 30  # Send every 30 seconds regardless of batch size
        
    def add_message(self, topic: str, timestamp: int, json_data: Optional[Dict] = None, decoded_packet: Optional[Dict] = None):
        """Add a message to the batch queue"""
        message = {
            'topic': topic,
            'timestamp': timestamp,
        }
        
        if json_data:
            message['json_data'] = json_data
        if decoded_packet:
            message['decoded_packet'] = decoded_packet
            
        self.batch_messages.append(message)
        
        # Send if batch is full or enough time has passed
        if len(self.batch_messages) >= self.batch_size or (time.time() - self.last_send_time) > self.send_interval:
            self.send_batch()
    
    def send_batch(self):
        """Send the current batch of messages to the API"""
        if not self.batch_messages:
            return
            
        try:
            payload = {
                'messages': self.batch_messages
            }
            
            # print(f"📤 Sending {len(self.batch_messages)} messages to API...")
            
            response = self.session.post(
                self.api_url,
                json=payload,
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    # print(f"✅ API: Saved {result.get('saved_count', 0)} messages")
                    if result.get('error_count', 0) > 0:
                        print(f"⚠️  API: {result['error_count']} errors occurred")
                        # Show specific errors if available
                        if result.get('errors'):
                            for error in result['errors'][:3]:  # Show first 3 errors
                                print(f"   • {error}")
                            if len(result['errors']) > 3:
                                print(f"   • ... and {len(result['errors']) - 3} more errors")
                else:
                    print(f"❌ API Error: {result.get('error', 'Unknown error')}")
            else:
                print(f"❌ API HTTP Error: {response.status_code}")
                if response.status_code == 404:
                    print("   Endpoint not found - check API_URL in .env")
                elif response.status_code >= 500:
                    print("   Server error - API may be down")
                print(f"   Response: {response.text[:100]}...")
                
        except requests.exceptions.RequestException as e:
            print(f"❌ API Request failed: {e}")
            if "Connection" in str(e):
                print("   Check network connectivity and API_URL")
        except Exception as e:
            print(f"❌ API Error: {e}")
        finally:
            # Clear the batch and update last send time
            self.batch_messages.clear()
            self.last_send_time = time.time()
    
    def flush(self):
        """Send any remaining messages in the batch"""
        if self.batch_messages:
            self.send_batch()

class MeshtasticDecoder:
    """Decodes Meshtastic messages from MQTT"""
    
    # Default LongFast key - Meshtastic's published default
    # This is "1PG7OiApB1nwvP+rz05pAQ==" in base64, which decodes to the standard LongFast PSK
    DEFAULT_LONGFAST_KEY = base64.b64decode("1PG7OiApB1nwvP+rz05pAQ==")
    
    # Port numbers for application identification
    PORTNUM_MAP = {
        0: "UNKNOWN_APP",
        1: "TEXT_MESSAGE_APP", 
        2: "REMOTE_HARDWARE_APP",
        3: "POSITION_APP",
        4: "NODEINFO_APP",
        5: "ROUTING_APP",
        6: "ADMIN_APP",
        7: "TEXT_MESSAGE_COMPRESSED_APP",
        8: "WAYPOINT_APP",
        9: "AUDIO_APP",
        10: "DETECTION_SENSOR_APP",
        32: "REPLY_APP",
        33: "IP_TUNNEL_APP",
        34: "PAXCOUNTER_APP",
        64: "SERIAL_APP",
        65: "STORE_FORWARD_APP",
        66: "RANGE_TEST_APP",
        67: "TELEMETRY_APP",
        68: "ZPS_APP",
        69: "SIMULATOR_APP",
        70: "TRACEROUTE_APP",
        71: "NEIGHBORINFO_APP",
        72: "ATAK_PLUGIN_APP",
        73: "MAP_REPORT_APP",
        74: "POWERSTRESS_APP",
        256: "PRIVATE_APP",
        257: "ATAK_FORWARDER_APP"
    }
    
    def __init__(self, longfast_key: Optional[bytes] = None):
        """Initialize decoder with optional LongFast key"""
        self.longfast_key = longfast_key or self.DEFAULT_LONGFAST_KEY
        print(f"🔑 Using LongFast key: {base64.b64encode(self.longfast_key).decode()}")
        
    def decrypt_longfast(self, encrypted_data: bytes, sender_id: int, packet_id: int) -> Optional[bytes]:
        """
        Decrypt LongFast encrypted payload using Meshtastic's algorithm
        Uses packet_id and sender_id to create the nonce (FIXED to match Go implementation)
        """
        try:
            if len(encrypted_data) < 16:  # Need at least 16 bytes
                return None
            
            # Create nonce from packet_id and sender_id (CORRECTED Meshtastic algorithm)
            # The nonce is 16 bytes: packet_id (4 bytes) + zeros (4 bytes) + sender_id (4 bytes) + zeros (4 bytes)
            # This matches the Go implementation: nonce[0:] = packet_id, nonce[8:] = sender_id
            nonce = bytearray(16)
            nonce[0:4] = packet_id.to_bytes(4, byteorder='little')  # packet_id at bytes 0-3
            # bytes 4-7 remain zero  
            nonce[8:12] = sender_id.to_bytes(4, byteorder='little')  # sender_id at bytes 8-11
            # bytes 12-15 remain zero
            
            # Use CTR mode with the constructed nonce
            cipher = Cipher(
                algorithms.AES(self.longfast_key),
                modes.CTR(bytes(nonce)),
                backend=default_backend()
            )
            decryptor = cipher.decryptor()
            
            # Decrypt the entire payload
            decrypted = decryptor.update(encrypted_data) + decryptor.finalize()
            
            # Validate the decrypted data looks like valid protobuf
            if len(decrypted) > 0 and self._looks_like_protobuf(decrypted):
                print(f"✅ Decryption successful with corrected nonce (packet_id={packet_id}, sender_id={sender_id})")
                return decrypted
            else:
                # Try alternative nonce construction (big endian) - kept as fallback
                nonce = bytearray(16)
                nonce[0:4] = packet_id.to_bytes(4, byteorder='big')
                nonce[8:12] = sender_id.to_bytes(4, byteorder='big')
                
                cipher = Cipher(
                    algorithms.AES(self.longfast_key),
                    modes.CTR(bytes(nonce)),
                    backend=default_backend()
                )
                decryptor = cipher.decryptor()
                decrypted = decryptor.update(encrypted_data) + decryptor.finalize()
                
                if len(decrypted) > 0 and self._looks_like_protobuf(decrypted):
                    print(f"✅ Decryption successful with big-endian nonce (packet_id={packet_id}, sender_id={sender_id})")
                    return decrypted
                    
            return None
            
        except Exception as e:
            print(f"❌ Decryption failed: {e}")
            return None
    
    def _looks_like_protobuf(self, data: bytes) -> bool:
        """Check if data looks like valid protobuf"""
        if len(data) < 2:
            return False
        
        # Basic protobuf validation - check for valid field tags
        # Protobuf messages typically start with field numbers
        first_byte = data[0]
        
        # Check if the first byte looks like a valid protobuf field tag
        # Field tags are encoded as (field_number << 3) | wire_type
        # Wire types are 0-5, so valid first bytes have lower 3 bits in range 0-5
        wire_type = first_byte & 0x07
        if wire_type > 5:
            return False
            
        # Additional heuristic: check for reasonable field numbers
        field_number = first_byte >> 3
        if field_number == 0:
            return False
            
        return True
    
    def _extract_packet_ids_from_encrypted(self, payload: bytes) -> List[tuple]:
        """Try to extract potential packet/sender IDs from encrypted payload structure"""
        potential_ids = []
        
        try:
            # Some encrypted packets may have unencrypted headers with ID info
            # Look for 32-bit values that could be IDs in the first 16 bytes
            if len(payload) >= 16:
                for i in range(0, min(16, len(payload) - 4), 4):
                    # Try both little and big endian interpretations
                    id_le = int.from_bytes(payload[i:i+4], byteorder='little')
                    id_be = int.from_bytes(payload[i:i+4], byteorder='big')
                    
                    # Filter for reasonable node IDs (avoid obviously invalid values)
                    for candidate_id in [id_le, id_be]:
                        if 0 < candidate_id < 0xFFFFFFFF and candidate_id not in [0xFFFFFFFF]:
                            # Try this ID as both sender and packet ID
                            potential_ids.append((candidate_id, 0))
                            potential_ids.append((0, candidate_id))
                            
        except Exception:
            pass  # Ignore extraction errors
            
        return potential_ids[:4]  # Limit to first 4 candidates
    
    def decode_service_envelope(self, payload: bytes) -> Optional[Dict[str, Any]]:
        """Decode ServiceEnvelope from MQTT payload"""
        if not PROTOBUF_AVAILABLE:
            return self.decode_basic_structure(payload)
            
        # First, try to parse as unencrypted ServiceEnvelope
        try:
            envelope = mqtt_pb2.ServiceEnvelope()
            envelope.ParseFromString(payload)
            
            result = {
                'channel_id': envelope.channel_id,
                'gateway_id': envelope.gateway_id,
                'has_packet': envelope.HasField('packet')
            }
            
            if envelope.HasField('packet'):
                result['packet'] = self.decode_mesh_packet(envelope.packet)
                
            return result
            
        except Exception as e:
            # If unencrypted parsing fails, this might be encrypted data
            # Try to decrypt using the default LongFast key
            print(f"🔒 ServiceEnvelope parsing failed, attempting decryption: {e}")
            
            # For encrypted ServiceEnvelope, we need to extract some metadata first
            # The payload structure for encrypted data is different
            try:
                # Try to decrypt the entire payload as encrypted data
                # Extract sender_id and packet_id from the first few bytes if possible
                if len(payload) >= 16:  # Minimum size for encrypted packet
                    # First try to extract real packet IDs from any unencrypted header
                    potential_ids = self._extract_packet_ids_from_encrypted(payload)
                    
                    # Try different approaches to extract IDs for decryption nonce
                    # Limit attempts to reduce noise in logs
                    attempts = [
                        (0, 0),  # Common fallback
                        (1, 0),  # Alternative sender
                        (0, 1),  # Alternative packet
                        (1, 1),  # Both alternative
                        (0xFFFFFFFF, 0)  # Broadcast sender
                    ]
                    
                    # Add any extracted IDs to the front of our attempts
                    if potential_ids:
                        attempts = potential_ids + attempts
                    
                    for sender_id_attempt, packet_id_attempt in attempts[:8]:  # Limit to 8 attempts
                            decrypted = self.decrypt_longfast(payload, sender_id_attempt, packet_id_attempt)
                            if decrypted:
                                print(f"✅ Successfully decrypted with sender_id={sender_id_attempt}, packet_id={packet_id_attempt}")
                                
                                # Try to parse the decrypted data as different message types
                                # First try ServiceEnvelope
                                try:
                                    envelope = mqtt_pb2.ServiceEnvelope()
                                    envelope.ParseFromString(decrypted)
                                    
                                    result = {
                                        'channel_id': envelope.channel_id,
                                        'gateway_id': envelope.gateway_id,
                                        'has_packet': envelope.HasField('packet'),
                                        'was_encrypted': True
                                    }
                                    
                                    if envelope.HasField('packet'):
                                        result['packet'] = self.decode_mesh_packet(envelope.packet)
                                        
                                    return result
                                except Exception as se_error:
                                    print(f"⚠ Decrypted data couldn't be parsed as ServiceEnvelope: {se_error}")
                                    
                                # If ServiceEnvelope fails, try parsing directly as MeshPacket
                                try:
                                    mesh_packet = mesh_pb2.MeshPacket()
                                    mesh_packet.ParseFromString(decrypted)
                                    
                                    print(f"✅ Successfully parsed decrypted data as MeshPacket")
                                    result = {
                                        'was_encrypted': True,
                                        'direct_packet': True,
                                        'packet': self.decode_mesh_packet(mesh_packet)
                                    }
                                    return result
                                except Exception as mp_error:
                                    print(f"⚠ Decrypted data couldn't be parsed as MeshPacket: {mp_error}")
                                    
                                # If both fail, try to extract basic info from decrypted data
                                print(f"⚠ Decrypted {len(decrypted)} bytes but couldn't parse as known message type")
                                return {
                                    'was_encrypted': True,
                                    'decrypted_size': len(decrypted),
                                    'decrypted_hex': decrypted[:32].hex() + '...' if len(decrypted) > 32 else decrypted.hex(),
                                    'basic_info': self.decode_basic_structure(decrypted)
                                }
            except Exception as decrypt_error:
                print(f"❌ Decryption attempt failed: {decrypt_error}")
            
            # If all decryption attempts fail, return error info
            return {
                'error': str(e),
                'basic_info': self.decode_basic_structure(payload),
                'likely_encrypted': True
            }
    
    def decode_mesh_packet(self, packet) -> Dict[str, Any]:
        """Decode MeshPacket protobuf"""
        try:
            result = {}

            def has_field(obj, field_name: str) -> bool:
                try:
                    return bool(obj.HasField(field_name))
                except Exception:
                    return False
            
            # Safely extract fields using getattr to handle reserved keywords
            if hasattr(packet, 'to'):
                result['to'] = packet.to
            if hasattr(packet, 'from_'):
                result['from'] = packet.from_
            elif hasattr(packet, 'from'):
                result['from'] = getattr(packet, 'from')
            if hasattr(packet, 'id'):
                result['id'] = packet.id
            if hasattr(packet, 'rx_time'):
                result['rx_time'] = packet.rx_time
            if hasattr(packet, 'rx_snr'):
                result['rx_snr'] = packet.rx_snr
            if hasattr(packet, 'rx_rssi'):
                result['rx_rssi'] = packet.rx_rssi
            if hasattr(packet, 'hop_limit'):
                result['hop_limit'] = packet.hop_limit
            if hasattr(packet, 'channel'):
                result['channel'] = packet.channel
            if hasattr(packet, 'want_ack'):
                result['want_ack'] = packet.want_ack
            if hasattr(packet, 'priority'):
                result['priority'] = packet.priority
            if hasattr(packet, 'delayed'):
                result['delayed'] = packet.delayed
            
            decoded_present = has_field(packet, 'decoded')
            encrypted_present = has_field(packet, 'encrypted') or (hasattr(packet, 'encrypted') and bool(packet.encrypted))

            # Decode payload if present
            if decoded_present and hasattr(packet, 'decoded'):
                try:
                    decoded_data = self.decode_data_payload(packet.decoded)
                    result['decoded'] = decoded_data
                except Exception as e:
                    result['decoded'] = {
                        'decode_error': str(e),
                        'raw_data': 'failed_to_parse'
                    }

                # Some messages can appear to have an empty decoded payload (port 0, no payload)
                # while still carrying encrypted bytes. Fall back to decryption in that case.
                has_meaningful_decoded = bool(
                    result.get('decoded', {}).get('payload_size')
                    or result.get('decoded', {}).get('text_message')
                    or result.get('decoded', {}).get('position')
                    or result.get('decoded', {}).get('nodeinfo')
                    or result.get('decoded', {}).get('telemetry')
                    or (result.get('decoded', {}).get('portnum', 0) not in [0, None])
                )

                if not has_meaningful_decoded and encrypted_present:
                    decoded_present = False

            if (not decoded_present) and encrypted_present:
                result['encrypted'] = {
                    'size': len(packet.encrypted),
                    'data_hex': packet.encrypted.hex()[:64] + "..." if len(packet.encrypted.hex()) > 64 else packet.encrypted.hex()
                }
                
                # Try to decrypt using both sender and packet ID
                sender_id = result.get('from', 0)
                packet_id = result.get('id', 0)
                
                if sender_id and packet_id:
                    decrypted = self.decrypt_longfast(packet.encrypted, sender_id, packet_id)
                    if decrypted:
                        result['decrypted_raw_size'] = len(decrypted)
                        
                        # Try multiple parsing approaches
                        data_parsed = False
                        
                        # Attempt 1: Parse as Data protobuf
                        try:
                            data = mesh_pb2.Data()
                            data.ParseFromString(decrypted)
                            result['decrypted'] = self.decode_data_payload(data)
                            result['decryption_success'] = True
                            data_parsed = True
                        except Exception as data_error:
                            result['data_parse_error'] = str(data_error)
                        
                        # Attempt 2: Try parsing as different message types if Data failed
                        if not data_parsed:
                            parsed_message = self.try_parse_as_specific_types(decrypted)
                            if parsed_message:
                                result['decrypted_specific'] = parsed_message
                                result['decryption_success'] = True
                                data_parsed = True
                        
                        # Attempt 3: Manual analysis of the decrypted data
                        if not data_parsed:
                            analysis = self.analyze_decrypted_data(decrypted)
                            result['decrypted_analysis'] = analysis
                            result['decrypted_hex'] = decrypted.hex()[:128] + "..." if len(decrypted.hex()) > 128 else decrypted.hex()
                    else:
                        result['decryption_failed'] = True
            
            return result
        except Exception as e:
            return {'decode_error': str(e), 'raw_packet': 'parsing_failed'}
    
    def decode_data_payload(self, data) -> Dict[str, Any]:
        """Decode Data protobuf payload"""
        try:
            result = {}
            
            # Safely extract basic fields
            if hasattr(data, 'portnum'):
                result['portnum'] = data.portnum
                result['portnum_name'] = self.PORTNUM_MAP.get(data.portnum, f"UNKNOWN_{data.portnum}")
            
            if hasattr(data, 'want_response'):
                result['want_response'] = data.want_response
            if hasattr(data, 'dest'):
                result['dest'] = data.dest
            if hasattr(data, 'source'):
                result['source'] = data.source
            if hasattr(data, 'request_id'):
                result['request_id'] = data.request_id
            if hasattr(data, 'reply_id'):
                result['reply_id'] = data.reply_id
            if hasattr(data, 'emoji'):
                result['emoji'] = data.emoji
            
            # Handle payload field carefully - bytes fields don't have "presence" in proto3
            payload_data = None
            if hasattr(data, 'payload'):
                payload_data = data.payload if data.payload else None
            
            if payload_data:
                result['payload_size'] = len(payload_data)
                result['payload_hex'] = payload_data.hex()[:128] + ("..." if len(payload_data) > 64 else "")
                
                # Try to decode specific message types based on port number
                portnum = result.get('portnum', 0)
                
                if portnum == 1:  # TEXT_MESSAGE_APP
                    try:
                        result['text_message'] = payload_data.decode('utf-8')
                    except:
                        result['text_message'] = f"<decode_error: {payload_data.hex()[:32]}...>"
                        
                elif portnum == 3:  # POSITION_APP
                    result['position'] = self.decode_position(payload_data)
                    
                elif portnum == 4:  # NODEINFO_APP
                    result['nodeinfo'] = self.decode_nodeinfo(payload_data)
                    
                elif portnum == 67:  # TELEMETRY_APP
                    result['telemetry'] = self.decode_telemetry(payload_data)
                    
                elif portnum == 70:  # TRACEROUTE_APP
                    result['traceroute'] = self.decode_traceroute(payload_data)
                    
                elif portnum == 71:  # NEIGHBORINFO_APP
                    result['neighborinfo'] = self.decode_neighborinfo(payload_data)
                    
                elif portnum == 73:  # MAP_REPORT_APP
                    result['map_report'] = self.decode_map_report(payload_data)
                else:
                    # Unknown port number - just show hex
                    result['unknown_payload'] = {
                        'port': portnum,
                        'hex': payload_data.hex()[:64] + ("..." if len(payload_data) > 32 else "")
                    }
            
            return result
        except Exception as e:
            return {'decode_error': str(e), 'raw_data': 'failed_to_parse'}
    
    def decode_position(self, payload: bytes) -> Dict[str, Any]:
        """Decode Position message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            pos = mesh_pb2.Position()
            pos.ParseFromString(payload)
            return {
                'latitude_i': pos.latitude_i,
                'longitude_i': pos.longitude_i,
                'latitude': pos.latitude_i / 1e7 if pos.latitude_i else None,
                'longitude': pos.longitude_i / 1e7 if pos.longitude_i else None,
                'altitude': pos.altitude,
                'time': pos.time,
                'location_source': pos.location_source,
                'altitude_source': pos.altitude_source,
                'timestamp': pos.timestamp,
                'timestamp_millis_adjust': pos.timestamp_millis_adjust,
                'altitude_hae': pos.altitude_hae,
                'altitude_geoidal_separation': pos.altitude_geoidal_separation,
                'PDOP': pos.PDOP,
                'HDOP': pos.HDOP,
                'VDOP': pos.VDOP,
                'gps_accuracy': pos.gps_accuracy,
                'ground_speed': pos.ground_speed,
                'ground_track': pos.ground_track,
                'fix_quality': pos.fix_quality,
                'fix_type': pos.fix_type,
                'sats_in_view': pos.sats_in_view,
                'sensor_id': pos.sensor_id,
                'next_update': pos.next_update,
                'seq_number': pos.seq_number,
                'precision_bits': pos.precision_bits
            }
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def decode_nodeinfo(self, payload: bytes) -> Dict[str, Any]:
        """Decode NodeInfo message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            user = mesh_pb2.User()
            user.ParseFromString(payload)
            return {
                'id': user.id,
                'long_name': user.long_name,
                'short_name': user.short_name,
                'macaddr': user.macaddr.hex() if user.macaddr else None,
                'hw_model': user.hw_model,
                'is_licensed': user.is_licensed,
                'role': user.role,
                'public_key': user.public_key.hex() if user.public_key else None
            }
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def decode_telemetry(self, payload: bytes) -> Dict[str, Any]:
        """Decode Telemetry message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            telemetry = mesh_pb2.Telemetry()
            telemetry.ParseFromString(payload)
            
            result = {
                'time': telemetry.time,
                'variant': None
            }
            
            if telemetry.HasField('device_metrics'):
                result['variant'] = 'device_metrics'
                result['device_metrics'] = {
                    'battery_level': telemetry.device_metrics.battery_level,
                    'voltage': telemetry.device_metrics.voltage,
                    'channel_utilization': telemetry.device_metrics.channel_utilization,
                    'air_util_tx': telemetry.device_metrics.air_util_tx,
                    'uptime_seconds': telemetry.device_metrics.uptime_seconds
                }
            elif telemetry.HasField('environment_metrics'):
                result['variant'] = 'environment_metrics'
                result['environment_metrics'] = {
                    'temperature': telemetry.environment_metrics.temperature,
                    'relative_humidity': telemetry.environment_metrics.relative_humidity,
                    'barometric_pressure': telemetry.environment_metrics.barometric_pressure,
                    'gas_resistance': telemetry.environment_metrics.gas_resistance,
                    'voltage': telemetry.environment_metrics.voltage,
                    'current': telemetry.environment_metrics.current,
                    'lux': telemetry.environment_metrics.lux,
                    'white_lux': telemetry.environment_metrics.white_lux,
                    'ir_lux': telemetry.environment_metrics.ir_lux,
                    'uv_lux': telemetry.environment_metrics.uv_lux,
                    'wind_direction': telemetry.environment_metrics.wind_direction,
                    'wind_speed': telemetry.environment_metrics.wind_speed,
                    'weight': telemetry.environment_metrics.weight,
                    'distance': telemetry.environment_metrics.distance
                }
            
            return result
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def decode_traceroute(self, payload: bytes) -> Dict[str, Any]:
        """Decode Traceroute message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            route = mesh_pb2.RouteDiscovery()
            route.ParseFromString(payload)
            return {
                'route': list(route.route),
                'snr_towards': list(route.snr_towards),
                'snr_back': list(route.snr_back)
            }
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def decode_neighborinfo(self, payload: bytes) -> Dict[str, Any]:
        """Decode NeighborInfo message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            neighbors = mesh_pb2.NeighborInfo()
            neighbors.ParseFromString(payload)
            
            neighbor_list = []
            for neighbor in neighbors.neighbors:
                neighbor_list.append({
                    'node_id': neighbor.node_id,
                    'node_broadcast_interval_secs': neighbor.node_broadcast_interval_secs,
                    'last_rx_time': neighbor.last_rx_time,
                    'snr': neighbor.snr
                })
            
            return {
                'node_id': neighbors.node_id,
                'node_broadcast_interval_secs': neighbors.node_broadcast_interval_secs,
                'last_sent_by_id': neighbors.last_sent_by_id,
                'neighbors': neighbor_list
            }
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def decode_map_report(self, payload: bytes) -> Dict[str, Any]:
        """Decode MapReport message"""
        try:
            if not PROTOBUF_AVAILABLE:
                return {'error': 'protobuf_not_available', 'hex': payload.hex()}
                
            map_report = mqtt_pb2.MapReport()
            map_report.ParseFromString(payload)
            result = self.protobuf_to_dict(map_report)
            
            # Add computed lat/lon if available
            if hasattr(map_report, 'latitude_i') and map_report.latitude_i:
                result['latitude'] = map_report.latitude_i / 1e7
            if hasattr(map_report, 'longitude_i') and map_report.longitude_i:
                result['longitude'] = map_report.longitude_i / 1e7
                
            return result
        except Exception as e:
            return {'error': str(e), 'hex': payload.hex()}
    
    def try_parse_as_specific_types(self, decrypted_data: bytes) -> Optional[Dict[str, Any]]:
        """Try parsing decrypted data as specific protobuf message types"""
        if not PROTOBUF_AVAILABLE:
            return None
            
        message_types = [
            ('Position', mesh_pb2.Position),
            ('User', mesh_pb2.User),
            ('Telemetry', telemetry_pb2.Telemetry),
            ('NodeInfo', mesh_pb2.NodeInfo),
            ('Routing', mesh_pb2.Routing),
        ]
        
        for type_name, proto_class in message_types:
            try:
                message = proto_class()
                message.ParseFromString(decrypted_data)
                return {
                    'type': type_name,
                    'message': self.protobuf_to_dict(message)
                }
            except Exception:
                continue
        return None
    
    def analyze_decrypted_data(self, decrypted_data: bytes) -> Dict[str, Any]:
        """Analyze decrypted data to provide insights about its structure"""
        analysis = {
            'size': len(decrypted_data),
            'hex_preview': decrypted_data[:32].hex() if len(decrypted_data) > 0 else '',
            'printable_chars': sum(1 for b in decrypted_data if 32 <= b <= 126),
            'null_bytes': decrypted_data.count(0)
        }
        
        # Check for common protobuf patterns
        if len(decrypted_data) > 0:
            # Protobuf fields typically start with field numbers (1-15 = 0x08-0x78 in varint)
            first_byte = decrypted_data[0]
            analysis['likely_protobuf'] = (first_byte & 0x07) in [0, 1, 2, 5] and (first_byte >> 3) <= 15
            
            # Look for varint patterns
            varints = []
            pos = 0
            while pos < min(len(decrypted_data), 20):  # Check first 20 bytes
                byte = decrypted_data[pos]
                if byte & 0x80 == 0:  # Single byte varint
                    varints.append(byte)
                    pos += 1
                elif pos + 1 < len(decrypted_data):  # Multi-byte varint
                    varint_bytes = [byte]
                    pos += 1
                    while pos < len(decrypted_data) and decrypted_data[pos] & 0x80:
                        varint_bytes.append(decrypted_data[pos])
                        pos += 1
                    if pos < len(decrypted_data):
                        varint_bytes.append(decrypted_data[pos])
                        pos += 1
                    if len(varint_bytes) <= 5:  # Valid varint length
                        varints.append(varint_bytes)
                else:
                    break
            analysis['varint_pattern'] = varints[:5]  # First 5 varints
        
        return analysis

    def protobuf_to_dict(self, message) -> Dict[str, Any]:
        """Convert protobuf message to dictionary"""
        result = {}
        if not message:
            return result
            
        try:
            # Get all fields from the message descriptor
            for field in message.DESCRIPTOR.fields:
                try:
                    # For proto3, bytes and scalar fields don't have presence
                    # Try to get the value first
                    value = getattr(message, field.name)
                    
                    # Check if field has a meaningful value
                    has_value = False
                    if field.type == field.TYPE_MESSAGE:
                        # Message fields can use HasField
                        has_value = message.HasField(field.name)
                    elif field.type == field.TYPE_BYTES:
                        # Bytes fields have value if not empty
                        has_value = bool(value)
                    elif field.type == field.TYPE_STRING:
                        # String fields have value if not empty
                        has_value = bool(value)
                    else:
                        # For scalar fields, include if not default value
                        has_value = True  # Include all scalar values for debugging
                    
                    if has_value:
                        if hasattr(value, 'DESCRIPTOR'):  # Nested message
                            result[field.name] = self.protobuf_to_dict(value)
                        else:
                            result[field.name] = value
                            
                except Exception as field_error:
                    result[f'_{field.name}_error'] = str(field_error)
        except Exception as e:
            result['_conversion_error'] = str(e)
            
        return result

    def decode_basic_structure(self, payload: bytes) -> Dict[str, Any]:
        """Basic payload analysis when protobuf is not available"""
        return {
            'size': len(payload),
            'hex': payload.hex(),
            'printable': ''.join(chr(b) if 32 <= b <= 126 else '.' for b in payload),
            'note': 'protobuf_not_available'
        }

class MQTTMonitor:
    """MQTT client for monitoring Meshtastic messages"""

    HEARTBEAT_INTERVAL_SECONDS = 60
    
    def __init__(self, config: MQTTConfig):
        self.config = config
        
        # Get LongFast key from environment or use Meshtastic default
        longfast_key = None
        longfast_b64 = os.getenv('LONGFAST_B64_KEY', '').strip('"')
        
        if longfast_b64 and longfast_b64 != "AQ==":  # AQ== is just a placeholder (single byte)
            try:
                longfast_key = base64.b64decode(longfast_b64)
                # Ensure it's 16 or 32 bytes for AES
                if len(longfast_key) < 16:
                    longfast_key = longfast_key + b'\x00' * (16 - len(longfast_key))
                elif len(longfast_key) > 16 and len(longfast_key) < 32:
                    longfast_key = longfast_key + b'\x00' * (32 - len(longfast_key))
                print(f"🔑 Using custom LongFast key from .env: {len(longfast_key)} bytes")
            except Exception as e:
                print(f"⚠ Failed to decode LONGFAST_B64_KEY: {e}")
                longfast_key = None
        
        if longfast_key is None:
            print(f"🔑 Using default Meshtastic LongFast key")
        
        self.decoder = MeshtasticDecoder(longfast_key)
        callback_api_enum = getattr(mqtt, 'CallbackAPIVersion', None)
        if callback_api_enum is not None:
            # Use callback API v2 to avoid deprecation warnings on paho-mqtt 2.x.
            self.client = mqtt.Client(
                callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
                client_id=config.client_id,
                clean_session=config.clean_session,
            )
        else:
            self.client = mqtt.Client(client_id=config.client_id, clean_session=config.clean_session)
        self.client.username_pw_set(config.username, config.password)
        self.client.on_connect = self.on_connect
        self.client.on_message = self.on_message
        self.client.on_disconnect = self.on_disconnect
        self.message_count = 0
        self.last_heartbeat_at = 0.0
        
        # Initialize API client
        api_url = os.getenv('API_URL', 'https://data.cnymesh.org/?r=api&a=mesh_data')
        self.api_client = ApiClient(api_url)
        print(f"🔗 API endpoint: {api_url}")

    def _reason_code_value(self, reason_code) -> int:
        """Normalize paho v1/v2 reason codes to an integer status code."""
        if isinstance(reason_code, (int, float)):
            return int(reason_code)

        value = getattr(reason_code, 'value', None)
        if isinstance(value, (int, float)):
            return int(value)

        try:
            return int(str(reason_code))
        except (TypeError, ValueError):
            return 1
        
    def on_connect(self, client, userdata, flags, reason_code, properties=None):
        """Callback for when the client receives a CONNACK response from the server"""
        code = self._reason_code_value(reason_code)
        if code == 0:
            print(f"✅ Connected to MQTT broker")
            client.subscribe(self.config.topic)
            print(f"📡 Subscribed to {self.config.topic}")
        else:
            print(f"❌ Failed to connect to MQTT broker (code: {reason_code})")
    
    def on_disconnect(self, client, userdata, flags, reason_code, properties=None):
        """Callback for when the client disconnects from the server"""
        code = self._reason_code_value(reason_code)
        if code != 0:
            print(f"⚠️  Unexpected disconnect (code: {reason_code})")
    
    def on_message(self, client, userdata, msg):
        """Callback for when a PUBLISH message is received from the server"""
        self.message_count += 1
        timestamp = int(time.time())
        
        # Try to decode as JSON first (some topics may send JSON)
        try:
            json_data = json.loads(msg.payload.decode('utf-8'))
            
            # Send JSON message to API
            self.api_client.add_message(
                topic=msg.topic,
                timestamp=timestamp,
                json_data=json_data
            )
            
            # Compact success message
            msg_type = json_data.get('type', 'unknown')
            node_id = json_data.get('from', 'unknown')
            # print(f"✅ JSON {msg_type} from {node_id} → API")
            
            return
        except:
            pass
        
        # Check if this is plain text (common on LongFast topics)
        try:
            text_content = msg.payload.decode('utf-8')
            # If it's readable text and doesn't contain binary markers, treat as text message
            if all(ord(c) < 127 and (c.isprintable() or c.isspace()) for c in text_content):
                print(f"📝 Plain text message on {msg.topic}: {text_content}")
                
                # Send plain text message to API
                self.api_client.add_message(
                    topic=msg.topic,
                    timestamp=timestamp,
                    decoded_packet={
                        'type': 'text_message',
                        'text': text_content,
                        'is_plain_text': True
                    }
                )
                return
        except UnicodeDecodeError:
            # Not plain text, continue with protobuf parsing
            pass
        
        # Try to decode as protobuf
        decoded = self.decoder.decode_service_envelope(msg.payload)
        if decoded:
            if 'error' in decoded:
                # Show errors in detail
                print(f"\n❌ Message #{self.message_count} - Decode Error")
                print(f"   Topic: {msg.topic}")
                print(f"   Error: {decoded['error']}")
                if decoded.get('likely_encrypted'):
                    print(f"   Status: Encrypted LongFast message - decryption failed")
                    print(f"   Size: {decoded['basic_info']['size']} bytes")
                    # Show first 32 bytes for debugging
                    hex_preview = decoded['basic_info']['hex'][:64] + "..." if len(decoded['basic_info']['hex']) > 64 else decoded['basic_info']['hex']
                    print(f"   Hex: {hex_preview}")
                
                # Store decode errors in raw_messages for searchability
                error_data = {
                    'decode_error': True,
                    'error_message': decoded['error'],
                    'likely_encrypted': decoded.get('likely_encrypted', False),
                    'size': decoded.get('basic_info', {}).get('size', 0),
                    'hex_preview': decoded.get('basic_info', {}).get('hex', '')[:64],  # First 32 bytes
                    'type': 'decode_error',  # Add type field for API processing
                    'from': None,  # Set defaults for required API fields
                    'to': None,
                    'rssi': None,
                    'snr': None
                }
                
                print(f"📤 Sending decode error to API: {len(decoded.get('basic_info', {}).get('hex', ''))} chars of hex data")
                
                self.api_client.add_message(
                    topic=msg.topic,
                    timestamp=timestamp,
                    json_data=error_data
                )
            else:
                # Show successful decode
                if 'packet' in decoded and 'from' in decoded['packet']:
                    packet = decoded['packet']
                    from_id = packet['from']

                    # Send both unencrypted decoded and decrypted payloads to API.
                    if 'decoded' in packet or 'decrypted' in packet:
                        normalized_packet = dict(packet)
                        if 'decoded' in packet:
                            normalized_packet['decoded'] = packet['decoded']
                        elif 'decrypted' in packet:
                            normalized_packet['decoded'] = packet['decrypted']

                        self.api_client.add_message(
                            topic=msg.topic,
                            timestamp=timestamp,
                            decoded_packet=normalized_packet
                        )

                        payload_data = normalized_packet.get('decoded', {})
                        port_name = payload_data.get('portnum_name', 'unknown')

                        extra_info = ""
                        if 'text_message' in payload_data:
                            extra_info = f" text: '{payload_data['text_message'][:30]}...'" if len(payload_data['text_message']) > 30 else f" text: '{payload_data['text_message']}'"
                        elif 'position' in payload_data:
                            pos = payload_data['position']
                            if pos.get('latitude') and pos.get('longitude'):
                                extra_info = f" pos: {pos['latitude']:.4f},{pos['longitude']:.4f}"
                        elif 'telemetry' in payload_data:
                            telem = payload_data['telemetry']
                            if 'device_metrics' in telem:
                                metrics = telem['device_metrics']
                                battery = metrics.get('battery_level', '?')
                                voltage = metrics.get('voltage', '?')
                                extra_info = f" battery: {battery}%, voltage: {voltage}V"
                        elif 'nodeinfo' in payload_data:
                            node = payload_data['nodeinfo']
                            name = node.get('long_name', node.get('short_name', 'unknown'))
                            extra_info = f" node: {name}"

                        # print(f"✅ {port_name} from {hex(from_id)}{extra_info} → API")

                    elif 'decrypted_hex' in packet:
                        # Partial decryption
                        print(f"⚠️  Partial decrypt from {hex(from_id)} - raw hex available")
                    elif 'encrypted' in packet:
                        # Failed to decrypt - show minimal info
                        print(f"🔒 Encrypted from {hex(from_id)} - decryption failed")
        else:
            # Complete failure to decode - decode_service_envelope returned None
            print(f"\n❌ Message #{self.message_count} - Complete Decode Failure")
            print(f"   Topic: {msg.topic}")
            print(f"   Size: {len(msg.payload)} bytes")
            print(f"   Hex: {msg.payload.hex()[:64]}..." if len(msg.payload.hex()) > 64 else f"   Hex: {msg.payload.hex()}")
            
            # Send complete decode failures to API too
            complete_failure_data = {
                'decode_error': True,
                'error_message': 'Complete decode failure - data does not appear to be valid protobuf',
                'likely_encrypted': False,  # If it was encrypted, decode_service_envelope would have returned an error dict
                'size': len(msg.payload),
                'hex_preview': msg.payload.hex()[:64],  # First 32 bytes
                'type': 'decode_error',
                'from': None,
                'to': None,
                'rssi': None,
                'snr': None
            }
            
            print(f"📤 Sending complete decode failure to API")
            
            self.api_client.add_message(
                topic=msg.topic,
                timestamp=timestamp,
                json_data=complete_failure_data
            )

    def heartbeat(self):
        """Emit a periodic liveness message while the worker is idle."""
        now = time.time()
        if now - self.last_heartbeat_at < self.HEARTBEAT_INTERVAL_SECONDS:
            return

        self.last_heartbeat_at = now
        print(
            f"💓 Worker heartbeat: connected to {self.config.host}:{self.config.port}, "
            f"topic={self.config.topic}, processed_messages={self.message_count}"
        )

        # Publish heartbeat to API so remote status is visible via DB-backed pages.
        self.api_client.add_message(
            topic='worker/heartbeat',
            timestamp=int(now),
            json_data={
                'type': 'worker_heartbeat',
                'status': 'running',
                'worker_host': self.config.host,
                'worker_topic': self.config.topic,
                'processed_messages': self.message_count,
            }
        )
    
    def start(self):
        """Start the MQTT monitoring"""
        print(f"🚀 CNY-Mesh MQTT Monitor Starting")
        print(f"🌐 Server: {self.config.host}:{self.config.port}")
        print(f"� Topic: {self.config.topic}")
        print(f"� API: {self.api_client.api_url}")
        print(f"🔑 Protobuf: {'Available' if PROTOBUF_AVAILABLE else 'Not Available'}")
        print(f"{'='*60}")
        
        try:
            self.client.connect(self.config.host, self.config.port, self.config.keepalive)
            self.client.loop_start()
            self.last_heartbeat_at = 0.0

            while True:
                self.heartbeat()
                time.sleep(1)
        except KeyboardInterrupt:
            print(f"\n⏹️  Stopping... (processed {self.message_count} messages)")
            print("📤 Flushing remaining API messages...")
            self.api_client.flush()
            self.client.disconnect()
            self.client.loop_stop()
            print("✅ Shutdown complete")
        except Exception as e:
            print(f"❌ Error: {e}")
            # Try to flush messages even on error
            try:
                self.api_client.flush()
            except:
                pass
            try:
                self.client.loop_stop()
            except:
                pass

def load_config() -> MQTTConfig:
    """Load configuration from .env file"""
    # Load .env file from the main folder
    env_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), '.env')
    load_dotenv(env_path)
    
    return MQTTConfig(
        host=os.getenv('MQTT_HOST', 'mqtt.meshtastic.org'),
        port=int(os.getenv('MQTT_PORT', '1883')),
        client_id=os.getenv('MQTT_CLIENT_ID', 'cnymesh_monitor'),
        username=os.getenv('MQTT_USERNAME', 'meshdev'),
        password=os.getenv('MQTT_PASSWORD', 'large4cats'),
        topic=os.getenv('MQTT_TOPIC', 'msh/US/#').strip('"'),
        keepalive=int(os.getenv('MQTT_KEEPALIVE', '60')),
        clean_session=bool(int(os.getenv('MQTT_CLEAN_SESSION', '1')))
    )

def main():
    """Main entry point"""
    print("🔄 Meshtastic MQTT Monitor & Decoder")
    print("📁 Loading configuration from .env file...")
    
    try:
        config = load_config()
        monitor = MQTTMonitor(config)
        monitor.start()
    except Exception as e:
        print(f"❌ Fatal error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
