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
    const createMontageBtn = document.getElementById("createMontage");
    const createMontageStatus = document.getElementById("createMontageStatus");
    const photoHashtagsInput = document.getElementById("photoHashtags");
    const hashtagsModal = document.getElementById("hashtagsModal");
    const hashtagsMontageStatus = document.getElementById("hashtagsMontageStatus");
    const validateHashtagsBtn = document.getElementById("validateHashtagsPhoto");
    const cancelHashtagsBtn = document.getElementById("cancelHashtagsPhoto");
    const cancelHashtagsAction = document.getElementById("cancelHashtagsAction");
    const myGallery = document.getElementById("myGallery");
    const photoViewerModal = document.getElementById("photoViewerModal");
    const photoViewerImage = document.getElementById("photoViewerImage");
    const photoViewerCounter = document.getElementById("photoViewerCounter");
    const closePhotoViewerBtn = document.getElementById("closePhotoViewer");
    const prevPhotoViewerBtn = document.getElementById("prevPhotoViewer");
    const nextPhotoViewerBtn = document.getElementById("nextPhotoViewer");

    let stream = null;
    let uploadedImageUrl = null;
    let cropState = null;
    let currentGalleryIndex = 0;
    let selectedFrame = null;
    let activeStickerUid = null;
    let nextLayerUid = 1;
    const layers = [];
    const stickerInstances = [];
    const createdPhotos = [];
    const acceptedUploadTypes = ["image/png", "image/jpeg"];
    const maxUploadSize = 10 * 1024 * 1024;
    const maxStickers = 10;
    const stickerState = {
        x: 50,
        y: 50,
        width: 62,
        isDragging: false,
        isResizing: false,
        isRotating: false,
        pointerId: null,
        dragStartX: 0,
        dragStartY: 0,
        startX: 50,
        startY: 50,
        startWidth: 62,
        startRotation: 0,
        startAngle: 0,
        resizeCorner: "br"
    };

    if (!startBtn || !captureBtn || !cropBtn || !uploadBtn || !uploadInput || !video || !canvas || !preview || !frameFiltersList || !stickerFiltersList || !cropModal || !cropFrame || !cropImage || !validateCropBtn || !cancelCropBtn || !cancelCropAction || !cropZoomRange || !zoomCropIn || !zoomCropOut || !createMontageBtn || !createMontageStatus || !photoHashtagsInput || !hashtagsModal || !hashtagsMontageStatus || !validateHashtagsBtn || !cancelHashtagsBtn || !cancelHashtagsAction || !myGallery || !photoViewerModal || !photoViewerImage || !photoViewerCounter || !closePhotoViewerBtn || !prevPhotoViewerBtn || !nextPhotoViewerBtn) {
        console.error("La page studio n'a pas tous les éléments attendus.");
        return;
    }

    const previewFilterOverlay = document.createElement("img");
    previewFilterOverlay.id = "selectedFilterPreview";
    previewFilterOverlay.className = "studio-filter-overlay";
    previewFilterOverlay.alt = "Filtre sélectionné";
    previewFilterOverlay.hidden = true;
    preview.appendChild(previewFilterOverlay);

    const cropFilterOverlay = document.createElement("img");
    cropFilterOverlay.id = "selectedFilterCropPreview";
    cropFilterOverlay.className = "studio-crop-filter-overlay";
    cropFilterOverlay.alt = "Filtre sélectionné";
    cropFilterOverlay.hidden = true;
    cropFrame.appendChild(cropFilterOverlay);

    const getCapturedImage = () => document.getElementById("capturedPhotoPreview");

    const hasSelectedFilter = () => Boolean(selectedFrame || stickerInstances.length > 0);

    const canCaptureFromCamera = () => Boolean(stream && video.videoWidth && video.videoHeight);

    const canCreateMontage = () => Boolean(getCapturedImage()?.src && hasSelectedFilter());

    const isLocalTrustedHost = () => ["localhost", "127.0.0.1", "::1"].includes(window.location.hostname);

    const syncCaptureButtonState = () => {
        captureBtn.disabled = !canCaptureFromCamera();
        createMontageBtn.disabled = !canCreateMontage();
        startBtn.classList.toggle("is-active", Boolean(stream));
        startBtn.setAttribute("aria-pressed", stream ? "true" : "false");
    };

    const getActiveSticker = () => stickerInstances.find((sticker) => sticker.uid === activeStickerUid) || null;

    const isStickerSelected = () => Boolean(getActiveSticker());

    const getLayerIndex = (uid) => layers.findIndex((layer) => layer.uid === uid);

    const applyStickerPosition = (sticker, box) => {
        box.style.left = `${sticker.x}%`;
        box.style.top = `${sticker.y}%`;
        box.style.width = `${sticker.width}%`;
        box.style.transform = `translate(-50%, -50%) rotate(${sticker.rotation}deg)`;
        box.style.zIndex = String(5 + Math.max(0, getLayerIndex(sticker.uid)));
        box.classList.toggle("is-active", sticker.uid === activeStickerUid);
    };

    const applyFramePosition = (overlay) => {
        overlay.style.left = "0";
        overlay.style.top = "0";
        overlay.style.width = "100%";
        overlay.style.height = "100%";
        overlay.style.transform = "none";
        overlay.style.objectFit = "fill";
    };

    const syncFilterButtons = () => {
        document.querySelectorAll(".studio-filter-option").forEach((button) => {
            const isSelectedFrame = Boolean(selectedFrame && button.dataset.filterId === selectedFrame.id);
            const isUsedSticker = stickerInstances.some((sticker) => button.dataset.filterId === sticker.id);
            const isSelected = isSelectedFrame || isUsedSticker;

            button.classList.toggle("is-selected", isSelected);
            button.setAttribute("aria-pressed", isSelected ? "true" : "false");
        });
    };

    const syncStickerInstance = (sticker) => {
        applyStickerPosition(sticker, sticker.previewBox);
        applyStickerPosition(sticker, sticker.cropBox);
    };

    const syncFilterOverlays = () => {
        const hasFrame = Boolean(selectedFrame);

        previewFilterOverlay.hidden = !hasFrame;
        cropFilterOverlay.hidden = !hasFrame;

        if (!hasFrame) {
            previewFilterOverlay.removeAttribute("src");
            cropFilterOverlay.removeAttribute("src");
        } else {
            previewFilterOverlay.hidden = false;
            cropFilterOverlay.hidden = false;
            previewFilterOverlay.src = selectedFrame.file;
            cropFilterOverlay.src = selectedFrame.file;
            previewFilterOverlay.style.zIndex = String(5 + Math.max(0, getLayerIndex(selectedFrame.uid)));
            cropFilterOverlay.style.zIndex = String(5 + Math.max(0, getLayerIndex(selectedFrame.uid)));
            applyFramePosition(previewFilterOverlay);
            applyFramePosition(cropFilterOverlay);
        }

        stickerInstances.forEach(syncStickerInstance);
        previewFilterOverlay.classList.remove("is-sticker");
        cropFilterOverlay.classList.remove("is-sticker");
        syncFilterButtons();
    };

    const removeLayer = (uid) => {
        const index = layers.findIndex((layer) => layer.uid === uid);

        if (index !== -1) {
            layers.splice(index, 1);
        }
    };

    const removeSticker = (uid) => {
        const index = stickerInstances.findIndex((sticker) => sticker.uid === uid);

        if (index === -1) {
            return;
        }

        const [sticker] = stickerInstances.splice(index, 1);
        sticker.previewBox.remove();
        sticker.cropBox.remove();
        removeLayer(uid);

        if (activeStickerUid === uid) {
            activeStickerUid = stickerInstances.at(-1)?.uid || null;
        }

        syncFilterOverlays();
        syncCaptureButtonState();
    };

    const createStickerBox = (sticker, cropMode = false) => {
        const box = document.createElement("div");
        box.className = cropMode ? "studio-sticker-box studio-crop-sticker-box" : "studio-sticker-box";
        box.dataset.stickerUid = String(sticker.uid);

        const image = document.createElement("img");
        image.src = sticker.file;
        image.alt = "Sticker sélectionné";

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "studio-sticker-remove";
        removeBtn.textContent = "×";
        removeBtn.setAttribute("aria-label", "Retirer ce sticker");
        removeBtn.addEventListener("pointerdown", (event) => event.stopPropagation());
        removeBtn.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            removeSticker(sticker.uid);
        });

        const rotateHandle = document.createElement("span");
        rotateHandle.className = "studio-sticker-rotate";
        rotateHandle.setAttribute("aria-hidden", "true");
        rotateHandle.addEventListener("pointerdown", startStickerRotate);
        rotateHandle.addEventListener("pointermove", (event) => moveStickerRotate(event, cropMode ? cropFrame : preview));
        rotateHandle.addEventListener("pointerup", stopStickerDrag);
        rotateHandle.addEventListener("pointercancel", stopStickerDrag);

        const handles = ["tl", "tr", "bl", "br"].map((corner) => {
            const handle = document.createElement("span");
            handle.className = `studio-sticker-resize studio-sticker-resize-${corner}`;
            handle.dataset.corner = corner;
            handle.setAttribute("aria-hidden", "true");
            handle.addEventListener("pointerdown", startStickerResize);
            handle.addEventListener("pointermove", (event) => moveStickerResize(event, cropMode ? cropFrame : preview));
            handle.addEventListener("pointerup", stopStickerDrag);
            handle.addEventListener("pointercancel", stopStickerDrag);
            return handle;
        });

        box.append(image, removeBtn, rotateHandle, ...handles);
        box.addEventListener("pointerdown", startStickerDrag);
        box.addEventListener("pointermove", (event) => {
            moveStickerDrag(event, cropMode ? cropFrame : preview);
            moveStickerResize(event, cropMode ? cropFrame : preview);
            moveStickerRotate(event, cropMode ? cropFrame : preview);
        });
        box.addEventListener("pointerup", stopStickerDrag);
        box.addEventListener("pointercancel", stopStickerDrag);

        return box;
    };

    const addSticker = (filter) => {
        if (stickerInstances.length >= maxStickers) {
            alert("Vous pouvez ajouter 10 stickers maximum.");
            return;
        }

        const sticker = {
            uid: nextLayerUid++,
            type: "sticker",
            id: filter.id,
            file: filter.file,
            x: 50,
            y: 50,
            width: 62,
            rotation: 0
        };

        sticker.previewBox = createStickerBox(sticker, false);
        sticker.cropBox = createStickerBox(sticker, true);
        stickerInstances.push(sticker);
        layers.push({ uid: sticker.uid, type: "sticker" });
        activeStickerUid = sticker.uid;
        preview.appendChild(sticker.previewBox);
        cropFrame.appendChild(sticker.cropBox);
        syncFilterOverlays();
        syncCaptureButtonState();
    };

    const toggleFrame = (filter) => {
        if (selectedFrame && selectedFrame.id === filter.id) {
            removeLayer(selectedFrame.uid);
            selectedFrame = null;
        } else {
            if (selectedFrame) {
                removeLayer(selectedFrame.uid);
            }

            selectedFrame = {
                uid: nextLayerUid++,
                type: "frame",
                id: filter.id,
                file: filter.file
            };
            layers.push({ uid: selectedFrame.uid, type: "frame" });
        }

        syncFilterOverlays();
        syncCaptureButtonState();
    };

    const selectFilter = (filter) => {
        if (filter.type === "sticker") {
            addSticker(filter);
            return;
        }

        toggleFrame(filter);
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
        syncCaptureButtonState();
    };

    const clearCapturedImage = () => {
        const image = getCapturedImage();
        const context = canvas.getContext("2d");

        if (image) {
            image.removeAttribute("src");
            image.remove();
        }

        context.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.display = "none";
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
            video.style.display = "block";
        }

        syncFilterOverlays();
        syncCaptureButtonState();
    };

    const hashtagPattern = /^\p{L}+(?:-\p{L}+)*$/u;

    const parseHashtags = () => photoHashtagsInput.value
        .split(/[\s,]+/)
        .map((hashtag) => hashtag.trim())
        .filter(Boolean)
        .map((hashtag) => {
            if ([...hashtag].length > 25) {
                throw new Error("HASHTAG_TOO_LONG");
            }

            if (!hashtagPattern.test(hashtag)) {
                throw new Error("INVALID_HASHTAG");
            }

            return hashtag.toLocaleLowerCase("fr-FR");
        });

    const setCreateStatus = (message) => {
        createMontageStatus.textContent = message;
        hashtagsMontageStatus.textContent = message;
    };

    const buildCreatePayload = () => {
        const image = getCapturedImage();

        if (!image?.src) {
            throw new Error("IMAGE_MISSING");
        }

        const hashtags = parseHashtags();

        if (new Set(hashtags).size > 5) {
            throw new Error("TOO_MANY_HASHTAGS");
        }

        return {
            image: image.src,
            frame_id: selectedFrame?.id || null,
            crop: {
                x_percent: 0,
                y_percent: 0,
                width_percent: 100,
                height_percent: 100
            },
            layers: layers.map((layer) => {
                if (layer.type === "frame") {
                    if (!selectedFrame) {
                        return null;
                    }

                    return {
                        type: "frame",
                        frame_id: selectedFrame.id
                    };
                }

                const sticker = stickerInstances.find((item) => item.uid === layer.uid);

                if (!sticker) {
                    return null;
                }

                return {
                    type: "sticker",
                    sticker_id: sticker.id,
                    center_x_percent: sticker.x,
                    center_y_percent: sticker.y,
                    width_percent: sticker.width,
                    rotation_degrees: sticker.rotation
                };
            }).filter(Boolean),
            hashtags
        };
    };

    const refreshPhotoViewer = () => {
        const photo = createdPhotos[currentGalleryIndex];

        if (!photo) {
            closePhotoViewer();
            return;
        }

        photoViewerImage.src = `/${photo.path}`;
        photoViewerCounter.textContent = `${currentGalleryIndex + 1} / ${createdPhotos.length}`;
        prevPhotoViewerBtn.disabled = createdPhotos.length <= 1;
        nextPhotoViewerBtn.disabled = createdPhotos.length <= 1;
    };

    function openPhotoViewer(index) {
        currentGalleryIndex = index;
        refreshPhotoViewer();
        photoViewerModal.hidden = false;
        document.body.classList.add("is-crop-modal-open");
    }

    function closePhotoViewer() {
        photoViewerModal.hidden = true;
        photoViewerImage.removeAttribute("src");
        document.body.classList.remove("is-crop-modal-open");
    }

    const showPreviousPhoto = () => {
        if (createdPhotos.length <= 1) {
            return;
        }

        currentGalleryIndex = (currentGalleryIndex - 1 + createdPhotos.length) % createdPhotos.length;
        refreshPhotoViewer();
    };

    const showNextPhoto = () => {
        if (createdPhotos.length <= 1) {
            return;
        }

        currentGalleryIndex = (currentGalleryIndex + 1) % createdPhotos.length;
        refreshPhotoViewer();
    };

    const syncGalleryEmptyState = () => {
        if (createdPhotos.length === 0) {
            myGallery.innerHTML = '<p class="studio-gallery-empty">Aucun montage pour le moment.</p>';
        }
    };

    const deletePhoto = async (photo, item) => {
        if (!confirm("Supprimer ce montage ?")) {
            return;
        }

        try {
            const response = await fetch("/?page=photo_delete", {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({ photo_id: photo.photo_id })
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "DELETE_FAILED");
            }

            const index = createdPhotos.findIndex((createdPhoto) => String(createdPhoto.photo_id) === String(photo.photo_id));

            if (index !== -1) {
                createdPhotos.splice(index, 1);
            }

            item.remove();
            syncGalleryEmptyState();

            if (!photoViewerModal.hidden) {
                if (createdPhotos.length === 0) {
                    closePhotoViewer();
                } else {
                    currentGalleryIndex = Math.min(currentGalleryIndex, createdPhotos.length - 1);
                    refreshPhotoViewer();
                }
            }
        } catch (error) {
            console.error("[STUDIO] impossible de supprimer le montage", error);
            alert("Impossible de supprimer ce montage.");
        }
    };

    const renderCreatedPhotoItem = (photo, prepend = false) => {
        const empty = myGallery.querySelector(".studio-gallery-empty");

        if (empty) {
            empty.remove();
        }

        const item = document.createElement("div");
        item.className = "studio-created-photo-item";
        item.dataset.photoId = String(photo.photo_id);

        const button = document.createElement("button");
        button.type = "button";
        button.className = "studio-created-photo";

        const image = document.createElement("img");
        image.src = `/${photo.path}${prepend ? `?t=${Date.now()}` : ""}`;
        image.alt = "Montage créé";

        const deleteBtn = document.createElement("button");
        deleteBtn.type = "button";
        deleteBtn.className = "studio-created-photo-delete";
        deleteBtn.textContent = "🗑";
        deleteBtn.setAttribute("aria-label", "Supprimer ce montage");
        deleteBtn.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            deletePhoto(photo, item);
        });

        button.appendChild(image);
        button.addEventListener("click", () => {
            const index = createdPhotos.findIndex((item) => String(item.photo_id) === String(photo.photo_id));

            if (index !== -1) {
                openPhotoViewer(index);
            }
        });
        item.append(button, deleteBtn);

        if (prepend) {
            myGallery.prepend(item);
        } else {
            myGallery.appendChild(item);
        }
    };

    const addCreatedPhotoToGallery = (photo) => {
        const normalizedPhoto = {
            photo_id: photo.photo_id,
            path: photo.path
        };

        createdPhotos.unshift(normalizedPhoto);
        renderCreatedPhotoItem(normalizedPhoto, true);
    };

    const loadUserPhotos = async () => {
        try {
            const response = await fetch("/?page=photo_mine", {
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                }
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "PHOTOS_LOAD_FAILED");
            }

            createdPhotos.splice(0, createdPhotos.length, ...(result.photos || []));
            myGallery.innerHTML = "";

            if (createdPhotos.length === 0) {
                syncGalleryEmptyState();
                return;
            }

            createdPhotos.forEach((photo) => renderCreatedPhotoItem(photo));
        } catch (error) {
            console.error("[STUDIO] impossible de charger les montages", error);
            myGallery.innerHTML = '<p class="studio-gallery-empty">Impossible de charger vos montages.</p>';
        }
    };

    const createMontage = async () => {
        try {
            const payload = buildCreatePayload();
            createMontageBtn.disabled = true;
            validateHashtagsBtn.disabled = true;
            setCreateStatus("Creation du montage...");

            const response = await fetch("/?page=photo_create", {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "CREATE_FAILED");
            }

            addCreatedPhotoToGallery(result);
            clearCapturedImage();
            closeHashtagsModal();
            setCreateStatus("Montage ajoute a vos creations.");
        } catch (error) {
            console.error("[STUDIO] impossible de creer le montage", error);
            if (error.message === "IMAGE_MISSING") {
                setCreateStatus("Capturez ou recadrez une image avant de creer le montage.");
            } else if (error.message === "TOO_MANY_HASHTAGS") {
                setCreateStatus("5 mots-cles maximum : retirez les mots-cles en trop.");
            } else if (error.message === "HASHTAG_TOO_LONG") {
                setCreateStatus("Chaque mot-cle doit faire 25 caracteres maximum.");
            } else if (error.message === "INVALID_HASHTAG") {
                setCreateStatus("Mots-cles invalides : lettres et tirets uniquement, sans tiret au debut ou a la fin.");
            } else {
                setCreateStatus("Impossible de creer le montage.");
            }
        } finally {
            validateHashtagsBtn.disabled = false;
            syncCaptureButtonState();
        }
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
        cropFrame.style.aspectRatio = "1 / 1";
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

    const openHashtagsModal = () => {
        hashtagsMontageStatus.textContent = "";
        hashtagsModal.hidden = false;
        document.body.classList.add("is-crop-modal-open");
        photoHashtagsInput.focus();
    };

    const closeHashtagsModal = () => {
        hashtagsModal.hidden = true;
        document.body.classList.remove("is-crop-modal-open");
    };

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
            if (!window.isSecureContext && !isLocalTrustedHost()) {
                alert("La caméra nécessite HTTPS, ou une adresse locale comme localhost / 127.0.0.1.");
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

            if (err.name === "NotAllowedError" || err.name === "SecurityError") {
                alert("Autorisez l'accès à la caméra dans le navigateur, puis réessayez.");
                return;
            }

            if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
                alert("Aucune caméra n'a été détectée sur cet appareil.");
                return;
            }

            alert("Impossible d'accéder à la caméra.");

        }

    });

    captureBtn.addEventListener("click", () => {
        if (!stream || !video.videoWidth || !video.videoHeight) {
            alert("Démarrez la caméra avant de capturer une photo.");
            return;
        }

        const sourceSize = Math.min(video.videoWidth, video.videoHeight);
        const sourceX = Math.max(0, (video.videoWidth - sourceSize) / 2);
        const sourceY = Math.max(0, (video.videoHeight - sourceSize) / 2);

        canvas.width = sourceSize;
        canvas.height = sourceSize;

        const context = canvas.getContext("2d");
        context.drawImage(
            video,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            canvas.width,
            canvas.height
        );

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

        if (!acceptedUploadTypes.includes(file.type)) {
            alert("Formats acceptés : PNG ou JPEG.");
            uploadInput.value = "";
            return;
        }

        if (file.size > maxUploadSize) {
            alert("L'image ne doit pas dépasser 10 Mo.");
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
        const sticker = stickerInstances.find((item) => item.uid === Number(event.currentTarget.dataset.stickerUid));

        if (!sticker) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        activeStickerUid = sticker.uid;
        stickerState.isDragging = true;
        stickerState.pointerId = event.pointerId;
        stickerState.dragStartX = event.clientX;
        stickerState.dragStartY = event.clientY;
        stickerState.startX = sticker.x;
        stickerState.startY = sticker.y;
        event.currentTarget.setPointerCapture(event.pointerId);
        syncFilterOverlays();
    };

    const startStickerResize = (event) => {
        const stickerBox = event.currentTarget.closest(".studio-sticker-box");
        const sticker = stickerInstances.find((item) => item.uid === Number(stickerBox?.dataset.stickerUid));

        if (!sticker) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        activeStickerUid = sticker.uid;
        stickerState.isResizing = true;
        stickerState.pointerId = event.pointerId;
        stickerState.dragStartX = event.clientX;
        stickerState.dragStartY = event.clientY;
        stickerState.startWidth = sticker.width;
        stickerState.resizeCorner = event.currentTarget.dataset.corner || "br";
        event.currentTarget.setPointerCapture(event.pointerId);
        syncFilterOverlays();
    };

    const getStickerPointerAngle = (event, container) => {
        const rect = container.getBoundingClientRect();
        const sticker = getActiveSticker();

        if (!sticker) {
            return 0;
        }

        const centerX = rect.left + (sticker.x / 100) * rect.width;
        const centerY = rect.top + (sticker.y / 100) * rect.height;

        return Math.atan2(event.clientY - centerY, event.clientX - centerX) * 180 / Math.PI;
    };

    const normalizeRotation = (rotation) => {
        let normalized = rotation % 360;

        if (normalized > 180) {
            normalized -= 360;
        }

        if (normalized < -180) {
            normalized += 360;
        }

        return normalized;
    };

    const startStickerRotate = (event) => {
        const stickerBox = event.currentTarget.closest(".studio-sticker-box");
        const sticker = stickerInstances.find((item) => item.uid === Number(stickerBox?.dataset.stickerUid));

        if (!sticker) {
            return;
        }

        const container = stickerBox.classList.contains("studio-crop-sticker-box") ? cropFrame : preview;

        event.preventDefault();
        event.stopPropagation();
        activeStickerUid = sticker.uid;
        stickerState.isRotating = true;
        stickerState.pointerId = event.pointerId;
        stickerState.startRotation = sticker.rotation;
        stickerState.startAngle = getStickerPointerAngle(event, container);
        event.currentTarget.setPointerCapture(event.pointerId);
        syncFilterOverlays();
    };

    const moveStickerDrag = (event, container) => {
        const sticker = getActiveSticker();

        if (!stickerState.isDragging || stickerState.pointerId !== event.pointerId || !sticker) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const rect = container.getBoundingClientRect();
        const deltaX = ((event.clientX - stickerState.dragStartX) / rect.width) * 100;
        const deltaY = ((event.clientY - stickerState.dragStartY) / rect.height) * 100;

        sticker.x = Math.min(110, Math.max(-10, stickerState.startX + deltaX));
        sticker.y = Math.min(110, Math.max(-10, stickerState.startY + deltaY));
        syncStickerInstance(sticker);
    };

    const moveStickerResize = (event, container) => {
        const sticker = getActiveSticker();

        if (!stickerState.isResizing || stickerState.pointerId !== event.pointerId || !sticker) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const rect = container.getBoundingClientRect();
        const deltaX = ((event.clientX - stickerState.dragStartX) / rect.width) * 100;
        const deltaY = ((event.clientY - stickerState.dragStartY) / rect.height) * 100;
        const corner = stickerState.resizeCorner;
        const horizontal = corner.includes("l") ? -deltaX : deltaX;
        const vertical = corner.includes("t") ? -deltaY : deltaY;
        const deltaWidth = (horizontal + vertical) / 2;

        sticker.width = Math.min(130, Math.max(18, stickerState.startWidth + deltaWidth));
        syncStickerInstance(sticker);
    };

    const moveStickerRotate = (event, container) => {
        const sticker = getActiveSticker();

        if (!stickerState.isRotating || stickerState.pointerId !== event.pointerId || !sticker) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const currentAngle = getStickerPointerAngle(event, container);
        sticker.rotation = normalizeRotation(stickerState.startRotation + currentAngle - stickerState.startAngle);
        syncStickerInstance(sticker);
    };

    const stopStickerDrag = (event) => {
        if ((!stickerState.isDragging && !stickerState.isResizing && !stickerState.isRotating) || stickerState.pointerId !== event.pointerId) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        stickerState.isDragging = false;
        stickerState.isResizing = false;
        stickerState.isRotating = false;
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

    cropBtn.addEventListener("click", validateImageCrop);
    validateCropBtn.addEventListener("click", validateImageCrop);
    cancelCropBtn.addEventListener("click", cancelImageCrop);
    cancelCropAction.addEventListener("click", cancelImageCrop);
    createMontageBtn.addEventListener("click", openHashtagsModal);
    validateHashtagsBtn.addEventListener("click", createMontage);
    cancelHashtagsBtn.addEventListener("click", closeHashtagsModal);
    cancelHashtagsAction.addEventListener("click", closeHashtagsModal);
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
    hashtagsModal.addEventListener("click", (event) => {
        if (event.target === hashtagsModal) {
            closeHashtagsModal();
        }
    });
    photoViewerModal.addEventListener("click", (event) => {
        if (event.target === photoViewerModal) {
            closePhotoViewer();
        }
    });
    closePhotoViewerBtn.addEventListener("click", closePhotoViewer);
    prevPhotoViewerBtn.addEventListener("click", showPreviousPhoto);
    nextPhotoViewerBtn.addEventListener("click", showNextPhoto);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            if (!cropModal.hidden) {
                cancelImageCrop();
            }

            if (!hashtagsModal.hidden) {
                closeHashtagsModal();
            }

            if (!photoViewerModal.hidden) {
                closePhotoViewer();
            }
        }

        if (!photoViewerModal.hidden && event.key === "ArrowLeft") {
            showPreviousPhoto();
        }

        if (!photoViewerModal.hidden && event.key === "ArrowRight") {
            showNextPhoto();
        }
    });

    loadFilters();
    loadUserPhotos();
    syncCaptureButtonState();

}
