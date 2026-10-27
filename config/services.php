<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID'),
        'pid' => env('ALIPAY_PID'), // 沙箱控制台的 PID
        'private_key' => env('ALIPAY_PRIVATE_KEY'), // 你的应用私钥
        'alipay_public_key' => env('ALIPAY_PUBLIC_KEY'), // 支付宝公钥（沙箱的）
        'gateway' => env('ALIPAY_GATEWAY_URL'), // 支付宝网关地址
    ],

    'aliyun' => [
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET'),
        'address_purification_project_id' => env('ALIYUN_ADDRESS_PURIFICATION_PROJECT_ID'),
        // local 环境使用的固定验证码，配置后不再真实发送短信
        'test_code' => env('ALIYUN_DYPNS_TEST_CODE'),
    ],

    'amap' => [
        'key' => env('AMAP_KEY'),
        'url' => env('AMAP_API_URL', 'https://restapi.amap.com'),
    ],
];
