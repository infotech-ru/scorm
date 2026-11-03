<?php

namespace mainapp\models;

use yii\base\Model;
use yii\helpers\Inflector;

abstract class ApiForm extends Model
{
    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if ($scope === '') {
            return parent::load($this->camelizeKeys($data), $formName);
        }
        if (isset($data[$scope]) && is_array($data[$scope])) {
            $data[$scope] = $this->camelizeKeys($data[$scope]);
        }

        return parent::load($data, $formName);
    }

    public function attributeLabels(): array
    {
        return array_combine(
            $this->attributes(),
            array_map(static fn(string $attribute): string => Inflector::underscore($attribute), $this->attributes())
        );
    }

    private function camelizeKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[Inflector::variablize((string)$key)] = $value;
        }

        return $result;
    }
}
