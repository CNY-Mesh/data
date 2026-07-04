# Mesh Data API Documentation

## Overview
The Mesh Data API allows the Python MQTT monitor script to send decoded Meshtastic messages to the PHP application for storage and analysis.

## Endpoint
**POST** `/api?a=mesh_data`

## Request Format
The API accepts JSON data with the following structure:

### Single Message
```json
{
  "topic": "msh/US/2/json/LongFast/!433c1598",
  "timestamp": 1758031009,
  "json_data": { ... },
  "decoded_packet": { ... }
}
```

### Multiple Messages
```json
{
  "messages": [
    {
      "topic": "msh/US/2/json/LongFast/!433c1598",
      "timestamp": 1758031009,
      "json_data": { ... }
    },
    {
      "topic": "msh/US/2/e/LongFast/!43b585fc", 
      "timestamp": 1758031010,
      "decoded_packet": { ... }
    }
  ]
}
```

## Message Types

### JSON Messages
Messages from topics ending in `/json/` contain parsed JSON data:
```json
{
  "topic": "msh/US/2/json/LongFast/!433c1598",
  "timestamp": 1758031009,
  "json_data": {
    "channel": 0,
    "from": 1128011160,
    "to": 4294967295,
    "type": "position",
    "payload": {
      "latitude_i": 387809280,
      "longitude_i": -905936896,
      "altitude": 134
    },
    "rssi": -95,
    "snr": 7.25
  }
}
```

### Decoded Packets
Messages from topics ending in `/e/` contain protobuf-decoded packet data:
```json
{
  "topic": "msh/US/2/e/LongFast/!43b585fc",
  "timestamp": 1758031010,
  "decoded_packet": {
    "from": 3777611546,
    "to": 4294967295,
    "decoded": {
      "portnum": 67,
      "portnum_name": "TELEMETRY_APP",
      "telemetry": {
        "battery_level": 85,
        "voltage": 4.12
      }
    }
  }
}
```

## Response Format

### Success Response
```json
{
  "success": true,
  "saved_count": 2,
  "error_count": 0,
  "errors": []
}
```

### Error Response
```json
{
  "error": "Invalid JSON"
}
```

## Supported Message Types

The API automatically processes and stores different types of mesh traffic:

### Position Data
- **JSON Type**: `"type": "position"`
- **Port Number**: 3 (POSITION_APP)
- **Storage**: Updates `positions` table with lat/lon coordinates

### Telemetry Data  
- **JSON Type**: `"type": "telemetry"`
- **Port Number**: 67 (TELEMETRY_APP)
- **Storage**: Updates `telemetry` table with battery, voltage, etc.

### Node Information
- **JSON Type**: `"type": "nodeinfo"`
- **Port Number**: 4 (NODEINFO_APP)
- **Storage**: Updates `nodes` table with device names and hardware info

### Text Messages
- **JSON Type**: `"type": "text"`
- **Port Number**: 1 (TEXT_MESSAGE_APP)
- **Storage**: Inserts into `text_messages` table

### Neighbor Information
- **JSON Type**: `"type": "neighborinfo"`
- **Port Number**: 71 (NEIGHBORINFO_APP)
- **Storage**: Inserts into `neighbors` table

### Map Reports
- **Port Number**: 73 (MAP_REPORT_APP)
- **Storage**: Inserts into `map_reports` table and updates node/position data

## Database Tables

All messages are stored in the `raw_messages` table for full history, while parsed data is stored in specific tables:

- `nodes` - Node information and last seen times
- `positions` - GPS coordinates and movement tracking  
- `telemetry` - Battery, voltage, and utilization metrics
- `neighbors` - Mesh network topology data
- `text_messages` - Chat messages between nodes
- `map_reports` - Network map and node status reports
- `raw_messages` - Complete message history with metadata

## Testing

Use the provided `test_api.php` script to test the API:

```bash
php test_api.php
```

This will send sample data to verify the endpoint is working correctly.

## Error Handling

The API includes comprehensive error handling:
- Invalid JSON returns 400 Bad Request
- Database errors return 500 Internal Server Error  
- Individual message parsing errors are logged but don't stop batch processing
- All errors are returned in the response for debugging
