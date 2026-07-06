"use strict";

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const featuredImage =
            document.getElementById(
                "bookingDetailFeaturedImage"
            );

        const thumbnailButtons =
            document.querySelectorAll(
                "[data-booking-detail-image]"
            );

        thumbnailButtons.forEach(
            function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        if (!featuredImage) {
                            return;
                        }

                        const selectedImage =
                            button.dataset
                                .bookingDetailImage;

                        if (!selectedImage) {
                            return;
                        }

                        featuredImage.style.opacity =
                            "0.35";

                        window.setTimeout(
                            function () {
                                featuredImage.src =
                                    selectedImage;

                                featuredImage
                                    .style
                                    .opacity =
                                        "1";
                            },
                            100
                        );

                        thumbnailButtons.forEach(
                            function (
                                thumbnail
                            ) {
                                thumbnail
                                    .classList
                                    .remove(
                                        "active"
                                    );
                            }
                        );

                        button
                            .classList
                            .add(
                                "active"
                            );
                    }
                );
            }
        );

        const searchForm =
            document.getElementById(
                "bookingAvailabilitySearchForm"
            );

        const feedback =
            document.getElementById(
                "bookingAvailabilityFeedback"
            );

        const results =
            document.getElementById(
                "bookingAvailabilityResults"
            );

        const datesGrid =
            document.getElementById(
                "bookingAvailabilityDatesGrid"
            );

        const monthLabel =
            document.getElementById(
                "bookingAvailabilityMonthLabel"
            );

        const timeLabel =
            document.getElementById(
                "bookingAvailabilityTimeLabel"
            );

        const confirmForm =
            document.getElementById(
                "bookingSlotConfirmForm"
            );

        const selectedDateInput =
            document.getElementById(
                "bookingSelectedDate"
            );

        const selectedStartInput =
            document.getElementById(
                "bookingSelectedStartTime"
            );

        const selectedEndInput =
            document.getElementById(
                "bookingSelectedEndTime"
            );

        const selectedSlotText =
            document.getElementById(
                "bookingSelectedSlotText"
            );

        function showFeedback(
            message,
            type
        ) {
            if (!feedback) {
                return;
            }

            feedback.textContent =
                message;

            feedback.className =
                "booking-availability-feedback "
                + type;

            feedback.hidden =
                false;
        }

        function hideFeedback() {
            if (!feedback) {
                return;
            }

            feedback.hidden =
                true;

            feedback.textContent =
                "";

            feedback.className =
                "booking-availability-feedback";
        }

        function formatTime(time) {
            const parts =
                String(time).split(
                    ":"
                );

            const hour =
                Number(parts[0]);

            const minute =
                parts[1]
                || "00";

            const suffix =
                hour >= 12
                    ? "PM"
                    : "AM";

            const normalizedHour =
                hour % 12
                || 12;

            return (
                normalizedHour
                + ":"
                + minute
                + " "
                + suffix
            );
        }

        function formatDate(
            dateValue
        ) {
            const date =
                new Date(
                    dateValue
                    + "T00:00:00"
                );

            return new Intl
                .DateTimeFormat(
                    "en-US",
                    {
                        day:
                            "numeric",

                        month:
                            "long",

                        year:
                            "numeric"
                    }
                )
                .format(date);
        }

        function clearSelection() {
            datesGrid
                ?.querySelectorAll(
                    ".booking-date-cell"
                )
                .forEach(
                    function (cell) {
                        cell
                            .classList
                            .remove(
                                "selected"
                            );
                    }
                );

            if (confirmForm) {
                confirmForm.hidden =
                    true;
            }

            if (
                selectedDateInput
            ) {
                selectedDateInput.value =
                    "";
            }
        }

        function selectDate(
            button,
            day,
            startTime,
            endTime
        ) {
            clearSelection();

            button
                .classList
                .add(
                    "selected"
                );

            if (
                selectedDateInput
            ) {
                selectedDateInput.value =
                    day.date;
            }

            if (
                selectedStartInput
            ) {
                selectedStartInput.value =
                    startTime;
            }

            if (
                selectedEndInput
            ) {
                selectedEndInput.value =
                    endTime;
            }

            if (
                selectedSlotText
            ) {
                selectedSlotText.textContent =
                    formatDate(
                        day.date
                    )
                    + " · "
                    + formatTime(
                        startTime
                    )
                    + " to "
                    + formatTime(
                        endTime
                    );
            }

            if (confirmForm) {
                confirmForm.hidden =
                    false;

                confirmForm.scrollIntoView(
                    {
                        behavior:
                            "smooth",

                        block:
                            "nearest"
                    }
                );
            }
        }

        function renderAvailability(
            payload
        ) {
            if (
                !results
                || !datesGrid
                || !monthLabel
                || !timeLabel
            ) {
                return;
            }

            const availability =
                payload.availability;

            datesGrid
                .replaceChildren();

            clearSelection();

            monthLabel.textContent =
                availability
                    .month_label;

            timeLabel.textContent =
                formatTime(
                    availability
                        .start_time
                )
                + " - "
                + formatTime(
                    availability
                        .end_time
                );

            availability
                .days
                .forEach(
                    function (day) {
                        const button =
                            document
                                .createElement(
                                    "button"
                                );

                        const weekday =
                            document
                                .createElement(
                                    "span"
                                );

                        const dayNumber =
                            document
                                .createElement(
                                    "strong"
                                );

                        const status =
                            document
                                .createElement(
                                    "small"
                                );

                        button.type =
                            "button";

                        button.className =
                            day.available
                                ? "booking-date-cell available"
                                : "booking-date-cell unavailable";

                        weekday.textContent =
                            day.weekday;

                        dayNumber.textContent =
                            day.day;

                        status.textContent =
                            day.status;

                        button.append(
                            weekday,
                            dayNumber,
                            status
                        );

                        if (
                            day.available
                        ) {
                            button
                                .addEventListener(
                                    "click",
                                    function () {
                                        selectDate(
                                            button,
                                            day,
                                            availability
                                                .start_time,
                                            availability
                                                .end_time
                                        );
                                    }
                                );
                        } else {
                            button.disabled =
                                true;
                        }

                        datesGrid
                            .appendChild(
                                button
                            );
                    }
                );

            results.hidden =
                false;
        }

        searchForm
            ?.addEventListener(
                "submit",
                async function (
                    event
                ) {
                    event
                        .preventDefault();

                    clearSelection();
                    hideFeedback();

                    const month =
                        document
                            .getElementById(
                                "bookingMonth"
                            )
                            ?.value
                        || "";

                    const startTime =
                        document
                            .getElementById(
                                "bookingStartTime"
                            )
                            ?.value
                        || "";

                    const endTime =
                        document
                            .getElementById(
                                "bookingEndTime"
                            )
                            ?.value
                        || "";

                    const submitButton =
                        searchForm
                            .querySelector(
                                "button[type='submit']"
                            );

                    if (
                        !month
                        || !startTime
                        || !endTime
                    ) {
                        showFeedback(
                            "Choose a month, start time and end time before searching.",
                            "error"
                        );

                        return;
                    }

                    if (
                        endTime
                        <= startTime
                    ) {
                        showFeedback(
                            "End time must be later than start time on the same day.",
                            "error"
                        );

                        return;
                    }

                    const requestUrl =
                        new URL(
                            searchForm
                                .dataset
                                .availabilityUrl,

                            window.location
                                .href
                        );

                    requestUrl
                        .searchParams
                        .set(
                            "type",
                            searchForm
                                .dataset
                                .entityType
                            || ""
                        );

                    requestUrl
                        .searchParams
                        .set(
                            "id",
                            searchForm
                                .dataset
                                .entityId
                            || ""
                        );

                    requestUrl
                        .searchParams
                        .set(
                            "month",
                            month
                        );

                    requestUrl
                        .searchParams
                        .set(
                            "start_time",
                            startTime
                        );

                    requestUrl
                        .searchParams
                        .set(
                            "end_time",
                            endTime
                        );

                    if (
                        submitButton
                    ) {
                        submitButton.disabled =
                            true;
                    }

                    showFeedback(
                        "Checking live availability...",
                        "loading"
                    );

                    try {
                        const response =
                            await fetch(
                                requestUrl
                                    .toString(),
                                {
                                    headers: {
                                        Accept:
                                            "application/json"
                                    },

                                    cache:
                                        "no-store"
                                }
                            );

                        const payload =
                            await response
                                .json();

                        if (
                            !response.ok
                            || !payload
                                .success
                        ) {
                            throw new Error(
                                payload
                                    .message
                                || "Availability could not be loaded."
                            );
                        }

                        hideFeedback();

                        renderAvailability(
                            payload
                        );
                    } catch (error) {
                        results.hidden =
                            true;

                        showFeedback(
                            error
                            instanceof Error
                                ? error.message
                                : "Availability could not be loaded.",
                            "error"
                        );
                    } finally {
                        if (
                            submitButton
                        ) {
                            submitButton.disabled =
                                false;
                        }
                    }
                }
            );

        const loginModal =
            document.getElementById(
                "bookingLoginModal"
            );

        function openLoginModal() {
            if (!loginModal) {
                return;
            }

            loginModal
                .classList
                .add(
                    "open"
                );

            loginModal
                .setAttribute(
                    "aria-hidden",
                    "false"
                );

            document.body
                .classList
                .add(
                    "booking-modal-open"
                );
        }

        function closeLoginModal() {
            if (!loginModal) {
                return;
            }

            loginModal
                .classList
                .remove(
                    "open"
                );

            loginModal
                .setAttribute(
                    "aria-hidden",
                    "true"
                );

            document.body
                .classList
                .remove(
                    "booking-modal-open"
                );
        }

        document
            .querySelectorAll(
                "[data-close-booking-login]"
            )
            .forEach(
                function (button) {
                    button
                        .addEventListener(
                            "click",
                            closeLoginModal
                        );
                }
            );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key
                    === "Escape"
                ) {
                    closeLoginModal();
                }
            }
        );

        if (
            document.body
                .dataset
                .loginPrompt
            === "true"
        ) {
            openLoginModal();
        }
    }
);