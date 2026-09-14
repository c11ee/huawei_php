<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class SyncRoutePermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-routes
                            {--guard=sanctum : Spatie guard_name}
                            {--prefix=admin : 只同步 URI 以此前缀开头的路由}
                            {--dry-run : 仅预览，不写入数据库}';

    protected $description = '从路由生成三层权限树：固定模块 → 资源菜单 → 按钮';

    private const ACTION_LABELS = [
        'index'         => '列表',
        'show'          => '详情',
        'store'         => '创建',
        'update'        => '更新',
        'destroy'       => '删除',
        'updateStatus'  => '更新状态',
    ];

    // 入口
    public function handle(): int
    {
        // 参数解析
        $guard = (string) $this->option('guard');
        $prefix = trim((string) $this->option('prefix'), '/');
        $dryRun = (bool) $this->option('dry-run');

        // 获取并过滤路由
        $routes = collect(RouteFacade::getRoutes())
            ->filter(fn(Route $route) => $this->shouldSync($route, $prefix));

        if ($routes->isEmpty()) {
            $this->warn('没有匹配的路由。请检查 --prefix 或路由是否已加载。');

            return self::SUCCESS;
        }

        $modules = $this->moduleRows($guard);
        $groups = $this->buildResourceGroups($routes, $guard);

        $this->printPreview($modules, $groups);

        if ($dryRun) {
            $this->info('dry-run 模式，未写入数据库。去掉 --dry-run 后执行写入。');

            return self::SUCCESS;
        }

        $moduleIds = [];
        foreach ($modules as $row) {
            $moduleIds[$row['_slug']] = Permission::query()->updateOrCreate(
                ['name' => $row['name'], 'guard_name' => $guard],
                collect($row)->except('_slug')->all()
            )->id;
        }

        $total = count($modules);

        foreach ($groups as $group) {
            $moduleId = $moduleIds[$group['module_slug']] ?? null;
            if (! $moduleId) {
                continue;
            }

            // 扁平化：资源名与模块 slug 相同时，跳过中间资源菜单，按钮直接挂在模块下
            $isFlat = ($group['resource']['name'] === $group['module_slug']);

            if ($isFlat) {
                $parentId = $moduleId;
            } else {
                $resourceModel = Permission::query()->updateOrCreate(
                    ['name' => $group['resource']['name'], 'guard_name' => $guard],
                    array_merge($group['resource'], ['parent_id' => $moduleId])
                );
                $parentId = $resourceModel->id;
                $total++;
            }

            foreach ($group['children'] as $child) {
                Permission::query()->updateOrCreate(
                    ['name' => $child['name'], 'guard_name' => $guard],
                    array_merge($child, ['parent_id' => $parentId])
                );
                $total++;
            }
        }

        $this->info(sprintf('已同步 %d 条权限（模块 + 资源菜单 + 按钮，guard: %s）。', $total, $guard));

        return self::SUCCESS;
    }

    /**
     * 模块行数据
     * @return array<int, array<string, mixed>>
     */
    private function moduleRows(string $guard): array
    {
        $rows = [];

        foreach ((array) config('permission_sync.menus', []) as $slug => $menu) {
            // 只取顶级菜单作为模块（parent 为 null）
            if (array_key_exists('parent', $menu) && $menu['parent'] !== null) {
                continue;
            }

            $sort = (int) ($menu['sort'] ?? 0);
            $rows[] = [
                '_slug' => $slug,
                'name' => 'module.' . $slug,
                'guard_name' => $guard,
                'label' => $menu['label'],
                'path' => $menu['path'] ?? '',
                'icon' => $menu['icon'] ?? '',
                'type' => 1,
                'sort' => $sort,
                'remark' => '模块',
                'parent_id' => 0,
            ];
        }

        return $rows;
    }

    /**
     * 构建资源组
     * @return array<int, array{module_slug: string, resource: array<string, mixed>, children: array<int, array<string, mixed>>}>
     */
    private function buildResourceGroups(Collection $routes, string $guard): array
    {
        $menusConfig = (array) config('permission_sync.menus', []);
        $grouped = $routes->groupBy(fn(Route $route) => $this->parseRoute($route)['resource']);
        $groups = [];

        foreach ($grouped as $resource => $resourceRoutes) {
            if (! isset($menusConfig[$resource])) {
                $this->warn("资源 [{$resource}] 未在 config/permission_sync.php 的 menus 中配置，已跳过。");

                continue;
            }

            $cfg = $menusConfig[$resource];

            // 确定模块 slug：如果资源有 parent，则归到 parent 模块；否则自身就是模块
            $moduleSlug = null;
            if (isset($cfg['parent']) && $cfg['parent'] !== null) {
                $moduleSlug = $cfg['parent'];
            } else {
                $moduleSlug = $resource;
            }

            if (! isset($menusConfig[$moduleSlug])) {
                $this->warn("资源 [{$resource}] 的 module [{$moduleSlug}] 在 menus 中未找到，已跳过。");

                continue;
            }

            $moduleSort = (int) ($menusConfig[$moduleSlug]['sort'] ?? 0);
            $indexRoute = $resourceRoutes->first(
                fn(Route $route) => ($this->parseRoute($route)['action'] ?? '') === 'index'
            );

            $modulePath = trim((string) ($menusConfig[$moduleSlug]['path'] ?? ''), '/');

            $resourceRow = [
                'name' => $resource,
                'guard_name' => $guard,
                'label' => $cfg['label'] ?? Str::headline($resource),
                'path' => ($modulePath ? '/' . $modulePath : '') . $this->frontendPath($indexRoute, $resource) . '/index',
                'icon' => $cfg['icon'] ?? '',
                'type' => 1,
                'sort' => $moduleSort * 100 + 10,
                'remark' => '资源菜单',
                'parent_id' => 0,
            ];

            $children = [];
            $actionSort = 0;
            $customLabels = (array) config('permission_sync.labels', []);

            foreach ($resourceRoutes as $route) {
                $parsed = $this->parseRoute($route);
                $actionSort++;
                $permissionKey = $this->permissionKey($route);

                $children[] = [
                    'name' => $permissionKey,
                    'guard_name' => $guard,
                    'label' => $customLabels[$permissionKey] ?? $this->permissionLabel($parsed, $cfg['label'] ?? $resource),
                    'path' => '',
                    'icon' => '',
                    'type' => 2,
                    'sort' => $resourceRow['sort'] + $actionSort,
                    'remark' => implode('|', $route->methods()),
                    'parent_id' => 0,
                ];
            }

            $groups[] = [
                'module_slug' => $moduleSlug,
                'resource' => $resourceRow,
                'children' => $children,
            ];
        }

        return $groups;
    }

    /**
     * 打印预览
     * @param  array<int, array<string, mixed>>  $modules
     * @param  array<int, array{module_slug: string, resource: array<string, mixed>, children: array<int, array<string, mixed>>}>  $groups
     */
    private function printPreview(array $modules, array $groups): void
    {
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module['name'],
                $module['label'],
                '模块',
                '—',
                $module['path'],
            ];
        }

        foreach ($groups as $group) {
            $moduleName = 'module.' . $group['module_slug'];
            $resource = $group['resource'];
            $isFlat = ($resource['name'] === $group['module_slug']);

            if (! $isFlat) {
                $rows[] = [
                    $resource['name'],
                    $resource['label'],
                    '菜单',
                    $moduleName,
                    $resource['path'],
                ];
            }

            foreach ($group['children'] as $child) {
                $rows[] = [
                    $child['name'],
                    $child['label'],
                    '按钮',
                    $isFlat ? $moduleName : $resource['name'],
                    $child['path'] ?: '—',
                ];
            }
        }

        $this->table(['key (name)', 'label', 'type', 'parent', 'path'], $rows);
    }

    /** 前端路由 path：去 api 前缀、去 v1/v2 等版本段、去 admin 前缀 */
    private function frontendPath(?Route $indexRoute, string $resource): string
    {
        if (! $indexRoute) {
            return '/' . $resource;
        }

        $segments = collect(explode('/', $indexRoute->uri()))
            ->reject(fn(string $segment) => $segment === '' || $segment === 'api' || $segment === 'admin')
            ->reject(fn(string $segment) => $this->isApiVersionSegment($segment))
            ->reject(fn(string $segment) => str_starts_with($segment, '{'))
            ->values();

        return '/' . $segments->implode('/');
    }

    /**
     * 是否是 API 版本段
     */
    private function isApiVersionSegment(string $segment): bool
    {
        return (bool) preg_match('/^v\d+$/i', $segment);
    }

    /**
     * 解析路由
     * @return array{resource: string, action: string}
     */
    private function parseRoute(Route $route): array
    {
        $name = $route->getName();

        if ($name && str_contains($name, '.')) {
            [$resource, $action] = explode('.', $name, 2);

            // 将文件夹路由映射到附件管理
            if ($resource === 'folder') {
                $resource = 'attachment';
            }

            return ['resource' => $resource, 'action' => $action];
        }

        $segments = collect(explode('/', $route->uri()))
            ->reject(fn(string $segment) => $segment === '' || $segment === 'api')
            ->reject(fn(string $segment) => $this->isApiVersionSegment($segment))
            ->reject(fn(string $segment) => str_starts_with($segment, '{'))
            ->values();

        $resource = $segments->last() ?? 'unknown';
        if ($resource === 'folder') {
            $resource = 'attachment';
        }

        return [
            'resource' => $resource,
            'action' => strtolower($route->methods()[0] ?? 'get'),
        ];
    }

    /**
     * 是否同步路由
     */
    private function shouldSync(Route $route, string $prefix): bool
    {
        if ($route->getActionName() === 'Closure') {
            return false;
        }

        $uri = $route->uri();

        if ($prefix !== '' && ! Str::startsWith($uri, $prefix)) {
            return false;
        }

        if (in_array($uri, ['up', 'storage/{path}'], true)) {
            return false;
        }

        // 排除 common 前缀下的公共路由（如上传、用户偏好等）
        if (Str::contains($uri, '/common/') || Str::startsWith($uri, 'common/')) {
            return false;
        }

        return true;
    }

    /**
     * 权限key
     */
    private function permissionKey(Route $route): string
    {
        if ($name = $route->getName()) {
            return $name;
        }

        $parsed = $this->parseRoute($route);

        return $parsed['resource'] . '.' . $parsed['action'];
    }

    /**
     * @param  array{resource: string, action: string}  $parsed
     */
    private function permissionLabel(array $parsed, string $resourceLabel): string
    {
        $actionLabel = self::ACTION_LABELS[$parsed['action']] ?? $parsed['action'];

        return $resourceLabel . $actionLabel;
    }
}