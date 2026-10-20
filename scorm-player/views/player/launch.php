<?php

use yii\helpers\Html;
use yii\helpers\Json;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::encode($package->title) ?></title>
    <link rel="stylesheet" href="/css/player.css">
</head>
<body>
    <iframe id="content-frame" title="<?= Html::encode($package->title) ?>" allow="fullscreen; autoplay"
            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-downloads allow-modals"></iframe>
    <div id="error" hidden></div>
    <script type="application/json" id="scorm-config"><?= Json::htmlEncode($config) ?></script>
    <script src="/js/scorm-bridge.js"></script>
</body>
</html>
