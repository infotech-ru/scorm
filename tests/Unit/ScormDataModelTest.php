<?php

namespace tests\Unit;

use common\services\ScormDataModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScormDataModelTest extends TestCase
{
    #[DataProvider('validValues')]
    public function testAcceptsValidValues(string $version, string $element, string $value): void
    {
        $this->assertNull(ScormDataModel::validate($version, $element, $value));
    }

    public static function validValues(): array
    {
        return [
            ['1.2', 'cmi.core.lesson_status', 'completed'],
            ['1.2', 'cmi.core.score.raw', '85.5'],
            ['1.2', 'cmi.core.score.raw', ''],
            ['1.2', 'cmi.core.exit', 'time-out'],
            ['1.2', 'cmi.core.session_time', '0001:02:03.50'],
            ['1.2', 'cmi.objectives.3.status', 'not attempted'],
            ['1.2', 'cmi.interactions.0.objectives.1.id', 'obj-1'],
            ['2004', 'cmi.completion_status', 'incomplete'],
            ['2004', 'cmi.score.scaled', '-0.25'],
            ['2004', 'cmi.session_time', 'PT1H2M3.5S'],
            ['2004', 'cmi.exit', 'normal'],
            ['2004', 'cmi.interactions.0.timestamp', '2026-09-28T10:00:00Z'],
            ['2004', 'adl.nav.request', 'continue'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $version, string $element, string $value, string $reason): void
    {
        $this->assertSame($reason, ScormDataModel::validate($version, $element, $value));
    }

    public static function invalidValues(): array
    {
        return [
            'unknown element' => ['1.2', 'cmi.core.unknown', 'x', 'undefined'],
            'read only' => ['1.2', 'cmi.core.student_id', '2', 'read-only'],
            'not attempted is LMS only' => ['1.2', 'cmi.core.lesson_status', 'not attempted', 'type'],
            'score out of range' => ['1.2', 'cmi.core.score.raw', '101', 'type'],
            'old exit vocabulary' => ['1.2', 'cmi.core.exit', 'timeout', 'type'],
            '1.2 format in 2004' => ['2004', 'cmi.session_time', '0001:02:03', 'type'],
            'scaled out of range' => ['2004', 'cmi.score.scaled', '1.5', 'type'],
            'suspend data too long' => ['1.2', 'cmi.suspend_data', str_repeat('x', 4097), 'type'],
            '2004 element in 1.2' => ['1.2', 'cmi.completion_status', 'completed', 'undefined'],
        ];
    }

    public function testConvertsDurations(): void
    {
        $this->assertSame(3723, ScormDataModel::durationToSeconds('0001:02:03'));
        $this->assertSame(3723, ScormDataModel::durationToSeconds('PT1H2M3S'));
        $this->assertSame(90061, ScormDataModel::durationToSeconds('P1DT1H1M1S'));
        $this->assertSame('0001:02:03', ScormDataModel::formatSeconds('1.2', 3723));
        $this->assertSame('PT1H2M3S', ScormDataModel::formatSeconds('2004', 3723));
    }
}
