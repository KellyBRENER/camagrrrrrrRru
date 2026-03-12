<div class="savane-card">
    <h2><span class="paw-print">📸</span> Studio de création félin</h2>
    <p>Créez vos photos avec nos filtres sauvages !</p>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="savane-card">
            <h3>Aperçu</h3>
            <div id="cameraPreview" style="background: #000; min-height: 400px; border-radius: 10px; position: relative;">
                <video id="webcam" autoplay style="width: 100%; border-radius: 10px;"></video>
                <canvas id="overlay" style="position: absolute; top: 0; left: 0;"></canvas>
            </div>
            
            <div class="mt-3 text-center">
                <button id="startCamera" class="btn-savane me-2">📹 Démarrer la caméra</button>
                <button id="capturePhoto" class="btn-savane-secondary me-2" disabled>📸 Capturer</button>
                <input type="file" id="uploadPhoto" accept="image/*" style="display: none;">
                <button id="uploadBtn" class="btn-savane-secondary">📤 Télécharger une image</button>
            </div>
        </div>
        
        <div class="savane-card mt-3">
            <h3>Filtres disponibles</h3>
            <div id="filtersList" class="d-flex flex-wrap gap-2">
                <!-- Les filtres seront chargés ici -->
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="savane-card">
            <h3>Mes créations</h3>
            <div id="myGallery">
                <!-- Les miniatures seront chargées ici -->
            </div>
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