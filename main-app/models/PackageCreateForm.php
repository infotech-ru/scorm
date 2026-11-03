<?php

namespace mainapp\models;

use yii\base\Model;

class PackageCreateForm extends Model
{
    public $source_url;
    public $file_name;

    public function rules(): array
    {
        return [
            [['source_url', 'file_name'], 'trim', 'skipOnArray' => true],
            [['source_url', 'file_name'], 'required'],
            ['source_url', 'string', 'max' => 2048],
            ['file_name', 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return array_combine($this->attributes(), $this->attributes());
    }
}
