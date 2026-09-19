<?php

/**
 * 路由同步权限时的固定配置（不从路由解析）
 */
return [
    'menus' => [
        'product' => [
            'label' => '商品管理',
            'path' => '/product',
            'icon' => 'ep:goods',
            'sort' => 90,
            'parent' => null,
        ],
        'brand' => [
            'label' => '品牌管理',
            'icon' => 'ant-design:trademark-outlined',
            'sort' => 95,
            'parent' => 'product',
        ],
        'category' => [
            'label' => '分类管理',
            'icon' => 'ri:apps-2-line',
            'sort' => 100,
            'parent' => 'product',
        ],
        'spec-template' => [
            'label' => '商品规格',
            'icon' => 'ri:stack-line',
            'sort' => 105,
            'parent' => 'product',
        ],
        'product-list' => [
            'label' => '商品列表',
            'icon' => 'ri:shopping-bag-3-line',
            'sort' => 110,
            // 商品列表路由前缀为 product，匹配 product.index 权限
            'prefix' => 'product',
            'parent' => 'product',
        ],

        'attachment' => [
            'label' => '附件管理',
            'path' => '/attachment',
            'icon' => 'ri:folder-2-line',
            'sort' => 95,
            'parent' => null,
        ],

        'system' => [
            'label' => '系统配置',
            'path' => '/system',
            'icon' => 'ri:settings-3-line',
            'sort' => 100,
            'parent' => null,
        ],
        'permissions' => [
            'label' => '权限管理',
            'icon' => 'ep:menu',
            'sort' => 110,
            'parent' => 'system',
        ],
        'role' => [
            'label' => '角色管理',
            'module' => 'system',
            'icon' => 'ri:admin-fill',
            'sort' => 120,
            'parent' => 'system',
        ],
        'user' => [
            'label' => '用户管理',
            'module' => 'system',
            'icon' => 'ri:admin-line',
            'sort' => 130,
            'parent' => 'system',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自定义权限按钮标签
    |--------------------------------------------------------------------------
    | key 为路由 name（权限 key），value 为显示标签。
    | 未配置的按钮会自动根据「资源 label + 动作」生成。
    */
    'labels' => [
        'folder.tree'   => '文件树',
        'folder.store'  => '文件添加',
        'folder.destroy'  => '文件删除',
        'attachment.restore'  => '文件还原',
        'user.permissions'  => '用户权限',
        'attachment.updateFolderId'  => '批量修改文件夹绑定',
        'product.index'  => '商品列表',
    ],
];
