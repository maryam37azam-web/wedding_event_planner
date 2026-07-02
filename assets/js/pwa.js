"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const baseUrlMeta = document.querySelector(
        'meta[name="app-base-url"]'
    );

    if (!baseUrlMeta) {
        console.warn(
            "PWA base URL meta tag was not found."
        );

        return;
    }

    const baseUrl =
        baseUrlMeta.content.replace(
            /\/$/,
            ""
        );

    /*
    |--------------------------------------------------------------------------
    | Load shared fancy sidebar branding
    |--------------------------------------------------------------------------
    */

    const sidebarBrandExists =
        document.querySelector(
            [
                ".admin-logo",
                ".admin-bookings-logo",
                ".admin-feedback-logo",
                ".admin-gallery-logo",
                ".event-logo",
                ".assigned-tasks-logo",
                ".event-feedback-logo",
                ".booking-logo",
                ".customer-shared-logo",
                ".customer-logo",
                ".profile-logo",
                ".sidebar-logo",
                ".gallery-sidebar-logo",
                ".notifications-logo",
                ".customer-sidebar-logo",
                ".customer-gallery-logo",
                ".customer-package-logo",
                ".customer-venue-logo"
            ].join(",")
        );

    if (
        sidebarBrandExists
        && !document.querySelector(
            'link[data-sidebar-brand-fancy="true"]'
        )
    ) {
        const sidebarBrandStylesheet =
            document.createElement(
                "link"
            );

        sidebarBrandStylesheet.rel =
            "stylesheet";

        sidebarBrandStylesheet.href =
            `${baseUrl}/assets/css/sidebar_brand_fancy.css?v=20260703-1`;

        sidebarBrandStylesheet.dataset
            .sidebarBrandFancy =
                "true";

        document.head.appendChild(
            sidebarBrandStylesheet
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Register service worker
    |--------------------------------------------------------------------------
    */

    if (
        "serviceWorker"
        in navigator
    ) {
        window.addEventListener(
            "load",
            function () {
                navigator
                    .serviceWorker
                    .register(
                        `${baseUrl}/service-worker.js`,
                        {
                            scope:
                                `${baseUrl}/`
                        }
                    )
                    .then(
                        function (
                            registration
                        ) {
                            console.log(
                                "Service worker registered:",
                                registration.scope
                            );
                        }
                    )
                    .catch(
                        function (
                            error
                        ) {
                            console.error(
                                "Service worker registration failed:",
                                error
                            );
                        }
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Detect installed mode
    |--------------------------------------------------------------------------
    */

    const isInstalled =
        window
            .matchMedia(
                "(display-mode: standalone)"
            )
            .matches
        || window.navigator
            .standalone === true;

    if (isInstalled) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Create temporary floating install button
    |--------------------------------------------------------------------------
    */

    const installButton =
        document.createElement(
            "button"
        );

    installButton.type =
        "button";

    installButton.id =
        "installAppButton";

    installButton.textContent =
        "Install App";

    installButton.setAttribute(
        "aria-label",
        "Install Wedding Event Planner"
    );

    Object.assign(
        installButton.style,
        {
            display:
                "none",

            position:
                "fixed",

            right:
                "18px",

            bottom:
                "18px",

            zIndex:
                "9999",

            padding:
                "13px 20px",

            border:
                "none",

            borderRadius:
                "30px",

            background:
                "#a4004d",

            color:
                "#ffffff",

            boxShadow:
                "0 8px 24px rgba(0, 0, 0, 0.24)",

            cursor:
                "pointer",

            fontSize:
                "14px",

            fontWeight:
                "700"
        }
    );

    document.body.appendChild(
        installButton
    );

    let deferredInstallPrompt =
        null;

    window.addEventListener(
        "beforeinstallprompt",
        function (event) {
            event.preventDefault();

            deferredInstallPrompt =
                event;

            installButton.style.display =
                "block";
        }
    );

    installButton.addEventListener(
        "click",
        async function () {
            if (
                deferredInstallPrompt
            ) {
                deferredInstallPrompt
                    .prompt();

                await deferredInstallPrompt
                    .userChoice;

                deferredInstallPrompt =
                    null;

                installButton.style.display =
                    "none";

                return;
            }

            /*
             * iPhone and iPad installation instructions.
             */
            const isIOS =
                /iphone|ipad|ipod/i
                    .test(
                        window.navigator
                            .userAgent
                    );

            if (isIOS) {
                window.alert(
                    "To install this app on iPhone or iPad, "
                    + "open it in Safari, tap the Share button, "
                    + "then choose Add to Home Screen."
                );

                return;
            }

            window.alert(
                "Use your browser menu and choose Install App "
                + "or Add to Home Screen."
            );
        }
    );

    /*
     * Show instructions on iPhone and iPad because iOS does not
     * provide the beforeinstallprompt browser event.
     */
    const isIOS =
        /iphone|ipad|ipod/i
            .test(
                window.navigator
                    .userAgent
            );

    if (isIOS) {
        installButton.style.display =
            "block";
    }

    window.addEventListener(
        "appinstalled",
        function () {
            deferredInstallPrompt =
                null;

            installButton.style.display =
                "none";

            console.log(
                "Wedding Event Planner was installed."
            );
        }
    );
});