<?php

namespace mainapp\models;

class PackageCreateForm extends ApiForm
{
    public $sourceUrl;
    public $fileName;

    public function rules(): array
    {
        return [
            [['sourceUrl', 'fileName'], 'trim', 'skipOnArray' => true],
            [['sourceUrl', 'fileName'], 'required'],
            ['sourceUrl', 'string', 'max' => 2048],
            ['fileName', 'string', 'max' => 255],
        ];
    }
}
