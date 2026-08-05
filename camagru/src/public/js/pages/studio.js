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

    if (!captureBtn || !cropBtn || !uploadBtn || !uploadInput || !video || !canvas || !preview || !cropModal || !cropFrame || !cropImage || !validateCropBtn || !cancelCropBtn || !cancelCropAction || !cropZoomRange || !zoomCropIn || !zoomCropOut) {
        console.error("La page studio n'a pas tous les éléments attendus.");
        return;
    }

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
        captureBtn.disabled = true;
        preview.classList.add("is-cropping");
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
            captureBtn.disabled = false;
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

            captureBtn.disabled = false;

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

}
