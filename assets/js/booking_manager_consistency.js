"use strict";

/*
|--------------------------------------------------------------------------
| Shared Booking Manager interface
|--------------------------------------------------------------------------
| Keeps Booking Manager sidebars consistent, displays the saved About
| value and connects shared navigation links.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const aboutMeta = document.querySelector(
            'meta[name="booking-manager-sidebar-about"]'
        );

        const aboutText =
            aboutMeta
                ?.getAttribute("content")
                ?.trim()
            || "Booking Manager";

        updateBookingManagerDescriptions(
            aboutText
        );

        normalizeOnlineStatus();

        normalizeBookingManagerIcons();

        makeAboutFieldCompact();

        connectGalleryTotalImagesCard();
    }
);

/*
|--------------------------------------------------------------------------
| Display saved About value
|--------------------------------------------------------------------------
*/

function updateBookingManagerDescriptions(
    aboutText
) {
    const descriptionSelectors = [
        ".booking-sidebar-profile > p",
        ".notifications-profile > p",
        ".gallery-sidebar-profile > p",
        ".sidebar-profile > span"
    ];

    document
        .querySelectorAll(
            descriptionSelectors.join(",")
        )
        .forEach(function (description) {
            description.textContent =
                aboutText;

            description.classList.add(
                "booking-manager-linked-about"
            );

            description.setAttribute(
                "title",
                aboutText
            );
        });

    /*
     * Manage Profile sidebar fallback.
     */
    const profileSidebar =
        document.querySelector(
            "body.profile-page .sidebar-profile"
        );

    if (profileSidebar) {
        let description =
            profileSidebar.querySelector(
                ".booking-manager-linked-about"
            );

        if (!description) {
            description =
                document.createElement(
                    "span"
                );

            description.className =
                "booking-manager-linked-about";

            profileSidebar.appendChild(
                description
            );
        }

        description.textContent =
            aboutText;

        description.setAttribute(
            "title",
            aboutText
        );
    }

    /*
     * Shared View All Gallery page top-right profile.
     */
    const galleryHeaderDescription =
        document.querySelector(
            "body.all-gallery-page "
            + ".all-gallery-user span"
        );

    if (galleryHeaderDescription) {
        galleryHeaderDescription.textContent =
            aboutText;

        galleryHeaderDescription.setAttribute(
            "title",
            aboutText
        );
    }
}

/*
|--------------------------------------------------------------------------
| Keep Online status identical
|--------------------------------------------------------------------------
*/

function normalizeOnlineStatus() {
    const onlineSelectors = [
        ".booking-online",
        ".notifications-online",
        ".gallery-online-status",
        ".booking-manager-profile-online"
    ];

    document
        .querySelectorAll(
            onlineSelectors.join(",")
        )
        .forEach(function (onlineStatus) {
            onlineStatus.textContent =
                "● Online";
        });

    /*
     * Add Online status to the shared profile page when it is missing.
     */
    const profileSidebar =
        document.querySelector(
            "body.profile-page .sidebar-profile"
        );

    if (
        profileSidebar
        && !profileSidebar.querySelector(
            ".booking-manager-profile-online"
        )
    ) {
        const onlineStatus =
            document.createElement("div");

        onlineStatus.className =
            "booking-manager-profile-online";

        onlineStatus.textContent =
            "● Online";

        profileSidebar.appendChild(
            onlineStatus
        );
    }
}

/*
|--------------------------------------------------------------------------
| Keep sidebar icons consistent
|--------------------------------------------------------------------------
*/

function normalizeBookingManagerIcons() {
    const iconMap = [
        {
            path: "/booking_manager/dashboard.php",
            icon: "fa-gauge"
        },
        {
            path: "/booking_manager/bookings.php",
            icon: "fa-calendar-check"
        },
        {
            path: "/booking_manager/booking.php",
            icon: "fa-calendar-plus"
        },
        {
            path: "/booking_manager/services.php",
            icon: "fa-bell-concierge"
        },
        {
            path: "/booking_manager/gallery.php",
            icon: "fa-images"
        },
        {
            path: "/booking_manager/packages.php",
            icon: "fa-gift"
        },
        {
            path: "/booking_manager/venues.php",
            icon: "fa-hotel"
        },
        {
            path: "/booking_manager/profile.php",
            icon: "fa-user"
        },
        {
            path: "/booking_manager/notifications.php",
            icon: "fa-bell"
        },
        {
            path: "/auth/logout.php",
            icon: "fa-right-from-bracket"
        }
    ];

    const menuLinks =
        document.querySelectorAll(
            ".booking-menu a, "
            + ".notifications-menu a, "
            + ".gallery-sidebar-menu a, "
            + ".sidebar-menu a"
        );

    menuLinks.forEach(function (link) {
        const icon = link.querySelector("i");

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
            iconMap.find(function (item) {
                return pathname.endsWith(
                    item.path
                );
            });

        if (!iconSettings) {
            return;
        }

        icon.className =
            "fa-solid "
            + iconSettings.icon;
    });
}

/*
|--------------------------------------------------------------------------
| Booking Manager gallery Total Images card
|--------------------------------------------------------------------------
| Clicking "Click to show all" must open the shared View All Gallery page.
*/

function connectGalleryTotalImagesCard() {
    const currentPath =
        window.location.pathname.replace(
            /\\/g,
            "/"
        );

    if (
        !currentPath.endsWith(
            "/booking_manager/gallery.php"
        )
    ) {
        return;
    }

    const appBaseMeta =
        document.querySelector(
            'meta[name="app-base-url"]'
        );

    const appBaseUrl =
        (
            appBaseMeta
                ?.getAttribute("content")
                ?.trim()
            || ""
        ).replace(
            /\/+$/,
            ""
        );

    const allGalleryUrl =
        `${appBaseUrl}/gallery/all_gallery.php`;

    /*
     * Find the visible "Click to show all" link.
     */
    const possibleLinks =
        document.querySelectorAll(
            "a, .gallery-summary-card"
        );

    possibleLinks.forEach(function (element) {
        const elementText =
            element.textContent
                ?.replace(/\s+/g, " ")
                .trim()
                .toLowerCase()
            || "";

        if (
            !elementText.includes(
                "click to show all"
            )
        ) {
            return;
        }

        if (
            element.tagName.toLowerCase()
            === "a"
        ) {
            element.href = allGalleryUrl;

            element.removeAttribute(
                "data-filter"
            );

            return;
        }

        const nestedLink =
            element.querySelector("a");

        if (nestedLink) {
            nestedLink.href =
                allGalleryUrl;

            nestedLink.removeAttribute(
                "data-filter"
            );

            return;
        }

        element.setAttribute(
            "role",
            "link"
        );

        element.setAttribute(
            "tabindex",
            "0"
        );

        element.style.cursor =
            "pointer";

        element.addEventListener(
            "click",
            function () {
                window.location.href =
                    allGalleryUrl;
            }
        );

        element.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key === "Enter"
                    || event.key === " "
                ) {
                    event.preventDefault();

                    window.location.href =
                        allGalleryUrl;
                }
            }
        );
    });
}

/*
|--------------------------------------------------------------------------
| Compact one-line About field
|--------------------------------------------------------------------------
*/

function makeAboutFieldCompact() {
    const aboutField =
        document.querySelector(
            'body.profile-page textarea[name="about"]'
        );

    if (!aboutField) {
        return;
    }

    aboutField.rows = 1;

    aboutField.setAttribute(
        "aria-label",
        "Booking Manager description"
    );
}