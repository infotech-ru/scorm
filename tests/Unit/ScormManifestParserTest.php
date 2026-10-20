<?php

namespace tests\Unit;

use common\services\ScormManifestParser;
use PHPUnit\Framework\TestCase;

final class ScormManifestParserTest extends TestCase
{
    private const SCORM_12_HEADER = '<manifest identifier="m" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"'
        . ' xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">';

    private const SCORM_2004_HEADER = '<manifest identifier="m" version="1" xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"'
        . ' xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3" xmlns:imsss="http://www.imsglobal.org/xsd/imsss">';

    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);
    }

    public function testDetectsScorm12(): void
    {
        $result = $this->parse(self::SCORM_12_HEADER, '1.2');

        $this->assertSame('1.2', $result['version']);
    }

    public function testDetectsScorm2004BySchemaVersion(): void
    {
        $result = $this->parse(self::SCORM_2004_HEADER, '2004 3rd Edition');

        $this->assertSame('2004', $result['version']);
    }

    public function testDetectsScorm2004ByNamespacesWithoutSchemaVersion(): void
    {
        $result = $this->parse(self::SCORM_2004_HEADER, null);

        $this->assertSame('2004', $result['version']);
    }

    public function testManifestVersionAttributeDoesNotAffectDetection(): void
    {
        $header = str_replace('identifier="m"', 'identifier="m" version="1.3"', self::SCORM_12_HEADER);

        $result = $this->parse($header, '1.2');

        $this->assertSame('1.2', $result['version']);
    }

    public function testExtractsScosInDocumentOrderWithParameters(): void
    {
        $parser = new ScormManifestParser();
        $parser->parse($this->write(self::SCORM_12_HEADER, '1.2'));

        $scos = $parser->extractScos();

        $this->assertSame(['intro', 'lesson1', 'lesson2'], array_column($scos, 'identifier'));
        $this->assertSame([null, '?page=1', '?page=2'], array_column($scos, 'parameters'));
    }

    public function testTocKeepsContainerItems(): void
    {
        $parser = new ScormManifestParser();
        $parser->parse($this->write(self::SCORM_12_HEADER, '1.2'));

        $toc = $parser->buildToc();

        $this->assertCount(2, $toc);
        $this->assertTrue($toc[0]['sco']);
        $this->assertSame('Chapter 1', $toc[1]['title']);
        $this->assertFalse($toc[1]['sco']);
        $this->assertSame(['lesson1', 'lesson2'], array_column($toc[1]['children'], 'identifier'));
    }

    public function testExtractsSequencingObjectivesAndPassingScore(): void
    {
        $path = $this->write(self::SCORM_2004_HEADER, '2004 4th Edition');
        $xml = str_replace(
            '<item identifier="intro" identifierref="res_intro"><title>Introduction</title></item>',
            '<item identifier="intro" identifierref="res_intro"><title>Introduction</title>'
            . '<imsss:sequencing><imsss:objectives>'
            . '<imsss:primaryObjective objectiveID="PRIMARY" satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.8</imsss:minNormalizedMeasure></imsss:primaryObjective>'
            . '<imsss:objective objectiveID="obj_a"/><imsss:objective objectiveID="obj_b"/>'
            . '</imsss:objectives></imsss:sequencing></item>',
            file_get_contents($path)
        );
        file_put_contents($path, $xml);

        $parser = new ScormManifestParser();
        $parser->parse($path);
        $intro = $parser->extractScos()[0];

        $this->assertSame(['PRIMARY', 'obj_a', 'obj_b'], $intro['objectives']);
        $this->assertSame(0.8, $intro['scaledPassingScore']);
    }

    private function parse(string $header, ?string $schemaVersion): array
    {
        return (new ScormManifestParser())->parse($this->write($header, $schemaVersion));
    }

    private function write(string $header, ?string $schemaVersion): string
    {
        $metadata = $schemaVersion === null
            ? ''
            : "<metadata><schema>ADL SCORM</schema><schemaversion>{$schemaVersion}</schemaversion></metadata>";

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
{$header}
  {$metadata}
  <organizations default="org">
    <organization identifier="org">
      <title>Course</title>
      <item identifier="intro" identifierref="res_intro"><title>Introduction</title></item>
      <item identifier="chapter">
        <title>Chapter 1</title>
        <item identifier="lesson1" identifierref="res_lesson" parameters="?page=1"><title>Lesson 1</title></item>
        <item identifier="lesson2" identifierref="res_lesson" parameters="?page=2"><title>Lesson 2</title></item>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="res_intro" type="webcontent" adlcp:scormtype="sco" href="intro.html"/>
    <resource identifier="res_lesson" type="webcontent" adlcp:scormtype="sco" href="lesson.html"/>
  </resources>
</manifest>
XML;

        $path = tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($path, $xml);
        $this->files[] = $path;

        return $path;
    }
}
