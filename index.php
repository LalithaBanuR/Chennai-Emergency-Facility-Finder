
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chennai Emergency Facilities</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;color:#222}
header{background:#17365d;color:white;padding:15px}
header h2{margin:0 0 5px;font-size:21px}
header p{margin:0;font-size:13px}
#controls{padding:12px;display:flex;flex-wrap:wrap;gap:9px;align-items:center;background:#f3f5f7}
select,input,button{padding:9px;border:1px solid #bbb;border-radius:5px;font-size:14px}
button{background:#17365d;color:white;border:0;cursor:pointer}
button:hover{background:#28558a}
#map{height:65vh;min-height:380px;width:100%}
#info{padding:12px;background:white;line-height:1.6}
#results{padding:0 12px 15px}
.facility{border:1px solid #ddd;border-radius:6px;padding:10px;margin:8px 0;cursor:pointer}
.facility:hover{background:#f0f5fb}
.facility button{margin-top:6px}
#message{font-weight:bold}
.legend{background:white;padding:8px 10px;line-height:1.7;border-radius:5px;box-shadow:0 1px 5px #aaa}
.dot{display:inline-block;width:11px;height:11px;border-radius:50%;margin-right:5px}
@media(max-width:600px){#map{height:58vh}select,input,button{max-width:100%}}
</style>
</head>
<body>
<header>
<h2>Chennai Emergency Facilities</h2>
<p>Find emergency facilities and calculate the shortest available road route</p>
</header>

<div id="controls">
<label for="facilityType">Facility:</label>
<select id="facilityType">
<option value="">All Facilities</option>
<option value="Hospital">Hospital</option>
<option value="Police">Police Station</option>
<option value="Fire Station">Fire Station</option>
<option value="Ambulance">Ambulance</option>
<option value="Pharmacy">Pharmacy</option>
</select>

<label for="radius">Search radius:</label>
<select id="radius">
<option value="5">5 km</option>
<option value="10" selected>10 km</option>
<option value="20">20 km</option>
<option value="50">50 km</option>
<option value="100">100 km</option>
</select>

<button id="locationBtn">Use My Location</button>
<button id="findBtn">Find Facilities</button>
<button id="shortestBtn">Find Shortest Road Route</button>
<button id="clearBtn">Clear Route</button>
</div>

<div id="map"></div>

<div id="info">
<div id="startInfo">Starting location: Click on the map or use your location.</div>
<div id="message">Choose a starting location to begin.</div>
<div id="routeInfo"></div>
</div>

<div id="results">
<h3>Available Facilities</h3>
<div id="facilityList">Select a location to load facilities.</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([13.0827,80.2707],12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxZoom:19,
    attribution:'&copy; OpenStreetMap contributors'
}).addTo(map);

let startLocation = null;
let startMarker = null;
let routeLayer = null;
let facilityLayer = L.layerGroup().addTo(map);
let facilityFeatures = [];
let facilityMarkers = {};
let routeRequestId = 0;

const colors = {
    'Hospital':'red',
    'Police':'blue',
    'Fire Station':'orange',
    'Ambulance':'purple',
    'Pharmacy':'green'
};

const message = document.getElementById('message');
const routeInfo = document.getElementById('routeInfo');
const facilityList = document.getElementById('facilityList');

const legend = L.control({position:'bottomright'});
legend.onAdd = function(){
    const div = L.DomUtil.create('div','legend');
    div.innerHTML =
        '<b>Legend</b><br>'+
        '<span class="dot" style="background:green"></span>Your location<br>'+
        '<span class="dot" style="background:red"></span>Hospital<br>'+
        '<span class="dot" style="background:blue"></span>Police Station<br>'+
        '<span class="dot" style="background:orange"></span>Fire Station<br>'+
        '<span class="dot" style="background:purple"></span>Ambulance<br>'+
        '<span class="dot" style="background:#16803c"></span>Pharmacy<br>'+
        '<span style="color:#0066ff">━━</span> Road route';
    return div;
};
legend.addTo(map);

function setMessage(text){
    message.textContent = text;
}

function clearRoute(){
    routeRequestId++;
    if(routeLayer){
        map.removeLayer(routeLayer);
        routeLayer = null;
    }
    routeInfo.textContent = '';
}

function setStartLocation(latlng, zoom=true){
    startLocation = L.latLng(latlng.lat,latlng.lng);

    if(startMarker) map.removeLayer(startMarker);

    startMarker = L.circleMarker(startLocation,{
        radius:8,
        color:'#087f23',
        fillColor:'#16a34a',
        fillOpacity:1,
        weight:3
    }).addTo(map).bindPopup('Your selected starting location');

    document.getElementById('startInfo').textContent =
        'Starting location: '+startLocation.lat.toFixed(6)+', '+startLocation.lng.toFixed(6);

    clearRoute();

    if(zoom) map.setView(startLocation,14);

    loadFacilities();
}

map.on('click',function(e){
    setStartLocation(e.latlng,false);
});

document.getElementById('locationBtn').addEventListener('click',function(){
    if(!navigator.geolocation){
        alert('Geolocation is not supported by this browser.');
        return;
    }

    setMessage('Getting your location...');

    navigator.geolocation.getCurrentPosition(
        function(position){
            setStartLocation({
                lat:position.coords.latitude,
                lng:position.coords.longitude
            });
        },
        function(){
            setMessage('Could not get your location. Click on the map instead.');
            alert('Location access failed. Allow location access or click the map.');
        },
        {enableHighAccuracy:true,timeout:10000}
    );
});

document.getElementById('findBtn').addEventListener('click',function(){
    if(!startLocation){
        alert('Click on the map or use your location first.');
        return;
    }
    loadFacilities();
});

document.getElementById('facilityType').addEventListener('change',function(){
    if(startLocation) loadFacilities();
});

document.getElementById('radius').addEventListener('change',function(){
    if(startLocation) loadFacilities();
});

document.getElementById('clearBtn').addEventListener('click',function(){
    clearRoute();
    setMessage('Route cleared. Your location and facilities remain visible.');
});

async function loadFacilities(){
    if(!startLocation) return;

    clearRoute();
    facilityLayer.clearLayers();
    facilityFeatures = [];
    facilityMarkers = {};
    facilityList.textContent = 'Loading facilities...';
    setMessage('Searching for facilities...');

    const params = new URLSearchParams({
        lon:startLocation.lng,
        lat:startLocation.lat,
        radius:document.getElementById('radius').value,
        type:document.getElementById('facilityType').value
    });

    try{
        const response = await fetch('nearest.php?'+params.toString());
        const data = await response.json();

        if(!response.ok || data.error){
            throw new Error(data.error || 'Failed to load facilities.');
        }

        facilityFeatures = data.features || [];
        facilityList.innerHTML = '';

        if(facilityFeatures.length === 0){
            facilityList.textContent = 'No facilities found within the selected radius.';
            setMessage('No matching facilities found.');
            return;
        }

        facilityFeatures.forEach(function(feature){
            const p = feature.properties;
            const coords = feature.geometry.coordinates;
            const latlng = L.latLng(coords[1],coords[0]);
            const color = colors[p.service_type] || '#444';

            const marker = L.circleMarker(latlng,{
                radius:7,
                color:color,
                fillColor:color,
                fillOpacity:0.85,
                weight:2
            }).addTo(facilityLayer);

            marker.bindPopup(
                '<b>'+escapeHTML(p.name || 'Facility')+'</b><br>'+
                escapeHTML(p.service_type || '')+'<br>'+
                escapeHTML(p.address || 'Address unavailable')+'<br>'+
                'Straight-line distance: '+Number(p.distance_km || 0).toFixed(2)+' km<br>'+
                '<button onclick="selectFacility('+Number(p.id)+')">Calculate road route</button>'
            );

            marker.on('click',function(){
                selectFacility(p.id);
            });

            facilityMarkers[p.id] = marker;

            const item = document.createElement('div');
            item.className = 'facility';

            const title = document.createElement('b');
            title.textContent = p.name || 'Unnamed facility';

            const details = document.createElement('div');
            details.textContent =
                (p.service_type || 'Facility')+' | Straight-line distance: '+
                Number(p.distance_km || 0).toFixed(2)+' km';

            const address = document.createElement('div');
            address.textContent = p.address || 'Address unavailable';

            const button = document.createElement('button');
            button.textContent = 'Find road route';
            button.addEventListener('click',function(e){
                e.stopPropagation();
                selectFacility(p.id);
            });

            item.append(title,details,address,button);
            item.addEventListener('click',function(){
                map.setView(latlng,15);
                marker.openPopup();
            });

            facilityList.appendChild(item);
        });

        setMessage(facilityFeatures.length+' facilities loaded. Calculating road routes will identify the shortest available route.');

    }catch(error){
        console.error(error);
        facilityList.textContent = 'Could not load facilities. Check nearest.php and the database connection.';
        setMessage(error.message);
    }
}

async function selectFacility(id){
    const feature = facilityFeatures.find(function(f){
        return String(f.properties.id) === String(id);
    });

    if(!feature){
        setMessage('Facility not found.');
        return;
    }

    if(!startLocation){
        alert('Select a starting location first.');
        return;
    }

    const coords = feature.geometry.coordinates;
    const destination = L.latLng(coords[1],coords[0]);
    await calculateRoute(startLocation,destination,feature.properties);
}

async function calculateRoute(start,destination,facility){
    clearRoute();
    const requestId = routeRequestId;
    setMessage('Calculating road route to '+(facility.name || 'facility')+'...');

    const url =
        'https://router.project-osrm.org/route/v1/driving/'+
        start.lng+','+start.lat+';'+destination.lng+','+destination.lat+
        '?overview=full&geometries=geojson&alternatives=false&steps=false';

    try{
        const response = await fetch(url);
        const data = await response.json();

        if(requestId !== routeRequestId) return;

        if(!response.ok || data.code !== 'Ok' || !data.routes || !data.routes.length){
            setMessage('No drivable road route found for this facility.');
            return;
        }

        const route = data.routes[0];

        routeLayer = L.geoJSON(route.geometry,{
            style:{
                color:'#0066ff',
                weight:6,
                opacity:0.85
            }
        }).addTo(map);

        L.popup()
            .setLatLng(destination)
            .setContent(
                '<b>'+escapeHTML(facility.name || 'Facility')+'</b><br>'+
                escapeHTML(facility.service_type || '')+'<br>'+
                'Road distance: '+(route.distance/1000).toFixed(2)+' km<br>'+
                'Estimated driving time: '+Math.round(route.duration/60)+' minutes'
            )
            .openOn(map);

        map.fitBounds(routeLayer.getBounds(),{padding:[35,35]});

        routeInfo.textContent =
            'Selected facility: '+(facility.name || 'Facility')+
            ' | Road distance: '+(route.distance/1000).toFixed(2)+
            ' km | Estimated driving time: '+Math.round(route.duration/60)+' minutes';

        setMessage('Road route calculated successfully.');

    }catch(error){
        console.error(error);
        if(requestId === routeRequestId){
            setMessage('Routing service unavailable. Please try again later.');
        }
    }
}

async function findShortestRoadRoute(){
    if(!startLocation){
        alert('Click on the map or use your location first.');
        return;
    }

    if(!facilityFeatures.length){
        alert('Find facilities first.');
        return;
    }

    clearRoute();
    const requestId = routeRequestId;
    setMessage('Comparing road distances for '+facilityFeatures.length+' facilities...');

    const candidates = facilityFeatures.map(function(feature){
        return {
            feature:feature,
            coords:feature.geometry.coordinates
        };
    });

    const batchSize = 5;
    let best = null;

    try{
        for(let i=0;i<candidates.length;i+=batchSize){
            if(requestId !== routeRequestId) return;

            const batch = candidates.slice(i,i+batchSize);

            const results = await Promise.all(batch.map(async function(candidate){
                const coords = candidate.coords;
                const url =
                    'https://router.project-osrm.org/route/v1/driving/'+
                    startLocation.lng+','+startLocation.lat+';'+coords[0]+','+coords[1]+
                    '?overview=false&alternatives=false&steps=false';

                try{
                    const response = await fetch(url);
                    const data = await response.json();

                    if(data.code !== 'Ok' || !data.routes || !data.routes.length){
                        return null;
                    }

                    return {
                        feature:candidate.feature,
                        route:data.routes[0]
                    };
                }catch(error){
                    return null;
                }
            }));

            results.forEach(function(result){
                if(result && (!best || result.route.distance < best.route.distance)){
                    best = result;
                }
            });

            setMessage('Checking road routes: '+Math.min(i+batchSize,candidates.length)+' of '+candidates.length+' facilities...');
        }

        if(requestId !== routeRequestId) return;

        if(!best){
            setMessage('No drivable routes found for the facilities.');
            return;
        }

        const p = best.feature.properties;
        const coords = best.feature.geometry.coordinates;

        await calculateRoute(
            startLocation,
            L.latLng(coords[1],coords[0]),
            p
        );

        if(requestId === routeRequestId){
            setMessage('Shortest road route found among the facilities with available OSRM routes.');
        }

    }catch(error){
        console.error(error);
        setMessage('Unable to compare road routes. Please try again.');
    }
}

document.getElementById('shortestBtn').addEventListener('click',findShortestRoadRoute);

function escapeHTML(value){
    return String(value).replace(/[&<>"']/g,function(ch){
        return {
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#39;'
        }[ch];
    });
}
</script>
</body>
</html>
