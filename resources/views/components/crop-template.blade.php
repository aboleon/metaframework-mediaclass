<!-- CROP VIEW TEMPLATE -->
<template id="mediaclass-crop-view-template">
    <div class="crop-view-container">
        <div class="crop-info">
            <h5 class="crop-key-title"></h5>
            <div class="dimensions crop-dimensions-text"></div>
        </div>

        <div class="crop-preview">
            <img src="" alt="" class="img-fluid crop-preview-image">
        </div>

        <div class="crop-details mt-3">
            <div class="row">
                <div class="col-md-4">
                    <b>Type de recadrage :</b> <span class="crop-key-label crop-badge"></span>
                </div>
                <div class="col-md-4">
                    <b>Dimensions :</b> <span class="crop-dimensions-label crop-badge"></span>
                </div>
                <div class="col-md-4">
                    <b>Fichier :</b> <span class="crop-filename crop-badge"></span>
                </div>
            </div>
        </div>

        <div class="crop-actions mt-4">
            <div id="mediaclass-delete-crop-form" data-ajax="{{ route('mediaclass.ajax') }}">
                <button type="button" class="btn btn-danger" id="mediaclass-delete-crop-btn">
                    <i class="fa-solid fa-trash"></i> Supprimer ce recadrage
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</template>