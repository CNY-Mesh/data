-- Custom messages table for unknown port analysis
CREATE TABLE IF NOT EXISTS custom_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    port_num INTEGER NOT NULL,
    node_from INTEGER NOT NULL,
    payload_length INTEGER NOT NULL,
    payload_type TEXT NOT NULL,
    has_text BOOLEAN DEFAULT 0,
    has_binary BOOLEAN DEFAULT 0,
    created_at INTEGER NOT NULL,
    
    INDEX idx_custom_port_node (port_num, node_from),
    INDEX idx_custom_created (created_at),
    INDEX idx_custom_type (payload_type)
);
