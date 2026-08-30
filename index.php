<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'src/functions.php';

send_security_headers();
header('Cache-Control: no-store, max-age=0');

if (isset($_GET['api']) && $_GET['api'] === 'images') {
    output_json(get_images());
    exit;
}

$images = get_images();
$initialImages = array_values($images);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TV Board</title>
<style>
:root {
    color-scheme: dark;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #000;
    font-family: Inter, Arial, sans-serif;
    cursor: none;
}

#stage {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #000;
    z-index: 1;
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    opacity: 0;
    transition: opacity 1200ms ease;
}

.slide.visible {
    opacity: 1;
}

.blur {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.blur img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(48px) brightness(0.52) saturate(1.18);
    transform: scale(1.28);
}

.main {
    position: absolute;
    top: 6vh;
    right: 3vw;
    bottom: 5vh;
    left: 3vw;
}

.main img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.top-shade {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 180px;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0.42), rgba(0, 0, 0, 0));
    z-index: 8;
    pointer-events: none;
}

.bottom-shade {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: 120px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.22), rgba(0, 0, 0, 0));
    z-index: 8;
    pointer-events: none;
}

#logo {
    position: fixed;
    top: clamp(18px, 2.3vw, 34px);
    left: clamp(20px, 2.4vw, 42px);
    z-index: 30;
    height: clamp(42px, 4.2vw, 84px);
    width: auto;
    max-width: 240px;
    object-fit: contain;
    filter: drop-shadow(0 10px 26px rgba(0, 0, 0, 0.45));
    pointer-events: none;
}

.clock {
    position: fixed;
    top: clamp(18px, 2.1vw, 34px);
    right: clamp(20px, 2.4vw, 42px);
    z-index: 30;
    text-align: right;
    color: #ffffff;
    pointer-events: none;
    text-shadow: 0 2px 18px rgba(0, 0, 0, 0.55);
}

#clock-time {
    font-size: clamp(34px, 4.2vw, 84px);
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1;
}

#clock-date {
    margin-top: clamp(4px, 0.5vw, 8px);
    font-size: clamp(11px, 0.95vw, 16px);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    opacity: 0.88;
}

#empty {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 50% 50%, #111111 0%, #000000 72%);
    opacity: 1;
    transition: opacity 700ms ease;
    z-index: 10;
}

#empty.hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

#empty img {
    height: 28vh;
    max-height: 240px;
    width: auto;
    object-fit: contain;
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.92;
    }

    50% {
        transform: scale(1.03);
        opacity: 1;
    }
}

@media (max-width: 800px) {
    .main {
        top: 8vh;
        right: 4vw;
        bottom: 7vh;
        left: 4vw;
    }
}
</style>
</head>
<body>
<div id="stage">
    <div class="slide visible" id="slide-a">
        <div class="blur"><img alt=""></div>
        <div class="main"><img alt=""></div>
    </div>

    <div class="slide" id="slide-b">
        <div class="blur"><img alt=""></div>
        <div class="main"><img alt=""></div>
    </div>
</div>

<div class="top-shade"></div>
<div class="bottom-shade"></div>

<div id="empty"<?php echo empty($images) ? '' : ' class="hidden"'; ?>>
    <img src="logo.png" alt="" onerror="this.style.display='none'">
</div>

<img id="logo" src="logo.png" alt="" onerror="this.style.display='none'">

<div class="clock">
    <div id="clock-time">--:--</div>
    <div id="clock-date">...</div>
</div>

<script>
const initialImages = <?php echo json_encode($initialImages, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;

const emptyScreen = document.getElementById('empty');
const slides = [
    document.getElementById('slide-a'),
    document.getElementById('slide-b')
];

let playlist = Array.isArray(initialImages) ? initialImages : [];
let index = -1;
let active = 0;
let timer = null;
let updating = false;
let transitioning = false;

const slideInterval = 8000;
const refreshInterval = 6000;

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function preload(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('load failed'));
        image.src = url;
    });
}

function setEmptyState(state) {
    if (state) {
        emptyScreen.classList.remove('hidden');
    } else {
        emptyScreen.classList.add('hidden');
    }
}

function stopTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function ensureTimer() {
    if (!timer && playlist.length) {
        timer = setInterval(showNext, slideInterval);
    }
}

function updateClock() {
    const now = new Date();

    const time = now.toLocaleTimeString('pl-PL', {
        hour: '2-digit',
        minute: '2-digit'
    });

    let date = now.toLocaleDateString('pl-PL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    date = date.charAt(0).toUpperCase() + date.slice(1);

    document.getElementById('clock-time').textContent = time;
    document.getElementById('clock-date').textContent = date;
}

async function showNext() {
    if (!playlist.length) {
        setEmptyState(true);
        stopTimer();
        return;
    }

    if (transitioning) {
        return;
    }

    transitioning = true;
    index = (index + 1) % playlist.length;

    const item = playlist[index];
    const nextSlide = slides[active === 0 ? 1 : 0];
    const mainImg = nextSlide.querySelector('.main img');
    const blurImg = nextSlide.querySelector('.blur img');

    try {
        await preload(item.url);

        mainImg.src = item.url;
        blurImg.src = item.url;

        await sleep(80);

        nextSlide.classList.add('visible');
        slides[active].classList.remove('visible');

        active = active === 0 ? 1 : 0;

        await sleep(1200);
    } catch (error) {
        await refreshImages();

        if (playlist.length > 1) {
            setTimeout(showNext, 300);
        } else if (!playlist.length) {
            setEmptyState(true);
            stopTimer();
        }
    }

    transitioning = false;
}

async function refreshImages() {
    if (updating) {
        return;
    }

    updating = true;

    try {
        const response = await fetch('?api=images&t=' + Date.now(), {
            cache: 'no-store'
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (!Array.isArray(data)) {
            return;
        }

        if (!playlist.length) {
            playlist = data;

            if (playlist.length) {
                setEmptyState(false);
                index = -1;
                showNext();
                ensureTimer();
            }

            return;
        }

        const serverFiles = new Set(data.map(item => item.file));
        const oldFiles = new Set(playlist.map(item => item.file));
        const currentFile = playlist[index] ? playlist[index].file : null;

        let nextPlaylist = playlist.filter(item => serverFiles.has(item.file));
        const newItems = data.filter(item => !oldFiles.has(item.file));

        let currentIndex = currentFile ? nextPlaylist.findIndex(item => item.file === currentFile) : -1;

        if (currentIndex === -1) {
            if (!nextPlaylist.length) {
                playlist = [];
                index = -1;
                setEmptyState(true);
                stopTimer();
                return;
            }

            playlist = nextPlaylist;
            index = -1;

            setEmptyState(false);
            ensureTimer();
            showNext();

            return;
        }

        if (newItems.length) {
            nextPlaylist.splice(currentIndex + 1, 0, ...newItems);
            newItems.forEach(item => preload(item.url).catch(() => {}));
        }

        playlist = nextPlaylist;
        index = currentIndex;

        setEmptyState(false);
        ensureTimer();
    } catch (error) {
    } finally {
        updating = false;
    }
}

if (playlist.length) {
    setEmptyState(false);
    showNext();
    ensureTimer();
} else {
    setEmptyState(true);
}

updateClock();
setInterval(updateClock, 1000);

refreshImages();
setInterval(refreshImages, refreshInterval);

setTimeout(() => window.location.reload(), 21600000);

document.addEventListener('click', () => {
    if (playlist.length) {
        showNext();
        ensureTimer();
    }
});
</script>
</body>
</html>
