"use strict";

/*
|--------------------------------------------------------------------------
| Wedding Event Planner Service Worker
|--------------------------------------------------------------------------
| Public static assets are cached for faster loading.
| Authentication and dashboard page responses are not cached.
*/

const CACHE_VERSION = "wedding-planner-v88";

const BASE_PATH = self.location.pathname.replace(
    /\/service-worker\.js$/,
    ""
);

const OFFLINE_PAGE =
    `${BASE_PATH}/offline.php`;

const STATIC_FILES = [
    `${BASE_PATH}/offline.php`,
    `${BASE_PATH}/manifest.json`,

    `${BASE_PATH}/assets/css/auth.css`,
    `${BASE_PATH}/assets/css/style.css`,
    `${BASE_PATH}/assets/css/responsive.css`,
    `${BASE_PATH}/assets/css/dashboard.css`,
    `${BASE_PATH}/assets/css/profile.css`,

    `${BASE_PATH}/assets/css/admin_dashboard.css`,
    `${BASE_PATH}/assets/css/admin_consistency.css`,
    `${BASE_PATH}/assets/css/admin_venues_consistency.css`,
    `${BASE_PATH}/assets/css/admin_listing_grid_fix.css`,
    `${BASE_PATH}/assets/css/admin_bookings.css`,
    `${BASE_PATH}/assets/css/admin_gallery.css`,
    `${BASE_PATH}/assets/css/admin_feedback.css`,

    `${BASE_PATH}/assets/css/event_manager_consistency.css`,
    `${BASE_PATH}/assets/css/event_manager_dashboard.css`,
    `${BASE_PATH}/assets/css/event_manager_feedback.css`,
    `${BASE_PATH}/assets/css/assigned_tasks.css`,

    `${BASE_PATH}/assets/css/booking_manager_consistency.css`,
    `${BASE_PATH}/assets/css/booking_manager_dashboard.css`,
    `${BASE_PATH}/assets/css/booking_manager_views.css`,
    `${BASE_PATH}/assets/css/booking_manager_gallery.css`,
    `${BASE_PATH}/assets/css/booking_manager_packages.css`,
    `${BASE_PATH}/assets/css/booking_manager_all_packages.css`,
    `${BASE_PATH}/assets/css/booking_manager_venues.css`,
    `${BASE_PATH}/assets/css/booking_manager_all_venues.css`,
    `${BASE_PATH}/assets/css/booking_manager_bookings.css`,
    `${BASE_PATH}/assets/css/booking_form.css`,

    `${BASE_PATH}/assets/css/customer_consistency.css`,
    `${BASE_PATH}/assets/css/customer_dashboard.css`,
    `${BASE_PATH}/assets/css/customer_packages.css`,
    `${BASE_PATH}/assets/css/customer_all_packages.css`,
    `${BASE_PATH}/assets/css/customer_venues.css`,
    `${BASE_PATH}/assets/css/customer_all_venues.css`,
    `${BASE_PATH}/assets/css/customer_gallery.css`,
    `${BASE_PATH}/assets/css/customer_all_gallery.css`,
    `${BASE_PATH}/assets/css/customer_booking.css`,
    `${BASE_PATH}/assets/css/customer_my_bookings.css`,
    `${BASE_PATH}/assets/css/customer_feedback.css`,

    `${BASE_PATH}/assets/css/package_management.css`,
    `${BASE_PATH}/assets/css/venue_management.css`,
    `${BASE_PATH}/assets/css/service_management.css`,
    `${BASE_PATH}/assets/css/staff_management.css`,
    `${BASE_PATH}/assets/css/gallery_management.css`,
    `${BASE_PATH}/assets/css/all_gallery.css`,
    `${BASE_PATH}/assets/css/all_packages.css`,
    `${BASE_PATH}/assets/css/all_venues.css`,
    `${BASE_PATH}/assets/css/notifications.css`,
    `${BASE_PATH}/assets/css/public_home.css`,

    `${BASE_PATH}/assets/images/elegant_wedding_reception_in_grand_hall.png`,

    `${BASE_PATH}/assets/js/main.js`,
    `${BASE_PATH}/assets/js/pwa.js`,
    `${BASE_PATH}/assets/js/validation.js`,
    `${BASE_PATH}/assets/js/admin_consistency.js`,
    `${BASE_PATH}/assets/js/event_manager_consistency.js`,
    `${BASE_PATH}/assets/js/booking_manager_consistency.js`,
    `${BASE_PATH}/assets/js/customer_consistency.js`,
    `${BASE_PATH}/assets/js/customer_gallery_view.js`,

    `${BASE_PATH}/assets/icons/icon-192.png`,
    `${BASE_PATH}/assets/icons/icon-512.png`,
    `${BASE_PATH}/assets/icons/icon-maskable-512.png`,
    `${BASE_PATH}/assets/icons/apple-touch-icon.png`
];

/*
|--------------------------------------------------------------------------
| Install service worker
|--------------------------------------------------------------------------
*/

self.addEventListener(
    "install",
    function (event) {
        event.waitUntil(
            caches
                .open(CACHE_VERSION)
                .then(function (cache) {
                    return cache.addAll(
                        STATIC_FILES
                    );
                })
                .then(function () {
                    return self.skipWaiting();
                })
        );
    }
);

/*
|--------------------------------------------------------------------------
| Activate service worker
|--------------------------------------------------------------------------
*/

self.addEventListener(
    "activate",
    function (event) {
        event.waitUntil(
            caches
                .keys()
                .then(function (cacheNames) {
                    return Promise.all(
                        cacheNames
                            .filter(
                                function (cacheName) {
                                    return (
                                        cacheName
                                        !== CACHE_VERSION
                                    );
                                }
                            )
                            .map(
                                function (cacheName) {
                                    return caches.delete(
                                        cacheName
                                    );
                                }
                            )
                    );
                })
                .then(function () {
                    return self.clients.claim();
                })
        );
    }
);

/*
|--------------------------------------------------------------------------
| Handle network requests
|--------------------------------------------------------------------------
*/

self.addEventListener(
    "fetch",
    function (event) {
        const request =
            event.request;

        if (request.method !== "GET") {
            return;
        }

        const requestUrl =
            new URL(request.url);

        if (
            requestUrl.origin
            !== self.location.origin
        ) {
            return;
        }

        if (request.mode === "navigate") {
            event.respondWith(
                fetch(request).catch(
                    function () {
                        return caches.match(
                            OFFLINE_PAGE
                        );
                    }
                )
            );

            return;
        }

        const staticDestinations = [
            "style",
            "script",
            "image",
            "font",
            "manifest"
        ];

        if (
            !staticDestinations.includes(
                request.destination
            )
        ) {
            return;
        }

        event.respondWith(
            caches
                .match(request)
                .then(function (cachedResponse) {
                    if (cachedResponse) {
                        return cachedResponse;
                    }

                    return fetch(request).then(
                        function (networkResponse) {
                            if (
                                !networkResponse
                                || networkResponse.status !== 200
                                || networkResponse.type !== "basic"
                            ) {
                                return networkResponse;
                            }

                            const responseCopy =
                                networkResponse.clone();

                            caches
                                .open(CACHE_VERSION)
                                .then(function (cache) {
                                    cache.put(
                                        request,
                                        responseCopy
                                    );
                                });

                            return networkResponse;
                        }
                    );
                })
        );
    }
);