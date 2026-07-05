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

Send a sample POST request to the API endpoint to verify it is working:

```bash
curl -X POST "http://localhost:8080/api?a=mesh_data" \
  -H "Content-Type: application/json" \
  -d '{"messages":[{"topic":"msh/US/2/json/LongFast/!test123","timestamp":1700000000,"json_data":{"channel":0,"from":1128011160,"to":4294967295,"type":"position","payload":{"latitude_i":387809280,"longitude_i":-905936896,"altitude":134,"time":1700000000},"rssi":-95,"snr":7.25}}]}'
```

This sends sample data to verify the endpoint is working correctly.

## Error Handling

The API includes comprehensive error handling:
- Invalid JSON returns 400 Bad Request
- Database errors return 500 Internal Server Error  
- Individual message parsing errors are logged but don't stop batch processing
- All errors are returned in the response for debugging

## Remote Debugging Endpoint

For remote troubleshooting without shell access, a secure read-only debug endpoint is available.

### Endpoint
**GET** `/?r=api&a=debug_bundle`

### Authentication
Set `DEBUG_ENDPOINT_KEY` in the server environment or `.env`.

Pass the key either as:
- query parameter: `key=...`
- header: `X-Debug-Key: ...`

If `DEBUG_ENDPOINT_KEY` is not configured, the endpoint returns `403`.

### Example
```bash
curl "https://your-host/?r=api&a=debug_bundle&minutes=30&limit=50&key=YOUR_DEBUG_ENDPOINT_KEY"
```

### Returned Data
- recent raw messages (bounded)
- recent decode errors
- port/message summary over a recent window
- latest node and position freshness
- bounded tail of `data/mqtt_worker.log`

This endpoint is intended for live diagnostics and should only be shared with trusted operators.
