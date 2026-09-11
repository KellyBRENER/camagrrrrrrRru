import { photoUrl } from "../image-paths.js";

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
    const pageSize = 5;
    const sentinel = document.getElementById("galleryLoadMore");
    const nextPage = document.getElementById("galleryNextPage");
    const pageStatus = document.getElementById("galleryPageStatus");
    const retry = document.getElementById("galleryRetry");
    let cursor = null;
    let hasMore = true;
    let loading = false;
    let disposed = false;
    let requestController = null;
    let observer = null;
    let requestVersion = 0;
    let lastRequest = ["", false];

    if (!sentinel || !nextPage || !pageStatus || !retry || !container || !searchForm || !searchInput || !searchResetBtn || !suggestions || !searchStatus || !viewerModal || !viewerImage || !viewerCounter || !closeViewerBtn || !prevViewerBtn || !nextViewerBtn) {
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
                "X-CSRF-Token": window.userConfig.csrfToken,
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

        viewerImage.src = photoUrl(photo.path);
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
        image.src = photoUrl(photo.path);
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
                alert("Impossible de modifier ce like.");
            } finally {
                likeBtn.disabled = false;
            }
        });

        const commentsBtn = document.createElement("button");
        commentsBtn.type = "button";
        commentsBtn.className = "gallery-action";
        commentsBtn.innerHTML = '<i class="bi bi-chat-dots-fill" aria-hidden="true"></i><span class="gallery-action-count"></span>';
        commentsBtn.querySelector(".gallery-action-count").textContent = String(photo.comments_count || 0);

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

    const loadGallery = async (hashtag = "", append = false) => {
        if (disposed || (append && (loading || !hasMore))) return;
        const version = ++requestVersion;
        requestController?.abort();
        requestController = new AbortController();
        observer?.unobserve(sentinel);
        lastRequest = [hashtag, append];
        loading = true;
        nextPage.disabled = true;
        retry.hidden = true;
        container.setAttribute("aria-busy", "true");
        pageStatus.textContent = "Chargement...";
        if (!append) {
            activeHashtag = hashtag;
            cursor = null;
            hasMore = true;
            galleryPhotos = [];
            setEmpty("Chargement de la galerie...");
            searchInput.value = hashtag;
            searchResetBtn.hidden = hashtag === "";
            closeViewer();
        }
        try {
            const query = new URLSearchParams({ page: "photo_public_list", limit: pageSize, cursor: 1 });
            if (cursor !== null) {
                query.set("before_created_at", cursor.created_at);
                query.set("before_id", cursor.photo_id);
            }
            if (hashtag) query.set("hashtag", hashtag);
            const result = await apiJson(`/?${query}`, { signal: requestController.signal });
            if (version !== requestVersion || disposed || !container.isConnected) return;
            const photos = result.photos || [];
            const seen = new Set(galleryPhotos.map(photo => Number(photo.photo_id)));
            const additions = photos.filter(photo => {
                const id = Number(photo.photo_id);
                if (seen.has(id)) return false;
                seen.add(id);
                return true;
            });
            if (!append) {
                renderGallery(additions, hashtag
                    ? "Aucune photo ne correspond à ce hashtag."
                    : "Aucun montage pour le moment.");
            } else {
                galleryPhotos.push(...additions);
                additions.forEach(photo => container.appendChild(createPhotoCard(photo)));
                if (!viewerModal.hidden) refreshViewer();
            }
            const nextCursor = result.pagination.next_cursor;
            const validNextCursor = nextCursor !== null
                && typeof nextCursor === "object"
                && typeof nextCursor.created_at === "string"
                && Number.isInteger(nextCursor.photo_id)
                && nextCursor.photo_id > 0;
            const movesForward = cursor === null || (validNextCursor
                && (nextCursor.created_at < cursor.created_at
                    || (nextCursor.created_at === cursor.created_at && nextCursor.photo_id < cursor.photo_id)));
            hasMore = result.pagination.has_more === true && validNextCursor && movesForward;
            cursor = validNextCursor ? nextCursor : null;
            setSearchStatus(hashtag ? `Résultats pour les mots-clés contenant "${hashtag}"` : "");
            pageStatus.textContent = galleryPhotos.length === 0 ? "Aucune photo" :
                `${galleryPhotos.length} photo${galleryPhotos.length > 1 ? "s" : ""} affichée${galleryPhotos.length > 1 ? "s" : ""}${hasMore ? "" : " · Vous avez tout vu"}`;
            const url = new URL(window.location.href);
            url.searchParams.set("page", "gallery");
            url.searchParams.delete("gallery_page");
            if (hashtag) url.searchParams.set("hashtag", hashtag);
            else url.searchParams.delete("hashtag");
            window.history.replaceState({ page: "gallery" }, "", url);
        } catch (error) {
            if (version !== requestVersion || disposed || !container.isConnected) return;
            setSearchStatus("Impossible de charger les photos. Réessayez.");
            retry.hidden = false;
            pageStatus.textContent = "Chargement interrompu";
            if (galleryPhotos.length === 0) setEmpty("Impossible de charger la galerie.");
        } finally {
            if (version === requestVersion && !disposed && container.isConnected) {
                loading = false;
                container.setAttribute("aria-busy", "false");
                nextPage.disabled = !hasMore;
                nextPage.hidden = !hasMore || !retry.hidden;
                // Re-observe to fill a large viewport even if the sentinel remains visible.
                if (hasMore && retry.hidden) observer?.observe(sentinel);
            }
        }
    };
    if ("IntersectionObserver" in window) {
        observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting) && retry.hidden) loadGallery(activeHashtag, true);
        }, { rootMargin: "300px" });
    }
    nextPage.addEventListener("click", () => loadGallery(activeHashtag, true));
    retry.addEventListener("click", () => loadGallery(...lastRequest));

    const dispose = () => {
        disposed = true;
        requestVersion++;
        requestController?.abort();
        observer?.disconnect();
        closeViewer();
        document.removeEventListener("keydown", onViewerKeydown);
        window.removeEventListener("app:page-leave", dispose);
        window.removeEventListener("pagehide", dispose);
    };
    window.addEventListener("app:page-leave", dispose);
    window.addEventListener("pagehide", dispose);

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

    const onViewerKeydown = (event) => {
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
    };
    document.addEventListener("keydown", onViewerKeydown);

    loadHashtagSuggestions();
    const initialParams = new URLSearchParams(window.location.search);
    let initialHashtag = "";
    try { initialHashtag = validateHashtag(initialParams.get("hashtag") || ""); } catch { /* Ignore invalid bookmarked filters. */ }
    loadGallery(initialHashtag);
}
