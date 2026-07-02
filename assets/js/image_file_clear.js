"use strict";

/*
|--------------------------------------------------------------------------
| Clear newly selected image files
|--------------------------------------------------------------------------
| Adds a "Remove selected image" button to image upload fields that use
| the data-preview attribute.
|
| This clears only the newly selected file. It does not delete an image
| that is already stored in the database.
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        addImageClearButtonStyles();

        document
            .querySelectorAll(
                'input[type="file"][data-preview]'
            )
            .forEach(function (fileInput) {
                prepareImageClearButton(
                    fileInput
                );
            });
    }
);

/*
|--------------------------------------------------------------------------
| Prepare one image field
|--------------------------------------------------------------------------
*/

function prepareImageClearButton(
    fileInput
) {
    if (
        fileInput.dataset.imageClearReady
        === "true"
    ) {
        return;
    }

    const previewId =
        String(
            fileInput.dataset.preview
            || ""
        ).trim();

    if (previewId === "") {
        return;
    }

    const previewImage =
        document.getElementById(
            previewId
        );

    if (!previewImage) {
        return;
    }

    const previewBox =
        previewImage.closest(
            [
                ".package-current-image",
                ".venue-current-image",
                ".gallery-current-image"
            ].join(", ")
        );

    if (!previewBox) {
        return;
    }

    const previewLabel =
        previewBox.querySelector(
            "span"
        );

    /*
     * Save the original state so it can be
     * restored when the selected file is cleared.
     */
    const originalImageSource =
        previewImage.getAttribute(
            "data-original-src"
        )
        || previewImage.getAttribute(
            "src"
        )
        || "";

    const originalLabel =
        previewLabel
            ? previewLabel.textContent.trim()
            : "";

    const originallyEmpty =
        previewBox.classList.contains(
            "empty"
        );

    const clearButton =
        document.createElement(
            "button"
        );

    clearButton.type =
        "button";

    clearButton.className =
        "image-file-clear-button";

    clearButton.setAttribute(
        "aria-label",
        "Remove selected image"
    );

    clearButton.innerHTML = `
        <span
            class="image-file-clear-icon"
            aria-hidden="true"
        >
            &times;
        </span>

        <span>
            Remove selected image
        </span>
    `;

    previewBox.insertAdjacentElement(
        "afterend",
        clearButton
    );

    fileInput.dataset.imageClearReady =
        "true";

    /*
     * Show the button only when a new file
     * has been selected.
     */
    function updateClearButtonVisibility() {
        const hasSelectedFile =
            Boolean(
                fileInput.files
                && fileInput.files.length > 0
            );

        clearButton.classList.toggle(
            "visible",
            hasSelectedFile
        );

        clearButton.setAttribute(
            "aria-hidden",
            hasSelectedFile
                ? "false"
                : "true"
        );
    }

    /*
     * Restore the preview that existed before
     * the user selected a new image.
     */
    function restoreOriginalPreview() {
        if (originalImageSource !== "") {
            previewImage.src =
                originalImageSource;
        }

        if (previewLabel) {
            previewLabel.textContent =
                originalLabel;
        }

        previewBox.classList.toggle(
            "empty",
            originallyEmpty
        );
    }

    fileInput.addEventListener(
        "change",
        function () {
            updateClearButtonVisibility();
        }
    );

    clearButton.addEventListener(
        "click",
        function () {
            /*
             * Clearing the value allows the same file
             * to be selected again later.
             */
            fileInput.value = "";

            restoreOriginalPreview();

            clearButton.classList.remove(
                "visible"
            );

            clearButton.setAttribute(
                "aria-hidden",
                "true"
            );

            fileInput.focus();

            /*
             * A custom event is provided in case another
             * page script needs to respond in the future.
             */
            fileInput.dispatchEvent(
                new CustomEvent(
                    "selectedImageCleared",
                    {
                        bubbles: true
                    }
                )
            );
        }
    );

    /*
     * Restore the field properly if the whole
     * form is reset.
     */
    if (fileInput.form) {
        fileInput.form.addEventListener(
            "reset",
            function () {
                window.setTimeout(
                    function () {
                        restoreOriginalPreview();

                        clearButton.classList.remove(
                            "visible"
                        );

                        clearButton.setAttribute(
                            "aria-hidden",
                            "true"
                        );
                    },
                    0
                );
            }
        );
    }

    updateClearButtonVisibility();
}

/*
|--------------------------------------------------------------------------
| Button styling
|--------------------------------------------------------------------------
*/

function addImageClearButtonStyles() {
    if (
        document.getElementById(
            "image-file-clear-styles"
        )
    ) {
        return;
    }

    const styleElement =
        document.createElement(
            "style"
        );

    styleElement.id =
        "image-file-clear-styles";

    styleElement.textContent = `
        .image-file-clear-button {
            width: 100%;
            min-height: 39px;
            margin-top: 9px;
            padding: 9px 13px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid #efb8ca;
            border-radius: 9px;
            background: #ffffff;
            color: #b00050;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.3;
            box-sizing: border-box;
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                color 0.2s ease,
                box-shadow 0.2s ease,
                transform 0.2s ease;
        }

        .image-file-clear-button.visible {
            display: inline-flex;
        }

        .image-file-clear-button:hover {
            border-color: #d90b61;
            background: #fff2f7;
            color: #a4004d;
            box-shadow:
                0 5px 14px
                rgba(164, 0, 77, 0.09);
            transform: translateY(-1px);
        }

        .image-file-clear-button:active {
            transform: translateY(0);
        }

        .image-file-clear-button:focus-visible {
            outline: 3px solid
                rgba(164, 0, 77, 0.18);
            outline-offset: 2px;
        }

        .image-file-clear-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 18px;
            border-radius: 50%;
            background: #ffe4ed;
            font-size: 17px;
            font-weight: 700;
            line-height: 1;
        }

        @media (max-width: 480px) {
            .image-file-clear-button {
                min-height: 42px;
            }
        }
    `;

    document.head.appendChild(
        styleElement
    );
}