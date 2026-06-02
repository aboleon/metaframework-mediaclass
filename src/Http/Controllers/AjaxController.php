<?php

declare(strict_types=1);

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

    public function uploadUrl(): array
    {
        return $this->uploader->setModel(request('model'))->uploadUrl()->fetchResponse();
    }

    public function saveDescriptions(): array
    {
        return $this->uploader->setModel(request('model'))->saveDescriptions()->fetchResponse();
    }

    public function saveSubgroup(): array
    {
        return $this->uploader->setModel(request('model'))->saveSubgroup()->fetchResponse();
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

    public function deleteBridge(): array
    {
        return $this->uploader->setModel(request('model'))->deleteBridge()->fetchResponse();
    }
}
