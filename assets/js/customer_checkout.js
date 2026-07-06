"use strict";

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const body =
            document.body;

        const bookingType =
            body.dataset.bookingType
            || "package";

        const basePrice =
            Number(
                body.dataset.basePrice
                || 0
            );

        const guestInput =
            document.getElementById(
                "guest_count"
            );

        const musicSelect =
            document.getElementById(
                "music_service_id"
            );

        const menuTrigger =
            document.getElementById(
                "checkoutMenuTrigger"
            );

        const menuHelp =
            document.getElementById(
                "checkoutMenuHelp"
            );

        const menuModal =
            document.getElementById(
                "checkoutMenuModal"
            );

        const menuPreview =
            document.getElementById(
                "checkoutMenuPreview"
            );

        const menuSaveButton =
            document.getElementById(
                "checkoutMenuSaveButton"
            );

        const menuPerHeadTotal =
            document.getElementById(
                "checkoutMenuPerHeadTotal"
            );

        const cateringCheckboxes =
            document.querySelectorAll(
                ".checkout-catering-checkbox"
            );

        const cateringTotalOutput =
            document.getElementById(
                "checkoutCateringTotal"
            );

        const musicTotalOutput =
            document.getElementById(
                "checkoutMusicTotal"
            );

        const grandTotalOutput =
            document.getElementById(
                "checkoutGrandTotal"
            );

        const advanceAmountOutput =
            document.getElementById(
                "checkoutAdvanceAmount"
            );

        const advanceNoticeOutput =
            document.getElementById(
                "checkoutAdvanceNoticeAmount"
            );

        const submitAdvanceOutput =
            document.getElementById(
                "checkoutSubmitAdvance"
            );

        function money(amount) {
            return "Rs. "
                + Number(
                    amount
                    || 0
                ).toLocaleString(
                    "en-PK",
                    {
                        maximumFractionDigits:
                            0
                    }
                );
        }

        function selectedCateringItems() {
            return Array
                .from(
                    cateringCheckboxes
                )
                .filter(
                    function (
                        checkbox
                    ) {
                        return checkbox
                            .checked;
                    }
                );
        }

        function cateringRatePerHead() {
            return selectedCateringItems()
                .reduce(
                    function (
                        total,
                        checkbox
                    ) {
                        return total
                            + Number(
                                checkbox
                                    .dataset
                                    .price
                                || 0
                            );
                    },
                    0
                );
        }

        function musicPrice() {
            if (!musicSelect) {
                return 0;
            }

            const selectedOption =
                musicSelect.options[
                    musicSelect
                        .selectedIndex
                ];

            return Number(
                selectedOption
                    ?.dataset
                    .price
                || 0
            );
        }

        function guestCount() {
            return Math.max(
                0,
                Number(
                    guestInput?.value
                    || 0
                )
            );
        }

        function updateMenuAvailability() {
            if (!menuTrigger) {
                return;
            }

            const hasGuestCount =
                guestCount() > 0;

            menuTrigger.disabled =
                !hasGuestCount;

            if (menuHelp) {
                menuHelp.textContent =
                    hasGuestCount
                        ? "Choose one or more per-head catering items."
                        : "Enter guest count first.";
            }
        }

        function updateMenuRunningTotal() {
            if (!menuPerHeadTotal) {
                return;
            }

            menuPerHeadTotal.textContent =
                "Total: "
                + money(
                    cateringRatePerHead()
                )
                + " / Head";
        }

        function updateMenuPreview() {
            if (!menuPreview) {
                return;
            }

            const selectedItems =
                selectedCateringItems();

            const rate =
                cateringRatePerHead();

            if (
                selectedItems.length
                === 0
            ) {
                menuPreview.textContent =
                    "No food items selected yet.";

                return;
            }

            const names =
                selectedItems.map(
                    function (
                        checkbox
                    ) {
                        return checkbox
                            .dataset
                            .name
                            || "Menu Item";
                    }
                );

            menuPreview.innerHTML =
                "<strong>Selected:</strong> "
                + names.join(", ")
                + " | <span>"
                + money(rate)
                + " / Head</span>";
        }

        function updateInvoice() {
            let cateringTotal = 0;
            let selectedMusicPrice = 0;

            if (
                bookingType
                === "venue"
            ) {
                cateringTotal =
                    guestCount()
                    * cateringRatePerHead();

                selectedMusicPrice =
                    musicPrice();
            }

            const grandTotal =
                basePrice
                + cateringTotal
                + selectedMusicPrice;

            const advance =
                grandTotal * 0.25;

            if (
                cateringTotalOutput
            ) {
                cateringTotalOutput
                    .textContent =
                        money(
                            cateringTotal
                        );
            }

            if (
                musicTotalOutput
            ) {
                musicTotalOutput
                    .textContent =
                        money(
                            selectedMusicPrice
                        );
            }

            if (
                grandTotalOutput
            ) {
                grandTotalOutput
                    .textContent =
                        money(
                            grandTotal
                        );
            }

            if (
                advanceAmountOutput
            ) {
                advanceAmountOutput
                    .textContent =
                        money(
                            advance
                        );
            }

            if (
                advanceNoticeOutput
            ) {
                advanceNoticeOutput
                    .textContent =
                        money(
                            advance
                        );
            }

            if (
                submitAdvanceOutput
            ) {
                submitAdvanceOutput
                    .textContent =
                        money(
                            advance
                        );
            }
        }

        function openMenuModal() {
            if (
                !menuModal
                || guestCount() < 1
            ) {
                updateMenuAvailability();

                guestInput?.focus();

                return;
            }

            menuModal.classList.add(
                "open"
            );

            menuModal.setAttribute(
                "aria-hidden",
                "false"
            );

            body.classList.add(
                "checkout-modal-open"
            );

            updateMenuRunningTotal();
        }

        function closeMenuModal() {
            if (!menuModal) {
                return;
            }

            menuModal.classList.remove(
                "open"
            );

            menuModal.setAttribute(
                "aria-hidden",
                "true"
            );

            body.classList.remove(
                "checkout-modal-open"
            );
        }

        guestInput?.addEventListener(
            "input",
            function () {
                updateMenuAvailability();
                updateInvoice();
            }
        );

        musicSelect?.addEventListener(
            "change",
            updateInvoice
        );

        cateringCheckboxes.forEach(
            function (checkbox) {
                checkbox.addEventListener(
                    "change",
                    function () {
                        updateMenuRunningTotal();
                    }
                );
            }
        );

        menuTrigger?.addEventListener(
            "click",
            openMenuModal
        );

        menuSaveButton
            ?.addEventListener(
                "click",
                function () {
                    updateMenuPreview();
                    updateInvoice();
                    closeMenuModal();
                }
            );

        document
            .querySelectorAll(
                "[data-close-checkout-menu]"
            )
            .forEach(
                function (element) {
                    element.addEventListener(
                        "click",
                        closeMenuModal
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
                    closeMenuModal();
                }
            }
        );

        updateMenuAvailability();
        updateMenuRunningTotal();
        updateMenuPreview();
        updateInvoice();
    }
);