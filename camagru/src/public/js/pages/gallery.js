export function init() {
    const container = document.getElementById("galleryContainer");
    const searchForm = document.getElementById("gallerySearchForm");
    const searchInput = document.getElementById("gallerySearchInput");
    const searchResetBtn = document.getElementById("galleryResetSearch");
    const suggestions = document.getElementById("galleryHashtagSuggestions");
    const searchStatus = document.getElementById("gallerySearchStatus");
    const viewerModal = document.getElementById("galleryViewerModal");
    const viewerImage = document.getElementById("galleryViewerImage");
    const viewerCounter = document.getElementById("galleryViewerCounter");
    const closeViewerBtn = document.getElementById("closeGalleryViewer");
    const prevViewerBtn = document.getElementById("prevGalleryViewer");
    const nextViewerBtn = document.getElementById("nextGalleryViewer");
    const isLoggedIn = Boolean(window.userConfig?.isLoggedIn);
    const hashtagPattern = /^\p{L}+(?:-\p{L}+)*$/u;
    let galleryPhotos = [];
    let currentViewerIndex = 0;
    let activeHashtag = "";

    if (!container || !searchForm || !searchInput || !searchResetBtn || !suggestions || !searchStatus || !viewerModal || !viewerImage || !viewerCounter || !closeViewerBtn || !prevViewerBtn || !nextViewerBtn) {
        return;
    }

    const setEmpty = (message) => {
        container.innerHTML = "";
        const empty = document.createElement("p");
        empty.className = "gallery-empty";
        empty.textContent = message;
        container.appendChild(empty);

        return empty;
    };

    const apiJson = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                ...(options.headers || {})
            }
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "REQUEST_FAILED");
        }

        return result;
    };

    const formatDate = (date) => {
        if (!date) {
            return "";
        }

        return new Intl.DateTimeFormat("fr-FR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        }).format(new Date(date.replace(" ", "T")));
    };

    const normalizeHashtag = (value) => value.trim().toLocaleLowerCase("fr-FR");

    const validateHashtag = (value) => {
        const hashtag = normalizeHashtag(value);

        if (hashtag === "") {
            return "";
        }

        if ([...hashtag].length > 25 || !hashtagPattern.test(hashtag)) {
            throw new Error("INVALID_HASHTAG");
        }

        return hashtag;
    };

    const setSearchStatus = (message) => {
        searchStatus.textContent = message;
    };

    const renderComments = (panel, comments) => {
        const list = panel.querySelector(".gallery-comments-list");
        list.innerHTML = "";

        if (comments.length === 0) {
            const empty = document.createElement("p");
            empty.className = "gallery-comments-empty";
            empty.textContent = "Aucun commentaire pour le moment.";
            list.appendChild(empty);
            return;
        }

        comments.forEach((comment) => {
            const item = document.createElement("article");
            item.className = "gallery-comment";

            const author = document.createElement("strong");
            author.textContent = comment.username || "Utilisateur";

            const text = document.createElement("p");
            text.textContent = comment.comment;

            item.append(author, text);
            list.appendChild(item);
        });
    };

    const loadComments = async (photo, panel) => {
        panel.hidden = false;
        panel.querySelector(".gallery-comments-list").innerHTML = '<p class="gallery-comments-empty">Chargement...</p>';

        try {
            const result = await apiJson(`/?page=photo_comments&photo_id=${encodeURIComponent(photo.photo_id)}`);
            renderComments(panel, result.comments || []);
        } catch (error) {
            console.error("[GALLERY] impossible de charger les commentaires", error);
            panel.querySelector(".gallery-comments-list").innerHTML = '<p class="gallery-comments-empty">Impossible de charger les commentaires.</p>';
        }
    };

    const syncLikeButton = (button, photo) => {
        button.classList.toggle("is-liked", Boolean(photo.liked_by_user));
        button.setAttribute("aria-pressed", photo.liked_by_user ? "true" : "false");
        button.querySelector(".gallery-action-count").textContent = String(photo.likes_count || 0);
    };

    const refreshViewer = () => {
        const photo = galleryPhotos[currentViewerIndex];

        if (!photo) {
            closeViewer();
            return;
        }

        viewerImage.src = `/${photo.path}`;
        viewerImage.alt = `Montage de ${photo.username || "la communaute"}`;
        viewerCounter.textContent = `${currentViewerIndex + 1} / ${galleryPhotos.length}`;
        prevViewerBtn.disabled = galleryPhotos.length <= 1;
        nextViewerBtn.disabled = galleryPhotos.length <= 1;
    };

    function openViewer(index) {
        currentViewerIndex = index;
        refreshViewer();
        viewerModal.hidden = false;
        document.body.classList.add("is-crop-modal-open");
    }

    function closeViewer() {
        viewerModal.hidden = true;
        document.body.classList.remove("is-crop-modal-open");
    }

    const showPreviousPhoto = () => {
        if (galleryPhotos.length <= 1) {
            return;
        }

        currentViewerIndex = (currentViewerIndex - 1 + galleryPhotos.length) % galleryPhotos.length;
        refreshViewer();
    };

    const showNextPhoto = () => {
        if (galleryPhotos.length <= 1) {
            return;
        }

        currentViewerIndex = (currentViewerIndex + 1) % galleryPhotos.length;
        refreshViewer();
    };

    const createPhotoCard = (photo) => {
        const card = document.createElement("article");
        card.className = "gallery-card savane-card";

        const imageWrap = document.createElement("div");
        imageWrap.className = "gallery-photo-wrap";

        const imageButton = document.createElement("button");
        imageButton.type = "button";
        imageButton.className = "gallery-photo-button";
        imageButton.setAttribute("aria-label", "Agrandir le montage");

        const image = document.createElement("img");
        image.src = `/${photo.path}`;
        image.alt = `Montage de ${photo.username || "la communaute"}`;
        image.loading = "lazy";

        imageButton.appendChild(image);
        imageButton.addEventListener("click", () => {
            const index = galleryPhotos.findIndex((item) => Number(item.photo_id) === Number(photo.photo_id));
            openViewer(index >= 0 ? index : 0);
        });
        imageWrap.appendChild(imageButton);

        const meta = document.createElement("div");
        meta.className = "gallery-meta";

        const author = document.createElement("strong");
        author.textContent = photo.username || "Utilisateur";

        const createdAt = document.createElement("span");
        createdAt.textContent = formatDate(photo.created_at);

        meta.append(author, createdAt);

        const actions = document.createElement("div");
        actions.className = "gallery-actions";

        const likeBtn = document.createElement("button");
        likeBtn.type = "button";
        likeBtn.className = "gallery-action gallery-like";
        likeBtn.disabled = !isLoggedIn;
        likeBtn.title = isLoggedIn ? "Aimer ce montage" : "Connectez-vous pour liker";
        likeBtn.innerHTML = '<i class="bi bi-heart-fill" aria-hidden="true"></i><span class="gallery-action-count"></span>';
        syncLikeButton(likeBtn, photo);

        likeBtn.addEventListener("click", async () => {
            if (!isLoggedIn) {
                return;
            }

            likeBtn.disabled = true;

            try {
                const result = await apiJson("/?page=photo_like_toggle", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ photo_id: photo.photo_id })
                });
                photo.liked_by_user = result.liked_by_user;
                photo.likes_count = result.likes_count;
                syncLikeButton(likeBtn, photo);
            } catch (error) {
                console.error("[GALLERY] impossible de liker", error);
                alert("Impossible de modifier ce like.");
            } finally {
                likeBtn.disabled = false;
            }
        });

        const commentsBtn = document.createElement("button");
        commentsBtn.type = "button";
        commentsBtn.className = "gallery-action";
        commentsBtn.innerHTML = `<i class="bi bi-chat-dots-fill" aria-hidden="true"></i><span class="gallery-action-count">${photo.comments_count || 0}</span>`;

        actions.append(likeBtn, commentsBtn);

        const commentsPanel = document.createElement("section");
        commentsPanel.className = "gallery-comments-panel";
        commentsPanel.hidden = true;

        const commentsList = document.createElement("div");
        commentsList.className = "gallery-comments-list";
        commentsPanel.appendChild(commentsList);

        if (isLoggedIn) {
            const form = document.createElement("form");
            form.className = "gallery-comment-form";

            const input = document.createElement("textarea");
            input.name = "comment";
            input.maxLength = 500;
            input.rows = 2;
            input.placeholder = "Ajouter un commentaire...";
            input.required = true;

            const submit = document.createElement("button");
            submit.type = "submit";
            submit.className = "btn-savane";
            submit.textContent = "Envoyer";

            form.append(input, submit);
            form.addEventListener("submit", async (event) => {
                event.preventDefault();

                const comment = input.value.trim();

                if (!comment) {
                    return;
                }

                submit.disabled = true;

                try {
                    const result = await apiJson("/?page=photo_comment_add", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ photo_id: photo.photo_id, comment })
                    });
                    input.value = "";
                    photo.comments_count = result.comments_count;
                    commentsBtn.querySelector(".gallery-action-count").textContent = String(result.comments_count);
                    renderComments(commentsPanel, result.comments || []);
                } catch (error) {
                    console.error("[GALLERY] impossible de commenter", error);
                    alert(error.message === "Commentaire limite a 500 caracteres."
                        ? "Le commentaire est limite a 500 caracteres."
                        : "Impossible d'ajouter ce commentaire.");
                } finally {
                    submit.disabled = false;
                }
            });
            commentsPanel.appendChild(form);
        } else {
            const locked = document.createElement("p");
            locked.className = "gallery-comments-locked";
            locked.textContent = "Connectez-vous pour commenter.";
            commentsPanel.appendChild(locked);
        }

        commentsBtn.addEventListener("click", () => {
            if (commentsPanel.hidden) {
                loadComments(photo, commentsPanel);
            } else {
                commentsPanel.hidden = true;
            }
        });

        card.append(imageWrap, meta, actions, commentsPanel);

        return card;
    };

    const renderGallery = (photos, emptyMessage) => {
        galleryPhotos = photos;
        container.innerHTML = "";

        if (photos.length === 0) {
            setEmpty(emptyMessage);

            if (activeHashtag) {
                const resetEmptyBtn = document.createElement("button");
                resetEmptyBtn.type = "button";
                resetEmptyBtn.className = "btn-savane-secondary gallery-empty-reset";
                resetEmptyBtn.textContent = "Afficher toutes les photos";
                resetEmptyBtn.addEventListener("click", () => {
                    searchInput.value = "";
                    loadGallery();
                });
                container.appendChild(resetEmptyBtn);
            }

            return;
        }

        photos.forEach((photo) => container.appendChild(createPhotoCard(photo)));
    };

    const loadGallery = async (hashtag = "") => {
        try {
            const query = hashtag ? `&hashtag=${encodeURIComponent(hashtag)}` : "";
            const result = await apiJson(`/?page=photo_public_list&limit=30${query}`);
            const photos = result.photos || [];
            activeHashtag = hashtag;
            searchResetBtn.hidden = hashtag === "";
            setSearchStatus(hashtag ? `Résultats pour les mots-clés contenant "${hashtag}"` : "");
            renderGallery(photos, hashtag
                ? "Aucune photo ne correspond à ce hashtag."
                : "Aucun montage pour le moment.");
        } catch (error) {
            console.error("[GALLERY] impossible de charger la galerie", error);

            if (error.message === "Hashtag invalide.") {
                setSearchStatus("Mot-clé invalide : lettres et tirets uniquement.");
                renderGallery([], "Aucune photo ne correspond à ce hashtag.");
            } else {
                setEmpty("Impossible de charger la galerie.");
            }
        }
    };

    const loadHashtagSuggestions = async () => {
        try {
            const result = await apiJson("/?page=photo_hashtag_list&limit=18");
            const hashtags = result.hashtags || [];
            suggestions.innerHTML = "";

            hashtags.forEach((item) => {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "gallery-hashtag-chip";
                button.textContent = `#${item.hashtag}`;
                button.title = `${item.photos_count} photo${item.photos_count > 1 ? "s" : ""}`;
                button.addEventListener("click", () => {
                    searchInput.value = item.hashtag;
                    loadGallery(item.hashtag);
                });
                suggestions.appendChild(button);
            });
        } catch (error) {
            console.error("[GALLERY] impossible de charger les mots-cles", error);
            suggestions.innerHTML = "";
        }
    };

    searchForm.addEventListener("submit", (event) => {
        event.preventDefault();

        try {
            const hashtag = validateHashtag(searchInput.value);

            if (hashtag === "") {
                loadGallery();
                return;
            }

            searchInput.value = hashtag;
            loadGallery(hashtag);
        } catch (error) {
            setSearchStatus("Mot-clé invalide : lettres et tirets uniquement.");
            searchInput.focus();
        }
    });

    searchResetBtn.addEventListener("click", () => {
        searchInput.value = "";
        activeHashtag = "";
        loadGallery();
    });

    closeViewerBtn.addEventListener("click", closeViewer);
    prevViewerBtn.addEventListener("click", showPreviousPhoto);
    nextViewerBtn.addEventListener("click", showNextPhoto);

    viewerModal.addEventListener("click", (event) => {
        if (event.target === viewerModal) {
            closeViewer();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (viewerModal.hidden) {
            return;
        }

        if (event.key === "Escape") {
            closeViewer();
        } else if (event.key === "ArrowLeft") {
            showPreviousPhoto();
        } else if (event.key === "ArrowRight") {
            showNextPhoto();
        }
    });

    loadHashtagSuggestions();
    loadGallery(activeHashtag);
}
