<?php

namespace mainapp\models;

use common\dto\LaunchRequestDTO;
use common\models\ScormLaunchToken;

class LaunchRequestForm extends ApiForm
{
    public $packageId;
    public $mode;
    public $registrationKey;
    public $learnerId;
    public $learnerName;
    public $courseRef;

    public function rules(): array
    {
        return [
            ['packageId', 'required'],
            ['packageId', 'integer'],
            ['mode', 'default', 'value' => ScormLaunchToken::MODE_NORMAL],
            ['mode', 'in', 'range' => ScormLaunchToken::MODES],
            [['registrationKey', 'learnerId', 'learnerName', 'courseRef'], 'trim', 'skipOnArray' => true],
            [['registrationKey', 'learnerId', 'courseRef'], 'string', 'max' => 64],
            ['learnerName', 'string', 'max' => 255],
            [['registrationKey', 'learnerId'], 'required', 'when' => fn() => $this->mode !== ScormLaunchToken::MODE_PREVIEW],
        ];
    }

    public function toLaunchRequest(): LaunchRequestDTO
    {
        return new LaunchRequestDTO($this->mode, $this->registrationKey, $this->learnerId, $this->learnerName, $this->courseRef);
    }
}
