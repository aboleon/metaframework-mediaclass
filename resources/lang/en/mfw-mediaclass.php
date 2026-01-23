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
    'errors' => [
        'missing_model' => "Media must belong to an object",
        'mustBeImage' => "The file is not an image",
        'maxNumberOfFiles' => "Maximum number of files you can upload is ",
        'maxFileSize' => "File is too large. Maximum size: ",
        'dimensions' => "Image must have a minimum size of :width x :height pixels. Uploaded image: :uploaded_width x :uploaded_height pixels.",
        'scale_for_crop' => "Image scale is not correct for cropping.",
        'upload_failed' => "Upload failed",
        'acceptFileTypes' => "File type not allowed",
    ],
    'uploaded_at' => "Uploaded on :date at :time",
    'crop_success' => "Image cropped successfully",
    'crop_deleted' => "Crop deleted successfully",
    'no_media' => "No media has been added",
];
