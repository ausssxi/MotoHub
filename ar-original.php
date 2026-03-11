<?php
ini_set('display_errors', "On");
date_default_timezone_set('Asia/Tokyo');
$mysqli = new mysqli('localhost', 'root', 'password', 'gourmet');
$mysqli->set_charset("utf8");
$result = $mysqli->query("SELECT id, name, x(location), y(location), altitude FROM shop WHERE id = 1");
$param_json = json_encode($result->fetch_assoc());
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, user-scalable=yes">
<script src="/js/leaflet.js"></script>
<script src="/js/leaflet.usermarker.js"></script>
<script src="/js/leaflet-vector-markers.min.js"></script>
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.167.0/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.167.0/examples/jsm/"
    }
}
</script>
<script type="module">
import LatLon from "https://esm.sh/geodesy@2.4.0/latlon-ellipsoidal-vincenty.js";
import * as THREE from "three";
import {
    FontLoader
} from "three/addons/loaders/FontLoader.js";
import {
    TextGeometry
} from "three/addons/geometries/TextGeometry.js";
import {
  　COF,
    Geomag
} from "./js/wmmacc2.js";

let os;
let alpha;
let beta;
let gamma;
let degrees;
let videoSource;
let offscreenCanvas;
let viewCanvasContext;
let latitude;
let longitude;
let altitude;
let scene;
let direction;
let magdec;
let arrow;
let textMesh = [];
let canvas = [];
let context = [];
let canvasWidth = [];
let canvasHeight = [];
let texture = [];
let canvasWidth0 = [];
let canvasHeight0 = [];
let canvas0 = [];
let context0 = [];
let texture0 = [];
let textMesh0 = [];

let mesh = [];
let text = [];
let targetDirection = [];
let targetDistance = [];
let angle = [];
let targetName = [];
let targetLatitude = [];
let targetLongitude = [];
let targetAltitude = [];
let moveX = [];
let x =[];

window.addEventListener("DOMContentLoaded", init);
function init() {
    os = detectOSSimply();
    if (os == "iphone") {
    } else if (os == "android") {
        window.addEventListener("deviceorientationabsolute", orientation, true);
    } else {
        window.alert("PC未対応サンプル");
    }
}

function permitDeviceOrientationForSafari() {
    if (DeviceOrientationEvent && DeviceOrientationEvent.requestPermission && typeof DeviceOrientationEvent.requestPermission === 'function') {
        DeviceOrientationEvent.requestPermission().then(response => {
            if (response === "granted") {
                window.addEventListener("deviceorientation", orientation);
                main();
            }
        }).catch(console.error);
    }
}

function orientation(event) {
    alpha = event.alpha;
    beta = event.beta;
    gamma = event.gamma;
    if (event.webkitCompassHeading + magdec < 0) {
        degrees = event.webkitCompassHeading + magdec + 360;
    } else {
        degrees = event.webkitCompassHeading + magdec
    }
}

function compassHeading(alpha, beta, gamma) {
    const degtorad = Math.PI / 180;

    const _x = beta ? beta * degtorad : 0;
    const _y = gamma ? gamma * degtorad : 0;
    const _z = alpha ? alpha * degtorad : 0;

    const cX = Math.cos(_x);
    const cY = Math.cos(_y);
    const cZ = Math.cos(_z);
    const sX = Math.sin(_x);
    const sY = Math.sin(_y);
    const sZ = Math.sin(_z);

    const Vx = -cZ * sY - sZ * sX * cY;
    const Vy = -sZ * sY + cZ * sX * cY;

    let compassHeading = Math.atan(Vx / Vy);
    if (Vy < 0) {
        compassHeading += Math.PI;
    } else if (Vx < 0) {
        compassHeading += 2 * Math.PI;
    }
    return compassHeading * (180 / Math.PI);
}

function getRotationMatrix(alpha, beta, gamma) {
    const degtorad = Math.PI / 180;

    const cX = Math.cos(beta  * degtorad);
    const cY = Math.cos(gamma * degtorad);
    const cZ = Math.cos(alpha * degtorad);
    const sX = Math.sin(beta  * degtorad);
    const sY = Math.sin(gamma * degtorad);
    const sZ = Math.sin(alpha * degtorad);

    const m11 = cZ * cY - sZ * sX * sY;
    const m12 = - cX * sZ;
    const m13 = cY * sZ * sX + cZ * sY;

    const m21 = cY * sZ + cZ * sX * sY;
    const m22 = cZ * cX;
    const m23 = sZ * sY - cZ * cY * sX;

    const m31 = - cX * sY;
    const m32 = sX;
    const m33 = cX * cY;
    return [
        m13, m11, m12,
        m23, m21, m22,
        m33, m31, m32
    ];
}

function getEulerAngles(matrix) {
    const radtodeg = 180 / Math.PI;
    const sy = Math.sqrt(matrix[0] * matrix[0] +  matrix[3] * matrix[3] );
    const singular = sy < 1e-6;
    let x;
    let y;
    let z;

    if (!singular) {
        x = Math.atan2(matrix[7] , matrix[8]);
        y = Math.atan2(-matrix[6], sy);
        z = Math.atan2(matrix[3], matrix[0]);
    } else {
        x = Math.atan2(-matrix[5], matrix[4]);
        y = Math.atan2(-matrix[6], sy);
        z = 0;
    }
    return [radtodeg * x, radtodeg * y, radtodeg * z];
}

function detectOSSimply() {
    let ret;
    if (navigator.userAgent.indexOf("iPhone") > 0 || navigator.userAgent.indexOf("iPad") > 0 || navigator.userAgent.indexOf("iPod") > 0) {
        ret = "iphone";
    } else if (navigator.userAgent.indexOf("Android") > 0) {
        ret = "android";
    } else {
        ret = "pc";
    }
    return ret;
}

const main = async () => {
    [videoSource, offscreenCanvas, viewCanvasContext] = canvasInit();
    threeJsInit(offscreenCanvas);
    textInit();
    await videoSourceInit(videoSource);
    moveAnimation();
}


function canvasInit() {
    const videoSource = document.createElement("video");
    const offscreenCanvas = document.createElement("canvas");
    const viewCanvas = document.querySelector("#result");

    viewCanvas.height = 360;
    viewCanvas.width = 360;

    const viewCanvasContext = viewCanvas.getContext("2d");

    offscreenCanvas.width = viewCanvas.width;
    offscreenCanvas.height = viewCanvas.height;

    return [videoSource, offscreenCanvas, viewCanvasContext];
}

async function videoSourceInit(exportCanvasElement) {
    const stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: {
                exact: 'environment'
            },
            width: 360, height: 360, aspectRatio: 360 / 360
        },
    });

    exportCanvasElement.muted = true;
    exportCanvasElement.playsInline = true;
    exportCanvasElement.srcObject = stream;
    exportCanvasElement.play();
}

const php_string = JSON.parse('<?php echo $param_json; ?>');
const options = {
    enableHighAccuracy: true,
    maximumAge: 0,
    timeout: 100,
}

window.onload = () => {
    if (!navigator.geolocation) return;
    setInterval(getPosition, 1000);
};

function getPosition() {
    navigator.geolocation.getCurrentPosition(onSuccess, onError);
}

function onSuccess(position) {
    const startLatitude = position.coords.latitude;
    const startLongitude = position.coords.longitude;
    let map;
    let tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="http://osm.org/copyright">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
    });
    if (map != undefined) {
        map.remove();
    }
    map = L.map('mapid', {
        center: [startLatitude, startLongitude],
        zoom: 17,
    });
    tileLayer.addTo(map);
    let options = {
        pulsing: true ,accuracy: 1 ,smallIcon: true
    };
    L.userMarker([startLatitude, startLongitude], options).addTo(map);

    options = {
        prefix: 'fa',
        icon: 'utensils',
        markerColor: 'blue'
    };
    for (let i = 0; i < targetLatitude.length; i++) {
        L.marker([targetLatitude[i], targetLongitude[i]], {
            icon: L.VectorMarkers.icon(options)
        }).addTo(map).bindPopup("Font Awesome");
    }
}

function onError(error) {
    console.warn(`ERROR(${error.code}): ${error.message}`);
}

navigator.geolocation.watchPosition(success, error, options);
function success(position) {
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    altitude = position.coords.altitude;
    const geomag = new Geomag(COF);
    const mag = geomag.calc(latitude, longitude, altitude / 1000);
    magdec = mag.dec;

    const data = {
        y: latitude,
        x: longitude
    }

    fetch('request.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    }).then(response => response.json())
    .then(responseData => {
        for (let i = 0; i < responseData.length; i++) {
            targetName[i] = responseData[i].name;
            targetLatitude[i] = responseData[i].y;
            targetLongitude[i] = responseData[i].x;
            targetAltitude[i] = responseData[i].altitude;
            targetDistance[i] = responseData[i].d;
        }
    })
    .catch(error => {
        console.log(error);
    });
    changeCanvas();
}

function error(error) {
    PERMISSION_DENIED = 1; // GPS機能の利用が許可されていない
    POSITION_UNAVAILABLE = 2; // 何らかの内部エラーが発生した
    TIMEOUT = 3; // タイムアウト
    console.log(TIMEOUT);
}

function threeJsInit(renderTarget) {
    const camera = new THREE.PerspectiveCamera(45, 360 / 360, 1, 2000,);
    const fovRad = (45 / 2) * (Math.PI / 180);
    const distance = (360 / 2) / Math.tan(fovRad);
    camera.position.z = distance;

    scene = new THREE.Scene();
    const renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        canvas: renderTarget,
    });
    renderer.setSize(360, 360,);

    const light = new THREE.DirectionalLight(0xFFFFFF, 1);
    scene.add(light);

    const arrowShape = new THREE.Shape();
    arrowShape.moveTo(0, -1);
    arrowShape.lineTo(0.4, -0.6);
    arrowShape.lineTo(0.2, -0.6);
    arrowShape.lineTo(0.2, 0);
    arrowShape.lineTo(-0.2, 0);
    arrowShape.lineTo(-0.2, -0.6);
    arrowShape.lineTo(-0.4, -0.6);
    arrowShape.lineTo(0, -1);

    const extrudeSettings = {
        depth: 0.5, bevelEnabled: false
    };
    const geometry = new THREE.ExtrudeGeometry(arrowShape, extrudeSettings);
    const material = new THREE.MeshPhongMaterial({
        color: 0xff0000
    });
    for (let i = 0; i < targetLatitude.length; i++) {
        mesh[i] = new THREE.Mesh(geometry, material);
        mesh[i].scale.set(80, 40, 60);
        mesh[i].rotation.set(Math.PI / 1.1, 0, 0);
        scene.add(mesh[i]);
    }

    renderer.setAnimationLoop((time) => {
        for (let i = 0; i < mesh.length; i++) {
            mesh[i].rotation.y = time / 700;
        }
        renderer.render(scene, camera);
    });
}

const moveAnimation = () => {
    let height = [];
    let tan = [];
    let setY = [];

    for (let i = 0; i < targetDirection.length; i++) {
        if (targetDirection[i] - degrees < -200) {
            x[i] = targetDirection[i] + (360 - degrees);
        } else {
            x[i] = targetDirection[i] - degrees;
        }
        moveX[i] = (360 / 45) * x[i];

        height[i] = targetAltitude[i] - altitude;
        tan[i] = height[i] / targetDistance[i];
        angle[i] = (Math.atan(tan[i]) * (180 / Math.PI));
        setY[i] = (360 / 45) * angle[i];
    }

    const rotation = getEulerAngles(getRotationMatrix(alpha, beta, gamma));
    const y = -rotation[1];
    const moveY = (360 / 45) * y;

    for (let i = 0; i < mesh.length; i++) {
        mesh[i].position.x = moveX[i];
        mesh[i].position.y = setY[i] + moveY;
        mesh[i].position.z = -targetDistance[i];
        textMesh0[i].position.x = moveX[i];
        textMesh0[i].position.y = moveY + setY[i] + 63;
        textMesh0[i].position.z = -targetDistance[i];
        textMesh[i].position.x = moveX[i];
        textMesh[i].position.y = moveY + setY[i] + 93;
        textMesh[i].position.z = -targetDistance[i];
    }
    viewCanvasContext.drawImage(videoSource, 0, 0);
    viewCanvasContext.drawImage(offscreenCanvas, 0, 0);
    requestAnimationFrame(moveAnimation);
}

function textInit() {
    const canvas10 = [];
    const context10 = [];
    const text0 = [];
    const measure0 = [];
    const material0 =[];
    const text = [];
    const measure = [];
    const material = [];

    for (let i = 0; i < mesh.length; i++) {
        canvas10[i] = document.createElement('canvas');
        context10[i] = canvas10[i].getContext('2d');
        context10[i].font = '900 30px system-ui';

        text0[i] = 'あと' + Math.floor(targetDistance[i]) + 'm'
        measure0[i] = context10[i].measureText(text0[i]);
        text[i] = targetName[i].substr(0, 3) + "…";
        measure[i] = context10[i].measureText(text[i]);

        canvasWidth0[i] = measure0[i].width;
        canvasHeight0[i] = measure0[i].fontBoundingBoxAscent + measure0[i].fontBoundingBoxDescent;
        canvasWidth[i] = measure[i].width;
        canvasHeight[i] = measure[i].fontBoundingBoxAscent + measure[i].fontBoundingBoxDescent;

        canvas0[i] = document.createElement('canvas');
        context0[i] = canvas0[i].getContext('2d');
        canvas[i] = document.createElement('canvas');
        context[i] = canvas[i].getContext('2d');

        canvas0[i].width = canvasWidth0[i];
        canvas0[i].height = canvasHeight0[i];
        canvas[i].width = canvasWidth[i];
        canvas[i].height = canvasHeight[i];

        context0[i].beginPath();
        context0[i].globalAlpha = 0;
        context0[i].fillRect(0, 0, canvasWidth0[i], canvasHeight0[i]);
        context0[i].globalAlpha = 1;
        context0[i].font = '900 30px system-ui';
        context0[i].textBaseline = 'top';
        context0[i].fillText(text0[i], 0, 0);
        context0[i].closePath();
        context0[i].stroke();

        context[i].beginPath();
        context[i].globalAlpha = 0;
        context[i].fillRect(0, 0, canvasWidth[i], canvasHeight[i]);
        context[i].globalAlpha = 1;
        context[i].font = '900 30px system-ui';
        context[i].textBaseline = 'top';
        context[i].fillText(text[i], 0, 0);
        context[i].closePath();
        context[i].stroke();

        texture0[i] = new THREE.Texture(canvas0[i]);
        texture0[i].needsUpdate = true;
        texture[i] = new THREE.Texture(canvas[i]);
        texture[i].needsUpdate = true;

        material0[i] = new THREE.MeshBasicMaterial({
            map: texture0[i],
            side: THREE.DoubleSide,
        })

        material[i] = new THREE.MeshBasicMaterial({
            map: texture[i],
            side: THREE.DoubleSide,
        })
        material0[i].transparent = true;
        textMesh0[i] = new THREE.Mesh(new THREE.PlaneGeometry(canvasWidth0[i], canvasHeight0[i]), material0[i]);
        scene.add(textMesh0[i]);
        material[i].transparent = true;
        textMesh[i] = new THREE.Mesh(new THREE.PlaneGeometry(canvasWidth[i], canvasHeight[i]), material[i]);
        scene.add(textMesh[i]);
    }
}

function changeCanvas() {
    new Promise((resolve, reject) => {
        for (let i = 0; i < mesh.length; i++) {
            context0[i].clearRect(0, 0, canvasWidth0[i], canvasHeight0[i]);
            context0[i].beginPath();
            context[i].clearRect(0, 0, canvasWidth[i], canvasHeight[i]);
            context[i].beginPath();
        }
        resolve();
    }).then(() => {
        const canvas10 = [];
        const context10 = [];
        const text0 = [];
        const measure0 = [];
        const text = [];
        const measure = [];
        for (let i = 0; i < mesh.length; i++) {
            canvas10[i] = document.createElement('canvas');
            context10[i] = canvas0[i].getContext('2d');
            context0[i].font = '900 30px system-ui';

            text0[i] = 'あと' + Math.floor(targetDistance[i]) + 'm'
            measure0[i] = context10[i].measureText(text0[i]);
            text[i] = targetName[i].substr(0, 3) + "…";
            measure[i] = context10[i].measureText(text[i]);

            canvasWidth0[i] = measure0[i].width;
            canvasHeight0[i] = measure0[i].fontBoundingBoxAscent + measure0[i].fontBoundingBoxDescent;
            canvasWidth[i] = measure[i].width;
            canvasHeight[i] = measure[i].fontBoundingBoxAscent + measure[i].fontBoundingBoxDescent;

            context0[i].beginPath();
            context0[i].globalAlpha = 0;
            context0[i].fillRect(0, 0, canvasWidth0[i], canvasHeight0[i]);
            context0[i].globalAlpha = 1;
            context0[i].font = '900 30px system-ui';
            context0[i].textBaseline = 'top';
            context0[i].fillText(text0[i], 0, 0);
            context0[i].closePath();
            context0[i].stroke();

            context[i].beginPath();
            context[i].globalAlpha = 0;
            context[i].fillRect(0, 0, canvasWidth[i], canvasHeight[i]);
            context[i].globalAlpha = 1;
            context[i].font = '900 30px system-ui';
            context[i].textBaseline = 'top';
            context[i].fillText(text[i], 0, 0);
            context[i].closePath();
            context[i].stroke();

            texture0[i].needsUpdate = true;
            texture[i].needsUpdate = true;
        }
    });
}

let button = document.getElementById('btn');
button.addEventListener('click', permitDeviceOrientationForSafari);

setInterval(callBack1, 100);
function callBack1() {
    const p1 = new LatLon(latitude, longitude);

    let p2 = [];
    for (let i = 0; i < targetLatitude.length; i++) {
        p2[i] = new LatLon(targetLatitude[i], targetLongitude[i]);
        targetDirection[i] = p1.finalBearingTo(p2[i]);
    }
}


        </script>
        <style>
            button {
                display: block;
                margin: auto;
            }
            #canvas_area{
	              width:360px;
	              margin:0 auto;
            }
            #result {
                background-color: gray;
                width: 360px;
                height: 360px;
            }
            #mapid {
                width: 100%;
                height: 33vh;
            }
            .ado {
                width: 320px;
                height: 50px;
                background-color: blue;
                margin: 0 auto;
            }
        </style>
    </head>
    <title>まっP</title>
    <body>
        <button id="btn" type="button">Add to favorites</button>
        <div id="mapid"></div>
        <div class = "ado"></div>
        <div id="canvas_area">
            <canvas id="result"></canvas>
        </div>
        <link rel="stylesheet" href="/css/leaflet.css" />
        <link rel="stylesheet" href="/css/leaflet.usermarker.css" />
        <link rel="stylesheet" href="/css/leaflet-vector-markers.css" />
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    </body>
</html>
