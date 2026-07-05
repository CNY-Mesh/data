#!/usr/bin/env python3
"""
Test script to simulate decode errors and verify they are sent to API
Upload this to the server bin/ directory and run with: python test_decode_error_simulation.py
"""

import sys
import os
import time
import json

# Add the current directory to Python path so we can import main
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    from main import ApiClient, MeshtasticDecoder
except ImportError as e:
    print(f"❌ Failed to import main modules: {e}")
    print("Make sure this script is in the same directory as main.py")
    sys.exit(1)

def test_decode_error_scenarios():
    """Test various decode error scenarios"""
    
    print("🧪 Testing Decode Error Scenarios")
    print("=" * 50)
    
    # Initialize API client (use local endpoint for testing)
    api_client = ApiClient("http://localhost/?r=api&a=mesh_data")
    decoder = MeshtasticDecoder()
    
    # Test 1: Send a manual decode error
    print("\n🧪 Test 1: Manual decode error via API")
    manual_error = {
        'decode_error': True,
        'error_message': 'Simulated decode error from Python test script',
        'likely_encrypted': False,
        'size': 42,
        'hex_preview': 'deadbeef12345678',
        'type': 'decode_error',
        'from': None,
        'to': None,
        'rssi': None,
        'snr': None
    }
    
    api_client.add_message(
        topic='test/python_simulation',
        timestamp=int(time.time()),
        json_data=manual_error
    )
    
    print("✅ Manual decode error added to batch")
    
    # Test 2: Try to decode invalid protobuf data (should trigger None return)
    print("\n🧪 Test 2: Invalid protobuf data (triggers None return)")
    invalid_data = b'\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09'  # Invalid protobuf
    
    decoded_result = decoder.decode_service_envelope(invalid_data)
    
    if decoded_result is None:
        print("✅ Invalid data returned None - this would trigger complete decode failure")
        
        # Simulate what the main script now does for None results
        complete_failure_data = {
            'decode_error': True,
            'error_message': 'Complete decode failure - data does not appear to be valid protobuf',
            'likely_encrypted': False,
            'size': len(invalid_data),
            'hex_preview': invalid_data.hex()[:64],
            'type': 'decode_error',
            'from': None,
            'to': None,
            'rssi': None,
            'snr': None
        }
        
        api_client.add_message(
            topic='test/invalid_protobuf',
            timestamp=int(time.time()),
            json_data=complete_failure_data
        )
        
        print("✅ Complete decode failure added to batch")
    else:
        print(f"⚠️  Expected None but got: {decoded_result}")
    
    # Test 3: Try encrypted data that should fail decryption
    print("\n🧪 Test 3: Encrypted data that fails decryption")
    encrypted_data = b'\x08\x01\x12\x20' + b'\xff' * 32  # Fake encrypted ServiceEnvelope
    
    decoded_result = decoder.decode_service_envelope(encrypted_data)
    
    if decoded_result and 'error' in decoded_result:
        print(f"✅ Encrypted data returned error: {decoded_result['error']}")
        
        # This would be handled by the 'error' in decoded block
        error_data = {
            'decode_error': True,
            'error_message': decoded_result['error'],
            'likely_encrypted': decoded_result.get('likely_encrypted', False),
            'size': decoded_result.get('basic_info', {}).get('size', 0),
            'hex_preview': decoded_result.get('basic_info', {}).get('hex', '')[:64],
            'type': 'decode_error',
            'from': None,
            'to': None,
            'rssi': None,
            'snr': None
        }
        
        api_client.add_message(
            topic='test/encryption_failure',
            timestamp=int(time.time()),
            json_data=error_data
        )
        
        print("✅ Encryption failure error added to batch")
    else:
        print(f"⚠️  Expected error but got: {decoded_result}")
    
    # Flush all messages to API
    print(f"\n📤 Sending {len(api_client.batch_messages)} test messages to API...")
    api_client.flush()
    
    print("✅ All test decode errors sent to API")
    print("\n🔍 Check the database now for these test decode errors:")
    print("   - Manual decode error from Python test script")
    print("   - Complete decode failure - invalid protobuf")
    print("   - Encryption failure error")
    print("\n💡 Open /test_decode_error_fix.php on the web server to verify they were stored")

if __name__ == "__main__":
    test_decode_error_scenarios()
