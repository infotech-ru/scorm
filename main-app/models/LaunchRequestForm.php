<?php

namespace mainapp\models;

use common\dto\LaunchRequestDTO;
use common\models\ScormLaunchToken;
use yii\base\Model;

class LaunchRequestForm extends Model
{
    public $package_id;
    public $mode;
    public $registration_key;
    public $learner_id;
    public $learner_name;
    public $course_ref;

    public function rules(): array
    {
        return [
            ['package_id', 'required'],
            ['package_id', 'integer'],
            ['mode', 'default', 'value' => ScormLaunchToken::MODE_NORMAL],
            ['mode', 'in', 'range' => ScormLaunchToken::MODES],
            [['registration_key', 'learner_id', 'learner_name', 'course_ref'], 'trim', 'skipOnArray' => true],
            [['registration_key', 'learner_id', 'course_ref'], 'string', 'max' => 64],
            ['learner_name', 'string', 'max' => 255],
            [['registration_key', 'learner_id'], 'required', 'when' => fn() => $this->mode !== ScormLaunchToken::MODE_PREVIEW],
        ];
    }

    public function attributeLabels(): array
    {
        return array_combine($this->attributes(), $this->attributes());
    }

    public function toLaunchRequest(): LaunchRequestDTO
    {
        return new LaunchRequestDTO($this->mode, $this->registration_key, $this->learner_id, $this->learner_name, $this->course_ref);
    }
}
