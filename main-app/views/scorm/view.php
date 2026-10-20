<?php

use yii\helpers\Html;
use common\models\ScormAttempt;

$this->title = $package->title;
?>

<h1><?= Html::encode($package->title) ?></h1>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">Package Details</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 200px;">ID</th>
                        <td><?= $package->id ?></td>
                    </tr>
                    <tr>
                        <th>Identifier</th>
                        <td><?= Html::encode($package->identifier ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>SCORM Version</th>
                        <td>
                            <span class="badge bg-<?= $package->version === '2004' ? 'info' : 'secondary' ?>">
                                SCORM <?= Html::encode($package->version) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $package->getStatusColor() ?>"><?= Html::encode($package->status) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Original File</th>
                        <td><?= Html::encode($package->original_filename) ?></td>
                    </tr>
                    <tr>
                        <th>Launch URL</th>
                        <td><code><?= Html::encode($package->launch_url ?? 'N/A') ?></code></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= Yii::$app->formatter->asDatetime($package->created_at) ?></td>
                    </tr>
                </table>

                <?php if ($package->error_message): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?= Html::encode($package->error_message) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">SCOs (Sharable Content Objects)</div>
            <div class="card-body">
                <?php if (empty($tocRows)): ?>
                    <p class="text-muted">No SCOs found in this package.</p>
                <?php else: ?>
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Mastery Score</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tocRows as $row): ?>
                                <?php $sco = $row['sco']; ?>
                                <?php if (!$sco): ?>
                                    <tr class="table-light">
                                        <td colspan="5" class="fw-semibold" style="padding-left: <?= 0.5 + $row['depth'] * 1.5 ?>rem;">
                                            <?= Html::encode($row['title']) ?>
                                        </td>
                                    </tr>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php $status = isset($scoAttempts[$sco->id]) ? $scoAttempts[$sco->id]->getDisplayStatus() : 'not attempted'; ?>
                                <tr>
                                    <td style="padding-left: <?= 0.5 + $row['depth'] * 1.5 ?>rem;">
                                        <?= Html::encode($row['title']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $sco->scorm_type === 'sco' ? 'primary' : 'secondary' ?>">
                                            <?= strtoupper($sco->scorm_type) ?>
                                        </span>
                                    </td>
                                    <td><?= $sco->mastery_score ?? '-' ?></td>
                                    <td>
                                        <span class="badge bg-<?= ScormAttempt::statusColor($status) ?>"><?= Html::encode($status) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($package->isReady()): ?>
                                            <a href="/scorm/player/<?= $package->id ?>?sco=<?= $sco->id ?>" class="btn btn-sm btn-outline-success">Launch</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recent Attempts</div>
            <div class="card-body">
                <?php if (empty($attempts)): ?>
                    <p class="text-muted">No attempts yet.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>SCO</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td>User <?= $attempt->user_id ?></td>
                                    <td><?= Html::encode($attempt->sco->title ?? $attempt->sco->identifier) ?></td>
                                    <td>
                                        <?php $status = $attempt->getDisplayStatus(); ?>
                                        <span class="badge bg-<?= ScormAttempt::statusColor($status) ?>"><?= Html::encode($status) ?></span>
                                    </td>
                                    <td><?= Html::encode($attempt->score_raw ?? '-') ?></td>
                                    <td><?= Yii::$app->formatter->asDatetime($attempt->started_at, 'short') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($package->isReady()): ?>
                    <a href="/scorm/player/<?= $package->id ?>" class="btn btn-success btn-lg">
                        Launch Player
                    </a>
                <?php endif; ?>
                <a href="/scorm" class="btn btn-outline-secondary">Back to List</a>
                <?= Html::beginForm(['scorm/delete', 'id' => $package->id], 'post', ['class' => 'd-grid', 'onsubmit' => 'return confirm("Delete this package?")']) ?>
                    <button type="submit" class="btn btn-outline-danger">Delete Package</button>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>
