'use strict';

/**
 * Keep the administrator description synchronized with the About field
 * saved on the Manage Profile page.
 */
document.addEventListener('DOMContentLoaded', () => {
    const aboutMeta = document.querySelector(
        'meta[name="admin-sidebar-about"]'
    );

    const aboutText =
        aboutMeta?.getAttribute('content')?.trim()
        || 'System Administrator';

    const existingDescriptionSelectors = [
        '.admin-profile > p',
        '.admin-bookings-profile > p',
        '.admin-feedback-profile > p',
        '.admin-gallery-profile > p',
        '.notifications-profile > p',
        '.gallery-sidebar-profile > p',
    ];

    document
        .querySelectorAll(existingDescriptionSelectors.join(','))
        .forEach((description) => {
            description.textContent = aboutText;
            description.classList.add('admin-linked-about');
        });

    const profileSidebar = document.querySelector(
        'body.profile-page .sidebar-profile'
    );

    if (profileSidebar) {
        let description = profileSidebar.querySelector(
            '.admin-linked-about'
        );

        if (!description) {
            description = document.createElement('span');
            description.className = 'admin-linked-about';
            profileSidebar.appendChild(description);
        }

        description.textContent = aboutText;
    }

    document
        .querySelectorAll('.all-packages-admin span')
        .forEach((description) => {
            description.textContent = aboutText;
        });

    addMainImageSelectors();
});

/**
 * Add the original main package image as the first selectable thumbnail.
 * This lets the administrator return to the genuine main image after
 * previewing any of the three gallery images.
 */
function addMainImageSelectors() {
    const packageConfigurations = [
        {
            cardSelector: '.package-card',
            imageSelector: '.package-card-main-image',
            imageWrapSelector: '.package-card-main-image-wrap',
            rowSelector: '.package-thumbnail-row',
            buttonClass: 'package-thumbnail-button',
        },
        {
            cardSelector: '.all-package-card',
            imageSelector: '.all-package-main-image',
            imageWrapSelector: '.all-package-main-image-wrap',
            rowSelector: '.all-package-thumbnails',
            buttonClass: 'all-package-thumbnail',
        },
    ];

    packageConfigurations.forEach((configuration) => {
        document
            .querySelectorAll(configuration.cardSelector)
            .forEach((card) => {
                const mainImage = card.querySelector(
                    configuration.imageSelector
                );

                const imageWrap = card.querySelector(
                    configuration.imageWrapSelector
                );

                const thumbnailRow = card.querySelector(
                    configuration.rowSelector
                );

                if (
                    !mainImage
                    || !imageWrap
                    || !thumbnailRow
                    || !mainImage.id
                    || !mainImage.src
                ) {
                    return;
                }

                mainImage.dataset.originalMainImage = mainImage.src;

                if (
                    !thumbnailRow.querySelector(
                        '[data-package-is-main="true"]'
                    )
                ) {
                    const mainButton = document.createElement('button');

                    mainButton.type = 'button';

                    mainButton.className =
                        `${configuration.buttonClass} active package-main-thumbnail`;

                    mainButton.dataset.packageMain = mainImage.id;
                    mainButton.dataset.packageImage = mainImage.src;
                    mainButton.dataset.packageIsMain = 'true';

                    mainButton.setAttribute(
                        'aria-label',
                        'Show the original main package photo'
                    );

                    const thumbnailImage =
                        document.createElement('img');

                    thumbnailImage.src = mainImage.src;
                    thumbnailImage.alt =
                        'Original main package photo';

                    const thumbnailLabel =
                        document.createElement('span');

                    thumbnailLabel.className =
                        'package-main-thumbnail-label';

                    thumbnailLabel.textContent = 'Main';

                    mainButton.append(
                        thumbnailImage,
                        thumbnailLabel
                    );

                    thumbnailRow.prepend(mainButton);
                }

                if (
                    !imageWrap.querySelector(
                        '.package-main-photo-badge'
                    )
                ) {
                    const badge = document.createElement('span');

                    badge.className =
                        'package-main-photo-badge';

                    badge.innerHTML =
                        '<i class="fa-regular fa-image" aria-hidden="true"></i>'
                        + '<span>Main Photo</span>';

                    imageWrap.appendChild(badge);
                }
            });
    });
}

/**
 * Use one delegated listener for both package pages.
 */
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const button = event.target.closest(
        '.package-thumbnail-button, .all-package-thumbnail'
    );

    if (!button) {
        return;
    }

    const mainImageId = button.dataset.packageMain;
    const selectedImage = button.dataset.packageImage;

    if (!mainImageId || !selectedImage) {
        return;
    }

    const mainImage = document.getElementById(mainImageId);

    if (!mainImage) {
        return;
    }

    mainImage.src = selectedImage;

    const thumbnailRow = button.closest(
        '.package-thumbnail-row, .all-package-thumbnails'
    );

    thumbnailRow
        ?.querySelectorAll(
            '.package-thumbnail-button, .all-package-thumbnail'
        )
        .forEach((thumbnail) => {
            thumbnail.classList.remove('active');
        });

    button.classList.add('active');

    const card = button.closest(
        '.package-card, .all-package-card'
    );

    const mainBadge = card?.querySelector(
        '.package-main-photo-badge'
    );

    mainBadge?.classList.toggle(
        'is-hidden',
        button.dataset.packageIsMain !== 'true'
    );
});