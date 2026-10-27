<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAreaCommand extends Command
{
    protected $signature = 'area:import
        {--force : 表内已有数据时不再询问，直接清空后重新导入}
        {--path= : JSON 数据文件路径，默认 database/data/area.json}';

    protected $description = '批量导入行政区划数据（省/市/区/街道）';

    /**
     * 按层级归类的节点，下标即 level（0 省 1 市 2 区 3 街道）
     *
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $levels = [[], [], [], []];

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/area.json');

        if (! is_file($path)) {
            $this->error(sprintf('数据文件不存在：%s', $path));

            return self::FAILURE;
        }

        if (DB::table('sys_area')->exists() && ! $this->option('force')) {
            if (! $this->confirm('sys_area 表已有数据，是否清空后重新导入？', false)) {
                $this->warn('已取消，未做任何修改。');

                return self::SUCCESS;
            }
        }

        // 源文件带 UTF-8 BOM，需先去除，否则 json_decode 会失败
        $json = ltrim(file_get_contents($path), "\xEF\xBB\xBF");
        $data = json_decode($json, true);

        if (! is_array($data)) {
            $this->error(sprintf('数据解析失败：%s', json_last_error_msg()));

            return self::FAILURE;
        }

        $this->groupByLevel($data);

        // TRUNCATE 会隐式提交事务，必须在 transaction 外执行
        DB::table('sys_area')->truncate();

        $now = now();
        // JSON 中的 id => 数据库自增 id
        $idMap = [];

        DB::transaction(function () use ($now, &$idMap) {
            foreach ($this->levels as $level => $nodes) {
                $rows = [];

                foreach ($nodes as $node) {
                    $rows[] = [
                        // 顶级节点的 parent_id 固定为 0，其余取父级插入后的新 id
                        'parent_id' => $level === 0 ? 0 : $idMap[$node['pid']],
                        'level' => $level,
                        'name' => $node['name'],
                        'ext_name' => $node['ext_name'],
                        'ext_id' => $node['ext_id'],
                        'pinyin' => $node['pinyin'],
                        'pinyin_prefix' => $node['pinyin_prefix'],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 数据量大，按 1000 条一批插入
                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table('sys_area')->insert($chunk);
                }

                // 回查本层新生成的自增 id，供下一层拼接 parent_id 使用；
                // 最后一层（街道）没有子节点，无需回查
                if ($level < count($this->levels) - 1) {
                    $inserted = DB::table('sys_area')
                        ->whereIn('ext_id', array_column($nodes, 'ext_id'))
                        ->pluck('id', 'ext_id');

                    foreach ($nodes as $node) {
                        $idMap[$node['id']] = $inserted[$node['ext_id']];
                    }
                }
            }
        });

        $this->info(sprintf(
            '行政区划导入完成：共 %d 条（省 %d / 市 %d / 区 %d / 街道 %d）',
            array_sum(array_map('count', $this->levels)),
            count($this->levels[0]),
            count($this->levels[1]),
            count($this->levels[2]),
            count($this->levels[3]),
        ));

        return self::SUCCESS;
    }

    /**
     * 递归按层级收集节点
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function groupByLevel(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->levels[$node['deep']][] = $node;

            if (! empty($node['childs'])) {
                $this->groupByLevel($node['childs']);
            }
        }
    }
}
