<div class="studio-page">
    <div class="studio-workspace">
        <aside class="studio-filters-panel savane-card">
            <h3>Filtres disponibles</h3>
            <div class="studio-filter-section">
                <h4>Cadres</h4>
                <div id="frameFiltersList" class="studio-filter-list">
                    <!-- Les cadres seront chargés ici -->
                </div>
            </div>

            <div class="studio-filter-section">
                <h4>Stickers</h4>
                <div id="stickerFiltersList" class="studio-filter-list">
                    <!-- Les stickers seront chargés ici -->
                </div>
            </div>
        </aside>

        <div class="studio-main">
            <div class="savane-card">
                <div class="studio-capture-layout">
                    <div id="cameraPreview" class="studio-preview">
                        <video id="webcam" autoplay muted playsinline></video>
                        <canvas id="overlay"></canvas>
                    </div>

                    <div class="studio-actions" aria-label="Outils de capture">
                        <button type="button" id="startCamera" class="btn-savane-secondary studio-tool-btn" aria-label="Démarrer la caméra" aria-pressed="false" title="Démarrer la caméra">
                            <i class="bi bi-camera-video-fill" aria-hidden="true"></i>
                        </button>
                        <input type="file" id="uploadPhoto" accept="image/png,image/jpeg" style="display: none;">
                        <button type="button" id="uploadBtn" class="btn-savane-secondary studio-tool-btn" aria-label="Télécharger une image" title="Télécharger une image">
                            <i class="bi bi-upload" aria-hidden="true"></i>
                        </button>
                        <button type="button" id="capturePhoto" class="btn-savane-secondary studio-tool-btn" disabled aria-label="Capturer" title="Capturer">
                            <i class="bi bi-camera-fill" aria-hidden="true"></i>
                        </button>
                        <button type="button" id="createMontage" class="btn-savane studio-tool-btn" disabled aria-label="Créer le montage" title="Créer le montage">
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                        </button>
                        <button type="button" id="cropPhoto" class="btn-savane-secondary studio-tool-btn" hidden aria-label="Valider le recadrage" title="Valider le recadrage">
                            <i class="bi bi-scissors" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <p id="createMontageStatus" class="studio-status" aria-live="polite"></p>
            </div>
        </div>

        <aside class="studio-side savane-card">
            <h3>Mes créations</h3>
            <div id="myGallery">
                <p class="studio-gallery-empty">Aucun montage pour le moment.</p>
            </div>
        </aside>
    </div>
</div>

<div id="hashtagsModal" class="studio-crop-modal" hidden>
    <div class="studio-hashtags-dialog" role="dialog" aria-modal="true" aria-labelledby="hashtagsDialogTitle">
        <div class="studio-crop-header">
            <div>
                <h3 id="hashtagsDialogTitle">Mots-clés</h3>
                <p>Ajoutez jusqu'à 5 mots-clés, séparés par des virgules ou des espaces. Lettres et tirets uniquement.</p>
            </div>
            <button type="button" id="cancelHashtagsPhoto" class="studio-crop-close" aria-label="Fermer">×</button>
        </div>

        <input type="text" id="photoHashtags" class="form-control" maxlength="160" placeholder="savane, portrait, fun">
        <p id="hashtagsMontageStatus" class="studio-status" aria-live="polite"></p>

        <div class="studio-crop-actions">
            <button type="button" id="cancelHashtagsAction" class="btn-savane-secondary">Annuler</button>
            <button type="button" id="validateHashtagsPhoto" class="btn-savane">Créer le montage</button>
        </div>
    </div>
</div>

<div id="photoViewerModal" class="studio-crop-modal" hidden>
    <div class="studio-photo-viewer" role="dialog" aria-modal="true" aria-labelledby="photoViewerTitle">
        <div class="studio-crop-header">
            <div>
                <h3 id="photoViewerTitle">Montage</h3>
                <p id="photoViewerCounter"></p>
            </div>
            <button type="button" id="closePhotoViewer" class="studio-crop-close" aria-label="Fermer">×</button>
        </div>

        <div class="studio-photo-viewer-frame">
            <button type="button" id="prevPhotoViewer" class="studio-photo-nav studio-photo-nav-prev" aria-label="Photo précédente">‹</button>
            <img id="photoViewerImage" alt="Montage sélectionné">
            <button type="button" id="nextPhotoViewer" class="studio-photo-nav studio-photo-nav-next" aria-label="Photo suivante">›</button>
        </div>
    </div>
</div>

<div id="imageCropModal" class="studio-crop-modal" hidden>
    <div class="studio-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="cropDialogTitle">
        <div class="studio-crop-header">
            <div>
                <h3 id="cropDialogTitle">Recadrer l'image</h3>
                <p>Glissez l'image dans le cadre, puis validez le cadrage.</p>
            </div>
            <button type="button" id="cancelCropPhoto" class="studio-crop-close" aria-label="Annuler le recadrage">×</button>
        </div>

        <div class="studio-crop-frame-wrap">
            <div id="cropFrame" class="studio-crop-frame">
                <img id="cropImage" alt="Image à recadrer">
            </div>
        </div>

        <div class="studio-crop-zoom">
            <button type="button" id="zoomCropOut" class="btn-savane-secondary" aria-label="Réduire le zoom">−</button>
            <label for="cropZoomRange">Zoom</label>
            <input type="range" id="cropZoomRange" min="100" max="400" value="100" step="1">
            <button type="button" id="zoomCropIn" class="btn-savane-secondary" aria-label="Augmenter le zoom">+</button>
        </div>

        <div class="studio-crop-actions">
            <button type="button" id="cancelCropAction" class="btn-savane-secondary">Annuler</button>
            <button type="button" id="validateCropPhoto" class="btn-savane">Valider le cadrage</button>
        </div>
    </div>
</div>

<?php
//accessible uniquement aux users connectés
//message de refus pour les visiteurs
//affiche la page du studio (webcam)
//une section principale : aperçu webcam / liste des filtres disponibles / bouton de capture
//une section latérale : galerie des images capturées par le user (miniatures cliquables pour voir en grand)
//capture impossible si aucun filtre sélectionné
//création image finale côté serveur : superposition de l'image capturée et du filtre sélectionné
//possibilité de téléchargé une image plutot que d'utiliser la webcam
//possibilité de supprimer une image de sa galerie
//Bonus : pouvoir créer son propre filtre
//Bonus : aperçu en temps réel du filtre sur la webcam avant capture
//Bonus : possibilité de partager l'image capturée sur les réseaux sociaux
//Bonus : générer un gif
?>
