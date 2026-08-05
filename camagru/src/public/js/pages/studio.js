export async function init() {
    console.info("[STUDIO] init");

    const startBtn = document.getElementById("startCamera");

    if (!startBtn) {
        return;
    }

    const captureBtn = document.getElementById("capturePhoto");
    const cropBtn = document.getElementById("cropPhoto");
    const uploadBtn = document.getElementById("uploadBtn");
    const uploadInput = document.getElementById("uploadPhoto");
    const video = document.getElementById("webcam");
    const canvas = document.getElementById("overlay");
    const preview = document.getElementById("cameraPreview");
    const frameFiltersList = document.getElementById("frameFiltersList");
    const stickerFiltersList = document.getElementById("stickerFiltersList");
    const cropModal = document.getElementById("imageCropModal");
    const cropFrame = document.getElementById("cropFrame");
    const cropImage = document.getElementById("cropImage");
    const validateCropBtn = document.getElementById("validateCropPhoto");
    const cancelCropBtn = document.getElementById("cancelCropPhoto");
    const cancelCropAction = document.getElementById("cancelCropAction");
    const cropZoomRange = document.getElementById("cropZoomRange");
    const zoomCropIn = document.getElementById("zoomCropIn");
    const zoomCropOut = document.getElementById("zoomCropOut");

    let stream = null;
    let uploadedImageUrl = null;
    let cropState = null;
    let selectedFilter = null;
    const stickerState = {
        x: 50,
        y: 50,
        width: 62,
        isDragging: false,
        isResizing: false,
        pointerId: null,
        dragStartX: 0,
        dragStartY: 0,
        startX: 50,
        startY: 50,
        startWidth: 62
    };

    if (!captureBtn || !cropBtn || !uploadBtn || !uploadInput || !video || !canvas || !preview || !frameFiltersList || !stickerFiltersList || !cropModal || !cropFrame || !cropImage || !validateCropBtn || !cancelCropBtn || !cancelCropAction || !cropZoomRange || !zoomCropIn || !zoomCropOut) {
        console.error("La page studio n'a pas tous les éléments attendus.");
        return;
    }

    const previewFilterOverlay = document.createElement("img");
    previewFilterOverlay.id = "selectedFilterPreview";
    previewFilterOverlay.className = "studio-filter-overlay";
    previewFilterOverlay.alt = "Filtre sélectionné";
    previewFilterOverlay.hidden = true;
    preview.appendChild(previewFilterOverlay);

    const previewStickerBox = document.createElement("div");
    previewStickerBox.className = "studio-sticker-box";
    previewStickerBox.hidden = true;
    const previewStickerImage = document.createElement("img");
    previewStickerImage.alt = "Sticker sélectionné";
    const previewStickerHandle = document.createElement("span");
    previewStickerHandle.className = "studio-sticker-resize";
    previewStickerHandle.setAttribute("aria-hidden", "true");
    previewStickerBox.append(previewStickerImage, previewStickerHandle);
    preview.appendChild(previewStickerBox);

    const cropFilterOverlay = document.createElement("img");
    cropFilterOverlay.id = "selectedFilterCropPreview";
    cropFilterOverlay.className = "studio-crop-filter-overlay";
    cropFilterOverlay.alt = "Filtre sélectionné";
    cropFilterOverlay.hidden = true;
    cropFrame.appendChild(cropFilterOverlay);

    const cropStickerBox = document.createElement("div");
    cropStickerBox.className = "studio-sticker-box studio-crop-sticker-box";
    cropStickerBox.hidden = true;
    const cropStickerImage = document.createElement("img");
    cropStickerImage.alt = "Sticker sélectionné";
    const cropStickerHandle = document.createElement("span");
    cropStickerHandle.className = "studio-sticker-resize";
    cropStickerHandle.setAttribute("aria-hidden", "true");
    cropStickerBox.append(cropStickerImage, cropStickerHandle);
    cropFrame.appendChild(cropStickerBox);

    const stickerControls = document.createElement("div");
    stickerControls.className = "studio-sticker-controls";
    stickerControls.hidden = true;

    const resetStickerBtn = document.createElement("button");
    resetStickerBtn.type = "button";
    resetStickerBtn.className = "btn-savane-secondary";
    resetStickerBtn.textContent = "Recentrer";

    stickerControls.append(resetStickerBtn);
    stickerFiltersList.after(stickerControls);

    const canCaptureFromCamera = () => Boolean(stream && video.videoWidth && video.videoHeight && selectedFilter);

    const syncCaptureButtonState = () => {
        captureBtn.disabled = !canCaptureFromCamera();
    };

    const isStickerSelected = () => selectedFilter?.type === "sticker";

    const applyStickerPosition = (box) => {
        box.style.left = `${stickerState.x}%`;
        box.style.top = `${stickerState.y}%`;
        box.style.width = `${stickerState.width}%`;
        box.style.transform = "translate(-50%, -50%)";
    };

    const applyFramePosition = (overlay) => {
        overlay.style.left = "0";
        overlay.style.top = "0";
        overlay.style.width = "100%";
        overlay.style.height = "100%";
        overlay.style.transform = "none";
        overlay.style.objectFit = "fill";
    };

    const syncStickerControls = () => {
        stickerControls.hidden = !isStickerSelected();
    };

    const syncFilterOverlays = () => {
        const hasFilter = Boolean(selectedFilter);

        previewFilterOverlay.hidden = !hasFilter;
        cropFilterOverlay.hidden = !hasFilter;
        previewStickerBox.hidden = true;
        cropStickerBox.hidden = true;

        if (!hasFilter) {
            previewFilterOverlay.removeAttribute("src");
            cropFilterOverlay.removeAttribute("src");
            previewStickerImage.removeAttribute("src");
            cropStickerImage.removeAttribute("src");
            previewFilterOverlay.classList.remove("is-sticker");
            cropFilterOverlay.classList.remove("is-sticker");
            syncStickerControls();
            return;
        }

        const sticker = isStickerSelected();

        if (sticker) {
            previewFilterOverlay.hidden = true;
            cropFilterOverlay.hidden = true;
            previewStickerBox.hidden = false;
            cropStickerBox.hidden = false;
            previewStickerImage.src = selectedFilter.file;
            cropStickerImage.src = selectedFilter.file;
            applyStickerPosition(previewStickerBox);
            applyStickerPosition(cropStickerBox);
        } else {
            previewFilterOverlay.hidden = false;
            cropFilterOverlay.hidden = false;
            previewFilterOverlay.src = selectedFilter.file;
            cropFilterOverlay.src = selectedFilter.file;
            applyFramePosition(previewFilterOverlay);
            applyFramePosition(cropFilterOverlay);
        }

        syncStickerControls();
    };

    const selectFilter = (filter) => {
        selectedFilter = filter;

        if (filter.type === "sticker") {
            stickerState.x = 50;
            stickerState.y = 50;
            stickerState.width = 62;
        }

        syncFilterOverlays();
        syncCaptureButtonState();

        document.querySelectorAll(".studio-filter-option").forEach((button) => {
            const isSelected = button.dataset.filterId === filter.id;
            button.classList.toggle("is-selected", isSelected);
            button.setAttribute("aria-pressed", isSelected ? "true" : "false");
        });
    };

    const loadFilters = async () => {
        frameFiltersList.innerHTML = '<p class="studio-filters-empty">Chargement des cadres...</p>';
        stickerFiltersList.innerHTML = '<p class="studio-filters-empty">Chargement des stickers...</p>';

        try {
            const response = await fetch("/images/filters/filters.json", {
                headers: { "Accept": "application/json" }
            });

            if (!response.ok) {
                throw new Error("FILTERS_UNAVAILABLE");
            }

            const filters = await response.json();
            frameFiltersList.innerHTML = "";
            stickerFiltersList.innerHTML = "";

            if (!Array.isArray(filters) || filters.length === 0) {
                frameFiltersList.innerHTML = '<p class="studio-filters-empty">Aucun cadre disponible.</p>';
                stickerFiltersList.innerHTML = '<p class="studio-filters-empty">Aucun sticker disponible.</p>';
                return;
            }

            const counts = {
                frame: 0,
                sticker: 0
            };

            filters.forEach((filter) => {
                if (!filter.id || !filter.name || !filter.file) {
                    return;
                }

                const type = filter.type === "sticker" ? "sticker" : "frame";

                const button = document.createElement("button");
                button.type = "button";
                button.className = "studio-filter-option";
                button.dataset.filterId = filter.id;
                button.dataset.filterType = type;
                button.setAttribute("aria-pressed", "false");

                const thumbnail = document.createElement("img");
                thumbnail.src = filter.file;
                thumbnail.alt = "";

                const label = document.createElement("span");
                label.textContent = filter.name;

                button.append(thumbnail, label);
                button.addEventListener("click", () => selectFilter({ ...filter, type }));

                if (type === "sticker") {
                    stickerFiltersList.appendChild(button);
                    counts.sticker += 1;
                } else {
                    frameFiltersList.appendChild(button);
                    counts.frame += 1;
                }
            });

            if (counts.frame === 0) {
                frameFiltersList.innerHTML = '<p class="studio-filters-empty">Aucun cadre disponible.</p>';
            }

            if (counts.sticker === 0) {
                stickerFiltersList.innerHTML = '<p class="studio-filters-empty">Aucun sticker disponible.</p>';
            }
        } catch (error) {
            console.error("[STUDIO] impossible de charger les filtres", error);
            frameFiltersList.innerHTML = '<p class="studio-filters-empty">Impossible de charger les cadres.</p>';
            stickerFiltersList.innerHTML = '<p class="studio-filters-empty">Impossible de charger les stickers.</p>';
        }
    };

    const getCameraStream = (constraints) => {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            return navigator.mediaDevices.getUserMedia(constraints);
        }

        const legacyGetUserMedia = navigator.getUserMedia
            || navigator.webkitGetUserMedia
            || navigator.mozGetUserMedia
            || navigator.msGetUserMedia;

        if (!legacyGetUserMedia) {
            return Promise.reject(new Error("GET_USER_MEDIA_UNAVAILABLE"));
        }

        return new Promise((resolve, reject) => {
            legacyGetUserMedia.call(navigator, constraints, resolve, reject);
        });
    };

    const displayCapturedImage = (imageUrl) => {
        let image = document.getElementById("capturedPhotoPreview");

        if (!image) {
            image = document.createElement("img");
            image.id = "capturedPhotoPreview";
            image.alt = "Photo capturée";
            image.style.display = "block";
            preview.appendChild(image);
        }

        image.onload = null;
        image.src = imageUrl;
        image.style.display = "block";
        image.style.transform = "";
        image.style.width = "";
        image.style.height = "";
        video.style.display = "none";
        canvas.style.display = "none";
        cropBtn.hidden = true;
        preview.classList.remove("is-cropping");
        closeCropModal();
        cropState = null;
        syncFilterOverlays();
    };

    const showCameraPreview = () => {
        const image = document.getElementById("capturedPhotoPreview");
        const context = canvas.getContext("2d");

        if (image) {
            image.style.display = "none";
        }

        context.clearRect(0, 0, canvas.width, canvas.height);
        video.style.display = "block";
        canvas.style.display = "none";
        cropBtn.hidden = true;
        preview.classList.remove("is-cropping");
        closeCropModal();
        cropState = null;
        syncFilterOverlays();
        syncCaptureButtonState();
    };

    const clampCropPosition = () => {
        const minX = Math.min(0, cropState.frameWidth - cropState.renderedWidth);
        const minY = Math.min(0, cropState.frameHeight - cropState.renderedHeight);

        cropState.offsetX = Math.min(0, Math.max(minX, cropState.offsetX));
        cropState.offsetY = Math.min(0, Math.max(minY, cropState.offsetY));
    };

    const updateCropImagePosition = () => {
        cropState.image.style.width = `${cropState.renderedWidth}px`;
        cropState.image.style.height = `${cropState.renderedHeight}px`;
        cropState.image.style.transform = `translate(${cropState.offsetX}px, ${cropState.offsetY}px)`;
    };

    const updateRenderedCropSize = () => {
        cropState.currentScale = cropState.baseScale * cropState.zoom;
        cropState.renderedWidth = cropState.image.naturalWidth * cropState.currentScale;
        cropState.renderedHeight = cropState.image.naturalHeight * cropState.currentScale;
    };

    const setCropZoom = (nextZoom) => {
        if (!cropState) {
            return;
        }

        const previousRenderedWidth = cropState.renderedWidth;
        const previousRenderedHeight = cropState.renderedHeight;
        const centerRatioX = (cropState.frameWidth / 2 - cropState.offsetX) / previousRenderedWidth;
        const centerRatioY = (cropState.frameHeight / 2 - cropState.offsetY) / previousRenderedHeight;

        cropState.zoom = Math.min(cropState.maxZoom, Math.max(1, nextZoom));
        cropZoomRange.value = String(Math.round(cropState.zoom * 100));
        updateRenderedCropSize();

        cropState.offsetX = cropState.frameWidth / 2 - centerRatioX * cropState.renderedWidth;
        cropState.offsetY = cropState.frameHeight / 2 - centerRatioY * cropState.renderedHeight;
        clampCropPosition();
        updateCropImagePosition();
    };

    function openCropModal() {
        const previewWidth = Math.max(1, preview.clientWidth);
        const previewHeight = Math.max(1, preview.clientHeight);

        cropFrame.style.aspectRatio = `${previewWidth} / ${previewHeight}`;
        cropModal.hidden = false;
        document.body.classList.add("is-crop-modal-open");
    }

    function closeCropModal() {
        cropModal.hidden = true;
        document.body.classList.remove("is-crop-modal-open");

        if (cropState?.pointerId !== null && cropState?.pointerId !== undefined && cropFrame.hasPointerCapture(cropState.pointerId)) {
            cropFrame.releasePointerCapture(cropState.pointerId);
        }
    }

    const startImageCrop = (imageUrl) => {
        openCropModal();
        cropImage.onload = null;
        cropImage.removeAttribute("src");
        cropImage.style.transform = "";
        cropImage.style.width = "";
        cropImage.style.height = "";

        cropImage.onload = () => {
            const frameWidth = cropFrame.clientWidth;
            const frameHeight = cropFrame.clientHeight;
            const baseScale = Math.max(frameWidth / cropImage.naturalWidth, frameHeight / cropImage.naturalHeight);
            const initialZoom = 1;

            cropState = {
                image: cropImage,
                imageUrl,
                frameWidth,
                frameHeight,
                baseScale,
                currentScale: baseScale * initialZoom,
                zoom: initialZoom,
                maxZoom: 4,
                renderedWidth: cropImage.naturalWidth * baseScale * initialZoom,
                renderedHeight: cropImage.naturalHeight * baseScale * initialZoom,
                offsetX: 0,
                offsetY: 0,
                isDragging: false,
                pointerId: null,
                dragStartX: 0,
                dragStartY: 0,
                startOffsetX: 0,
                startOffsetY: 0
            };

            cropState.offsetX = (frameWidth - cropState.renderedWidth) / 2;
            cropState.offsetY = (frameHeight - cropState.renderedHeight) / 2;
            cropZoomRange.value = String(initialZoom * 100);
            clampCropPosition();
            updateCropImagePosition();
        };

        cropImage.src = imageUrl;
        cropImage.style.display = "block";
        video.style.display = "none";
        canvas.style.display = "none";
        cropBtn.hidden = true;
        syncCaptureButtonState();
        preview.classList.add("is-cropping");
        syncFilterOverlays();
    };

    const validateImageCrop = () => {
        if (!cropState) {
            return;
        }

        const sourceX = -cropState.offsetX / cropState.currentScale;
        const sourceY = -cropState.offsetY / cropState.currentScale;
        const sourceWidth = cropState.frameWidth / cropState.currentScale;
        const sourceHeight = cropState.frameHeight / cropState.currentScale;

        canvas.width = Math.max(1, Math.round(sourceWidth));
        canvas.height = Math.max(1, Math.round(sourceHeight));

        const context = canvas.getContext("2d");

        context.drawImage(
            cropState.image,
            sourceX,
            sourceY,
            sourceWidth,
            sourceHeight,
            0,
            0,
            canvas.width,
            canvas.height
        );

        const croppedImageUrl = canvas.toDataURL("image/png");
        displayCapturedImage(croppedImageUrl);

        if (uploadedImageUrl) {
            URL.revokeObjectURL(uploadedImageUrl);
            uploadedImageUrl = null;
            uploadInput.value = "";
        }
    };

    const cancelImageCrop = () => {
        cropImage.onload = null;
        cropImage.removeAttribute("src");
        cropImage.style.transform = "";
        cropImage.style.width = "";
        cropImage.style.height = "";
        cropZoomRange.value = "100";
        cropBtn.hidden = true;
        preview.classList.remove("is-cropping");
        closeCropModal();
        cropState = null;

        if (uploadedImageUrl) {
            URL.revokeObjectURL(uploadedImageUrl);
            uploadedImageUrl = null;
            uploadInput.value = "";
        }

        if (stream) {
            syncCaptureButtonState();
        }
    };

    startBtn.addEventListener("click", async () => {
        console.info("[STUDIO] start camera clicked");

        try {
            if (!window.isSecureContext && window.location.hostname !== "localhost") {
                alert("La caméra est bloquée sur mobile si le site n'est pas ouvert en HTTPS ou sur localhost.");
                return;
            }

            if (uploadedImageUrl) {
                URL.revokeObjectURL(uploadedImageUrl);
                uploadedImageUrl = null;
            }

            try {
                stream = await getCameraStream({
                    video: {
                        facingMode: "user"
                    },
                    audio: false
                });
            } catch (cameraErr) {
                console.warn("Impossible d'utiliser la contrainte facingMode, nouvelle tentative simple.", cameraErr);
                stream = await getCameraStream({
                    video: true,
                    audio: false
                });
            }

            video.srcObject = stream;
            video.muted = true;
            video.playsInline = true;
            await video.play();

            showCameraPreview();

            // permettra de couper la caméra lorsqu'on change de page
            window.currentStream = stream;

            syncCaptureButtonState();

        } catch (err) {

            console.error(err);
            if (err.message === "GET_USER_MEDIA_UNAVAILABLE") {
                alert("Votre navigateur ne permet pas d'accéder à la caméra.");
                return;
            }

            alert("Impossible d'accéder à la caméra");

        }

    });

    captureBtn.addEventListener("click", () => {
        if (!stream || !video.videoWidth || !video.videoHeight) {
            alert("Démarrez la caméra avant de capturer une photo.");
            return;
        }

        if (!selectedFilter) {
            alert("Choisissez un filtre avant de capturer une photo.");
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext("2d");
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageUrl = canvas.toDataURL("image/png");
        displayCapturedImage(imageUrl);
    });

    uploadBtn.addEventListener("click", () => {
        uploadInput.click();
    });

    uploadInput.addEventListener("change", () => {
        const file = uploadInput.files[0];

        if (!file) {
            return;
        }

        if (!file.type.startsWith("image/")) {
            alert("Veuillez sélectionner une image.");
            uploadInput.value = "";
            return;
        }

        if (window.currentStream) {
            window.currentStream.getTracks().forEach(track => track.stop());
            window.currentStream = null;
            stream = null;
        }

        if (uploadedImageUrl) {
            URL.revokeObjectURL(uploadedImageUrl);
        }

        uploadedImageUrl = URL.createObjectURL(file);
        startImageCrop(uploadedImageUrl);
    });

    cropFrame.addEventListener("pointerdown", (event) => {
        if (!cropState) {
            return;
        }

        cropState.isDragging = true;
        cropState.pointerId = event.pointerId;
        cropState.dragStartX = event.clientX;
        cropState.dragStartY = event.clientY;
        cropState.startOffsetX = cropState.offsetX;
        cropState.startOffsetY = cropState.offsetY;
        cropFrame.setPointerCapture(event.pointerId);
    });

    cropFrame.addEventListener("pointermove", (event) => {
        if (!cropState || !cropState.isDragging) {
            return;
        }

        cropState.offsetX = cropState.startOffsetX + event.clientX - cropState.dragStartX;
        cropState.offsetY = cropState.startOffsetY + event.clientY - cropState.dragStartY;
        clampCropPosition();
        updateCropImagePosition();
    });

    cropFrame.addEventListener("wheel", (event) => {
        if (!cropState) {
            return;
        }

        event.preventDefault();
            const direction = event.deltaY > 0 ? -1 : 1;
            setCropZoom(cropState.zoom + direction * 0.08);
    }, { passive: false });

    const startStickerDrag = (event) => {
        if (!isStickerSelected()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        stickerState.isDragging = true;
        stickerState.pointerId = event.pointerId;
        stickerState.dragStartX = event.clientX;
        stickerState.dragStartY = event.clientY;
        stickerState.startX = stickerState.x;
        stickerState.startY = stickerState.y;
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const startStickerResize = (event) => {
        if (!isStickerSelected()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        stickerState.isResizing = true;
        stickerState.pointerId = event.pointerId;
        stickerState.dragStartX = event.clientX;
        stickerState.dragStartY = event.clientY;
        stickerState.startWidth = stickerState.width;
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const moveStickerDrag = (event, container) => {
        if (!stickerState.isDragging || stickerState.pointerId !== event.pointerId || !isStickerSelected()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const rect = container.getBoundingClientRect();
        const deltaX = ((event.clientX - stickerState.dragStartX) / rect.width) * 100;
        const deltaY = ((event.clientY - stickerState.dragStartY) / rect.height) * 100;

        stickerState.x = Math.min(110, Math.max(-10, stickerState.startX + deltaX));
        stickerState.y = Math.min(110, Math.max(-10, stickerState.startY + deltaY));
        syncFilterOverlays();
    };

    const moveStickerResize = (event, container) => {
        if (!stickerState.isResizing || stickerState.pointerId !== event.pointerId || !isStickerSelected()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const rect = container.getBoundingClientRect();
        const deltaX = ((event.clientX - stickerState.dragStartX) / rect.width) * 100;
        const deltaY = ((event.clientY - stickerState.dragStartY) / rect.height) * 100;
        const deltaWidth = (deltaX + deltaY) / 2;

        stickerState.width = Math.min(130, Math.max(18, stickerState.startWidth + deltaWidth));
        syncFilterOverlays();
    };

    const stopStickerDrag = (event) => {
        if ((!stickerState.isDragging && !stickerState.isResizing) || stickerState.pointerId !== event.pointerId) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        stickerState.isDragging = false;
        stickerState.isResizing = false;
        stickerState.pointerId = null;
    };

    cropFrame.addEventListener("pointerup", () => {
        if (cropState) {
            cropState.isDragging = false;
            cropState.pointerId = null;
        }
    });

    cropFrame.addEventListener("pointercancel", () => {
        if (cropState) {
            cropState.isDragging = false;
            cropState.pointerId = null;
        }
    });

    previewStickerBox.addEventListener("pointerdown", startStickerDrag);
    previewStickerBox.addEventListener("pointermove", (event) => {
        moveStickerDrag(event, preview);
        moveStickerResize(event, preview);
    });
    previewStickerBox.addEventListener("pointerup", stopStickerDrag);
    previewStickerBox.addEventListener("pointercancel", stopStickerDrag);
    previewStickerHandle.addEventListener("pointerdown", startStickerResize);
    previewStickerHandle.addEventListener("pointermove", (event) => moveStickerResize(event, preview));
    previewStickerHandle.addEventListener("pointerup", stopStickerDrag);
    previewStickerHandle.addEventListener("pointercancel", stopStickerDrag);

    cropStickerBox.addEventListener("pointerdown", startStickerDrag);
    cropStickerBox.addEventListener("pointermove", (event) => {
        moveStickerDrag(event, cropFrame);
        moveStickerResize(event, cropFrame);
    });
    cropStickerBox.addEventListener("pointerup", stopStickerDrag);
    cropStickerBox.addEventListener("pointercancel", stopStickerDrag);
    cropStickerHandle.addEventListener("pointerdown", startStickerResize);
    cropStickerHandle.addEventListener("pointermove", (event) => moveStickerResize(event, cropFrame));
    cropStickerHandle.addEventListener("pointerup", stopStickerDrag);
    cropStickerHandle.addEventListener("pointercancel", stopStickerDrag);

    resetStickerBtn.addEventListener("click", () => {
        stickerState.x = 50;
        stickerState.y = 50;
        stickerState.width = 62;
        syncFilterOverlays();
    });

    cropBtn.addEventListener("click", validateImageCrop);
    validateCropBtn.addEventListener("click", validateImageCrop);
    cancelCropBtn.addEventListener("click", cancelImageCrop);
    cancelCropAction.addEventListener("click", cancelImageCrop);
    cropZoomRange.addEventListener("input", () => {
        setCropZoom(Number(cropZoomRange.value) / 100);
    });
    zoomCropIn.addEventListener("click", () => {
        setCropZoom((cropState?.zoom || 1) + 0.1);
    });
    zoomCropOut.addEventListener("click", () => {
        setCropZoom((cropState?.zoom || 1) - 0.1);
    });
    cropModal.addEventListener("click", (event) => {
        if (event.target === cropModal) {
            cancelImageCrop();
        }
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !cropModal.hidden) {
            cancelImageCrop();
        }
    });

    loadFilters();
    syncCaptureButtonState();

}
