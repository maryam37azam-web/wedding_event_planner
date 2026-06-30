"use strict";

/*
|--------------------------------------------------------------------------
| Shared Event Manager interface
|--------------------------------------------------------------------------
| Keeps the Event Manager description synchronized with the About field,
| fixes shared Gallery navigation and removes unwanted form controls.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const aboutMeta = document.querySelector(
            'meta[name="event-manager-sidebar-about"]'
        );

        const aboutText =
            aboutMeta
                ?.getAttribute("content")
                ?.trim()
            || "Event Manager";

        updateEventManagerDescriptions(
            aboutText
        );

        connectTotalImagesCard();

        removeSecondImageRemovalOption();

        makeAboutFieldCompact();
    }
);

/*
|--------------------------------------------------------------------------
| Display the saved About value
|--------------------------------------------------------------------------
*/

function updateEventManagerDescriptions(
    aboutText
) {
    const descriptionSelectors = [
        ".event-profile > p",
        ".assigned-tasks-profile > p",
        ".notifications-profile > p",
        ".gallery-sidebar-profile > p",
        ".event-feedback-profile > p",
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
                "event-manager-linked-about"
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
                ".event-manager-linked-about"
            );

        if (!description) {
            description =
                document.createElement(
                    "span"
                );

            description.className =
                "event-manager-linked-about";

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
     * View All Gallery Images top-right profile.
     */
    const galleryHeaderProfile =
        document.querySelector(
            ".all-gallery-user"
        );

    if (galleryHeaderProfile) {
        let profileCopy =
            galleryHeaderProfile.querySelector(
                ":scope > div"
            );

        if (!profileCopy) {
            profileCopy =
                document.createElement(
                    "div"
                );

            galleryHeaderProfile.prepend(
                profileCopy
            );
        }

        let profileDescription =
            profileCopy.querySelector(
                "span"
            );

        if (!profileDescription) {
            profileDescription =
                document.createElement(
                    "span"
                );

            profileCopy.appendChild(
                profileDescription
            );
        }

        profileDescription.textContent =
            aboutText;

        profileDescription.classList.add(
            "event-manager-header-about"
        );

        profileDescription.setAttribute(
            "title",
            aboutText
        );
    }
}

/*
|--------------------------------------------------------------------------
| Total Images card
|--------------------------------------------------------------------------
| The first summary card is the Total Images card. It must open the shared
| View All Gallery Images page rather than filtering the current page.
*/

function connectTotalImagesCard() {
    const totalImagesCard =
        document.querySelector(
            "body.gallery-role-event_manager "
            + ".gallery-summary-grid "
            + ".gallery-summary-card:first-child"
        );

    if (!totalImagesCard) {
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

    totalImagesCard.href =
        `${appBaseUrl}/gallery/all_gallery.php`;

    totalImagesCard.removeAttribute(
        "data-filter"
    );
}

/*
|--------------------------------------------------------------------------
| Remove the "Remove Second Image" option
|--------------------------------------------------------------------------
| The Event Manager may still replace the second image by selecting another
| file, but the separate removal button is no longer shown or submitted.
*/

function removeSecondImageRemovalOption() {
    document
        .querySelectorAll(
            "#removeSecondImageButton, "
            + ".gallery-remove-second-button"
        )
        .forEach(function (button) {
            button.remove();
        });

    const removeSecondImageInput =
        document.getElementById(
            "removeImageTwo"
        );

    removeSecondImageInput?.remove();
}

/*
|--------------------------------------------------------------------------
| Compact About field
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
        "Event Manager description"
    );
}