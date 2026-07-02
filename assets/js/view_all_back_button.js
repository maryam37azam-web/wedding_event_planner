"use strict";

/*
|--------------------------------------------------------------------------
| View All Page Back Buttons
|--------------------------------------------------------------------------
| Admin and Customer package/venue pages receive a back button.
|
| Booking Manager pages already contain their own back button, so this
| script removes any previously injected duplicate and adds nothing.
|
| Gallery pages also already contain their own Back to Gallery button.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const currentPath =
            window.location.pathname.replace(
                /\/+/g,
                "/"
            );

        /*
         * Remove buttons injected by an older version
         * of this script.
         */
        document
            .querySelectorAll(
                ".view-all-return-button"
            )
            .forEach(function (button) {
                button.remove();
            });

        document
            .querySelectorAll(
                ".view-all-back-arrow"
            )
            .forEach(function (button) {
                button.remove();
            });

        /*
         * Restore the heading if an older script
         * placed it inside a special heading row.
         */
        document
            .querySelectorAll(
                ".view-all-heading-row"
            )
            .forEach(function (headingRow) {
                const heading =
                    headingRow.querySelector("h1");

                if (
                    heading
                    && headingRow.parentNode
                ) {
                    headingRow.parentNode.insertBefore(
                        heading,
                        headingRow
                    );
                }

                headingRow.remove();
            });

        /*
         * Booking Manager pages already have their
         * own back buttons.
         */
        if (
            currentPath.endsWith(
                "/booking_manager/all_packages.php"
            )
            || currentPath.endsWith(
                "/booking_manager/all_venues.php"
            )
        ) {
            return;
        }

        /*
         * Gallery pages already contain their own
         * Back to Gallery button.
         */
        if (
            currentPath.endsWith(
                "/gallery/all_gallery.php"
            )
            || currentPath.endsWith(
                "/event_manager/all_gallery.php"
            )
            || currentPath.endsWith(
                "/customer/all_gallery.php"
            )
        ) {
            return;
        }

        /*
         * Detect the project base path.
         */
        const projectBasePath =
            currentPath.replace(
                /\/(?:admin|customer)\/[^/]+$/,
                ""
            );

        const pageRoutes = {
            "/admin/all_packages.php": {
                url:
                    `${projectBasePath}/admin/packages.php`,
                label:
                    "Back to Packages",
                adjacentButton:
                    "Add New Package"
            },

            "/admin/all_venues.php": {
                url:
                    `${projectBasePath}/admin/venues.php`,
                label:
                    "Back to Venues",
                adjacentButton:
                    "Add New Venue"
            },

            "/customer/all_packages.php": {
                url:
                    `${projectBasePath}/customer/packages.php`,
                label:
                    "Back to Packages",
                adjacentButton:
                    ""
            },

            "/customer/all_venues.php": {
                url:
                    `${projectBasePath}/customer/venues.php`,
                label:
                    "Back to Venues",
                adjacentButton:
                    ""
            }
        };

        const routeKey =
            Object.keys(pageRoutes).find(
                function (pathEnding) {
                    return currentPath.endsWith(
                        pathEnding
                    );
                }
            );

        if (!routeKey) {
            return;
        }

        const pageRoute =
            pageRoutes[routeKey];

        const backButton =
            document.createElement("a");

        backButton.className =
            "view-all-return-button";

        backButton.href =
            pageRoute.url;

        backButton.innerHTML = `
            <i class="fa-solid fa-arrow-left"></i>
            <span>${pageRoute.label}</span>
        `;

        /*
         * Admin pages: insert before the Add New button.
         */
        let adjacentButton = null;

        if (pageRoute.adjacentButton !== "") {
            adjacentButton = Array.from(
                document.querySelectorAll(
                    "a, button"
                )
            ).find(function (element) {
                return String(
                    element.textContent || ""
                )
                    .trim()
                    .includes(
                        pageRoute.adjacentButton
                    );
            });
        }

        if (
            adjacentButton
            && adjacentButton.parentElement
        ) {
            adjacentButton.parentElement.insertBefore(
                backButton,
                adjacentButton
            );

            adjacentButton.parentElement.classList.add(
                "view-all-top-actions"
            );

            addBackButtonStyles();

            /*
             * Keep the same height as the adjacent button
             * while allowing enough horizontal padding.
             */
            window.requestAnimationFrame(
                function () {
                    const adjacentSize =
                        adjacentButton.getBoundingClientRect();

                    if (adjacentSize.height > 0) {
                        const matchingHeight =
                            `${Math.ceil(
                                adjacentSize.height
                            )}px`;

                        backButton.style.height =
                            matchingHeight;

                        backButton.style.minHeight =
                            matchingHeight;
                    }
                }
            );

            return;
        }

        /*
         * Customer pages: insert into the existing
         * search/filter toolbar.
         */
        const searchInput =
            document.querySelector(
                [
                    'input[placeholder*="Search packages"]',
                    'input[placeholder*="Search venues"]',
                    'input[placeholder*="Search Packages"]',
                    'input[placeholder*="Search Venues"]'
                ].join(", ")
            );

        if (!searchInput) {
            return;
        }

        let toolbar =
            searchInput.closest("form");

        if (!toolbar) {
            toolbar =
                searchInput.parentElement
                    ? searchInput.parentElement.parentElement
                    : null;
        }

        if (!toolbar) {
            return;
        }

        toolbar.appendChild(
            backButton
        );

        toolbar.classList.add(
            "view-all-top-actions"
        );

        addBackButtonStyles();
    }
);

/*
|--------------------------------------------------------------------------
| Back button styling
|--------------------------------------------------------------------------
*/

function addBackButtonStyles() {
    if (
        document.getElementById(
            "view-all-return-button-styles"
        )
    ) {
        return;
    }

    const style =
        document.createElement("style");

    style.id =
        "view-all-return-button-styles";

    style.textContent = `
        .view-all-top-actions {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
        }

        .view-all-return-button {
            min-width: max-content;
            min-height: 52px;
            padding: 0 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-shrink: 0;
            box-sizing: border-box;
            border: 1px solid #dcdfe7;
            border-radius: 11px;
            background: #ffffff;
            color: #2f3138;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .view-all-return-button i {
            flex: 0 0 auto;
            font-size: 13px;
        }

        .view-all-return-button span {
            display: inline-block;
        }

        .view-all-return-button:hover {
            border-color: #a4004d;
            background: #fff5f8;
            color: #a4004d;
            box-shadow:
                0 6px 17px
                rgba(164, 0, 77, 0.10);
            transform: translateY(-1px);
        }

        .view-all-return-button:focus-visible {
            outline: 3px solid
                rgba(164, 0, 77, 0.20);
            outline-offset: 3px;
        }

        @media (max-width: 1050px) {
            .view-all-top-actions {
                flex-wrap: wrap !important;
            }
        }

        @media (max-width: 620px) {
            .view-all-return-button {
                width: 100%;
                min-height: 48px;
                padding: 0 16px;
            }
        }
    `;

    document.head.appendChild(
        style
    );
}