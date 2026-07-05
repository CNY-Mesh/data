-- Database Schema Export
-- Generated on: 2025-09-16 20:24:02
-- Database file: /var/www/cny-mesh/data/data/meshtastic.sqlite

PRAGMA journal_mode=WAL;

CREATE TABLE IF NOT EXISTS nodes (
  node_num     INTEGER PRIMARY KEY,
  node_id      TEXT,
  long_name    TEXT,
  short_name   TEXT,
  hardware     INTEGER,
  last_seen    INTEGER
);

CREATE TABLE IF NOT EXISTS positions (
  node_num   INTEGER PRIMARY KEY,
  lat        REAL,
  lon        REAL,
  altitude   INTEGER,
  time       INTEGER,
  rx_rssi    REAL,
  rx_snr     REAL
, topic TEXT);

CREATE TABLE IF NOT EXISTS neighbors (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  reporter_node_num  INTEGER,
  neighbor_node_num  INTEGER,
  snr                REAL,
  heard_at           INTEGER
, topic TEXT);

CREATE TABLE IF NOT EXISTS telemetry (
  node_num            INTEGER PRIMARY KEY,
  battery_level       REAL,
  voltage             REAL,
  channel_utilization REAL,
  air_util_tx         REAL,
  uptime_seconds      INTEGER,
  updated_at          INTEGER
, topic TEXT);

CREATE TABLE IF NOT EXISTS traceroutes (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  mesh_packet_id  INTEGER,
  src_node_num    INTEGER,
  dest_node_num   INTEGER,
  hop_index       INTEGER,
  hop_node_num    INTEGER,
  snr             REAL,
  logged_at       INTEGER
);

CREATE TABLE IF NOT EXISTS map_reports (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  node_num     INTEGER,
  channel_id   TEXT,
  lat          REAL,
  lon          REAL,
  raw_pb       BLOB,
  saved_at     INTEGER
);

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

CREATE TABLE IF NOT EXISTS text_messages (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  node_from    INTEGER,
  node_to      INTEGER,
  message      TEXT,
  rx_time      INTEGER
, topic TEXT, message_hash TEXT);

CREATE TABLE IF NOT EXISTS custom_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            port_num INTEGER NOT NULL,
            node_from INTEGER NOT NULL,
            payload_length INTEGER NOT NULL,
            payload_type TEXT NOT NULL,
            has_text BOOLEAN DEFAULT 0,
            has_binary BOOLEAN DEFAULT 0,
            created_at INTEGER NOT NULL
        );

-- Indexes
CREATE INDEX IF NOT EXISTS idx_raw_messages_port ON raw_messages(port_num);
CREATE INDEX IF NOT EXISTS idx_raw_messages_node_from ON raw_messages(node_from);
CREATE INDEX IF NOT EXISTS idx_raw_messages_channel ON raw_messages(channel_id);
CREATE INDEX IF NOT EXISTS idx_raw_messages_time ON raw_messages(rx_time);
CREATE INDEX IF NOT EXISTS idx_text_messages_node_from ON text_messages(node_from);
CREATE INDEX IF NOT EXISTS idx_text_messages_time ON text_messages(rx_time);
CREATE INDEX IF NOT EXISTS idx_positions_topic ON positions(topic);
CREATE INDEX IF NOT EXISTS idx_telemetry_topic ON telemetry(topic);
CREATE INDEX IF NOT EXISTS idx_text_messages_topic ON text_messages(topic);
CREATE INDEX IF NOT EXISTS idx_neighbors_topic ON neighbors(topic);

-- Table Statistics (as of export time)
-- Table 'nodes': 1354 rows
-- Table 'positions': 884 rows
-- Table 'neighbors': 812 rows
-- Table 'telemetry': 984 rows
-- Table 'traceroutes': 0 rows
-- Table 'map_reports': 0 rows
-- Table 'raw_messages': 124780 rows
-- Table 'text_messages': 688 rows
-- Table 'custom_messages': 0 rows
