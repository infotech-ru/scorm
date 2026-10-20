<?php

namespace common\services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class ScormManifestParser
{
    private DOMDocument $dom;
    private DOMXPath $xpath;
    private string $scormVersion;
    private array $namespaces = [];

    public function parse(string $manifestPath): array
    {
        if (!file_exists($manifestPath)) {
            throw new RuntimeException("Manifest file not found: {$manifestPath}");
        }

        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = false;

        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $this->dom->load($manifestPath);
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            throw new RuntimeException("Failed to parse manifest XML");
        }

        $this->xpath = new DOMXPath($this->dom);
        $this->detectNamespacesAndVersion();
        $this->registerNamespaces();

        return [
            'version' => $this->scormVersion,
            'identifier' => $this->getManifestIdentifier(),
            'title' => $this->getTitle(),
            'metadata' => $this->getMetadata(),
            'organizations' => $this->getOrganizations(),
            'resources' => $this->getResources(),
            'defaultOrganization' => $this->getDefaultOrganization(),
            'launchData' => $this->getLaunchData(),
        ];
    }

    private function detectNamespacesAndVersion(): void
    {
        $root = $this->dom->documentElement;

        $this->namespaces = [];
        foreach ($this->xpath->query('namespace::*', $root) as $ns) {
            if ($ns->prefix === 'xml') {
                continue;
            }
            $this->namespaces[$ns->prefix ?: 'imscp'] = $ns->nodeValue;
        }

        $schemaVersion = null;
        foreach ($this->dom->getElementsByTagName('schemaversion') as $node) {
            $schemaVersion = trim($node->textContent);
            break;
        }

        if ($schemaVersion !== null && $schemaVersion !== '') {
            $isScorm2004 = stripos($schemaVersion, '2004') !== false
                || stripos($schemaVersion, 'CAM 1.3') !== false
                || $schemaVersion === '1.3';
            $this->scormVersion = $isScorm2004 ? '2004' : '1.2';
            return;
        }

        $this->scormVersion = '1.2';

        foreach ($this->namespaces as $uri) {
            if (str_contains($uri, 'adlcp_v1p3')
                || str_contains($uri, 'imsss')
                || str_contains($uri, 'adlseq')
                || str_contains($uri, 'adlnav')) {
                $this->scormVersion = '2004';
                break;
            }
        }
    }

    private function registerNamespaces(): void
    {
        if (!isset($this->namespaces['imscp'])) {
            $this->namespaces['imscp'] = 'http://www.imsproject.org/xsd/imscp_rootv1p1p2';
        }

        foreach ($this->namespaces as $prefix => $uri) {
            $this->xpath->registerNamespace($prefix, $uri);
        }

        $commonNs = [
            'adlcp' => $this->scormVersion === '2004'
                ? 'http://www.adlnet.org/xsd/adlcp_v1p3'
                : 'http://www.adlnet.org/xsd/adlcp_rootv1p2',
            'imsmd' => 'http://www.imsglobal.org/xsd/imsmd_rootv1p2p1',
        ];

        foreach ($commonNs as $prefix => $uri) {
            if (!isset($this->namespaces[$prefix])) {
                $this->xpath->registerNamespace($prefix, $uri);
            }
        }
    }

    private function getManifestIdentifier(): ?string
    {
        $root = $this->dom->documentElement;
        return $root->getAttribute('identifier') ?: null;
    }

    private function getTitle(): ?string
    {
        $queries = [
            '//imscp:organization/imscp:title',
            '//organization/title',
            '//imscp:organizations/imscp:organization/imscp:title',
            '//organizations/organization/title',
        ];

        foreach ($queries as $query) {
            $nodes = $this->xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        }

        $title = $this->dom->getElementsByTagName('title')->item(0);
        return $title ? trim($title->textContent) : null;
    }

    private function getMetadata(): array
    {
        $metadata = [];

        $queries = [
            'schema' => ['//imscp:metadata/imscp:schema', '//metadata/schema'],
            'schemaversion' => ['//imscp:metadata/imscp:schemaversion', '//metadata/schemaversion'],
        ];

        foreach ($queries as $key => $xpaths) {
            foreach ($xpaths as $xpath) {
                $nodes = $this->xpath->query($xpath);
                if ($nodes && $nodes->length > 0) {
                    $metadata[$key] = trim($nodes->item(0)->textContent);
                    break;
                }
            }
        }

        return $metadata;
    }

    private function getOrganizations(): array
    {
        $organizations = [];

        $orgNodes = $this->dom->getElementsByTagName('organization');

        foreach ($orgNodes as $org) {
            $orgId = $org->getAttribute('identifier');
            $orgData = [
                'identifier' => $orgId,
                'title' => null,
                'items' => [],
            ];

            foreach ($org->childNodes as $child) {
                if ($child->nodeName === 'title') {
                    $orgData['title'] = trim($child->textContent);
                    break;
                }
            }

            $orgData['items'] = $this->parseItems($org);

            $organizations[$orgId] = $orgData;
        }

        return $organizations;
    }

    private function parseItems($parent, int $level = 0): array
    {
        $items = [];
        $sortOrder = 0;

        foreach ($parent->childNodes as $child) {
            if ($child->nodeName !== 'item') {
                continue;
            }

            $item = [
                'identifier' => $child->getAttribute('identifier'),
                'identifierref' => $child->getAttribute('identifierref') ?: null,
                'isvisible' => $child->getAttribute('isvisible') !== 'false',
                'parameters' => $child->getAttribute('parameters') ?: null,
                'title' => null,
                'prerequisites' => null,
                'masteryScore' => null,
                'maxTimeAllowed' => null,
                'timeLimitAction' => null,
                'dataFromLMS' => null,
                'objectives' => [],
                'scaledPassingScore' => null,
                'sortOrder' => $sortOrder++,
                'level' => $level,
                'children' => [],
            ];

            foreach ($child->childNodes as $itemChild) {
                switch ($itemChild->nodeName) {
                    case 'title':
                        $item['title'] = trim($itemChild->textContent);
                        break;
                    case 'adlcp:prerequisites':
                    case 'prerequisites':
                        $item['prerequisites'] = trim($itemChild->textContent);
                        break;
                    case 'adlcp:masteryscore':
                    case 'masteryscore':
                        $item['masteryScore'] = (float)trim($itemChild->textContent);
                        break;
                    case 'adlcp:maxtimeallowed':
                    case 'maxtimeallowed':
                        $item['maxTimeAllowed'] = trim($itemChild->textContent);
                        break;
                    case 'adlcp:timelimitaction':
                    case 'timelimitaction':
                        $item['timeLimitAction'] = trim($itemChild->textContent);
                        break;
                    case 'adlcp:datafromlms':
                    case 'datafromlms':
                        $item['dataFromLMS'] = trim($itemChild->textContent);
                        break;
                }
            }

            $item = array_merge($item, $this->parseSequencing($child));
            $item['children'] = $this->parseItems($child, $level + 1);

            $items[] = $item;
        }

        return $items;
    }

    private function parseSequencing(DOMElement $item): array
    {
        $result = ['objectives' => [], 'scaledPassingScore' => null];

        foreach ($item->childNodes as $sequencing) {
            if (!$sequencing instanceof DOMElement || $sequencing->localName !== 'sequencing') {
                continue;
            }
            foreach ($sequencing->getElementsByTagNameNS('*', '*') as $node) {
                if (!in_array($node->localName, ['primaryObjective', 'objective'], true)) {
                    continue;
                }

                $id = trim($node->getAttribute('objectiveID'));
                if ($id !== '' && !in_array($id, $result['objectives'], true)) {
                    $result['objectives'][] = $id;
                }

                if ($node->localName === 'primaryObjective' && $node->getAttribute('satisfiedByMeasure') === 'true') {
                    $measure = $node->getElementsByTagNameNS('*', 'minNormalizedMeasure')->item(0);
                    $result['scaledPassingScore'] = $measure ? (float)trim($measure->textContent) : 1.0;
                }
            }
        }

        return $result;
    }

    private function getResources(): array
    {
        $resources = [];

        $resNodes = $this->dom->getElementsByTagName('resource');

        foreach ($resNodes as $res) {
            $resId = $res->getAttribute('identifier');
            $resources[$resId] = [
                'identifier' => $resId,
                'type' => $res->getAttribute('type') ?: null,
                'scormType' => $res->getAttributeNS('*', 'scormtype')
                    ?: $res->getAttribute('adlcp:scormtype')
                    ?: $res->getAttribute('scormtype')
                    ?: 'sco',
                'href' => $res->getAttribute('href') ?: null,
                'files' => [],
                'dependencies' => [],
            ];

            foreach ($res->childNodes as $child) {
                if ($child->nodeName === 'file') {
                    $resources[$resId]['files'][] = $child->getAttribute('href');
                } elseif ($child->nodeName === 'dependency') {
                    $resources[$resId]['dependencies'][] = $child->getAttribute('identifierref');
                }
            }
        }

        return $resources;
    }

    private function getDefaultOrganization(): ?string
    {
        $orgs = $this->dom->getElementsByTagName('organizations')->item(0);
        return $orgs ? ($orgs->getAttribute('default') ?: null) : null;
    }

    private function getLaunchData(): ?array
    {
        $organizations = $this->getOrganizations();
        $resources = $this->getResources();
        $defaultOrg = $this->getDefaultOrganization();

        $org = isset($organizations[$defaultOrg])
            ? $organizations[$defaultOrg]
            : reset($organizations);

        if (!$org || empty($org['items'])) {
            return null;
        }

        return $this->findFirstLaunchableItem($org['items'], $resources);
    }

    private function findFirstLaunchableItem(array $items, array $resources): ?array
    {
        foreach ($items as $item) {
            if ($item['identifierref'] && isset($resources[$item['identifierref']])) {
                $resource = $resources[$item['identifierref']];
                if ($resource['href']) {
                    return [
                        'item' => $item,
                        'resource' => $resource,
                        'launchUrl' => $resource['href'],
                    ];
                }
            }

            if (!empty($item['children'])) {
                $result = $this->findFirstLaunchableItem($item['children'], $resources);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    public function getVersion(): string
    {
        return $this->scormVersion;
    }

    public function extractScos(): array
    {
        $organizations = $this->getOrganizations();
        $resources = $this->getResources();
        $scos = [];

        foreach ($organizations as $org) {
            $this->collectScos($org['items'], $resources, $scos);
        }

        return $scos;
    }

    public function buildToc(): array
    {
        $organizations = $this->getOrganizations();
        $resources = $this->getResources();

        if (count($organizations) === 1) {
            return $this->buildTocNodes(reset($organizations)['items'], $resources);
        }

        $toc = [];
        foreach ($organizations as $org) {
            $children = $this->buildTocNodes($org['items'], $resources);
            if ($children) {
                $toc[] = [
                    'identifier' => $org['identifier'],
                    'title' => $org['title'] ?? $org['identifier'],
                    'sco' => false,
                    'children' => $children,
                ];
            }
        }

        return $toc;
    }

    private function buildTocNodes(array $items, array $resources): array
    {
        $nodes = [];

        foreach ($items as $item) {
            $isSco = $item['identifierref']
                && isset($resources[$item['identifierref']])
                && $resources[$item['identifierref']]['href'];
            $children = $this->buildTocNodes($item['children'], $resources);

            if (!$isSco && !$children) {
                continue;
            }

            $nodes[] = [
                'identifier' => $item['identifier'],
                'title' => $item['title'] ?? $item['identifier'],
                'sco' => (bool)$isSco,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    private function collectScos(array $items, array $resources, array &$scos, ?int $parentIndex = null): void
    {
        foreach ($items as $item) {
            if (!$item['identifierref'] || !isset($resources[$item['identifierref']])) {
                if (!empty($item['children'])) {
                    $this->collectScos($item['children'], $resources, $scos, $parentIndex);
                }
                continue;
            }

            $resource = $resources[$item['identifierref']];
            if (!$resource['href']) {
                continue;
            }

            $scoIndex = count($scos);
            $scos[] = [
                'identifier' => $item['identifier'],
                'title' => $item['title'],
                'launchUrl' => $resource['href'],
                'scormType' => strtolower($resource['scormType']) === 'asset' ? 'asset' : 'sco',
                'parameters' => $item['parameters'],
                'prerequisites' => $item['prerequisites'],
                'masteryScore' => $item['masteryScore'],
                'maxTimeAllowed' => $item['maxTimeAllowed'],
                'timeLimitAction' => $item['timeLimitAction'],
                'objectives' => $item['objectives'],
                'scaledPassingScore' => $item['scaledPassingScore'],
                'sortOrder' => $item['sortOrder'],
                'parentIndex' => $parentIndex,
            ];

            if (!empty($item['children'])) {
                $this->collectScos($item['children'], $resources, $scos, $scoIndex);
            }
        }
    }
}
