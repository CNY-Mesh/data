<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Position Data</h2>
        <p class="text-muted mb-0">Real-time location tracking from mesh network nodes</p>
    </div>
    <div class="text-end">
        <!-- Auto-refresh controls -->
        <div class="d-flex align-items-center justify-content-end mb-2">
            <span class="badge bg-secondary me-2" id="refreshCountdown">30</span>
            <button class="btn btn-sm btn-outline-primary me-2" id="refreshNow" title="Refresh now">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="toggleAutoRefresh" title="Toggle auto-refresh">
                <i class="fas fa-pause"></i>
            </button>
            <div class="spinner-border spinner-border-sm ms-2 d-none" id="loadingSpinner" role="status"></div>
        </div>
        <div class="small text-muted" id="positionCount">
            <?= count($positions) ?> positions shown
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="filter-section">
    <details open>
        <summary><i class="fas fa-filter"></i> Filters</summary>
        <form method="GET" action="" class="filters-form">
            <input type="hidden" name="r" value="positions">
            <div class="filter-row">
                <!-- Node Filter -->
                <div class="filter-group">
                    <label for="node_num">Node Number</label>
                    <input type="number" id="node_num" name="node_num" 
                           value="<?= htmlspecialchars($filters['node_num']) ?>" 
                           placeholder="e.g. 123456789">
                </div>
                
                <!-- Coordinate Filters -->
                <div class="filter-group">
                    <label>Latitude Range</label>
                    <div class="range-inputs">
                        <input type="number" step="0.000001" name="lat_min" 
                               value="<?= htmlspecialchars($filters['lat_min']) ?>" 
                               placeholder="Min Lat">
                        <span>to</span>
                        <input type="number" step="0.000001" name="lat_max" 
                               value="<?= htmlspecialchars($filters['lat_max']) ?>" 
                               placeholder="Max Lat">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label>Longitude Range</label>
                    <div class="range-inputs">
                        <input type="number" step="0.000001" name="lon_min" 
                               value="<?= htmlspecialchars($filters['lon_min']) ?>" 
                               placeholder="Min Lon">
                        <span>to</span>
                        <input type="number" step="0.000001" name="lon_max" 
                               value="<?= htmlspecialchars($filters['lon_max']) ?>" 
                               placeholder="Max Lon">
                    </div>
                </div>
            </div>
            
            <div class="filter-row">
                <!-- Date Filters -->
                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" 
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                
                <!-- Additional Filters -->
                <div class="filter-group">
                    <label for="has_altitude">Has Altitude</label>
                    <select id="has_altitude" name="has_altitude">
                        <option value="">Any</option>
                        <option value="yes" <?= $filters['has_altitude'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="no" <?= $filters['has_altitude'] === 'no' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="limit">Results Limit</label>
                    <select id="limit" name="limit">
                        <option value="50" <?= $filters['limit'] == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $filters['limit'] == 100 ? 'selected' : '' ?>>100</option>
                        <option value="250" <?= $filters['limit'] == 250 ? 'selected' : '' ?>>250</option>
                        <option value="500" <?= $filters['limit'] == 500 ? 'selected' : '' ?>>500</option>
                        <option value="1000" <?= $filters['limit'] == 1000 ? 'selected' : '' ?>>1000</option>
                    </select>
                </div>
            </div>
            
            <!-- Buttons -->
            <div class="filter-buttons">
                <button type="submit"><i class="fas fa-search"></i> Apply Filters</button>
                <a href="?r=positions" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <button type="button" onclick="useCurrentLocation()"><i class="fas fa-crosshairs"></i> Use My Location</button>
                <button type="button" onclick="exportData()"><i class="fas fa-download"></i> Export CSV</button>
            </div>
        </form>
    </details>
</div>

<!-- Refreshable content container -->
<div id="refreshableContent">
    <?php include 'positions_content.php'; ?>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
/* Layout-only styles - colors handled by main CSS */
.filter-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.filter-group input, .filter-group select {
    width: 100%;
    padding: 8px;
    border-radius: 3px;
}

.range-inputs {
    display: flex;
    align-items: center;
    gap: 10px;
}

.range-inputs input {
    flex: 1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
}

.stat-label {
    font-size: 14px;
}

.coverage-info {
    text-align: center;
}

.map-controls {
    margin-bottom: 20px;
}

.map-controls button {
    padding: 10px 15px;
    border-radius: 3px;
    cursor: pointer;
}

.map-container {
    margin-bottom: 20px;
    border-radius: 5px;
    overflow: hidden;
}

.results-section h3 {
    margin-bottom: 15px;
}

.table-container {
    overflow-x: auto;
    border-radius: 5px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons button, .action-buttons a {
    padding: 5px 8px;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
}

.no-results {
    padding: 20px;
    text-align: center;
    font-style: italic;
}
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let positionsMap = null;
let mapVisible = false;

function toggleMap() {
    const container = document.getElementById('mapContainer');
    const toggleText = document.getElementById('mapToggleText');
    
    if (mapVisible) {
        container.style.display = 'none';
        toggleText.textContent = 'Show Map';
        mapVisible = false;
    } else {
        container.style.display = 'block';
        toggleText.textContent = 'Hide Map';
        mapVisible = true;
        
        if (!positionsMap) {
            initMap();
        }
    }
}

function initMap() {
    // Center on Central NY if no specific coordinates
    const centerLat = <?= $stats['min_lat'] && $stats['max_lat'] ? (($stats['min_lat'] + $stats['max_lat']) / 2) : 43.0481 ?>;
    const centerLon = <?= $stats['min_lon'] && $stats['max_lon'] ? (($stats['min_lon'] + $stats['max_lon']) / 2) : -76.1474 ?>;
    
    positionsMap = L.map('positionsMap').setView([centerLat, centerLon], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(positionsMap);
    
    // Add all positions to map
    const positions = <?= json_encode($positions) ?>;
    positions.forEach(pos => {
        const isKnown = pos.is_known_node;
        const color = isKnown ? 'green' : 'red';
        const name = pos.long_name || 'Node ' + pos.node_id;
        
        const marker = L.circleMarker([pos.lat, pos.lon], {
            color: color,
            fillColor: color,
            fillOpacity: 0.6,
            radius: 6
        }).addTo(positionsMap);
        
        marker.bindPopup(`
            <strong>${name}</strong><br>
            Node: ${pos.node_num}<br>
            Lat: ${pos.lat}<br>
            Lon: ${pos.lon}<br>
            ${pos.altitude > 0 ? 'Alt: ' + pos.altitude + 'm<br>' : ''}
            Time: ${new Date(pos.time * 1000).toLocaleString()}
        `);
    });
    
    // Fit bounds if we have positions
    if (positions.length > 0) {
        const group = new L.featureGroup(positionsMap._layers);
        positionsMap.fitBounds(group.getBounds().pad(0.1));
    }
}

function showOnMap(lat, lon, name) {
    if (!mapVisible) {
        toggleMap();
    }
    
    setTimeout(() => {
        if (positionsMap) {
            positionsMap.setView([lat, lon], 15);
            L.popup()
                .setLatLng([lat, lon])
                .setContent(`<strong>${name}</strong><br>Lat: ${lat}<br>Lon: ${lon}`)
                .openOn(positionsMap);
        }
    }, 100);
}

function copyCoordinates(lat, lon) {
    const coords = `${lat}, ${lon}`;
    navigator.clipboard.writeText(coords).then(() => {
        alert('Coordinates copied: ' + coords);
    });
}

function useCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            const accuracy = position.coords.accuracy; // meters
            
            // Set a reasonable search radius based on accuracy
            const radius = Math.max(0.01, accuracy / 111320); // Convert meters to degrees (rough)
            
            document.querySelector('[name="lat_min"]').value = (lat - radius).toFixed(6);
            document.querySelector('[name="lat_max"]').value = (lat + radius).toFixed(6);
            document.querySelector('[name="lon_min"]').value = (lon - radius).toFixed(6);
            document.querySelector('[name="lon_max"]').value = (lon + radius).toFixed(6);
            
            alert(`Location set! Searching within ~${Math.round(accuracy)}m of your position.`);
        }, function(error) {
            alert('Unable to get your location: ' + error.message);
        });
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}

function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    // Ensure we keep the positions route
    if (!params.has('r')) {
        params.set('r', 'positions');
    }
    window.location.href = '?' + params.toString();
}

// Auto-submit form on Enter in coordinate fields
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.querySelector('form').submit();
        }
    });
});

// Initialize auto-refresh for Positions page
document.addEventListener('DOMContentLoaded', function() {
    // Build AJAX URL with current filters
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('ajax', '1');
    
    const autoRefresh = new AutoRefresh(currentUrl.toString(), {
        refreshButtonId: 'refreshNow',
        toggleButtonId: 'toggleAutoRefresh',
        countdownId: 'refreshCountdown',
        loadingSpinnerId: 'loadingSpinner',
        contentContainerId: 'refreshableContent',
        interval: 30000,
        onRefresh: function(data) {
            // Update position count
            const positionCountElement = document.getElementById('positionCount');
            if (positionCountElement && data.positionCount !== undefined) {
                positionCountElement.innerHTML = data.positionCount + ' positions shown';
            }
            
            // Re-initialize map markers if map is visible
            if (typeof initializeMap === 'function' && document.getElementById('mapContainer').style.display !== 'none') {
                initializeMap();
            }
        }
    });
});
</script>
