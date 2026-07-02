"use strict";

const bookingSidebar =
    document.getElementById(
        "bookingSidebar"
    );

const bookingSidebarOverlay =
    document.getElementById(
        "bookingSidebarOverlay"
    );

const bookingMenuButton =
    document.getElementById(
        "bookingMenuButton"
    );

function closeBookingSidebar() {
    bookingSidebar?.classList.remove(
        "open"
    );

    bookingSidebarOverlay
        ?.classList.remove(
            "open"
        );
}

bookingMenuButton?.addEventListener(
    "click",
    function () {
        bookingSidebar
            ?.classList.toggle(
                "open"
            );

        bookingSidebarOverlay
            ?.classList.toggle(
                "open"
            );
    }
);

bookingSidebarOverlay?.addEventListener(
    "click",
    closeBookingSidebar
);

document.addEventListener(
    "click",
    function (event) {
        const thumbnail =
            event.target.closest(
                ".manager-package-thumbnail"
            );

        if (!thumbnail) {
            return;
        }

        const mainImage =
            document.getElementById(
                thumbnail.dataset
                    .cardTarget
                || ""
            );

        if (
            !mainImage
            || !thumbnail.dataset
                .cardImage
        ) {
            return;
        }

        mainImage.src =
            thumbnail.dataset
                .cardImage;

        const card =
            thumbnail.closest(
                ".manager-package-card"
            );

        card
            ?.querySelectorAll(
                ".manager-package-thumbnail"
            )
            .forEach(
                function (item) {
                    item.classList.remove(
                        "active"
                    );
                }
            );

        thumbnail.classList.add(
            "active"
        );

        const badge =
            card?.querySelector(
                ".manager-package-main-badge"
            );

        badge?.classList.toggle(
            "hidden",
            thumbnail.dataset
                .cardIsMain
                !== "true"
        );
    }
);

const packageModal =
    document.getElementById(
        "managerPackageModal"
    );

const packageModalClose =
    document.getElementById(
        "managerPackageModalClose"
    );

const packageMainImage =
    document.getElementById(
        "managerPackageModalMainImage"
    );

const packageMainBadge =
    document.getElementById(
        "managerPackageModalMainBadge"
    );

const packageThumbnails =
    document.getElementById(
        "managerPackageModalThumbnails"
    );

const packageName =
    document.getElementById(
        "managerPackageModalName"
    );

const packagePrice =
    document.getElementById(
        "managerPackageModalPrice"
    );

const packageDescription =
    document.getElementById(
        "managerPackageModalDescription"
    );

const packageDecoration =
    document.getElementById(
        "managerPackageModalDecoration"
    );

const packageGuests =
    document.getElementById(
        "managerPackageModalGuests"
    );

const packageMusic =
    document.getElementById(
        "managerPackageModalMusic"
    );

const packageCatering =
    document.getElementById(
        "managerPackageModalCatering"
    );

const packageFeatures =
    document.getElementById(
        "managerPackageModalFeatures"
    );

const packageBookButton =
    document.getElementById(
        "managerPackageModalBook"
    );

function renderModalImages(
    images
) {
    if (
        !packageThumbnails
        || !packageMainImage
        || !packageMainBadge
    ) {
        return;
    }

    packageThumbnails.innerHTML = "";

    images.forEach(
        function (
            imageUrl,
            index
        ) {
            const button =
                document.createElement(
                    "button"
                );

            const image =
                document.createElement(
                    "img"
                );

            button.type =
                "button";

            button.className =
                "manager-package-modal-thumbnail";

            if (index === 0) {
                button.classList.add(
                    "active"
                );
            }

            image.src =
                imageUrl;

            image.alt =
                index === 0
                    ? "Original main package photo"
                    : "Package gallery photo "
                        + index;

            button.appendChild(
                image
            );

            if (index === 0) {
                const label =
                    document.createElement(
                        "span"
                    );

                label.textContent =
                    "Main";

                button.appendChild(
                    label
                );
            }

            button.addEventListener(
                "click",
                function () {
                    packageMainImage.src =
                        imageUrl;

                    packageThumbnails
                        .querySelectorAll(
                            ".manager-package-modal-thumbnail"
                        )
                        .forEach(
                            function (
                                item
                            ) {
                                item.classList.remove(
                                    "active"
                                );
                            }
                        );

                    button.classList.add(
                        "active"
                    );

                    packageMainBadge
                        .classList.toggle(
                            "hidden",
                            index !== 0
                        );
                }
            );

            packageThumbnails
                .appendChild(
                    button
                );
        }
    );
}

function closePackageModal() {
    if (!packageModal) {
        return;
    }

    packageModal.classList.remove(
        "open"
    );

    packageModal.setAttribute(
        "aria-hidden",
        "true"
    );

    document.body.classList.remove(
        "manager-package-modal-open"
    );
}

document
    .querySelectorAll(
        "[data-package-details]"
    )
    .forEach(
        function (button) {
            button.addEventListener(
                "click",
                function () {
                    if (
                        !packageModal
                        || !packageMainImage
                        || !packageMainBadge
                        || !packageName
                        || !packagePrice
                        || !packageDescription
                        || !packageDecoration
                        || !packageGuests
                        || !packageMusic
                        || !packageCatering
                        || !packageFeatures
                        || !packageBookButton
                    ) {
                        return;
                    }

                    let images = [];

                    try {
                        images =
                            JSON.parse(
                                button.dataset
                                    .images
                                || "[]"
                            );
                    } catch (error) {
                        images = [];
                    }

                    packageName.textContent =
                        button.dataset.name
                        || "Package";

                    packagePrice.textContent =
                        button.dataset.price
                        || "";

                    packageDescription.textContent =
                        button.dataset
                            .description
                        || "";

                    packageDecoration.textContent =
                        button.dataset
                            .decoration
                        || "Not specified";

                    packageGuests.textContent =
                        (
                            button.dataset
                                .guests
                            || "0"
                        )
                        + " guests";

                    packageMusic.textContent =
                        button.dataset.music
                        || "Not specified";

                    packageCatering.textContent =
                        button.dataset
                            .catering
                        || "Not specified";

                    const bookingBase =
                        packageModal.dataset
                            .bookingBase
                        || "";

                    packageBookButton.href =
                        bookingBase
                        + (
                            button.dataset.id
                            || ""
                        );

                    packageFeatures.innerHTML =
                        "";

                    const features =
                        button.dataset.features
                            ? button.dataset
                                .features
                                .split("||")
                            : [];

                    if (
                        features.length
                        === 0
                    ) {
                        const item =
                            document.createElement(
                                "li"
                            );

                        item.textContent =
                            "No additional features listed.";

                        packageFeatures
                            .appendChild(
                                item
                            );
                    } else {
                        features.forEach(
                            function (
                                feature
                            ) {
                                const item =
                                    document.createElement(
                                        "li"
                                    );

                                const icon =
                                    document.createElement(
                                        "i"
                                    );

                                const text =
                                    document.createElement(
                                        "span"
                                    );

                                icon.className =
                                    "fa-solid fa-check";

                                text.textContent =
                                    feature;

                                item.append(
                                    icon,
                                    text
                                );

                                packageFeatures
                                    .appendChild(
                                        item
                                    );
                            }
                        );
                    }

                    if (
                        images.length > 0
                    ) {
                        packageMainImage.src =
                            images[0];

                        packageMainBadge
                            .classList.remove(
                                "hidden"
                            );

                        renderModalImages(
                            images
                        );
                    }

                    packageModal
                        .classList.add(
                            "open"
                        );

                    packageModal.setAttribute(
                        "aria-hidden",
                        "false"
                    );

                    document.body
                        .classList.add(
                            "manager-package-modal-open"
                        );
                }
            );
        }
    );

packageModalClose?.addEventListener(
    "click",
    closePackageModal
);

packageModal?.addEventListener(
    "click",
    function (event) {
        if (
            event.target
            === packageModal
        ) {
            closePackageModal();
        }
    }
);

document.addEventListener(
    "keydown",
    function (event) {
        if (
            event.key === "Escape"
        ) {
            closePackageModal();
        }
    }
);