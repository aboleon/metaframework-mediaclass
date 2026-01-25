<?php

return [
    'buttons' => [
        'send' => 'Send',
        'save' => 'Save',
        'select_image' => 'Select an image',
        'download' => 'Download',
        'cancel' => 'Cancel',
        'error' => 'Error',
        'select' => 'Select'
    ],
    'labels' => [
        'media' => 'Media',
        'positions' => 'Positions relative to content',
        'description' => 'Description',
        'processing' => 'Processing...',
    ],
    'notices' => [
        'limit_reached' => 'Limit of :count file(s) reached',
        'dimension_requirements' => 'Required dimensions: :width × :height px minimum',
    ],
    'errors' => [
        'missing_model' => "Media must belong to an object",
        'mustBeImage' => "The file is not an image",
        'maxNumberOfFiles' => "Maximum number of files you can upload is ",
        'maxFileSize' => "File is too large. Maximum size: ",
        'dimensions' => "Image must have a minimum size of :width x :height pixels. Uploaded image: :uploaded_width x :uploaded_height pixels.",
        'scale_for_crop' => "Image scale is not correct for cropping.",
        'upload_failed' => "Upload failed",
        'acceptFileTypes' => "File type not allowed",
        'minImageWidth' => 'Minimum required width: :width px',
        'minImageHeight' => 'Minimum required height: :height px',
        'imageDimensions' => 'Minimum required dimensions: :width × :height px',
        'upload_error_title' => 'Upload error',
        'upload_error_generic' => 'An error occurred while uploading your file',
    ],
    'uploaded_at' => "Uploaded on :date at :time",
    'crop_success' => "Image cropped successfully",
    'crop_deleted' => "Crop deleted successfully",
    'no_media' => "No media has been added",
];
