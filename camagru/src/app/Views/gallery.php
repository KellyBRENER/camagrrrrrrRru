<div class="savane-card">
    <h2><span class="paw-print">🖼️</span> Galerie de la savane</h2>
    <p>Découvrez les créations félines de notre communauté !</p>
</div>

<section class="savane-card gallery-search-panel" aria-labelledby="gallerySearchTitle">
    <h3 id="gallerySearchTitle">Recherche par mot-clé</h3>
    <form id="gallerySearchForm" class="gallery-search-form">
        <label for="gallerySearchInput" class="visually-hidden">Mot-clé</label>
        <input type="search" id="gallerySearchInput" class="form-control" maxlength="25" placeholder="ex : leopard">
        <button type="submit" class="btn-savane">Rechercher</button>
        <button type="button" id="galleryResetSearch" class="btn-savane-secondary" hidden>Tout afficher</button>
    </form>
    <div id="galleryHashtagSuggestions" class="gallery-hashtag-suggestions" aria-live="polite"></div>
    <p id="gallerySearchStatus" class="gallery-search-status" aria-live="polite"></p>
</section>

<div id="galleryContainer" class="gallery-grid" aria-live="polite">
    <p class="gallery-empty">Chargement de la galerie...</p>
</div>

<nav class="savane-card d-flex flex-wrap align-items-center justify-content-center gap-3 mt-3" aria-label="Pagination de la galerie">
    <button type="button" id="galleryPreviousPage" class="btn-savane-secondary" disabled>Page précédente</button>
    <span id="galleryPageStatus" role="status" tabindex="-1">Chargement...</span>
    <button type="button" id="galleryNextPage" class="btn-savane-secondary" disabled>Page suivante</button>
    <button type="button" id="galleryRetry" class="btn-savane-secondary" hidden>Réessayer</button>
</nav>

<div id="galleryViewerModal" class="studio-crop-modal" hidden>
    <div class="studio-photo-viewer" role="dialog" aria-modal="true" aria-labelledby="galleryViewerTitle">
        <div class="studio-crop-header">
            <div>
                <h3 id="galleryViewerTitle">Montage</h3>
                <p id="galleryViewerCounter"></p>
            </div>
            <button type="button" id="closeGalleryViewer" class="studio-crop-close" aria-label="Fermer">×</button>
        </div>

        <div class="studio-photo-viewer-frame">
            <button type="button" id="prevGalleryViewer" class="studio-photo-nav studio-photo-nav-prev" aria-label="Photo précédente">‹</button>
            <img id="galleryViewerImage" alt="Montage sélectionné">
            <button type="button" id="nextGalleryViewer" class="studio-photo-nav studio-photo-nav-next" aria-label="Photo suivante">›</button>
        </div>
    </div>
</div>

<?php
//publique : affiche toutes les images modifiées par tous les users
//classées par ordre chronologique et paginées par 5 minimum
//les users connectés peuvent liker/commenter les images
//Bonus : ajouter une recherche par image ou # ou user
//Bonus : choisir la pagination
//Bonus : pagination infinie
//lorsqu'une image est commentée => envoyer notif à l'auteur par email
?>
