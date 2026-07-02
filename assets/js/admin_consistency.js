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
        .querySelectorAll(
            existingDescriptionSelectors.join(',')
        )
        .forEach((description) => {
            description.textContent =
                aboutText;

            description.classList.add(
                'admin-linked-about'
            );
        });

    const profileSidebar =
        document.querySelector(
            'body.profile-page .sidebar-profile'
        );

    if (profileSidebar) {
        let description =
            profileSidebar.querySelector(
                '.admin-linked-about'
            );

        if (!description) {
            description =
                document.createElement(
                    'span'
                );

            description.className =
                'admin-linked-about';

            profileSidebar.appendChild(
                description
            );
        }

        description.textContent =
            aboutText;
    }

    document
        .querySelectorAll(
            '.all-packages-admin span'
        )
        .forEach((description) => {
            description.textContent =
                aboutText;
        });

    loadAdminCardPricingStyles();
    addMainImageSelectors();
    matchAdminPackageAndVenuePrices();
});

/**
 * Run the price formatter again after all deferred scripts have finished.
 * This prevents an older cached script from joining the values again.
 */
window.addEventListener('load', () => {
    window.requestAnimationFrame(() => {
        matchAdminPackageAndVenuePrices();
    });
});

/**
 * Load the final Admin card-pricing stylesheet after page-specific CSS.
 */
function loadAdminCardPricingStyles() {
    const existingStylesheet =
        document.querySelector(
            'link[data-admin-card-pricing="true"]'
        );

    if (existingStylesheet) {
        return;
    }

    const currentScript =
        Array.from(
            document.scripts
        ).find((script) =>
            script.src.includes(
                '/assets/js/admin_consistency.js'
            )
        );

    if (!currentScript) {
        return;
    }

    const stylesheet =
        document.createElement(
            'link'
        );

    stylesheet.rel =
        'stylesheet';

    stylesheet.href =
        new URL(
            '../css/admin_card_pricing.css?v=20260703-2',
            currentScript.src
        ).href;

    stylesheet.dataset.adminCardPricing =
        'true';

    document.head.appendChild(
        stylesheet
    );
}

/**
 * Add the original main package image as the first selectable thumbnail.
 */
function addMainImageSelectors() {
    const packageConfigurations = [
        {
            cardSelector:
                '.package-card',

            imageSelector:
                '.package-card-main-image',

            imageWrapSelector:
                '.package-card-main-image-wrap',

            rowSelector:
                '.package-thumbnail-row',

            buttonClass:
                'package-thumbnail-button',
        },
        {
            cardSelector:
                '.all-package-card',

            imageSelector:
                '.all-package-main-image',

            imageWrapSelector:
                '.all-package-main-image-wrap',

            rowSelector:
                '.all-package-thumbnails',

            buttonClass:
                'all-package-thumbnail',
        },
    ];

    packageConfigurations.forEach(
        (configuration) => {
            document
                .querySelectorAll(
                    configuration.cardSelector
                )
                .forEach((card) => {
                    const mainImage =
                        card.querySelector(
                            configuration
                                .imageSelector
                        );

                    const imageWrap =
                        card.querySelector(
                            configuration
                                .imageWrapSelector
                        );

                    const thumbnailRow =
                        card.querySelector(
                            configuration
                                .rowSelector
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

                    mainImage.dataset
                        .originalMainImage =
                            mainImage.src;

                    if (
                        !thumbnailRow.querySelector(
                            '[data-package-is-main="true"]'
                        )
                    ) {
                        const mainButton =
                            document.createElement(
                                'button'
                            );

                        mainButton.type =
                            'button';

                        mainButton.className =
                            `${configuration.buttonClass} active package-main-thumbnail`;

                        mainButton.dataset
                            .packageMain =
                                mainImage.id;

                        mainButton.dataset
                            .packageImage =
                                mainImage.src;

                        mainButton.dataset
                            .packageIsMain =
                                'true';

                        mainButton.setAttribute(
                            'aria-label',
                            'Show the original main package photo'
                        );

                        const thumbnailImage =
                            document.createElement(
                                'img'
                            );

                        thumbnailImage.src =
                            mainImage.src;

                        thumbnailImage.alt =
                            'Original main package photo';

                        const thumbnailLabel =
                            document.createElement(
                                'span'
                            );

                        thumbnailLabel.className =
                            'package-main-thumbnail-label';

                        thumbnailLabel.textContent =
                            'Main';

                        mainButton.append(
                            thumbnailImage,
                            thumbnailLabel
                        );

                        thumbnailRow.prepend(
                            mainButton
                        );
                    }

                    if (
                        !imageWrap.querySelector(
                            '.package-main-photo-badge'
                        )
                    ) {
                        const badge =
                            document.createElement(
                                'span'
                            );

                        badge.className =
                            'package-main-photo-badge';

                        badge.innerHTML =
                            '<i class="fa-regular fa-image" aria-hidden="true"></i>'
                            + '<span>Main Photo</span>';

                        imageWrap.appendChild(
                            badge
                        );
                    }
                });
        }
    );
}

/**
 * Match Admin package and venue prices with Booking Manager cards.
 */
function matchAdminPackageAndVenuePrices() {
    const cardConfigurations = [
        {
            descriptionSelector:
                '.package-card-description',

            cardSelector:
                '.package-card',

            headingSelector:
                'h3',

            layout:
                'package',
        },
        {
            descriptionSelector:
                '.all-package-description',

            cardSelector:
                '.all-package-card',

            headingSelector:
                'h2',

            layout:
                'package',
        },
        {
            descriptionSelector:
                '.venue-card-description',

            cardSelector:
                '.venue-card',

            headingSelector:
                'h3',

            layout:
                'venue',
        },
        {
            descriptionSelector:
                '.all-venue-description',

            cardSelector:
                '.all-venue-card',

            headingSelector:
                'h2',

            layout:
                'venue',
        },
    ];

    cardConfigurations.forEach(
        (configuration) => {
            document
                .querySelectorAll(
                    configuration
                        .descriptionSelector
                )
                .forEach(
                    (descriptionElement) => {
                        formatAdminCardPrice(
                            descriptionElement,
                            configuration
                        );
                    }
                );
        }
    );
}

/**
 * Extract the price even when an older script removed the line break.
 */
function formatAdminCardPrice(
    descriptionElement,
    configuration
) {
    if (
        !(
            descriptionElement
            instanceof HTMLElement
        )
    ) {
        return;
    }

    const card =
        descriptionElement.closest(
            configuration.cardSelector
        );

    const heading =
        card?.querySelector(
            configuration.headingSelector
        );

    if (
        !card
        || !heading
    ) {
        return;
    }

    const existingPriceSelector =
        configuration.layout === 'package'
            ? '.admin-matched-package-price'
            : '.admin-matched-venue-price';

    const existingPrice =
        card.querySelector(
            existingPriceSelector
        );

    /*
     * Support elements produced by the older text-polish script.
     */
    const oldPrice =
        descriptionElement.querySelector(
            '.admin-card-price-text'
        );

    const oldDescription =
        descriptionElement.querySelector(
            '.admin-card-description-text'
        );

    const normalizedText =
        descriptionElement.innerText
            .replace(
                /\r\n?/g,
                '\n'
            )
            .replace(
                /\n+/g,
                ' '
            )
            .replace(
                /\s+/g,
                ' '
            )
            .trim();

    const pricePattern =
        /^Rs\.\s*[\d,]+(?:\.\d+)?/i;

    let priceText =
        existingPrice
            ?.textContent
            ?.trim()
        || oldPrice
            ?.textContent
            ?.trim()
        || '';

    let descriptionText =
        oldDescription
            ?.textContent
            ?.trim()
        || '';

    /*
     * Extract only "Rs. 90,000" even if the description immediately
     * follows it without a line break.
     */
    if (priceText === '') {
        const priceMatch =
            normalizedText.match(
                pricePattern
            );

        if (!priceMatch) {
            return;
        }

        priceText =
            priceMatch[0];

        descriptionText =
            normalizedText
                .slice(
                    priceMatch[0].length
                )
                .replace(
                    /^[\s•|\-–—:]+/,
                    ''
                )
                .trim();
    } else if (
        descriptionText === ''
    ) {
        descriptionText =
            normalizedText
                .replace(
                    pricePattern,
                    ''
                )
                .replace(
                    /^[\s•|\-–—:]+/,
                    ''
                )
                .trim();
    }

    /*
     * Remove any old nested bold spans and restore a normal description.
     */
    descriptionElement.replaceChildren();

    descriptionElement.textContent =
        descriptionText;

    descriptionElement.dataset
        .cardTextPolished =
            'true';

    descriptionElement.classList.add(
        'admin-matched-card-description'
    );

    if (
        configuration.layout
        === 'package'
    ) {
        createPackageTitleRow(
            heading,
            priceText
        );

        return;
    }

    createVenuePrice(
        heading,
        priceText
    );
}

/**
 * Place package price on the right side of the package name.
 */
function createPackageTitleRow(
    heading,
    priceText
) {
    let titleRow =
        heading.closest(
            '.admin-matched-package-title-row'
        );

    if (!titleRow) {
        titleRow =
            document.createElement(
                'div'
            );

        titleRow.className =
            'admin-matched-package-title-row';

        heading.parentNode?.insertBefore(
            titleRow,
            heading
        );

        titleRow.appendChild(
            heading
        );
    }

    let price =
        titleRow.querySelector(
            '.admin-matched-package-price'
        );

    if (!price) {
        price =
            document.createElement(
                'strong'
            );

        price.className =
            'admin-matched-package-price';

        titleRow.appendChild(
            price
        );
    }

    price.textContent =
        priceText;
}

/**
 * Place venue price immediately below the venue name.
 */
function createVenuePrice(
    heading,
    priceText
) {
    const existingPrice =
        heading.parentElement
            ?.querySelector(
                ':scope > .admin-matched-venue-price'
            );

    const price =
        existingPrice
        || document.createElement(
            'strong'
        );

    price.className =
        'admin-matched-venue-price';

    price.textContent =
        priceText;

    if (!existingPrice) {
        heading.insertAdjacentElement(
            'afterend',
            price
        );
    }
}

/**
 * Use one delegated listener for both package pages.
 */
document.addEventListener(
    'click',
    (event) => {
        if (
            !(
                event.target
                instanceof Element
            )
        ) {
            return;
        }

        const button =
            event.target.closest(
                '.package-thumbnail-button, '
                + '.all-package-thumbnail'
            );

        if (!button) {
            return;
        }

        const mainImageId =
            button.dataset.packageMain;

        const selectedImage =
            button.dataset.packageImage;

        if (
            !mainImageId
            || !selectedImage
        ) {
            return;
        }

        const mainImage =
            document.getElementById(
                mainImageId
            );

        if (!mainImage) {
            return;
        }

        mainImage.src =
            selectedImage;

        const thumbnailRow =
            button.closest(
                '.package-thumbnail-row, '
                + '.all-package-thumbnails'
            );

        thumbnailRow
            ?.querySelectorAll(
                '.package-thumbnail-button, '
                + '.all-package-thumbnail'
            )
            .forEach(
                (thumbnail) => {
                    thumbnail.classList.remove(
                        'active'
                    );
                }
            );

        button.classList.add(
            'active'
        );

        const card =
            button.closest(
                '.package-card, '
                + '.all-package-card'
            );

        const mainBadge =
            card?.querySelector(
                '.package-main-photo-badge'
            );

        mainBadge?.classList.toggle(
            'is-hidden',
            button.dataset
                .packageIsMain
                !== 'true'
        );
    }
);