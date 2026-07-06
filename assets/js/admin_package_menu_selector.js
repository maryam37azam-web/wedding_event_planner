"use strict";

(function () {
    const textarea =
        document.getElementById(
            "catering_menu"
        );

    if (
        !(
            textarea
            instanceof HTMLTextAreaElement
        )
    ) {
        return;
    }

    const baseMeta =
        document.querySelector(
            'meta[name="app-base-url"]'
        );

    const baseUrl =
        baseMeta
            ? baseMeta.content.replace(
                /\/$/,
                ""
            )
            : window.location.origin;

    if (
        !document.querySelector(
            'link[data-admin-package-menu-style="true"]'
        )
    ) {
        const stylesheet =
            document.createElement(
                "link"
            );

        stylesheet.rel =
            "stylesheet";

        stylesheet.href =
            baseUrl
            + "/assets/css/admin_package_menu_selector.css?v=20260705-1";

        stylesheet.dataset
            .adminPackageMenuStyle =
                "true";

        document.head.appendChild(
            stylesheet
        );
    }

    textarea.style.display =
        "none";

    const wrapper =
        document.createElement(
            "div"
        );

    wrapper.className =
        "admin-package-menu-selector";

    const trigger =
        document.createElement(
            "button"
        );

    trigger.type =
        "button";

    trigger.className =
        "admin-package-menu-selector-button";

    trigger.innerHTML =
        '<i class="fa-solid fa-folder-open"></i> Open Catering Menu Sheet';

    const preview =
        document.createElement(
            "div"
        );

    preview.className =
        "admin-package-menu-selector-preview";

    wrapper.append(
        trigger,
        preview
    );

    textarea.insertAdjacentElement(
        "afterend",
        wrapper
    );

    const modal =
        document.createElement(
            "div"
        );

    modal.className =
        "admin-package-menu-modal";

    modal.setAttribute(
        "aria-hidden",
        "true"
    );

    modal.innerHTML = `
        <div class="admin-package-menu-modal-backdrop" data-close-admin-package-menu></div>

        <section class="admin-package-menu-modal-card" role="dialog" aria-modal="true" aria-labelledby="adminPackageMenuTitle">

            <header class="admin-package-menu-modal-header">

                <div>
                    <h2 id="adminPackageMenuTitle">Select Package Catering Menu</h2>

                    <p>
                        Only active catering items from Admin Manage Services are shown.
                    </p>
                </div>

                <button type="button" data-close-admin-package-menu aria-label="Close menu selector">
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </header>

            <div class="admin-package-menu-modal-body" id="adminPackageMenuBody">

                <div class="admin-package-menu-loading">
                    Loading active catering items...
                </div>

            </div>

            <footer class="admin-package-menu-modal-footer">

                <strong id="adminPackageMenuTotal">
                    Total: Rs. 0 / Head
                </strong>

                <button type="button" id="adminPackageMenuSave">
                    Save Package Menu
                </button>

            </footer>

        </section>
    `;

    document.body.appendChild(
        modal
    );

    const modalBody =
        modal.querySelector(
            "#adminPackageMenuBody"
        );

    const modalTotal =
        modal.querySelector(
            "#adminPackageMenuTotal"
        );

    const saveButton =
        modal.querySelector(
            "#adminPackageMenuSave"
        );

    let catalogue = [];
    let catalogueLoaded = false;

    function existingNames() {
        return textarea.value
            .split(
                /\r\n|\r|\n|,/
            )
            .map(
                function (value) {
                    return value.trim();
                }
            )
            .filter(Boolean);
    }

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

    function escapeHtml(value) {
        const element =
            document.createElement(
                "div"
            );

        element.textContent =
            String(value);

        return element.innerHTML;
    }

    function updatePreview() {
        const names =
            existingNames();

        if (
            names.length
            === 0
        ) {
            preview.textContent =
                "No catering items selected for this package.";

            return;
        }

        preview.innerHTML =
            "<strong>Selected Menu:</strong><br>"
            + names.map(
                function (name) {
                    return "<span>"
                        + escapeHtml(name)
                        + "</span>";
                }
            ).join("");
    }

    function selectedCheckboxes() {
        return Array.from(
            modal.querySelectorAll(
                ".admin-package-menu-checkbox:checked"
            )
        );
    }

    function updateTotal() {
        const total =
            selectedCheckboxes()
                .reduce(
                    function (
                        sum,
                        checkbox
                    ) {
                        return sum
                            + Number(
                                checkbox
                                    .dataset
                                    .price
                                || 0
                            );
                    },
                    0
                );

        if (modalTotal) {
            modalTotal.textContent =
                "Total: "
                + money(total)
                + " / Head";
        }
    }

    function renderCatalogue() {
        if (!modalBody) {
            return;
        }

        if (
            catalogue.length
            === 0
        ) {
            modalBody.innerHTML =
                '<div class="admin-package-menu-empty">'
                + 'No active catering items are available. '
                + 'Add and activate items in Manage Services first.'
                + '</div>';

            return;
        }

        const selectedNames =
            new Set(
                existingNames().map(
                    function (name) {
                        return name
                            .toLowerCase();
                    }
                )
            );

        modalBody.replaceChildren();

        let previousCategory = "";

        catalogue.forEach(
            function (item) {
                const category =
                    String(
                        item.category
                        || "Other Items"
                    ).trim();

                if (
                    category
                    !== previousCategory
                ) {
                    const heading =
                        document.createElement(
                            "h3"
                        );

                    heading.className =
                        "admin-package-menu-category";

                    heading.textContent =
                        category;

                    modalBody.appendChild(
                        heading
                    );

                    previousCategory =
                        category;
                }

                const label =
                    document.createElement(
                        "label"
                    );

                label.className =
                    "admin-package-menu-item";

                const left =
                    document.createElement(
                        "span"
                    );

                const checkbox =
                    document.createElement(
                        "input"
                    );

                checkbox.type =
                    "checkbox";

                checkbox.className =
                    "admin-package-menu-checkbox";

                checkbox.value =
                    String(item.id);

                checkbox.dataset.name =
                    String(
                        item.name
                        || ""
                    );

                checkbox.dataset.price =
                    String(
                        item.price
                        || 0
                    );

                checkbox.checked =
                    selectedNames.has(
                        String(
                            item.name
                            || ""
                        ).toLowerCase()
                    );

                const copy =
                    document.createElement(
                        "span"
                    );

                const name =
                    document.createElement(
                        "strong"
                    );

                name.textContent =
                    String(
                        item.name
                        || "Menu Item"
                    );

                copy.appendChild(
                    name
                );

                if (
                    String(
                        item.description
                        || ""
                    ).trim() !== ""
                ) {
                    const description =
                        document.createElement(
                            "small"
                        );

                    description.textContent =
                        String(
                            item.description
                        );

                    copy.appendChild(
                        description
                    );
                }

                left.append(
                    checkbox,
                    copy
                );

                const price =
                    document.createElement(
                        "strong"
                    );

                price.textContent =
                    money(
                        Number(
                            item.price
                            || 0
                        )
                    )
                    + " / head";

                label.append(
                    left,
                    price
                );

                modalBody.appendChild(
                    label
                );

                checkbox.addEventListener(
                    "change",
                    updateTotal
                );
            }
        );

        updateTotal();
    }

    async function loadCatalogue() {
        if (catalogueLoaded) {
            renderCatalogue();
            return;
        }

        if (modalBody) {
            modalBody.innerHTML =
                '<div class="admin-package-menu-loading">'
                + 'Loading active catering items...'
                + '</div>';
        }

        try {
            const response =
                await fetch(
                    baseUrl
                    + "/admin/service_catalog.php?type=catering",
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
                await response.json();

            if (
                !response.ok
                || !payload.success
            ) {
                throw new Error(
                    payload.message
                    || "Menu catalogue could not be loaded."
                );
            }

            catalogue =
                Array.isArray(
                    payload.items
                )
                    ? payload.items
                    : [];

            catalogueLoaded =
                true;

            renderCatalogue();
        } catch (error) {
            if (modalBody) {
                modalBody.innerHTML =
                    '<div class="admin-package-menu-error">'
                    + escapeHtml(
                        error
                        instanceof Error
                            ? error.message
                            : "Menu catalogue could not be loaded."
                    )
                    + "</div>";
            }
        }
    }

    function openModal() {
        modal.classList.add(
            "open"
        );

        modal.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body
            .classList
            .add(
                "admin-package-menu-open"
            );

        loadCatalogue();
    }

    function closeModal() {
        modal.classList.remove(
            "open"
        );

        modal.setAttribute(
            "aria-hidden",
            "true"
        );

        document.body
            .classList
            .remove(
                "admin-package-menu-open"
            );
    }

    trigger.addEventListener(
        "click",
        openModal
    );

    modal
        .querySelectorAll(
            "[data-close-admin-package-menu]"
        )
        .forEach(
            function (element) {
                element.addEventListener(
                    "click",
                    closeModal
                );
            }
        );

    saveButton?.addEventListener(
        "click",
        function () {
            const selectedNames =
                selectedCheckboxes()
                    .map(
                        function (
                            checkbox
                        ) {
                            return checkbox
                                .dataset
                                .name
                                || "";
                        }
                    )
                    .filter(Boolean);

            textarea.value =
                selectedNames.join(
                    "\n"
                );

            textarea.dispatchEvent(
                new Event(
                    "input",
                    {
                        bubbles:
                            true
                    }
                )
            );

            updatePreview();
            closeModal();
        }
    );

    document.addEventListener(
        "keydown",
        function (event) {
            if (
                event.key
                === "Escape"
            ) {
                closeModal();
            }
        }
    );

    updatePreview();
})();