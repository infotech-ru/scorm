<?php

use yii\helpers\Html;

$this->title = 'SCORM LMS - Home';
?>

<div class="text-center py-5">
    <h1>Welcome to SCORM LMS</h1>
    <p class="lead">A minimal SCORM 1.2 & 2004 Learning Management System</p>
    <hr>
    <p>
        <a href="/scorm/upload" class="btn btn-primary btn-lg">Upload SCORM Package</a>
        <a href="/scorm" class="btn btn-outline-primary btn-lg">View Packages</a>
    </p>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Upload</h5>
                <p class="card-text">Upload SCORM 1.2 or 2004 packages in ZIP format.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Play</h5>
                <p class="card-text">Launch SCORM content in an isolated player.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Track</h5>
                <p class="card-text">View learner progress and scores.</p>
            </div>
        </div>
    </div>
</div>
