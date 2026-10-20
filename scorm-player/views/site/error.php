<?php

use yii\helpers\Html;

$this->title = $name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::encode($this->title) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            text-align: center;
            padding: 40px;
        }
        h1 {
            font-size: 72px;
            margin: 0;
            color: #e94560;
        }
        h2 {
            font-size: 24px;
            margin: 20px 0;
            color: #7ec8e3;
        }
        p {
            color: #aaa;
            margin: 20px 0;
        }
        .back-link {
            color: #7ec8e3;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1><?= Html::encode($exception->statusCode ?? 500) ?></h1>
        <h2><?= Html::encode($name) ?></h2>
        <p><?= nl2br(Html::encode($message)) ?></p>
    </div>
</body>
</html>
