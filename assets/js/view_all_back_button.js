"use strict";

/*
|--------------------------------------------------------------------------
| View All Page Back Buttons
|--------------------------------------------------------------------------
| Admin pages receive an injected Back button beside the Add New button.
|
| Customer View All pages already contain a Back button in their PHP markup.
| This script keeps only that one button and changes its destination according
| to where the visitor opened the page:
|
| - Public website -> back to the matching homepage section.
| - Customer dashboard -> back to the matching customer browse page.
|
| Booking Manager pages already contain their own correctly placed buttons.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const currentPath =
            normalisePath(
                window.location.pathname
            );

        removeOldInjectedElements();

        if (
            isBookingManagerViewAllPage(
                currentPath
            )
        ) {
            return;
        }

        const projectBasePath =
            getProjectBasePath(
                currentPath
            );

        const customerPage =
            getCustomerPageConfiguration(
                currentPath,
                projectBasePath
            );

        if (customerPage) {
            configureCustomerBackButton(
                customerPage
            );

            return;
        }

        const adminPage =
            getAdminPageConfiguration(
                currentPath,
                projectBasePath
            );

        if (adminPage) {
            addAdminBackButton(
                adminPage
            );
        }
    }
);

/*
|--------------------------------------------------------------------------
| Path helpers
|--------------------------------------------------------------------------
*/

function normalisePath(path) {
    return String(path || "")
        .replace(
            /\\/g,
            "/"
        )
        .replace(
            /\/{2,}/g,
            "/"
        );
}

function getProjectBasePath(currentPath) {
    return currentPath.replace(
        /\/(?:admin|customer|booking_manager|event_manager|gallery)\/[^/]+$/,
        ""
    );
}

function isBookingManagerViewAllPage(currentPath) {
    return (
        currentPath.endsWith(
            "/booking_manager/all_packages.php"
        )
        || currentPath.endsWith(
            "/booking_manager/all_venues.php"
        )
    );
}

/*
|--------------------------------------------------------------------------
| Customer page configuration
|--------------------------------------------------------------------------
*/

function getCustomerPageConfiguration(
    currentPath,
    projectBasePath
) {
    const configurations = [
        {
            route:
                "/customer/all_packages.php",

            existingButtonSelector:
                ".customer-all-packages-back",

            label:
                "Back to Packages",

            publicUrl:
                `${projectBasePath}/index.php#packages`,

            dashboardUrl:
                `${projectBasePath}/customer/packages.php`,

            dashboardSourcePath:
                `${projectBasePath}/customer/packages.php`
        },

        {
            route:
                "/customer/all_venues.php",

            existingButtonSelector:
                ".customer-all-venues-back",

            label:
                "Back to Venues",

            publicUrl:
                `${projectBasePath}/index.php#venues`,

            dashboardUrl:
                `${projectBasePath}/customer/venues.php`,

            dashboardSourcePath:
                `${projectBasePath}/customer/venues.php`
        },

        {
            route:
                "/customer/all_gallery.php",

            existingButtonSelector:
                ".customer-all-gallery-back",

            label:
                "Back to Gallery",

            publicUrl:
                `${projectBasePath}/index.php#gallery`,

            dashboardUrl:
                `${projectBasePath}/customer/gallery.php`,

            dashboardSourcePath:
                `${projectBasePath}/customer/gallery.php`
        }
    ];

    const configuration =
        configurations.find(
            function (item) {
                return currentPath.endsWith(
                    item.route
                );
            }
        );

    if (!configuration) {
        return null;
    }

    return {
        ...configuration,

        currentPath:
            currentPath,

        projectBasePath:
            projectBasePath,

        storageKey:
            `wedding-planner-view-all-source:${configuration.route}`
    };
}

function configureCustomerBackButton(configuration) {
    const source =
        resolveCustomerPageSource(
            configuration
        );

    rememberCustomerPageSource(
        configuration,
        source
    );

    keepSourceDuringViewAllNavigation(
        configuration,
        source
    );

    const targetUrl =
        source === "dashboard"
            ? configuration.dashboardUrl
            : configuration.publicUrl;

    const existingButtons =
        findCustomerBackButtons(
            configuration
        );

    let backButton =
        existingButtons.shift()
        || null;

    existingButtons.forEach(
        function (button) {
            button.remove();
        }
    );

    if (!backButton) {
        backButton =
            createCustomerBackButton(
                configuration
            );
    }

    if (!backButton) {
        return;
    }

    backButton.href =
        targetUrl;

    backButton.setAttribute(
        "data-view-all-source",
        source
    );

    backButton.innerHTML = `
        <i class="fa-solid fa-arrow-left"></i>
        <span>${configuration.label}</span>
    `;
}

function resolveCustomerPageSource(configuration) {
    const urlSource =
        new URLSearchParams(
            window.location.search
        ).get(
            "source"
        );

    if (
        urlSource === "public"
        || urlSource === "dashboard"
    ) {
        return urlSource;
    }

    const referrerSource =
        getSourceFromReferrer(
            configuration
        );

    if (referrerSource) {
        return referrerSource;
    }

    try {
        const rememberedSource =
            window.sessionStorage.getItem(
                configuration.storageKey
            );

        if (
            rememberedSource === "public"
            || rememberedSource === "dashboard"
        ) {
            return rememberedSource;
        }
    } catch (error) {
        /*
         * Session storage may be unavailable
         * in some private browser modes.
         */
    }

    return "public";
}

function getSourceFromReferrer(configuration) {
    if (!document.referrer) {
        return "";
    }

    try {
        const referrer =
            new URL(
                document.referrer,
                window.location.href
            );

        if (
            referrer.origin
            !== window.location.origin
        ) {
            return "";
        }

        const referrerPath =
            normalisePath(
                referrer.pathname
            );

        if (
            referrerPath
            === normalisePath(
                configuration.dashboardSourcePath
            )
        ) {
            return "dashboard";
        }

        const publicRoot =
            normalisePath(
                `${configuration.projectBasePath}/`
            );

        const publicIndex =
            normalisePath(
                `${configuration.projectBasePath}/index.php`
            );

        if (
            referrerPath === publicRoot
            || referrerPath === publicIndex
        ) {
            return "public";
        }
    } catch (error) {
        return "";
    }

    return "";
}

function rememberCustomerPageSource(
    configuration,
    source
) {
    try {
        window.sessionStorage.setItem(
            configuration.storageKey,
            source
        );
    } catch (error) {
        /*
         * The page still works when session
         * storage is unavailable.
         */
    }
}

function keepSourceDuringViewAllNavigation(
    configuration,
    source
) {
    const currentUrl =
        new URL(
            window.location.href
        );

    if (
        currentUrl.searchParams.get(
            "source"
        ) !== source
    ) {
        currentUrl.searchParams.set(
            "source",
            source
        );

        window.history.replaceState(
            {},
            document.title,
            currentUrl.toString()
        );
    }

    /*
     * Keep the source when the search
     * or filter form is submitted.
     */
    document
        .querySelectorAll(
            "form"
        )
        .forEach(
            function (form) {
                const method =
                    String(
                        form.getAttribute(
                            "method"
                        )
                        || "get"
                    ).toLowerCase();

                if (method !== "get") {
                    return;
                }

                let sourceInput =
                    form.querySelector(
                        'input[name="source"]'
                    );

                if (!sourceInput) {
                    sourceInput =
                        document.createElement(
                            "input"
                        );

                    sourceInput.type =
                        "hidden";

                    sourceInput.name =
                        "source";

                    form.appendChild(
                        sourceInput
                    );
                }

                sourceInput.value =
                    source;
            }
        );

    /*
     * Keep the source inside pagination,
     * clear-filter and same-page links.
     */
    document
        .querySelectorAll(
            "a[href]"
        )
        .forEach(
            function (link) {
                if (
                    link.matches(
                        configuration.existingButtonSelector
                    )
                    || link.classList.contains(
                        "view-all-return-button"
                    )
                ) {
                    return;
                }

                let linkUrl;

                try {
                    linkUrl =
                        new URL(
                            link.href,
                            window.location.href
                        );
                } catch (error) {
                    return;
                }

                if (
                    linkUrl.origin
                    !== window.location.origin
                ) {
                    return;
                }

                if (
                    normalisePath(
                        linkUrl.pathname
                    )
                    !== configuration.currentPath
                ) {
                    return;
                }

                linkUrl.searchParams.set(
                    "source",
                    source
                );

                link.href =
                    linkUrl.toString();
            }
        );
}

function findCustomerBackButtons(configuration) {
    const candidates =
        Array.from(
            document.querySelectorAll(
                [
                    configuration.existingButtonSelector,
                    ".view-all-return-button",
                    ".view-all-back-arrow"
                ].join(", ")
            )
        );

    /*
     * Also detect a duplicate button by its
     * visible text even if an older version
     * used another class name.
     */
    document
        .querySelectorAll(
            "a, button"
        )
        .forEach(
            function (element) {
                const text =
                    String(
                        element.textContent
                        || ""
                    )
                        .replace(
                            /\s+/g,
                            " "
                        )
                        .trim();

                if (
                    text === configuration.label
                    && !candidates.includes(
                        element
                    )
                ) {
                    candidates.push(
                        element
                    );
                }
            }
        );

    /*
     * Prefer the original PHP button,
     * then delete all remaining duplicates.
     */
    candidates.sort(
        function (first, second) {
            const firstIsOriginal =
                first.matches(
                    configuration.existingButtonSelector
                );

            const secondIsOriginal =
                second.matches(
                    configuration.existingButtonSelector
                );

            if (
                firstIsOriginal
                === secondIsOriginal
            ) {
                return 0;
            }

            return firstIsOriginal
                ? -1
                : 1;
        }
    );

    return candidates;
}

function createCustomerBackButton(configuration) {
    const searchInput =
        document.querySelector(
            [
                'input[placeholder*="Search packages"]',
                'input[placeholder*="Search venues"]',
                'input[placeholder*="Search gallery"]',
                'input[placeholder*="Search Packages"]',
                'input[placeholder*="Search Venues"]',
                'input[placeholder*="Search Gallery"]'
            ].join(", ")
        );

    if (!searchInput) {
        return null;
    }

    let toolbar =
        searchInput.closest(
            "form"
        );

    if (!toolbar) {
        toolbar =
            searchInput.parentElement
                ? searchInput.parentElement.parentElement
                : null;
    }

    if (!toolbar) {
        return null;
    }

    const button =
        document.createElement(
            "a"
        );

    const originalClass =
        configuration
            .existingButtonSelector
            .replace(
                ".",
                ""
            );

    button.className =
        `view-all-return-button ${originalClass}`;

    if (toolbar.parentElement) {
        toolbar.parentElement.appendChild(
            button
        );
    } else {
        toolbar.appendChild(
            button
        );
    }

    addBackButtonStyles();

    return button;
}

/*
|--------------------------------------------------------------------------
| Admin page configuration and injection
|--------------------------------------------------------------------------
*/

function getAdminPageConfiguration(
    currentPath,
    projectBasePath
) {
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
        }
    };

    const routeKey =
        Object.keys(
            pageRoutes
        ).find(
            function (pathEnding) {
                return currentPath.endsWith(
                    pathEnding
                );
            }
        );

    return routeKey
        ? pageRoutes[routeKey]
        : null;
}

function addAdminBackButton(pageRoute) {
    const adjacentButton =
        Array.from(
            document.querySelectorAll(
                "a, button"
            )
        ).find(
            function (element) {
                return String(
                    element.textContent
                    || ""
                )
                    .trim()
                    .includes(
                        pageRoute.adjacentButton
                    );
            }
        );

    if (
        !adjacentButton
        || !adjacentButton.parentElement
    ) {
        return;
    }

    const backButton =
        document.createElement(
            "a"
        );

    backButton.className =
        "view-all-return-button";

    backButton.href =
        pageRoute.url;

    backButton.innerHTML = `
        <i class="fa-solid fa-arrow-left"></i>
        <span>${pageRoute.label}</span>
    `;

    adjacentButton.parentElement.insertBefore(
        backButton,
        adjacentButton
    );

    adjacentButton.parentElement.classList.add(
        "view-all-top-actions"
    );

    addBackButtonStyles();

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
}

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

function removeOldInjectedElements() {
    document
        .querySelectorAll(
            ".view-all-return-button, .view-all-back-arrow"
        )
        .forEach(
            function (button) {
                button.remove();
            }
        );

    document
        .querySelectorAll(
            ".view-all-heading-row"
        )
        .forEach(
            function (headingRow) {
                const heading =
                    headingRow.querySelector(
                        "h1"
                    );

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
            }
        );
}

/*
|--------------------------------------------------------------------------
| Shared injected button styling
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
        document.createElement(
            "style"
        );

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