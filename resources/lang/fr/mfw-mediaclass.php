<?php

return [
    'buttons'      => [
        'send'         => 'Envoyer',
        'save'         => 'Enregistrer',
        'select_image' => 'Choisir une image',
        'download'     => 'Télécharger',
        'cancel'       => 'Annuler',
        'error'        => 'Erreur',
        'select'       => 'Sélectionner',
    ],
    'file'         => 'fichier|fichiers',
    'uploaded_at'  => "Téléchargé le :date à :time",
    'errors'       => [
        'missing_model'    => "Le média doit obligatoirement appartenir à un objet",
        'mustBeImage'      => "Le fichier n'est pas une image",
        'maxNumberOfFiles' => "Le nombre maxium de fichiers que vous pouvez télécharger est ",
        'maxFileSize'      => "Le fichier est trop volumineux. Poids maximum : ",
        'dimensions'       => "L'image doit avoir une taille minimale de :width x :height pixels. Image uploadée : :uploaded_width x :uploaded_height pixels.",
        'scale_for_crop'   => "L'échelle de l'image n'est pas correcte pour du recadrage.",
        'upload_failed'    => "Échec du téléchargement",
        'acceptFileTypes'  => "Type de fichier non autorisé",
    ],
    'no_media'     => "Aucun média n'a été ajouté",
    'no_documents' => "Aucun document n'a été ajouté",
    'crop_success' => "Image recadrée avec succès",
    'crop_deleted' => "Recadrage supprimé avec succès",
];
