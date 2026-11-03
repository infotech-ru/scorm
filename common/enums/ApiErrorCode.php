<?php

namespace common\enums;

enum ApiErrorCode: string
{
    case InvalidRequest = 'invalid_request';
    case RateLimited = 'rate_limited';
    case TooManyProcessing = 'too_many_processing';
    case DownloadFailed = 'download_failed';
    case TooLarge = 'too_large';
    case InvalidZip = 'invalid_zip';
    case ManifestMissing = 'manifest_missing';
    case UnsupportedVersion = 'unsupported_version';
    case NoLaunchResource = 'no_launch_resource';
    case MultipleSco = 'multiple_sco';
}
