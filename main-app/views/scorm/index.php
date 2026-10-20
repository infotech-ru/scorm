<?php

use yii\helpers\Html;

$this->title = 'SCORM Packages';
?>

<h1>SCORM Packages</h1>

<p>
    <a href="/scorm/upload" class="btn btn-primary">Upload New Package</a>
</p>

<?php if (empty($packages)): ?>
    <div class="alert alert-info">
        No packages uploaded yet. <a href="/scorm/upload">Upload your first SCORM package</a>.
    </div>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Version</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><?= $package->id ?></td>
                    <td><?= Html::encode($package->title) ?></td>
                    <td>
                        <span class="badge bg-<?= $package->version === '2004' ? 'info' : 'secondary' ?>">
                            SCORM <?= Html::encode($package->version) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $package->getStatusColor() ?>"><?= Html::encode($package->status) ?></span>
                    </td>
                    <td><?= Yii::$app->formatter->asDatetime($package->created_at, 'short') ?></td>
                    <td>
                        <a href="/scorm/view/<?= $package->id ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if ($package->isReady()): ?>
                            <a href="/scorm/player/<?= $package->id ?>" class="btn btn-sm btn-success">Play</a>
                        <?php endif; ?>
                        <?= Html::beginForm(['scorm/delete', 'id' => $package->id], 'post', ['class' => 'd-inline', 'onsubmit' => 'return confirm("Delete this package?")']) ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        <?= Html::endForm() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
