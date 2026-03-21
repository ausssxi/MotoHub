/**
 * MotoHub AR Finder
 * Refactored from ar-original.php - shows nearby bike parkings & shops through camera
 */
import LatLon from "https://esm.sh/geodesy@2.4.0/latlon-ellipsoidal-vincenty.js";
import * as THREE from "three";
import { COF, Geomag } from "./wmm.js";

// --- State ---
let os;
let alpha = 0, beta = 0, gamma = 0, degrees = 0;
let videoSource, offscreenCanvas, viewCanvasContext;
let latitude, longitude, altitude = 0;
let scene, camera, renderer;
let magdec = 0;
let showParking = true, showShop = true;
let targets = [];
let markerGroups = []; // { group, labelGroup, target }
let arRunning = false;
let lastFetchTime = 0;
const FETCH_INTERVAL = 5000; // re-fetch every 5s

// --- OS Detection ---
function detectOS() {
    const ua = navigator.userAgent;
    if (/iPhone|iPad|iPod/.test(ua)) return "iphone";
    if (/Android/.test(ua)) return "android";
    return "pc";
}

// --- Orientation ---
function onOrientation(event) {
    alpha = event.alpha || 0;
    beta = event.beta || 0;
    gamma = event.gamma || 0;

    if (os === "iphone") {
        // iOS: webkitCompassHeading is true north heading
        if (event.webkitCompassHeading != null) {
            degrees = event.webkitCompassHeading + (magdec || 0);
            if (degrees < 0) degrees += 360;
            if (degrees >= 360) degrees -= 360;
        }
    } else {
        // Android: compute compass heading from alpha/beta/gamma
        degrees = computeCompassHeading(alpha, beta, gamma) + (magdec || 0);
        if (degrees < 0) degrees += 360;
        if (degrees >= 360) degrees -= 360;
    }
}

function computeCompassHeading(a, b, g) {
    const degtorad = Math.PI / 180;
    const _x = b ? b * degtorad : 0;
    const _y = g ? g * degtorad : 0;
    const _z = a ? a * degtorad : 0;

    const cX = Math.cos(_x);
    const cY = Math.cos(_y);
    const cZ = Math.cos(_z);
    const sX = Math.sin(_x);
    const sY = Math.sin(_y);
    const sZ = Math.sin(_z);

    const Vx = -cZ * sY - sZ * sX * cY;
    const Vy = -sZ * sY + cZ * sX * cY;

    let heading = Math.atan(Vx / Vy);
    if (Vy < 0) heading += Math.PI;
    else if (Vx < 0) heading += 2 * Math.PI;

    return heading * (180 / Math.PI);
}

function getRotationMatrix(a, b, g) {
    const d = Math.PI / 180;
    const cX = Math.cos(b * d), cY = Math.cos(g * d), cZ = Math.cos(a * d);
    const sX = Math.sin(b * d), sY = Math.sin(g * d), sZ = Math.sin(a * d);
    return [
        cY * sZ * sX + cZ * sY, cZ * cY - sZ * sX * sY, -cX * sZ,
        sZ * sY - cZ * cY * sX, cY * sZ + cZ * sX * sY, cZ * cX,
        cX * cY, cX * sY, sX
    ];
}

function getEulerAngles(m) {
    const r = 180 / Math.PI;
    const sy = Math.sqrt(m[0] * m[0] + m[3] * m[3]);
    if (sy < 1e-6) {
        return [Math.atan2(-m[5], m[4]) * r, Math.atan2(-m[6], sy) * r, 0];
    }
    return [
        Math.atan2(m[7], m[8]) * r,
        Math.atan2(-m[6], sy) * r,
        Math.atan2(m[3], m[0]) * r
    ];
}

// --- Camera ---
async function initCamera() {
    videoSource = document.createElement("video");
    const stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: { exact: "environment" },
            width: { ideal: window.innerWidth },
            height: { ideal: window.innerHeight }
        }
    });
    videoSource.muted = true;
    videoSource.playsInline = true;
    videoSource.srcObject = stream;
    await videoSource.play();

    const arCanvas = document.getElementById("ar-canvas");
    arCanvas.width = window.innerWidth;
    arCanvas.height = window.innerHeight;
    viewCanvasContext = arCanvas.getContext("2d");

    offscreenCanvas = document.createElement("canvas");
    offscreenCanvas.width = arCanvas.width;
    offscreenCanvas.height = arCanvas.height;

    return stream;
}

// --- Geolocation ---
function initGeolocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(new Error("Geolocation not supported"));

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                latitude = pos.coords.latitude;
                longitude = pos.coords.longitude;
                altitude = pos.coords.altitude || 0;
                updateMagDec();
                resolve();
            },
            reject,
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );

        // Watch for position updates
        navigator.geolocation.watchPosition(
            (pos) => {
                latitude = pos.coords.latitude;
                longitude = pos.coords.longitude;
                altitude = pos.coords.altitude || 0;
                updateMagDec();
            },
            () => {},
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    });
}

function updateMagDec() {
    try {
        const geomag = new Geomag(COF);
        const mag = geomag.calc(latitude, longitude, (altitude || 0) / 1000);
        magdec = mag.dec;
    } catch (e) {
        console.warn("WMM calc error:", e);
    }
}

// --- Three.js ---
function initThreeJS() {
    const w = window.innerWidth;
    const h = window.innerHeight;

    camera = new THREE.PerspectiveCamera(45, w / h, 1, 5000);
    const fovRad = (45 / 2) * (Math.PI / 180);
    const distance = (h / 2) / Math.tan(fovRad);
    camera.position.z = distance;

    scene = new THREE.Scene();
    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        canvas: offscreenCanvas
    });
    renderer.setSize(w, h);

    const light = new THREE.DirectionalLight(0xffffff, 1);
    light.position.set(0, 1, 1);
    scene.add(light);
    scene.add(new THREE.AmbientLight(0xffffff, 0.5));
}

// --- Create 3D Markers ---
function createMarker(target) {
    const group = new THREE.Group();

    const pinColor = target.type === "parking" ? 0x16a34a : 0x3b82f6;
    const pinMaterial = new THREE.MeshPhongMaterial({ color: pinColor });

    // Pin stem
    const cylinder = new THREE.Mesh(
        new THREE.CylinderGeometry(15, 15, 60, 16),
        pinMaterial
    );
    cylinder.position.y = -30;
    group.add(cylinder);

    // Pin head
    const sphere = new THREE.Mesh(
        new THREE.SphereGeometry(25, 16, 16),
        pinMaterial
    );
    group.add(sphere);

    // Icon on pin
    const iconCanvas = document.createElement("canvas");
    iconCanvas.width = 64;
    iconCanvas.height = 64;
    const ctx = iconCanvas.getContext("2d");
    ctx.fillStyle = "white";
    ctx.font = "900 36px system-ui";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(target.type === "parking" ? "P" : "S", 32, 32);

    const iconTexture = new THREE.Texture(iconCanvas);
    iconTexture.needsUpdate = true;
    const iconPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(40, 40),
        new THREE.MeshBasicMaterial({ map: iconTexture, transparent: true, side: THREE.DoubleSide })
    );
    iconPlane.position.z = 26;
    group.add(iconPlane);

    return group;
}

function createTextCanvas(text, { fontSize = 24, bgColor = "rgba(0,0,0,0.7)", textColor = "white", padding = 8 } = {}) {
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    ctx.font = `900 ${fontSize}px system-ui`;
    const measure = ctx.measureText(text);
    const width = measure.width + padding * 2;
    const height = fontSize * 1.4 + padding * 2;

    canvas.width = width;
    canvas.height = height;

    // Rounded rect background
    const r = 6;
    ctx.fillStyle = bgColor;
    ctx.beginPath();
    ctx.moveTo(r, 0);
    ctx.lineTo(width - r, 0);
    ctx.quadraticCurveTo(width, 0, width, r);
    ctx.lineTo(width, height - r);
    ctx.quadraticCurveTo(width, height, width - r, height);
    ctx.lineTo(r, height);
    ctx.quadraticCurveTo(0, height, 0, height - r);
    ctx.lineTo(0, r);
    ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.fill();

    // Text
    ctx.fillStyle = textColor;
    ctx.font = `900 ${fontSize}px system-ui`;
    ctx.textBaseline = "middle";
    ctx.textAlign = "center";
    ctx.fillText(text, width / 2, height / 2);

    return canvas;
}

function createLabel(target, distance) {
    // >400m: no labels at all (pin only)
    if (distance > 400) return null;

    const group = new THREE.Group();

    // Distance label (always shown when <=400m)
    const distText = distance < 1000 ? `${Math.round(distance)}m` : `${(distance / 1000).toFixed(1)}km`;
    const distCanvas = createTextCanvas(distText, {
        fontSize: 20,
        bgColor: target.type === "parking" ? "rgba(22,163,74,0.8)" : "rgba(59,130,246,0.8)",
        textColor: "white",
        padding: 6
    });
    const distTexture = new THREE.Texture(distCanvas);
    distTexture.needsUpdate = true;
    const distMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(distCanvas.width, distCanvas.height),
        new THREE.MeshBasicMaterial({ map: distTexture, transparent: true, side: THREE.DoubleSide })
    );
    distMesh.position.y = 15;
    group.add(distMesh);

    // Name label (only shown when <=200m)
    if (distance <= 200) {
        const displayName = target.name.length > 6 ? target.name.substring(0, 6) + "\u2026" : target.name;
        const nameCanvas = createTextCanvas(displayName, {
            fontSize: 24, bgColor: "rgba(0,0,0,0.7)", textColor: "white", padding: 8
        });
        const nameTexture = new THREE.Texture(nameCanvas);
        nameTexture.needsUpdate = true;
        const nameMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(nameCanvas.width, nameCanvas.height),
            new THREE.MeshBasicMaterial({ map: nameTexture, transparent: true, side: THREE.DoubleSide })
        );
        nameMesh.position.y = 50;
        group.add(nameMesh);
    }

    return group;
}

// --- Data Fetching ---
async function fetchNearbyTargets() {
    if (!latitude || !longitude) return;

    const radius = 0.005; // ~500m
    const newTargets = [];

    // Parking API (existing)
    if (showParking) {
        try {
            const res = await fetch(
                `/parking/api/search?ne_lat=${latitude + radius}&ne_lng=${longitude + radius}&sw_lat=${latitude - radius}&sw_lng=${longitude - radius}`
            );
            const parkings = await res.json();
            parkings.forEach(p => {
                newTargets.push({
                    id: `parking-${p.id}`,
                    name: p.name,
                    latitude: parseFloat(p.latitude),
                    longitude: parseFloat(p.longitude),
                    altitude: 0,
                    type: "parking",
                    detail: p.price_detail || "",
                    capacity: p.capacity,
                    url: `/parking/${p.id}`,
                    address: p.address || ""
                });
            });
        } catch (e) {
            console.warn("Parking API error:", e);
        }
    }

    // Shop API
    if (showShop) {
        try {
            const res = await fetch(`/api/shops/nearby?lat=${latitude}&lng=${longitude}&radius=500`);
            const shops = await res.json();
            shops.forEach(s => {
                newTargets.push({
                    id: `shop-${s.id}`,
                    name: s.name,
                    latitude: parseFloat(s.latitude),
                    longitude: parseFloat(s.longitude),
                    altitude: 0,
                    type: "shop",
                    detail: s.address || "",
                    url: `/shops/${s.id}`,
                    address: s.address || ""
                });
            });
        } catch (e) {
            console.warn("Shop API not available:", e);
        }
    }

    // Compute distances & bearings
    const p1 = new LatLon(latitude, longitude);
    newTargets.forEach(t => {
        const p2 = new LatLon(t.latitude, t.longitude);
        t.distance = p1.distanceTo(p2);
        t.bearing = p1.finalBearingTo(p2);
    });

    // Sort by distance, limit to 20
    newTargets.sort((a, b) => a.distance - b.distance);
    targets = newTargets.slice(0, 20);

    rebuildMarkers();
    updateStatusBar();
}

function rebuildMarkers() {
    // Remove old markers
    markerGroups.forEach(mg => {
        scene.remove(mg.group);
        if (mg.labelGroup) scene.remove(mg.labelGroup);
    });
    markerGroups = [];

    targets.forEach(target => {
        const visible = (target.type === "parking" && showParking) || (target.type === "shop" && showShop);
        const group = createMarker(target);
        const labelGroup = createLabel(target, target.distance); // may be null if >400m
        group.visible = visible;
        scene.add(group);
        if (labelGroup) {
            labelGroup.visible = visible;
            scene.add(labelGroup);
        }
        markerGroups.push({ group, labelGroup, target });
    });
}

// --- Update Compass ---
function updateCompass(heading) {
    const directions = ["N", "NE", "E", "SE", "S", "SW", "W", "NW"];
    const index = Math.round(heading / 45) % 8;
    const el = document.getElementById("compass");
    if (el) el.textContent = `${directions[index]} ${Math.round(heading)}\u00B0`;
}

// --- Update Status Bar ---
function updateStatusBar() {
    const parkingCount = targets.filter(t => t.type === "parking" && showParking).length;
    const shopCount = targets.filter(t => t.type === "shop" && showShop).length;
    const el = document.getElementById("status-bar");
    if (el) {
        const parts = [];
        if (showParking) parts.push(`駐車場 ${parkingCount}件`);
        if (showShop) parts.push(`ショップ ${shopCount}件`);
        el.textContent = parts.join(" / ") || "検索中...";
    }
}

// --- Scale by distance ---
function getMarkerScale(distance) {
    if (distance < 50) return 0.5;
    if (distance < 200) return 1.0;
    return 0.8;
}

// --- Animation Loop ---
function startARLoop() {
    arRunning = true;
    const canvasW = window.innerWidth;
    const canvasH = window.innerHeight;
    const fov = 45;

    function animate() {
        if (!arRunning) return;

        // Re-fetch periodically
        const now = Date.now();
        if (now - lastFetchTime > FETCH_INTERVAL) {
            lastFetchTime = now;
            fetchNearbyTargets();
        }

        // Update compass
        updateCompass(degrees);

        // Update bearing for each target
        if (latitude && longitude) {
            const p1 = new LatLon(latitude, longitude);
            targets.forEach(t => {
                try {
                    const p2 = new LatLon(t.latitude, t.longitude);
                    t.bearing = p1.finalBearingTo(p2);
                    t.distance = p1.distanceTo(p2);
                } catch (e) { /* ignore */ }
            });
        }

        // Position markers based on device orientation
        // Fix: beta≈90° when phone held horizontally, subtract 90 so markers center on screen
        const rotation = getEulerAngles(getRotationMatrix(alpha, beta, gamma));
        const pitchY = -(rotation[1] + 5);
        const moveYFromPitch = (canvasH / fov) * pitchY;

        // Debug: show pitch value on compass
        const compassEl = document.getElementById("compass");
        if (compassEl) {
            const dirs = ["N","NE","E","SE","S","SW","W","NW"];
            compassEl.textContent = dirs[Math.round(degrees / 45) % 8] + ' ' + Math.round(degrees) + '\u00B0 p:' + Math.round(rotation[1]);
        }

        markerGroups.forEach(mg => {
            const t = mg.target;
            let xAngle = t.bearing - degrees;
            if (xAngle < -200) xAngle += 360;
            if (xAngle > 200) xAngle -= 360;

            const moveX = (canvasW / fov) * xAngle;

            // Altitude angle
            const heightDiff = (t.altitude || 0) - (altitude || 0);
            const altAngle = Math.atan2(heightDiff, Math.max(t.distance, 1)) * (180 / Math.PI);
            const setY = (canvasH / fov) * altAngle;

            // Scale by distance: close=small, mid=normal, far=slightly small
            const scale = getMarkerScale(t.distance);

            mg.group.position.set(moveX, setY + moveYFromPitch, -Math.min(t.distance, 1000));
            mg.group.scale.set(scale, scale, scale);

            if (mg.labelGroup) {
                mg.labelGroup.position.set(moveX, setY + moveYFromPitch + 63 * scale, -Math.min(t.distance, 1000));
                mg.labelGroup.scale.set(scale, scale, scale);
            }

            // Visibility
            const visible = (t.type === "parking" && showParking) || (t.type === "shop" && showShop);
            mg.group.visible = visible;
            if (mg.labelGroup) mg.labelGroup.visible = visible;
        });

        // Render
        renderer.render(scene, camera);

        // Draw camera + overlay
        viewCanvasContext.drawImage(videoSource, 0, 0, canvasW, canvasH);
        viewCanvasContext.drawImage(offscreenCanvas, 0, 0);

        requestAnimationFrame(animate);
    }

    animate();
}

// --- Info Panel ---
function showInfoPanel(target) {
    const panel = document.getElementById("info-panel");
    document.getElementById("panel-name").textContent = target.name;
    document.getElementById("panel-address").textContent = target.detail || target.address || "";

    const metaHtml = [];
    if (target.type === "parking") {
        metaHtml.push('<span class="meta-item" style="background:#dcfce7;color:#16a34a;">P 駐車場</span>');
        if (target.capacity) metaHtml.push(`<span class="meta-item" style="background:#f1f5f9;color:#334155;">${target.capacity}台</span>`);
    } else {
        metaHtml.push('<span class="meta-item" style="background:#dbeafe;color:#3b82f6;">S ショップ</span>');
    }
    const dist = target.distance < 1000 ? `${Math.round(target.distance)}m` : `${(target.distance / 1000).toFixed(1)}km`;
    metaHtml.push(`<span class="meta-item" style="background:#f1f5f9;color:#334155;">${dist}</span>`);
    document.getElementById("panel-meta").innerHTML = metaHtml.join("");

    document.getElementById("panel-navigate").href =
        `https://www.google.com/maps/dir/?api=1&destination=${target.latitude},${target.longitude}&travelmode=driving`;
    document.getElementById("panel-detail").href = target.url;

    panel.classList.add("visible");
}

// --- Tap Detection (Raycaster) ---
function initTapDetection() {
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const canvas = document.getElementById("ar-canvas");

    function onTap(clientX, clientY) {
        pointer.x = (clientX / window.innerWidth) * 2 - 1;
        pointer.y = -(clientY / window.innerHeight) * 2 + 1;

        raycaster.setFromCamera(pointer, camera);
        // Include both pin groups and label groups for wider hit area
        const allObjects = markerGroups
            .filter(mg => mg.group.visible)
            .flatMap(mg => mg.labelGroup ? [mg.group, mg.labelGroup] : [mg.group]);
        const intersects = raycaster.intersectObjects(allObjects, true);

        if (intersects.length > 0) {
            const hitObj = intersects[0].object;
            const mg = markerGroups.find(m => {
                let parent = hitObj;
                while (parent) {
                    if (parent === m.group || parent === m.labelGroup) return true;
                    parent = parent.parent;
                }
                return false;
            });
            if (mg) showInfoPanel(mg.target);
        }
    }

    // Desktop click
    canvas.addEventListener("click", (event) => {
        if (event.target !== canvas) return;
        onTap(event.clientX, event.clientY);
    });

    // Mobile touch (more reliable than click on some devices)
    canvas.addEventListener("touchend", (event) => {
        event.preventDefault();
        const touch = event.changedTouches[0];
        onTap(touch.clientX, touch.clientY);
    }, { passive: false });
}

// --- Filter Toggle ---
window.toggleFilter = function (type) {
    if (type === "parking") {
        showParking = !showParking;
        document.querySelector('[data-type="parking"]').className =
            showParking ? "filter-btn active-parking" : "filter-btn inactive";
    } else {
        showShop = !showShop;
        document.querySelector('[data-type="shop"]').className =
            showShop ? "filter-btn active-shop" : "filter-btn inactive";
    }
    markerGroups.forEach(mg => {
        const visible = (mg.target.type === "parking" && showParking) || (mg.target.type === "shop" && showShop);
        mg.group.visible = visible;
        if (mg.labelGroup) mg.labelGroup.visible = visible;
    });
    updateStatusBar();
};

window.closePanel = function () {
    document.getElementById("info-panel").classList.remove("visible");
};

// --- Startup ---
document.getElementById("start-ar-btn").addEventListener("click", async () => {
    document.getElementById("loading").style.display = "flex";

    try {
        os = detectOS();

        if (os === "pc") {
            document.getElementById("loading").style.display = "none";
            alert("ARファインダーはスマートフォンでご利用ください。");
            return;
        }

        // iOS: DeviceOrientation permission
        if (typeof DeviceOrientationEvent !== "undefined" &&
            typeof DeviceOrientationEvent.requestPermission === "function") {
            const response = await DeviceOrientationEvent.requestPermission();
            if (response !== "granted") {
                alert("コンパスの使用許可が必要です");
                document.getElementById("loading").style.display = "none";
                return;
            }
        }

        // Register orientation listener
        if (os === "iphone") {
            window.addEventListener("deviceorientation", onOrientation);
        } else {
            window.addEventListener("deviceorientationabsolute", onOrientation, true);
        }

        // Camera + GPS
        await initCamera();
        await initGeolocation();
        initThreeJS();
        initTapDetection();

        // UI switch
        document.getElementById("permission-screen").style.display = "none";
        document.getElementById("ar-container").style.display = "block";
        document.getElementById("loading").style.display = "none";

        // Initial fetch
        lastFetchTime = Date.now();
        await fetchNearbyTargets();

        // Start loop
        startARLoop();

    } catch (err) {
        document.getElementById("loading").style.display = "none";
        alert("カメラまたは位置情報の許可が必要です: " + err.message);
    }
});

// Handle resize
window.addEventListener("resize", () => {
    if (!arRunning) return;
    const w = window.innerWidth;
    const h = window.innerHeight;
    const arCanvas = document.getElementById("ar-canvas");
    arCanvas.width = w;
    arCanvas.height = h;
    if (offscreenCanvas) {
        offscreenCanvas.width = w;
        offscreenCanvas.height = h;
    }
    if (camera) {
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    if (renderer) renderer.setSize(w, h);
});
