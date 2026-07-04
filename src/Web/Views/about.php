<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        About This Dashboard
                    </h2>
                </div>
                <div class="card-body">
                    <h4 class="text-primary mb-3">What is this site?</h4>
                    <p class="lead">
                        This dashboard provides real-time monitoring and analysis of Meshtastic mesh network activity 
                        in Central New York. It collects, processes, and visualizes data from the regional mesh network 
                        to help operators understand network performance, node activity, and communication patterns.
                    </p>

                    <div class="alert alert-warning mb-4" role="alert">
                        <h4 class="alert-heading text-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>Known Issues
                        </h4>
                        <h6 class="fw-bold">Encrypted LongFast Message Decoding</h6>
                        <p class="mb-3">
                            The system currently experiences difficulties decoding some or all encrypted LongFast messages, 
                            even when the correct encryption keys are provided. This is an ongoing technical limitation 
                            that affects the completeness of message data displayed in the dashboard.
                        </p>
                        <h6 class="fw-bold">Impact:</h6>
                        <ul class="mb-3">
                            <li>Some encrypted text messages may appear as raw/undecoded data</li>
                            <li>Certain telemetry and position updates may not display properly</li>
                            <li>Node information from encrypted channels may be incomplete</li>
                        </ul>
                        <p class="mb-0">
                            <small class="text-muted">
                                <i class="fas fa-tools me-1"></i>
                                This issue is actively being investigated and improved. Message decoding accuracy 
                                may vary depending on the specific encryption configuration used by different nodes.
                            </small>
                        </p>
                    </div>

                    <h4 class="text-primary mb-3 mt-4">How it works</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="feature-box p-3 border rounded mb-3">
                                <h5><i class="fas fa-satellite-dish text-primary me-2"></i>Data Collection</h5>
                                <p class="mb-0">Continuously monitors MQTT messages from Meshtastic devices in the mesh network.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-box p-3 border rounded mb-3">
                                <h5><i class="fas fa-database text-primary me-2"></i>Data Storage</h5>
                                <p class="mb-0">Stores telemetry, position data, text messages, and network topology information.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-box p-3 border rounded mb-3">
                                <h5><i class="fas fa-chart-line text-primary me-2"></i>Analytics</h5>
                                <p class="mb-0">Provides insights into network performance, node activity, and communication patterns.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-box p-3 border rounded mb-3">
                                <h5><i class="fas fa-map-marked-alt text-primary me-2"></i>Visualization</h5>
                                <p class="mb-0">Interactive maps, charts, and tables to explore network data and trends.</p>
                            </div>
                        </div>
                    </div>


                    <h4 class="text-primary mb-3 mt-4">Current Configuration</h4>
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="fas fa-server me-2"></i>MQTT Server:</strong><br>
                                <code><?= htmlspecialchars($mqttServer) ?></code>
                            </div>
                            <div class="col-md-6">
                                <strong><i class="fas fa-tag me-2"></i>MQTT Topic:</strong><br>
                                <code><?= htmlspecialchars($mqttTopic) ?></code>
                            </div>
                        </div>
                    </div>

                    <h4 class="text-primary mb-3 mt-4">Data Types Collected</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-map-pin text-success me-2"></i>Position Data</li>
                                <li><i class="fas fa-comments text-success me-2"></i>Text Messages</li>
                                <li><i class="fas fa-thermometer-half text-success me-2"></i>Telemetry</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-network-wired text-success me-2"></i>Node Information</li>
                                <li><i class="fas fa-users text-success me-2"></i>Neighbor Data</li>
                                <li><i class="fas fa-route text-success me-2"></i>Traceroutes</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-map text-success me-2"></i>Map Reports</li>
                                <li><i class="fas fa-broadcast-tower text-success me-2"></i>Signal Quality</li>
                                <li><i class="fas fa-battery-half text-success me-2"></i>Device Status</li>
                            </ul>
                        </div>
                    </div>

                    <h4 class="text-primary mb-3 mt-4">Features</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-eye text-primary me-2"></i>
                                    Real-time monitoring dashboard
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-map text-primary me-2"></i>
                                    Interactive node maps
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-chart-bar text-primary me-2"></i>
                                    Network performance analytics
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-search text-primary me-2"></i>
                                    Advanced search capabilities
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-history text-primary me-2"></i>
                                    Historical data analysis
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-download text-primary me-2"></i>
                                    Data export functionality
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-mobile-alt text-primary me-2"></i>
                                    Mobile-responsive design
                                </li>
                                <li class="list-group-item border-0 px-0">
                                    <i class="fas fa-lock text-primary me-2"></i>
                                    Secure access controls
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-light rounded">
                        <h4 class="text-primary mb-3">
                            <i class="fas fa-heart text-danger me-2"></i>
                            Sponsored by CNYmesh.org
                        </h4>
                        <p class="mb-2">
                            This dashboard is hosted and sponsored by <strong>CNYmesh.org</strong>, a community-driven 
                            organization dedicated to building and maintaining mesh networking infrastructure in Central New York.
                        </p>
                        <p class="mb-0">
                            <a href="https://cnymesh.org" target="_blank" class="btn btn-primary">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Visit CNYmesh.org
                            </a>
                        </p>
                    </div>

                    <div class="mt-4">
                        <h4 class="text-primary mb-3">Technical Stack</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary fs-6">PHP 8+</span>
                            <span class="badge bg-secondary fs-6">SQLite</span>
                            <span class="badge bg-secondary fs-6">Python MQTT Client</span>
                            <span class="badge bg-secondary fs-6">Bootstrap 5</span>
                            <span class="badge bg-secondary fs-6">Chart.js</span>
                            <span class="badge bg-secondary fs-6">Leaflet Maps</span>
                            <span class="badge bg-secondary fs-6">Meshtastic Protocol Buffers</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
