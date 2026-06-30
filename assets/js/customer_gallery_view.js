"use strict";

/*
|--------------------------------------------------------------------------
| Customer read-only gallery preview
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const modal =
            document.getElementById(
                "customerGalleryModal"
            );

        const closeButton =
            document.getElementById(
                "customerGalleryModalClose"
            );

        const mainImage =
            document.getElementById(
                "customerGalleryModalMainImage"
            );

        const thumbnailContainer =
            document.getElementById(
                "customerGalleryModalThumbnails"
            );

        const modalTitle =
            document.getElementById(
                "customerGalleryModalTitle"
            );

        const modalEvent =
            document.getElementById(
                "customerGalleryModalEvent"
            );

        const modalDescription =
            document.getElementById(
                "customerGalleryModalDescription"
            );

        const modalDate =
            document.getElementById(
                "customerGalleryModalDate"
            );

        if (
            !modal
            || !mainImage
            || !thumbnailContainer
        ) {
            return;
        }

        function parseImages(button) {
            try {
                const images = JSON.parse(
                    button.dataset.galleryImages
                    || "[]"
                );

                if (Array.isArray(images)) {
                    return images.filter(
                        function (imageUrl) {
                            return (
                                typeof imageUrl
                                === "string"
                                && imageUrl.trim()
                                !== ""
                            );
                        }
                    );
                }
            } catch (error) {
                return [];
            }

            return [];
        }

        function renderThumbnails(images) {
            thumbnailContainer.innerHTML = "";

            images.forEach(
                function (imageUrl, index) {
                    const thumbnail =
                        document.createElement(
                            "button"
                        );

                    const image =
                        document.createElement(
                            "img"
                        );

                    thumbnail.type = "button";

                    thumbnail.className =
                        "customer-gallery-modal-thumbnail";

                    if (index === 0) {
                        thumbnail.classList.add(
                            "active"
                        );
                    }

                    image.src = imageUrl;

                    image.alt =
                        "Gallery image "
                        + (index + 1);

                    thumbnail.appendChild(
                        image
                    );

                    thumbnail.addEventListener(
                        "click",
                        function () {
                            mainImage.src =
                                imageUrl;

                            thumbnailContainer
                                .querySelectorAll(
                                    ".customer-gallery-modal-thumbnail"
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
                        }
                    );

                    thumbnailContainer.appendChild(
                        thumbnail
                    );
                }
            );
        }

        function openModal(button) {
            const images =
                parseImages(button);

            if (images.length === 0) {
                return;
            }

            mainImage.src =
                images[0];

            mainImage.alt =
                button.dataset.galleryTitle
                || "Gallery preview";

            modalTitle.textContent =
                button.dataset.galleryTitle
                || "Wedding Gallery Image";

            modalEvent.textContent =
                button.dataset.galleryEvent
                || "Wedding Event";

            modalDescription.textContent =
                button.dataset.galleryDescription
                || "Wedding-event gallery image.";

            modalDate.textContent =
                button.dataset.galleryDate
                || "Not available";

            renderThumbnails(images);

            modal.classList.add(
                "open"
            );

            modal.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "customer-gallery-modal-open"
            );
        }

        function closeModal() {
            modal.classList.remove(
                "open"
            );

            modal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "customer-gallery-modal-open"
            );
        }

        document.addEventListener(
            "click",
            function (event) {
                const galleryButton =
                    event.target.closest(
                        "[data-gallery-open]"
                    );

                if (!galleryButton) {
                    return;
                }

                openModal(
                    galleryButton
                );
            }
        );

        closeButton?.addEventListener(
            "click",
            closeModal
        );

        modal.addEventListener(
            "click",
            function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key === "Escape"
                    && modal.classList.contains(
                        "open"
                    )
                ) {
                    closeModal();
                }
            }
        );
    }
);