<?php

use yii\helpers\Html;

$this->title = 'Upload SCORM Package';
?>

<h1>Upload SCORM Package</h1>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="/scorm/upload" method="post" enctype="multipart/form-data">
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

                    <div class="mb-3">
                        <label for="scorm_file" class="form-label">SCORM Package (ZIP file)</label>
                        <input type="file"
                               class="form-control"
                               id="scorm_file"
                               name="scorm_file"
                               accept=".zip"
                               required>
                        <div class="form-text">
                            Upload a valid SCORM 1.2 or SCORM 2004 package in ZIP format.
                            Maximum size: <?= round(Yii::$app->params['maxPackageSize'] / 1024 / 1024) ?>MB
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload</button>
                    <a href="/scorm" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Requirements</div>
            <div class="card-body">
                <ul>
                    <li>ZIP file containing SCORM content</li>
                    <li>Must include <code>imsmanifest.xml</code> in the root</li>
                    <li>SCORM 1.2 or SCORM 2004 compliant</li>
                </ul>
                <p class="text-muted">
                    You can download sample SCORM packages from
                    <a href="https://scorm.com/scorm-explained/technical-scorm/golf-examples/" target="_blank">scorm.com</a>
                </p>
            </div>
        </div>
    </div>
</div>
