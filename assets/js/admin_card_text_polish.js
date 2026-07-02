"use strict";

(function () {
    const cardTextSelector = [
        ".package-card-description",
        ".venue-card-description",
        ".all-package-description",
        ".all-venue-description"
    ].join(",");

    function cleanCardLines(element) {
        return element.innerText
            .replace(/\r\n?/g, "\n")
            .split("\n")
            .map(function (line) {
                return line
                    .replace(/\s+/g, " ")
                    .trim();
            })
            .filter(Boolean);
    }

    function polishCardText(element) {
        if (
            element.dataset.cardTextPolished
            === "true"
        ) {
            return;
        }

        const lines = cleanCardLines(
            element
        );

        if (lines.length === 0) {
            return;
        }

        const priceText = lines.shift();

        const descriptionText = lines.join(
            " "
        );

        const price = document.createElement(
            "span"
        );

        price.className =
            "admin-card-price-text";

        price.textContent = priceText;

        const description =
            document.createElement(
                "span"
            );

        description.className =
            "admin-card-description-text";

        description.textContent =
            descriptionText;

        element.replaceChildren(price);

        if (descriptionText !== "") {
            element.appendChild(
                description
            );
        }

        element.dataset.cardTextPolished =
            "true";
    }

    function polishAllCards() {
        document
            .querySelectorAll(
                cardTextSelector
            )
            .forEach(
                polishCardText
            );
    }

    if (
        document.readyState === "loading"
    ) {
        document.addEventListener(
            "DOMContentLoaded",
            polishAllCards
        );
    } else {
        polishAllCards();
    }
})();