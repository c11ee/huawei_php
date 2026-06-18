<?php

/**
 * 路由同步权限时的固定配置（不从路由解析）
 *
 * modules: 顶级菜单，如「系统配置」
 * resources: 资源 slug => 所属模块 + 显示名；须与路由名中的资源段一致（如 permissions.index → permissions）
 */
return [

    'modules' => [
        'system' => [
            'label' => '系统配置',
            'path' => '/system',
            'icon' => 'ri:settings-3-line',
            'sort' => 100,
        ],
        // 'business' => [
        //     'label' => '业务管理',
        //     'path' => '/business',
        //     'icon' => '',
        //     'sort' => 200,
        // ],
    ],

    'resources' => [
        'permissions' => [
            'label' => '权限管理',
            'module' => 'system',
            'icon' => 'ep:menu',
        ],
        'role' => [
            'label' => '角色管理',
            'module' => 'system',
            'icon' => 'ri:admin-fill',
        ],
        'user' => [
            'label' => '用户管理',
            'module' => 'system',
            'icon' => 'ri:admin-line',
        ],
    ],

];