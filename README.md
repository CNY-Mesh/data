# Meshtastic MQTT PHP (envfix)

- MQTT worker subscribes to Meshtastic regional topics and writes to SQLite.
- Web UI (Leaflet + Chart.js) for nodes, positions, neighbors, telemetry, traceroutes.
- Uses .env via phpdotenv; reads vars with Env::get() (so getenv() quirks won't bite).

## Quick start
```bash
composer install
sudo apt update && sudo apt install -y protobuf-compiler
bash scripts/generate_protos.sh
sqlite3 data/meshtastic.sqlite < schema/sqlite.sql

cp .env.example .env
# edit values as needed

php -r 'require "bootstrap.php"; var_dump(\App\Support\Env::get("MQTT_USERNAME"), \App\Support\Env::get("MQTT_TOPIC"));'
# should print strings

composer run       # start the MQTT worker
composer serve     # web UI at http://localhost:8080
```

## Verify environment loading
```
php -r 'require "bootstrap.php"; var_dump(\App\Support\Env::get("MQTT_USERNAME"), \App\Support\Env::get("MQTT_TOPIC"));'
```
