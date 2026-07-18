// Service Worker - Moodle Tracker Background Checker
const API = '/model/checker_api.php?key=moodle_tracker_2024_secret';
const INTERVAL = 60000; // 1 minute

self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

// Background sync every minute
let checking = false;

async function doCheck() {
    if (checking) return;
    checking = true;
    try {
        await fetch(API);
    } catch(e) {}
    checking = false;
}

// Use setInterval via message
self.addEventListener('message', e => {
    if (e.data === 'start') {
        setInterval(doCheck, INTERVAL);
        doCheck();
    }
});

// Periodic background sync (if supported)
self.addEventListener('periodicsync', e => {
    if (e.tag === 'moodle-check') {
        e.waitUntil(doCheck());
    }
});
