<?php

namespace MetaFramework\Mediaclass\Http\Controllers;


use MetaFramework\Support\Traits\Ajax;

class AjaxController
{
    use Ajax;

    private FileUploadImages $uploader;

    public function __construct()
    {
        $this->uploader = new FileUploadImages;
    }

    public function upload(): array
    {
        return $this->uploader->setModel(request('model'))->upload()->fetchResponse();
    }

    public function crop(): array
    {
        return Cropper::crop();
    }

    public function deleteCrop(): array
    {
        return Cropper::deleteCrop();
    }


    public function delete(): array
    {
        return $this->uploader->delete()->fetchResponse();
    }


}