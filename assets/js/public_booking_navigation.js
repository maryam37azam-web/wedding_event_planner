"use strict";

(function () {
    function applicationBaseUrl() {
        const meta =
            document.querySelector(
                'meta[name="app-base-url"]'
            );

        return meta
            ? meta.content.replace(
                /\/$/,
                ""
            )
            : window.location
                .origin;
    }

    function numericId(value) {
        const match =
            String(
                value
                || ""
            ).match(
                /(\d+)$/
            );

        return match
            ? Number(match[1])
            : 0;
    }

    function packageIdFromCard(
        card,
        button
    ) {
        const directId =
            numericId(
                button
                    ?.dataset
                    .id
            );

        if (directId > 0) {
            return directId;
        }

        const image =
            card?.querySelector(
                "[id^='publicPackageMainImage'], "
                + "[id^='customerPackageMain']"
            );

        return numericId(
            image?.id
        );
    }

    function venueIdFromCard(
        card,
        button
    ) {
        const directId =
            numericId(
                button
                    ?.dataset
                    .id
            );

        if (directId > 0) {
            return directId;
        }

        const image =
            card?.querySelector(
                "[id^='publicVenueMainImage'], "
                + "[id^='customerVenueMain']"
            );

        return numericId(
            image?.id
        );
    }

    function detailUrl(
        type,
        entityId
    ) {
        const page =
            type === "package"
                ? "package_details.php"
                : "venue_details.php";

        return (
            applicationBaseUrl()
            + "/"
            + page
            + "?id="
            + encodeURIComponent(
                entityId
            )
        );
    }

    function navigateToDetails(
        event,
        type,
        entityId
    ) {
        if (entityId < 1) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.location.href =
            detailUrl(
                type,
                entityId
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Redirect existing View Details and Book buttons
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        "click",
        function (event) {
            if (
                !(
                    event.target
                    instanceof Element
                )
            ) {
                return;
            }

            const publicDetail =
                event.target.closest(
                    "[data-public-detail]"
                );

            if (publicDetail) {
                const type =
                    publicDetail
                        .dataset
                        .detailType;

                const card =
                    publicDetail.closest(
                        type === "package"
                            ? ".public-package-card"
                            : ".public-venue-card"
                    );

                const entityId =
                    type === "package"
                        ? packageIdFromCard(
                            card,
                            publicDetail
                        )
                        : venueIdFromCard(
                            card,
                            publicDetail
                        );

                navigateToDetails(
                    event,
                    type,
                    entityId
                );

                return;
            }

            const customerPackageDetail =
                event.target.closest(
                    "[data-package-details]"
                );

            if (
                customerPackageDetail
            ) {
                navigateToDetails(
                    event,
                    "package",
                    packageIdFromCard(
                        customerPackageDetail
                            .closest(
                                ".customer-package-card, "
                                + ".customer-all-package-card"
                            ),
                        customerPackageDetail
                    )
                );

                return;
            }

            const customerVenueDetail =
                event.target.closest(
                    "[data-venue-details]"
                );

            if (
                customerVenueDetail
            ) {
                navigateToDetails(
                    event,
                    "venue",
                    venueIdFromCard(
                        customerVenueDetail
                            .closest(
                                ".customer-venue-card, "
                                + ".customer-all-venue-card"
                            ),
                        customerVenueDetail
                    )
                );

                return;
            }

            /*
             * Public homepage Book buttons must also
             * pass through the availability page.
             */
            const publicPackageBook =
                event.target.closest(
                    ".public-package-card "
                    + ".public-card-book-button"
                );

            if (
                publicPackageBook
            ) {
                navigateToDetails(
                    event,
                    "package",
                    packageIdFromCard(
                        publicPackageBook
                            .closest(
                                ".public-package-card"
                            ),
                        publicPackageBook
                    )
                );

                return;
            }

            const publicVenueBook =
                event.target.closest(
                    ".public-venue-card "
                    + ".public-card-book-button"
                );

            if (publicVenueBook) {
                navigateToDetails(
                    event,
                    "venue",
                    venueIdFromCard(
                        publicVenueBook
                            .closest(
                                ".public-venue-card"
                            ),
                        publicVenueBook
                    )
                );

                return;
            }

            const customerPackageBook =
                event.target.closest(
                    ".customer-package-card "
                    + ".customer-package-book-button"
                );

            if (
                customerPackageBook
            ) {
                navigateToDetails(
                    event,
                    "package",
                    packageIdFromCard(
                        customerPackageBook
                            .closest(
                                ".customer-package-card"
                            ),
                        customerPackageBook
                    )
                );

                return;
            }

            const customerVenueBook =
                event.target.closest(
                    ".customer-venue-card "
                    + ".customer-venue-book-button"
                );

            if (customerVenueBook) {
                navigateToDetails(
                    event,
                    "venue",
                    venueIdFromCard(
                        customerVenueBook
                            .closest(
                                ".customer-venue-card"
                            ),
                        customerVenueBook
                    )
                );
            }
        },
        true
    );

    /*
    |--------------------------------------------------------------------------
    | Carry the selected schedule into the existing booking form
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        "DOMContentLoaded",
        function () {
            const parameters =
                new URLSearchParams(
                    window.location
                        .search
                );

            const eventDate =
                parameters.get(
                    "event_date"
                );

            const startTime =
                parameters.get(
                    "start_time"
                );

            const endTime =
                parameters.get(
                    "end_time"
                );

            const eventDateField =
                document.getElementById(
                    "event_date"
                );

            const eventTimeField =
                document.getElementById(
                    "event_time"
                );

            if (
                eventDate
                && eventDateField
                instanceof HTMLInputElement
            ) {
                eventDateField.value =
                    eventDate;
            }

            if (
                startTime
                && eventTimeField
                instanceof HTMLInputElement
            ) {
                eventTimeField.value =
                    startTime;
            }

            if (
                endTime
                && eventTimeField
                && !document
                    .getElementById(
                        "selectedScheduleNotice"
                    )
            ) {
                const notice =
                    document
                        .createElement(
                            "div"
                        );

                notice.id =
                    "selectedScheduleNotice";

                notice.style.margin =
                    "10px 0 16px";

                notice.style.padding =
                    "11px 13px";

                notice.style.border =
                    "1px solid #f0c2d2";

                notice.style.borderRadius =
                    "9px";

                notice.style.background =
                    "#fff5f8";

                notice.style.color =
                    "#8d2450";

                notice.style.fontSize =
                    "12px";

                notice.style.fontWeight =
                    "700";

                notice.textContent =
                    "Selected schedule: "
                    + (
                        eventDate
                        || ""
                    )
                    + " · "
                    + (
                        startTime
                        || ""
                    )
                    + " to "
                    + endTime;

                const container =
                    eventTimeField.closest(
                        ".form-group"
                    )
                    || eventTimeField
                        .parentElement;

                container
                    ?.insertAdjacentElement(
                        "afterend",
                        notice
                    );
            }
        }
    );
})();