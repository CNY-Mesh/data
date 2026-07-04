# Meshtastic Data Collection Enhancement Summary

## What We've Added

### 1. Expanded Port Number Support
Added constants for common Meshtastic ports in `src/Decoder.php`:
- `TEXT_MESSAGE_APP = 1` - Text messages
- `REPLY_APP = 32` - Reply messages  
- `IP_TUNNEL_APP = 33` - IP tunneling
- `SERIAL_APP = 64` - Serial data
- `STORE_FORWARD_APP = 65` - Store and forward
- `RANGE_TEST_APP = 66` - Range testing
- `ATAK_PLUGIN_APP = 72` - ATAK plugin
- `PRIVATE_APP = 256` - Private applications
- `ATAK_FORWARDER_APP = 257` - ATAK forwarder

### 2. New Database Tables
Added to `schema/sqlite.sql`:

#### `raw_messages` table
Captures ALL message data regardless of port number:
```sql
CREATE TABLE IF NOT EXISTS raw_messages (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  topic           TEXT,
  channel_id      TEXT,
  gateway_id      TEXT,
  node_from       INTEGER,
  node_to         INTEGER,
  port_num        INTEGER,
  payload_hex     TEXT,
  payload_length  INTEGER,
  is_encrypted    BOOLEAN,
  is_json         BOOLEAN,
  message_type    TEXT,
  rx_time         INTEGER,
  rx_rssi         REAL,
  rx_snr          REAL,
  raw_message     BLOB,
  processed_at    INTEGER DEFAULT (strftime('%s', 'now'))
);
```

#### `text_messages` table
Stores decoded text messages:
```sql
CREATE TABLE IF NOT EXISTS text_messages (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  node_from    INTEGER,
  node_to      INTEGER,
  message      TEXT,
  rx_time      INTEGER
);
```

### 3. New Handlers
Created handlers to store additional data types:

#### `RawMessageHandler` (`src/Handlers/RawMessageHandler.php`)
Stores ALL incoming messages for complete data capture and debugging.

#### `TextMessageHandler` (`src/Handlers/TextMessageHandler.php`) 
Stores decoded text messages from port 1.

### 4. Enhanced MqttWorker
Updated `src/MqttWorker.php` to:
- Always store raw message data using `RawMessageHandler`
- Handle text messages (port 1) using `TextMessageHandler`
- Store both JSON and binary message data
- Provide complete audit trail of all received messages

## To Deploy These Changes

1. **Apply database schema**:
   ```bash
   php update_schema.php
   ```

2. **Restart MQTT worker**:
   ```bash
   php bin/run.php
   ```

## What This Solves

### Before:
- Only specific ports (3, 4, 67, 70, 71, 73) were handled
- Messages with unknown ports were discarded
- No complete audit trail of received data
- Port 0 messages (heartbeats) were processed but not stored

### After:
- **ALL** messages are stored in `raw_messages` table
- Text messages (port 1) are decoded and stored
- Complete debugging information available
- Nothing is lost - even unknown port numbers are captured
- Easy to analyze what message types are being received
- Can add new handlers for specific ports as needed

## Expected Results

After deployment, you should see:
1. `raw_messages` table populated with ALL incoming MQTT data
2. `text_messages` table populated with decoded text messages
3. Continued population of existing tables (positions, telemetry, etc.)
4. Debug output showing successful storage of previously discarded messages

## Next Steps

1. Deploy these changes to your server
2. Monitor the `raw_messages` table to see what port numbers are most common
3. Create specific handlers for high-volume ports that need structured storage
4. Use the raw data to understand what message types your mesh network uses most

This provides a complete data capture solution while maintaining all existing functionality.
