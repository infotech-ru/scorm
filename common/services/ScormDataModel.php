<?php

namespace common\services;

final class ScormDataModel
{
    public const ACCESS_READ = 'r';
    public const ACCESS_WRITE = 'w';
    public const ACCESS_READ_WRITE = 'rw';

    private const STATUS_12 = ['passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted'];
    private const COMPLETION_2004 = ['completed', 'incomplete', 'not attempted', 'unknown'];
    private const SUCCESS_2004 = ['passed', 'failed', 'unknown'];

    public static function definitions(string $version): array
    {
        return $version === '2004' ? self::scorm2004() : self::scorm12();
    }

    public static function normalize(string $element): string
    {
        return preg_replace('/\.\d+(?=\.)/', '.n', $element);
    }

    public static function find(string $version, string $element): ?array
    {
        return self::definitions($version)[self::normalize($element)] ?? null;
    }

    public static function validate(string $version, string $element, mixed $value): ?string
    {
        $definition = self::find($version, $element);
        if ($definition === null) {
            return 'undefined';
        }

        [$access, $type] = $definition;
        $param = $definition[2] ?? null;

        if ($access === self::ACCESS_READ) {
            return 'read-only';
        }

        if (!is_scalar($value)) {
            return 'type';
        }

        return self::matchesType($type, $param, (string)$value) ? null : 'type';
    }

    private static function matchesType(string $type, mixed $param, string $value): bool
    {
        return match ($type) {
            'string' => mb_strlen($value) <= $param,
            'vocab' => in_array($value, $param, true),
            'decimal' => $value === '' || (is_numeric($value) && self::inRange((float)$value, $param)),
            'int' => preg_match('/^-?\d+$/', $value) === 1 && self::inRange((float)$value, $param),
            'real' => is_numeric($value) && self::inRange((float)$value, $param),
            'timespan' => preg_match('/^\d{2,4}:\d{2}:\d{2}(\.\d{1,2})?$/', $value) === 1,
            'time' => preg_match('/^\d{2}:\d{2}:\d{2}(\.\d{1,2})?$/', $value) === 1,
            'duration' => preg_match('/^P(?=.)(\d+Y)?(\d+M)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d{1,2})?S)?)?$/', $value) === 1,
            'timestamp' => preg_match('/^\d{4}(-\d{2}(-\d{2}(T\d{2}(:\d{2}(:\d{2}(\.\d{1,2})?)?)?(Z|[+-]\d{2}(:\d{2})?)?)?)?)?$/', $value) === 1,
            'result' => in_array($value, $param, true) || is_numeric($value),
            default => false,
        };
    }

    private static function inRange(float $value, ?array $range): bool
    {
        [$min, $max] = $range ?? [null, null];

        return ($min === null || $value >= $min) && ($max === null || $value <= $max);
    }

    public static function durationToSeconds(string $value): int
    {
        if (preg_match('/^(\d+):(\d{2}):(\d{2})(?:\.\d+)?$/', $value, $m)) {
            return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
        }

        if (preg_match('/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/', $value, $m)) {
            $part = static fn(int $i): float => (float)($m[$i] ?? 0);

            return (int)($part(1) * 31536000 + $part(2) * 2592000 + $part(3) * 86400
                + $part(4) * 3600 + $part(5) * 60 + $part(6));
        }

        return 0;
    }

    public static function formatSeconds(string $version, int $seconds): string
    {
        if ($version === '2004') {
            return sprintf('PT%dH%dM%dS', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }

        return sprintf('%04d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private static function scorm12(): array
    {
        $r = self::ACCESS_READ;
        $w = self::ACCESS_WRITE;
        $rw = self::ACCESS_READ_WRITE;
        $score = [0, 100];

        return [
            'cmi._children' => [$r, 'keyword', 'core,suspend_data,launch_data,comments,objectives,student_data,student_preference,interactions'],
            'cmi.core._children' => [$r, 'keyword', 'student_id,student_name,lesson_location,credit,lesson_status,entry,score,total_time,lesson_mode,exit,session_time'],
            'cmi.core.student_id' => [$r, 'string', 255],
            'cmi.core.student_name' => [$r, 'string', 255],
            'cmi.core.lesson_location' => [$rw, 'string', 255],
            'cmi.core.credit' => [$r, 'string', 255],
            'cmi.core.lesson_status' => [$rw, 'vocab', array_slice(self::STATUS_12, 0, 5)],
            'cmi.core.entry' => [$r, 'string', 255],
            'cmi.core.score._children' => [$r, 'keyword', 'raw,min,max'],
            'cmi.core.score.raw' => [$rw, 'decimal', $score],
            'cmi.core.score.min' => [$rw, 'decimal', $score],
            'cmi.core.score.max' => [$rw, 'decimal', $score],
            'cmi.core.total_time' => [$r, 'timespan'],
            'cmi.core.lesson_mode' => [$r, 'string', 255],
            'cmi.core.exit' => [$w, 'vocab', ['time-out', 'suspend', 'logout', '']],
            'cmi.core.session_time' => [$w, 'timespan'],
            'cmi.suspend_data' => [$rw, 'string', 4096],
            'cmi.launch_data' => [$r, 'string', 4096],
            'cmi.comments' => [$rw, 'string', 4096],
            'cmi.comments_from_lms' => [$r, 'string', 4096],
            'cmi.objectives._children' => [$r, 'keyword', 'id,score,status'],
            'cmi.objectives._count' => [$r, 'count'],
            'cmi.objectives.n.id' => [$rw, 'string', 255],
            'cmi.objectives.n.score._children' => [$r, 'keyword', 'raw,min,max'],
            'cmi.objectives.n.score.raw' => [$rw, 'decimal', $score],
            'cmi.objectives.n.score.min' => [$rw, 'decimal', $score],
            'cmi.objectives.n.score.max' => [$rw, 'decimal', $score],
            'cmi.objectives.n.status' => [$rw, 'vocab', self::STATUS_12],
            'cmi.student_data._children' => [$r, 'keyword', 'mastery_score,max_time_allowed,time_limit_action'],
            'cmi.student_data.mastery_score' => [$r, 'string', 255],
            'cmi.student_data.max_time_allowed' => [$r, 'string', 255],
            'cmi.student_data.time_limit_action' => [$r, 'string', 255],
            'cmi.student_preference._children' => [$r, 'keyword', 'audio,language,speed,text'],
            'cmi.student_preference.audio' => [$rw, 'int', [-1, 100]],
            'cmi.student_preference.language' => [$rw, 'string', 255],
            'cmi.student_preference.speed' => [$rw, 'int', [-100, 100]],
            'cmi.student_preference.text' => [$rw, 'int', [-1, 1]],
            'cmi.interactions._children' => [$r, 'keyword', 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency'],
            'cmi.interactions._count' => [$r, 'count'],
            'cmi.interactions.n.id' => [$w, 'string', 255],
            'cmi.interactions.n.objectives._count' => [$r, 'count'],
            'cmi.interactions.n.objectives.n.id' => [$w, 'string', 255],
            'cmi.interactions.n.time' => [$w, 'time'],
            'cmi.interactions.n.type' => [$w, 'vocab', ['true-false', 'choice', 'fill-in', 'matching', 'performance', 'sequencing', 'likert', 'numeric']],
            'cmi.interactions.n.correct_responses._count' => [$r, 'count'],
            'cmi.interactions.n.correct_responses.n.pattern' => [$w, 'string', 255],
            'cmi.interactions.n.weighting' => [$w, 'decimal', null],
            'cmi.interactions.n.student_response' => [$w, 'string', 255],
            'cmi.interactions.n.result' => [$w, 'result', ['correct', 'wrong', 'unanticipated', 'neutral']],
            'cmi.interactions.n.latency' => [$w, 'timespan'],
        ];
    }

    private static function scorm2004(): array
    {
        $r = self::ACCESS_READ;
        $w = self::ACCESS_WRITE;
        $rw = self::ACCESS_READ_WRITE;
        $scaled = [-1, 1];
        $unit = [0, 1];

        return [
            'cmi._version' => [$r, 'keyword', '1.0'],
            'cmi.comments_from_learner._children' => [$r, 'keyword', 'comment,location,timestamp'],
            'cmi.comments_from_learner._count' => [$r, 'count'],
            'cmi.comments_from_learner.n.comment' => [$rw, 'string', 4000],
            'cmi.comments_from_learner.n.location' => [$rw, 'string', 250],
            'cmi.comments_from_learner.n.timestamp' => [$rw, 'timestamp'],
            'cmi.comments_from_lms._children' => [$r, 'keyword', 'comment,location,timestamp'],
            'cmi.comments_from_lms._count' => [$r, 'count'],
            'cmi.comments_from_lms.n.comment' => [$r, 'string', 4000],
            'cmi.comments_from_lms.n.location' => [$r, 'string', 250],
            'cmi.comments_from_lms.n.timestamp' => [$r, 'timestamp'],
            'cmi.completion_status' => [$rw, 'vocab', self::COMPLETION_2004],
            'cmi.completion_threshold' => [$r, 'real', $unit],
            'cmi.credit' => [$r, 'string', 255],
            'cmi.entry' => [$r, 'string', 255],
            'cmi.exit' => [$w, 'vocab', ['time-out', 'suspend', 'logout', 'normal', '']],
            'cmi.interactions._children' => [$r, 'keyword', 'id,type,objectives,timestamp,correct_responses,weighting,learner_response,result,latency,description'],
            'cmi.interactions._count' => [$r, 'count'],
            'cmi.interactions.n.id' => [$rw, 'string', 4000],
            'cmi.interactions.n.type' => [$rw, 'vocab', ['true-false', 'choice', 'fill-in', 'long-fill-in', 'likert', 'matching', 'performance', 'sequencing', 'numeric', 'other']],
            'cmi.interactions.n.objectives._count' => [$r, 'count'],
            'cmi.interactions.n.objectives.n.id' => [$rw, 'string', 4000],
            'cmi.interactions.n.timestamp' => [$rw, 'timestamp'],
            'cmi.interactions.n.correct_responses._count' => [$r, 'count'],
            'cmi.interactions.n.correct_responses.n.pattern' => [$rw, 'string', 7800],
            'cmi.interactions.n.weighting' => [$rw, 'real', null],
            'cmi.interactions.n.learner_response' => [$rw, 'string', 7800],
            'cmi.interactions.n.result' => [$rw, 'result', ['correct', 'incorrect', 'unanticipated', 'neutral']],
            'cmi.interactions.n.latency' => [$rw, 'duration'],
            'cmi.interactions.n.description' => [$rw, 'string', 250],
            'cmi.launch_data' => [$r, 'string', 4000],
            'cmi.learner_id' => [$r, 'string', 4000],
            'cmi.learner_name' => [$r, 'string', 250],
            'cmi.learner_preference._children' => [$r, 'keyword', 'audio_level,language,delivery_speed,audio_captioning'],
            'cmi.learner_preference.audio_level' => [$rw, 'real', [0, null]],
            'cmi.learner_preference.language' => [$rw, 'string', 250],
            'cmi.learner_preference.delivery_speed' => [$rw, 'real', [0, null]],
            'cmi.learner_preference.audio_captioning' => [$rw, 'vocab', ['-1', '0', '1']],
            'cmi.location' => [$rw, 'string', 1000],
            'cmi.max_time_allowed' => [$r, 'duration'],
            'cmi.mode' => [$r, 'string', 255],
            'cmi.objectives._children' => [$r, 'keyword', 'id,score,success_status,completion_status,progress_measure,description'],
            'cmi.objectives._count' => [$r, 'count'],
            'cmi.objectives.n.id' => [$rw, 'string', 4000],
            'cmi.objectives.n.score._children' => [$r, 'keyword', 'scaled,raw,min,max'],
            'cmi.objectives.n.score.scaled' => [$rw, 'real', $scaled],
            'cmi.objectives.n.score.raw' => [$rw, 'real', null],
            'cmi.objectives.n.score.min' => [$rw, 'real', null],
            'cmi.objectives.n.score.max' => [$rw, 'real', null],
            'cmi.objectives.n.success_status' => [$rw, 'vocab', self::SUCCESS_2004],
            'cmi.objectives.n.completion_status' => [$rw, 'vocab', self::COMPLETION_2004],
            'cmi.objectives.n.progress_measure' => [$rw, 'real', $unit],
            'cmi.objectives.n.description' => [$rw, 'string', 250],
            'cmi.progress_measure' => [$rw, 'real', $unit],
            'cmi.scaled_passing_score' => [$r, 'real', $scaled],
            'cmi.score._children' => [$r, 'keyword', 'scaled,raw,min,max'],
            'cmi.score.scaled' => [$rw, 'real', $scaled],
            'cmi.score.raw' => [$rw, 'real', null],
            'cmi.score.min' => [$rw, 'real', null],
            'cmi.score.max' => [$rw, 'real', null],
            'cmi.session_time' => [$w, 'duration'],
            'cmi.success_status' => [$rw, 'vocab', self::SUCCESS_2004],
            'cmi.suspend_data' => [$rw, 'string', 64000],
            'cmi.time_limit_action' => [$r, 'string', 255],
            'cmi.total_time' => [$r, 'duration'],
            'adl.nav.request' => [$rw, 'string', 4000],
        ];
    }
}
