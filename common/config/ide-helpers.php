<?php

/**
 * Этот файл не для исполнения. Он не подключается в других файлах, а нужен
 * лишь для того, чтобы IDE делала правильные подсказки при написании кода.
 *
 * Для избавления от предупреждения PhpStorm "Multiple Implementations"
 * нужно пометить исходный файл vendor/yiisoft/yii2/Yii.php как Plain Text
 */

namespace {

    use common\components\SelectelStorageS3;
    use common\models\ScormClient;
    use yii\BaseYii;
    use yii\caching\FileCache;
    use yii\db\Connection;
    use yii\queue\db\Queue;
    use yii\web\User;

    require __DIR__ . '/../../vendor/yiisoft/yii2/BaseYii.php';

    class Yii extends BaseYii
    {
        /**
         * @var WebApplication|ConsoleApplication
         */
        public static $app;
    }

    /**
     * @property-read ScormClient|null $identity
     */
    class WebUser extends User
    {
    }

    /**
     * @property-read SelectelStorageS3 $storage
     * @property-read Queue $queue
     * @property-read Connection $db
     * @property-read FileCache $cache
     */
    trait BaseApplication
    {
    }

    /**
     * @property-read WebUser $user
     */
    class WebApplication extends yii\web\Application
    {
        use BaseApplication;
    }

    class ConsoleApplication extends yii\console\Application
    {
        use BaseApplication;
    }
}
