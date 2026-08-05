<div class="savane-card">
    <h2><span class="paw-print">📸</span> Studio de création félin</h2>
    <p>Créez vos photos avec nos filtres sauvages !</p>
</div>

<div class="row">
    <div class="col-md-8 studio-main">
        <div class="savane-card">
            <h3>Aperçu</h3>
            <div id="cameraPreview" class="studio-preview">
                <video id="webcam" autoplay muted playsinline></video>
                <canvas id="overlay"></canvas>
            </div>
            
            <div class="studio-actions mt-3">
                <button type="button" id="startCamera" class="btn-savane">📹 Démarrer la caméra</button>
                <button type="button" id="capturePhoto" class="btn-savane-secondary" disabled>📸 Capturer</button>
                <input type="file" id="uploadPhoto" accept="image/*" style="display: none;">
                <button type="button" id="uploadBtn" class="btn-savane-secondary">📤 Télécharger une image</button>
                <button type="button" id="cropPhoto" class="btn-savane-secondary" hidden>✂️ Valider le recadrage</button>
            </div>
        </div>
        
        <div class="savane-card mt-3">
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
        </div>
    </div>
    
    <div class="col-md-4 studio-side">
        <div class="savane-card">
            <h3>Mes créations</h3>
            <div id="myGallery">
                <!-- Les miniatures seront chargées ici -->
            </div>
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
