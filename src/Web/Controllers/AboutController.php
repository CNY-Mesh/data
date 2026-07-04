<?php
declare(strict_types=1);

namespace App\Web\Controllers;

use App\Support\Env;

class AboutController extends BaseController
{
    public function handle(): void
    {
        // Get environment configuration for display
        $mqttServer = Env::get('MQTT_SERVER', 'mqtt.meshtastic.org');
        $mqttTopic = Env::get('MQTT_TOPIC', 'msh/US/NY/CNY/#');
        $siteName = Env::get('SITE_NAME', 'CNY Mesh Data Dashboard');
        
        $this->render('about', [
            'mqttServer' => $mqttServer,
            'mqttTopic' => $mqttTopic,
            'siteName' => $siteName
        ]);
    }
}
