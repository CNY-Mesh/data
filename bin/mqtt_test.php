#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Support\Env as E;

$host = 'mqtt.meshtastic.org';
$port = 1883;
$username = 'meshdev';
$password = 'large4cats';
$prefix = E::get('MQTT_CLIENT_ID', 'cnymesh');
$suffix = date('YmdHis') . '-' . rand(1000,9999);
$clientId = substr($prefix . '-' . $suffix, 0, 23);
$topic = 'msh/US/#';

try {
    echo "[DEBUG] Connecting to $host:$port as $clientId\n";
    $settings = (new ConnectionSettings())
        ->setUsername($username)
        ->setPassword($password);
    $client = new MqttClient($host, $port, $clientId);
    $client->connect($settings, true);
    echo "[DEBUG] Connected. Subscribing to $topic\n";
    $client->subscribe($topic, function ($topic, $message, $retained) {
        echo "[RECV] [$topic] $message\n";
    }, 0);
    echo "[DEBUG] Entering loop...\n";
    $client->loop(true);
    echo "[DEBUG] Loop ended. Disconnecting.\n";
    $client->disconnect();
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    var_dump($e);
}
