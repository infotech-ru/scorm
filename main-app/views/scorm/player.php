<?php

use yii\helpers\Html;
use yii\helpers\Json;
use common\models\ScormAttempt;

$this->title = 'Playing: ' . $package->title;
$hasToc = $scoCount > 1;
$scoUrl = fn($s) => '/scorm/player/' . $package->id . '?sco=' . $s->id;
$scoTitle = fn($s) => $s->title ?? $s->identifier;
$statuses = ['passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted', 'unknown'];
$currentStatus = $attempt->getDisplayStatus();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= Html::encode($package->title) ?></h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-info">Attempt #<?= $attempt->attempt_number ?></span>
        <?php if ($review): ?>
            <span class="badge bg-secondary">Review</span>
            <a href="<?= $scoUrl($sco) ?>&amp;new=1" class="btn btn-primary btn-sm">Start new attempt</a>
        <?php endif; ?>
        <span class="badge bg-<?= ScormAttempt::statusColor($currentStatus) ?>" id="status-badge"><?= Html::encode($currentStatus) ?></span>
        <?php if ($hasToc): ?>
            <a href="<?= $prevSco ? $scoUrl($prevSco) : '#' ?>"
               class="btn btn-outline-primary btn-sm<?= $prevSco ? '' : ' disabled' ?>">&larr; Previous</a>
            <a href="<?= $nextSco ? $scoUrl($nextSco) : '#' ?>" id="next-sco"
               class="btn btn-outline-primary btn-sm<?= $nextSco ? '' : ' disabled' ?>">Next &rarr;</a>
        <?php endif; ?>
        <a href="/scorm/view/<?= $package->id ?>" class="btn btn-outline-secondary btn-sm">Close</a>
    </div>
</div>

<div class="row g-3">
    <?php if ($hasToc): ?>
        <div class="col-md-3">
            <div class="list-group scorm-toc">
                <?php foreach ($tocRows as $row): ?>
                    <?php $s = $row['sco']; ?>
                    <?php if (!$s): ?>
                        <div class="list-group-item bg-light fw-semibold small text-uppercase text-muted"
                             style="padding-left: <?= 1 + $row['depth'] * 1.25 ?>rem;">
                            <?= Html::encode($row['title']) ?>
                        </div>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php
                    $isCurrent = $s->id === $sco->id;
                    $status = isset($scoAttempts[$s->id]) ? $scoAttempts[$s->id]->getDisplayStatus() : 'not attempted';
                    ?>
                    <a href="<?= $scoUrl($s) ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $isCurrent ? ' active' : '' ?>"
                       style="padding-left: <?= 1 + $row['depth'] * 1.25 ?>rem;">
                        <span class="text-truncate me-2"><?= Html::encode($row['title']) ?></span>
                        <span class="badge bg-<?= ScormAttempt::statusColor($status) ?>"
                              <?= $isCurrent ? 'id="toc-current-status"' : '' ?>><?= Html::encode($status) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= $hasToc ? 'col-md-9' : 'col-12' ?>">
        <iframe
            id="scorm-player"
            class="scorm-player-frame"
            src="<?= Html::encode($launchUrl) ?>"
            allow="fullscreen; autoplay"
            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-downloads allow-modals"
        ></iframe>

        <div id="sco-finished" class="alert alert-success mt-3 d-none">
            Module finished.
            <?php if ($nextSco): ?>
                <a href="<?= $scoUrl($nextSco) ?>" class="alert-link">Continue to &laquo;<?= Html::encode($scoTitle($nextSco)) ?>&raquo; &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="mt-3">
            <small class="text-muted">
                SCO: <?= Html::encode($scoTitle($sco)) ?> |
                SCORM <?= Html::encode($package->version) ?>
            </small>
        </div>
    </div>
</div>

<script>
(function() {
    var playerOrigin = <?= Json::encode(Yii::$app->params['scormPlayerUrl']) ?>;
    var frame = document.getElementById('scorm-player');
    var colors = <?= Json::encode(array_map([ScormAttempt::class, 'statusColor'], array_combine($statuses, $statuses))) ?>;

    function setStatus(el, status) {
        if (!el) {
            return;
        }
        el.textContent = status;
        el.className = el.className.replace(/\bbg-\w+/, 'bg-' + (colors[status] || 'secondary'));
    }

    window.addEventListener('message', function(event) {
        if (event.origin !== playerOrigin || event.source !== frame.contentWindow || !event.data) {
            return;
        }

        if (event.data.type === 'scorm-status' && event.data.status) {
            setStatus(document.getElementById('status-badge'), event.data.status);
            setStatus(document.getElementById('toc-current-status'), event.data.status);
        }

        if (event.data.type === 'scorm-terminate') {
            document.getElementById('sco-finished').classList.remove('d-none');
            var next = document.getElementById('next-sco');
            if (next) {
                next.classList.replace('btn-outline-primary', 'btn-primary');
            }
        }
    });
})();
</script>
