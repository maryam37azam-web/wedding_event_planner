"use strict";

/*
|--------------------------------------------------------------------------
| Shared Customer interface
|--------------------------------------------------------------------------
| Keeps every Customer sidebar consistent and displays the saved About
| value underneath the customer's name.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const aboutMeta =
            document.querySelector(
                'meta[name="customer-sidebar-about"]'
            );

        const aboutText =
            aboutMeta
                ?.getAttribute("content")
                ?.trim()
            || "Customer Account";

        normalizeCustomerLayout();

        updateCustomerAbout(
            aboutText
        );

        normalizeCustomerOnlineStatus();

        normalizeCustomerMenuIcons();

        normalizeCustomerMenuButtons();

        makeCustomerAboutFieldCompact();

        updateCustomerGalleryHeader(
            aboutText
        );
    }
);

/*
|--------------------------------------------------------------------------
| Normalize customer layout
|--------------------------------------------------------------------------
*/

function normalizeCustomerLayout() {
    const sidebarSelectors = [
        ".customer-sidebar",
        ".profile-sidebar",
        ".customer-package-sidebar",
        ".customer-packages-sidebar",
        ".customer-venue-sidebar",
        ".customer-venues-sidebar",
        ".customer-gallery-sidebar",
        ".customer-booking-sidebar",
        ".customer-feedback-sidebar",
        ".my-bookings-sidebar",
        ".sidebar",
        "aside[class*='sidebar']",
        "aside"
    ];

    const sidebar =
        findFirstElement(
            sidebarSelectors
        );

    if (sidebar) {
        sidebar.classList.add(
            "customer-shared-sidebar"
        );

        const menu =
            sidebar.querySelector("nav");

        if (menu) {
            menu.classList.add(
                "customer-shared-menu"
            );
        }

        const logo =
            findFirstInside(
                sidebar,
                [
                    ".customer-logo",
                    ".sidebar-logo",
                    ".profile-logo",
                    ".customer-sidebar-logo",
                    ".customer-gallery-logo",
                    ".customer-package-logo",
                    ".customer-venue-logo"
                ]
            )
            || findWeddingLogo(sidebar);

        if (logo) {
            logo.classList.add(
                "customer-shared-logo"
            );
        }

        const profileBlock =
            findCustomerProfileBlock(
                sidebar
            );

        if (profileBlock) {
            profileBlock.classList.add(
                "customer-shared-profile"
            );
        }

        /*
         * Only align the main content when a sidebar exists.
         * Standalone View All pages must remain full width.
         */
        const mainContent =
            document.querySelector("main");

        if (mainContent) {
            mainContent.classList.add(
                "customer-shared-main"
            );
        }
    }

    const overlay =
        findFirstElement([
            ".customer-sidebar-overlay",
            ".sidebar-overlay",
            ".customer-overlay"
        ]);

    if (overlay) {
        overlay.classList.add(
            "customer-shared-overlay"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Show saved About value
|--------------------------------------------------------------------------
*/

function updateCustomerAbout(
    aboutText
) {
    const sidebar =
        document.querySelector(
            ".customer-shared-sidebar"
        );

    if (!sidebar) {
        return;
    }

    const profileBlock =
        findCustomerProfileBlock(
            sidebar
        );

    if (!profileBlock) {
        return;
    }

    profileBlock.classList.add(
        "customer-shared-profile"
    );

    let description =
        findFirstInside(
            profileBlock,
            [
                ".customer-shared-about",
                ".customer-about",
                ".customer-role",
                ".profile-role",
                ".sidebar-role",
                "p:not([class*='online'])",
                "span:not([class*='online'])"
            ]
        );

    if (!description) {
        description =
            document.createElement("p");

        const customerName =
            profileBlock.querySelector(
                "h2, h3, strong"
            );

        if (customerName) {
            customerName.insertAdjacentElement(
                "afterend",
                description
            );
        } else {
            profileBlock.appendChild(
                description
            );
        }
    }

    description.textContent =
        aboutText;

    description.classList.add(
        "customer-shared-about"
    );

    description.setAttribute(
        "title",
        aboutText
    );
}

/*
|--------------------------------------------------------------------------
| Keep Online status identical
|--------------------------------------------------------------------------
*/

function normalizeCustomerOnlineStatus() {
    const sidebar =
        document.querySelector(
            ".customer-shared-sidebar"
        );

    if (!sidebar) {
        return;
    }

    const profileBlock =
        findCustomerProfileBlock(
            sidebar
        );

    if (!profileBlock) {
        return;
    }

    let onlineStatus =
        findFirstInside(
            profileBlock,
            [
                ".customer-online",
                ".profile-online",
                ".sidebar-online",
                ".online-status",
                "[class*='online']"
            ]
        );

    if (!onlineStatus) {
        onlineStatus =
            document.createElement("div");

        profileBlock.appendChild(
            onlineStatus
        );
    }

    onlineStatus.textContent =
        "● Online";

    onlineStatus.classList.add(
        "customer-shared-online"
    );
}

/*
|--------------------------------------------------------------------------
| Keep menu icons consistent
|--------------------------------------------------------------------------
*/

function normalizeCustomerMenuIcons() {
    const iconMap = [
        {
            paths: [
                "/customer/dashboard.php"
            ],
            icon: "fa-house"
        },
        {
            paths: [
                "/customer/packages.php"
            ],
            icon: "fa-gift"
        },
        {
            paths: [
                "/customer/venues.php"
            ],
            icon: "fa-hotel"
        },
        {
            paths: [
                "/customer/gallery.php"
            ],
            icon: "fa-images"
        },
        {
            paths: [
                "/customer/booking.php",
                "/customer/book_event.php"
            ],
            icon: "fa-calendar-plus"
        },
        {
            paths: [
                "/customer/my_bookings.php",
                "/customer/bookings.php"
            ],
            icon: "fa-calendar-check"
        },
        {
            paths: [
                "/customer/feedback.php"
            ],
            icon: "fa-star"
        },
        {
            paths: [
                "/customer/profile.php"
            ],
            icon: "fa-user"
        },
        {
            paths: [
                "/auth/logout.php"
            ],
            icon: "fa-right-from-bracket"
        }
    ];

    const menuLinks =
        document.querySelectorAll(
            ".customer-shared-menu a"
        );

    menuLinks.forEach(
        function (link) {
            const icon =
                link.querySelector("i");

            if (!icon) {
                return;
            }

            let pathname = "";

            try {
                pathname = new URL(
                    link.href,
                    window.location.href
                ).pathname;
            } catch (error) {
                return;
            }

            const iconSettings =
                iconMap.find(
                    function (item) {
                        return item.paths.some(
                            function (path) {
                                return pathname.endsWith(
                                    path
                                );
                            }
                        );
                    }
                );

            if (!iconSettings) {
                return;
            }

            icon.className =
                "fa-solid "
                + iconSettings.icon;
        }
    );
}

/*
|--------------------------------------------------------------------------
| Normalize menu buttons
|--------------------------------------------------------------------------
*/

function normalizeCustomerMenuButtons() {
    document
        .querySelectorAll("button")
        .forEach(function (button) {
            const containsBarsIcon =
                button.querySelector(
                    ".fa-bars"
                );

            const label =
                (
                    button.getAttribute(
                        "aria-label"
                    )
                    || ""
                ).toLowerCase();

            if (
                containsBarsIcon
                || label.includes("menu")
                || label.includes("navigation")
            ) {
                button.classList.add(
                    "customer-shared-menu-button"
                );
            }
        });
}

/*
|--------------------------------------------------------------------------
| Compact About field
|--------------------------------------------------------------------------
*/

function makeCustomerAboutFieldCompact() {
    const aboutField =
        document.querySelector(
            'textarea[name="about"], #about'
        );

    if (!aboutField) {
        return;
    }

    aboutField.rows = 1;

    aboutField.classList.add(
        "customer-shared-about-field"
    );

    aboutField.setAttribute(
        "aria-label",
        "Customer description"
    );

    aboutField.addEventListener(
        "input",
        function () {
            const oneLineValue =
                aboutField.value.replace(
                    /\s*\r?\n+\s*/g,
                    " "
                );

            if (
                oneLineValue
                !== aboutField.value
            ) {
                aboutField.value =
                    oneLineValue;
            }
        }
    );

    aboutField.addEventListener(
        "keydown",
        function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
            }
        }
    );
}

/*
|--------------------------------------------------------------------------
| Shared View All Gallery profile
|--------------------------------------------------------------------------
*/

function updateCustomerGalleryHeader(
    aboutText
) {
    const galleryUser =
        document.querySelector(
            "body.all-gallery-page "
            + ".all-gallery-user"
        );

    if (!galleryUser) {
        return;
    }

    let copyBlock =
        galleryUser.querySelector(
            ":scope > div"
        );

    if (!copyBlock) {
        copyBlock =
            document.createElement("div");

        galleryUser.prepend(
            copyBlock
        );
    }

    let description =
        copyBlock.querySelector("span");

    if (!description) {
        description =
            document.createElement("span");

        copyBlock.appendChild(
            description
        );
    }

    description.textContent =
        aboutText;

    description.classList.add(
        "customer-gallery-header-about"
    );

    description.setAttribute(
        "title",
        aboutText
    );
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function findFirstElement(
    selectors
) {
    for (const selector of selectors) {
        const element =
            document.querySelector(
                selector
            );

        if (element) {
            return element;
        }
    }

    return null;
}

function findFirstInside(
    container,
    selectors
) {
    for (const selector of selectors) {
        const element =
            container.querySelector(
                selector
            );

        if (element) {
            return element;
        }
    }

    return null;
}

function findWeddingLogo(
    sidebar
) {
    const blocks =
        sidebar.querySelectorAll(
            "div"
        );

    for (const block of blocks) {
        const heading =
            block.querySelector(
                ":scope > h1"
            );

        if (
            heading
            && heading.textContent
                ?.trim()
                .toLowerCase()
                === "wedding"
        ) {
            return block;
        }
    }

    return null;
}

function findCustomerProfileBlock(
    sidebar
) {
    const knownProfile =
        findFirstInside(
            sidebar,
            [
                ".customer-profile",
                ".customer-sidebar-profile",
                ".sidebar-profile",
                ".profile-sidebar-user",
                ".customer-package-profile",
                ".customer-venue-profile",
                ".customer-gallery-profile",
                ".customer-booking-profile",
                ".customer-feedback-profile",
                ".my-bookings-profile",
                "[class*='profile']"
            ]
        );

    if (
        knownProfile
        && (
            knownProfile.querySelector(
                "h2, h3, strong"
            )
            || knownProfile.querySelector(
                "img"
            )
        )
    ) {
        return knownProfile;
    }

    const possibleBlocks =
        sidebar.querySelectorAll(
            "div, section"
        );

    for (const block of possibleBlocks) {
        const hasName =
            block.querySelector(
                ":scope > h2, "
                + ":scope > h3, "
                + ":scope > strong"
            );

        const hasImage =
            block.querySelector(
                ":scope > img"
            );

        if (hasName && hasImage) {
            return block;
        }
    }

    return null;
}