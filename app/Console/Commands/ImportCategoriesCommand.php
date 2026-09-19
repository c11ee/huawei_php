<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCategoriesCommand extends Command
{
    protected $signature = 'category:import {--force : 表内已有数据时不再询问，直接清空后重新导入}';

    protected $description = '批量导入分类数据（id 自增生成，parent_id 按层级重新映射）';

    public function handle(): int
    {
        if (DB::table('product_category')->exists() && ! $this->option('force')) {
            if (! $this->confirm('product_category 表已有数据，是否清空后重新导入？', false)) {
                $this->warn('已取消，未做任何修改。');

                return self::SUCCESS;
            }
        }

        $categories = $this->categories();
        $now = now();

        // 清空并重置自增，使 id 从默认规则重新生成
        DB::table('product_category')->truncate();
        $this->insertWithRemappedParents($categories, $now);

        $this->info(sprintf('分类导入完成，共 %d 条。', count($categories)));

        return self::SUCCESS;
    }

    /**
     * 按层级顺序写入：id 自增生成，parent_id 用「源 id => 新 id」映射还原
     *
     * @param  array<int, array{id: int, category_name: string, parent_id: int, sort: int}>  $categories
     */
    private function insertWithRemappedParents(array $categories, $now): void
    {
        $idMap = [];   // 源 id => 新 id
        $pending = $categories;

        while ($pending !== []) {
            $next = [];

            foreach ($pending as $item) {
                $sourceParentId = (int) $item['parent_id'];

                // 父级还没写入，留到下一轮处理
                if ($sourceParentId !== 0 && ! isset($idMap[$sourceParentId])) {
                    $next[] = $item;
                    continue;
                }

                $newId = DB::table('product_category')->insertGetId([
                    'category_name' => $item['category_name'],
                    'parent_id' => $sourceParentId === 0 ? 0 : $idMap[$sourceParentId],
                    'sort' => $item['sort'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $idMap[$item['id']] = $newId;
            }

            // 数据里存在找不到父级的脏数据，避免死循环
            if (count($next) === count($pending)) {
                $this->warn(sprintf('有 %d 条数据的父级不存在，已跳过。', count($next)));
                break;
            }

            $pending = $next;
        }
    }

    /**
     * 分类数据（id 为源系统 id，仅用于建立 parent_id 映射）
     *
     * @return array<int, array{id: int, category_name: string, parent_id: int, sort: int}>
     */
    private function categories(): array
    {
        return [
            // 一级分类：新品
            ['id' => 29693, 'category_name' => '新品', 'parent_id' => 0, 'sort' => 13],
            ['id' => 30901, 'category_name' => '手机新品', 'parent_id' => 29693, 'sort' => 2],
            ['id' => 31242, 'category_name' => '穿戴新品', 'parent_id' => 29693, 'sort' => 11],
            ['id' => 31243, 'category_name' => '平板新品', 'parent_id' => 29693, 'sort' => 12],
            ['id' => 31244, 'category_name' => '笔记本新品', 'parent_id' => 29693, 'sort' => 13],
            ['id' => 31245, 'category_name' => '台显打印新品', 'parent_id' => 29693, 'sort' => 14],
            ['id' => 31246, 'category_name' => '耳机音箱新品', 'parent_id' => 29693, 'sort' => 15],
            ['id' => 31247, 'category_name' => '智慧屏新品', 'parent_id' => 29693, 'sort' => 16],
            ['id' => 31280, 'category_name' => '路由存储新品', 'parent_id' => 29693, 'sort' => 18],
            ['id' => 33426, 'category_name' => '智能门锁新品', 'parent_id' => 29693, 'sort' => 19],
            ['id' => 29724, 'category_name' => '鸿蒙智行新品', 'parent_id' => 29693, 'sort' => 20],
            ['id' => 32971, 'category_name' => '配件新品', 'parent_id' => 29693, 'sort' => 21],

            // 一级分类：手机
            ['id' => 29694, 'category_name' => '手机', 'parent_id' => 0, 'sort' => 14],
            ['id' => 29726, 'category_name' => 'Mate 系列', 'parent_id' => 29694, 'sort' => 2],
            ['id' => 29727, 'category_name' => 'Pura 系列', 'parent_id' => 29694, 'sort' => 3],
            ['id' => 29729, 'category_name' => 'Pocket 系列', 'parent_id' => 29694, 'sort' => 4],
            ['id' => 29728, 'category_name' => 'nova 系列', 'parent_id' => 29694, 'sort' => 5],
            ['id' => 29730, 'category_name' => '华为畅享系列', 'parent_id' => 29694, 'sort' => 6],
            ['id' => 29732, 'category_name' => '华为官方翻新', 'parent_id' => 29694, 'sort' => 7],
            ['id' => 29731, 'category_name' => '智选手机', 'parent_id' => 29694, 'sort' => 9],
            ['id' => 29734, 'category_name' => '配件周边', 'parent_id' => 29694, 'sort' => 10],

            // 一级分类：穿戴
            ['id' => 29695, 'category_name' => '穿戴', 'parent_id' => 0, 'sort' => 15],
            ['id' => 29736, 'category_name' => 'WATCH Ultimate 系列', 'parent_id' => 29695, 'sort' => 2],
            ['id' => 29737, 'category_name' => 'WATCH 系列', 'parent_id' => 29695, 'sort' => 3],
            ['id' => 29738, 'category_name' => 'WATCH GT 系列', 'parent_id' => 29695, 'sort' => 4],
            ['id' => 33724, 'category_name' => 'WATCH FIT 系列', 'parent_id' => 29695, 'sort' => 5],
            ['id' => 33725, 'category_name' => 'WATCH D 系列', 'parent_id' => 29695, 'sort' => 6],
            ['id' => 29739, 'category_name' => '手环系列', 'parent_id' => 29695, 'sort' => 7],
            ['id' => 29740, 'category_name' => '儿童手表系列', 'parent_id' => 29695, 'sort' => 8],
            ['id' => 29741, 'category_name' => '智能观影眼镜', 'parent_id' => 29695, 'sort' => 9],
            ['id' => 29742, 'category_name' => '穿戴配件', 'parent_id' => 29695, 'sort' => 10],
            ['id' => 34127, 'category_name' => '健康配件', 'parent_id' => 29695, 'sort' => 11],

            // 一级分类：平板
            ['id' => 29696, 'category_name' => '平板', 'parent_id' => 0, 'sort' => 16],
            ['id' => 35941, 'category_name' => 'MatePad Edge 系列', 'parent_id' => 29696, 'sort' => 6],
            ['id' => 30819, 'category_name' => 'MatePad Pro 系列 专业创造力', 'parent_id' => 29696, 'sort' => 7],
            ['id' => 32970, 'category_name' => 'MatePad Mini 系列', 'parent_id' => 29696, 'sort' => 8],
            ['id' => 29745, 'category_name' => 'MatePad Air 系列 潮流生产力', 'parent_id' => 29696, 'sort' => 11],
            ['id' => 29746, 'category_name' => 'MatePad 系列 学习好拍档', 'parent_id' => 29696, 'sort' => 12],
            ['id' => 31251, 'category_name' => 'MatePad SE 系列 娱乐多面手', 'parent_id' => 29696, 'sort' => 13],
            ['id' => 32419, 'category_name' => '智选平板', 'parent_id' => 29696, 'sort' => 14],
            ['id' => 29749, 'category_name' => '配件周边', 'parent_id' => 29696, 'sort' => 15],

            // 一级分类：笔记本
            ['id' => 29697, 'category_name' => '笔记本', 'parent_id' => 0, 'sort' => 17],
            ['id' => 33923, 'category_name' => '鸿蒙电脑', 'parent_id' => 29697, 'sort' => 2],
            ['id' => 29751, 'category_name' => 'MateBook X 系列 高端商务', 'parent_id' => 29697, 'sort' => 3],
            ['id' => 30902, 'category_name' => 'MateBook GT 系列 专业性能', 'parent_id' => 29697, 'sort' => 5],
            ['id' => 29753, 'category_name' => 'MateBook 系列 时尚轻薄', 'parent_id' => 29697, 'sort' => 6],
            ['id' => 29754, 'category_name' => 'MateBook D 系列 高效学习', 'parent_id' => 29697, 'sort' => 8],
            ['id' => 32058, 'category_name' => '智选笔记本', 'parent_id' => 29697, 'sort' => 10],
            ['id' => 29757, 'category_name' => '鼠标及配件', 'parent_id' => 29697, 'sort' => 11],

            // 一级分类：台显打印
            ['id' => 29698, 'category_name' => '台显打印', 'parent_id' => 0, 'sort' => 18],
            ['id' => 29759, 'category_name' => '台式机和一体机', 'parent_id' => 29698, 'sort' => 2],
            ['id' => 29761, 'category_name' => '打印机', 'parent_id' => 29698, 'sort' => 4],
            ['id' => 33991, 'category_name' => '显示器', 'parent_id' => 29698, 'sort' => 6],
            ['id' => 29762, 'category_name' => '配件周边', 'parent_id' => 29698, 'sort' => 7],

            // 一级分类：耳机音箱
            ['id' => 29699, 'category_name' => '耳机音箱', 'parent_id' => 0, 'sort' => 19],
            ['id' => 29764, 'category_name' => 'FreeBuds 系列', 'parent_id' => 29699, 'sort' => 2],
            ['id' => 32577, 'category_name' => 'FreeClip 系列', 'parent_id' => 29699, 'sort' => 7],
            ['id' => 33920, 'category_name' => 'FreeArc 系列', 'parent_id' => 29699, 'sort' => 8],
            ['id' => 29765, 'category_name' => 'FreeLace 系列', 'parent_id' => 29699, 'sort' => 9],
            ['id' => 32578, 'category_name' => '智能眼镜', 'parent_id' => 29699, 'sort' => 10],
            ['id' => 29766, 'category_name' => '有线耳机', 'parent_id' => 29699, 'sort' => 11],
            ['id' => 29767, 'category_name' => '智能音箱', 'parent_id' => 29699, 'sort' => 12],
            ['id' => 29769, 'category_name' => '配件周边', 'parent_id' => 29699, 'sort' => 13],

            // 一级分类：智慧屏
            ['id' => 29700, 'category_name' => '智慧屏', 'parent_id' => 0, 'sort' => 20],
            ['id' => 29773, 'category_name' => 'Vision智慧屏系列 智慧先锋', 'parent_id' => 29700, 'sort' => 28],
            ['id' => 34456, 'category_name' => '智慧屏 MateTV系列 跨时代旗舰', 'parent_id' => 29700, 'sort' => 29],
            ['id' => 29772, 'category_name' => '智慧屏 S系列 智慧娱乐', 'parent_id' => 29700, 'sort' => 30],
            ['id' => 29771, 'category_name' => '智慧屏 V系列 旗舰音画', 'parent_id' => 29700, 'sort' => 38],
            ['id' => 29775, 'category_name' => '配件周边', 'parent_id' => 29700, 'sort' => 39],

            // 一级分类：路由存储
            ['id' => 29701, 'category_name' => '路由存储', 'parent_id' => 0, 'sort' => 22],
            ['id' => 29779, 'category_name' => '智能路由', 'parent_id' => 29701, 'sort' => 8],
            ['id' => 29780, 'category_name' => '移动路由', 'parent_id' => 29701, 'sort' => 9],
            ['id' => 33941, 'category_name' => '家庭存储', 'parent_id' => 29701, 'sort' => 10],
            ['id' => 33921, 'category_name' => '智选路由', 'parent_id' => 29701, 'sort' => 11],
            ['id' => 29781, 'category_name' => '配件周边', 'parent_id' => 29701, 'sort' => 12],

            // 一级分类：智能门锁
            ['id' => 32412, 'category_name' => '智能门锁', 'parent_id' => 0, 'sort' => 24],
            ['id' => 32413, 'category_name' => '智能门锁', 'parent_id' => 32412, 'sort' => 2],
            ['id' => 32414, 'category_name' => '配件周边', 'parent_id' => 32412, 'sort' => 3],

            // 一级分类：鸿蒙智行
            ['id' => 29702, 'category_name' => '鸿蒙智行', 'parent_id' => 0, 'sort' => 25],
            ['id' => 29782, 'category_name' => '预约试驾', 'parent_id' => 29702, 'sort' => 1],
            ['id' => 32266, 'category_name' => '尊界 S800 系列', 'parent_id' => 29702, 'sort' => 2],
            ['id' => 29786, 'category_name' => '问界 M9 系列', 'parent_id' => 29702, 'sort' => 3],
            ['id' => 32789, 'category_name' => '问界 M8 系列', 'parent_id' => 29702, 'sort' => 4],
            ['id' => 29785, 'category_name' => '问界 M7 系列', 'parent_id' => 29702, 'sort' => 5],
            ['id' => 29784, 'category_name' => '问界 M5 系列', 'parent_id' => 29702, 'sort' => 6],
            ['id' => 34118, 'category_name' => '享界 S9T 系列', 'parent_id' => 29702, 'sort' => 10],
            ['id' => 30842, 'category_name' => '享界 S9 系列', 'parent_id' => 29702, 'sort' => 11],
            ['id' => 31273, 'category_name' => '智界 R7 系列', 'parent_id' => 29702, 'sort' => 12],
            ['id' => 29783, 'category_name' => '智界 S7 系列', 'parent_id' => 29702, 'sort' => 13],
            ['id' => 34128, 'category_name' => '尚界 H5 系列', 'parent_id' => 29702, 'sort' => 14],
            ['id' => 32057, 'category_name' => '家充桩', 'parent_id' => 29702, 'sort' => 15],
            ['id' => 29789, 'category_name' => '汽车装饰', 'parent_id' => 29702, 'sort' => 16],

            // 一级分类：配件中心
            ['id' => 29703, 'category_name' => '配件中心', 'parent_id' => 0, 'sort' => 26],
            ['id' => 29792, 'category_name' => '通用配件', 'parent_id' => 29703, 'sort' => 6],
            ['id' => 29793, 'category_name' => '专属配件', 'parent_id' => 29703, 'sort' => 7],
            ['id' => 29794, 'category_name' => '更多配件', 'parent_id' => 29703, 'sort' => 8],

            // 一级分类：全屋智能
            ['id' => 29859, 'category_name' => '全屋智能', 'parent_id' => 0, 'sort' => 28],
            ['id' => 29860, 'category_name' => '解决方案介绍', 'parent_id' => 29859, 'sort' => 1],
            ['id' => 29874, 'category_name' => '热卖单品', 'parent_id' => 29859, 'sort' => 5],
            ['id' => 29864, 'category_name' => '生态推荐', 'parent_id' => 29859, 'sort' => 6],

            // 一级分类：华为服务
            ['id' => 29705, 'category_name' => '华为服务', 'parent_id' => 0, 'sort' => 29],
            ['id' => 35234, 'category_name' => '保障服务', 'parent_id' => 29705, 'sort' => 2],
            ['id' => 29808, 'category_name' => '焕新服务', 'parent_id' => 29705, 'sort' => 3],
            ['id' => 29810, 'category_name' => '备件服务', 'parent_id' => 29705, 'sort' => 5],
            ['id' => 29809, 'category_name' => '上门服务', 'parent_id' => 29705, 'sort' => 6],

            // 一级分类：鸿蒙智选
            ['id' => 29704, 'category_name' => '鸿蒙智选', 'parent_id' => 0, 'sort' => 30],
            ['id' => 29797, 'category_name' => '智能安防', 'parent_id' => 29704, 'sort' => 12],
            ['id' => 29798, 'category_name' => '环境电器', 'parent_id' => 29704, 'sort' => 13],
            ['id' => 29799, 'category_name' => '健康饮水', 'parent_id' => 29704, 'sort' => 14],
            ['id' => 29800, 'category_name' => '个护健康', 'parent_id' => 29704, 'sort' => 15],
            ['id' => 29801, 'category_name' => '智能照明', 'parent_id' => 29704, 'sort' => 16],
            ['id' => 29802, 'category_name' => '运动健康', 'parent_id' => 29704, 'sort' => 17],
            ['id' => 34284, 'category_name' => '居家智品', 'parent_id' => 29704, 'sort' => 19],
            ['id' => 29804, 'category_name' => '影音娱乐', 'parent_id' => 29704, 'sort' => 20],

            // 一级分类：数字内容
            ['id' => 29706, 'category_name' => '数字内容', 'parent_id' => 0, 'sort' => 31],
            ['id' => 34069, 'category_name' => '华为移动路由升级流量卡', 'parent_id' => 29706, 'sort' => 2],
            ['id' => 29812, 'category_name' => '华为音乐卡', 'parent_id' => 29706, 'sort' => 3],
            ['id' => 29813, 'category_name' => '华为视频卡', 'parent_id' => 29706, 'sort' => 4],
            ['id' => 29814, 'category_name' => '华为主题', 'parent_id' => 29706, 'sort' => 6],
            ['id' => 29816, 'category_name' => '华为运动健康卡', 'parent_id' => 29706, 'sort' => 7],

            // 一级分类：商用终端
            ['id' => 29707, 'category_name' => '商用终端', 'parent_id' => 0, 'sort' => 38],
            ['id' => 29818, 'category_name' => '商用笔记本', 'parent_id' => 29707, 'sort' => 2],
            ['id' => 29820, 'category_name' => '商用显示器', 'parent_id' => 29707, 'sort' => 4],
            ['id' => 29821, 'category_name' => '商用打印机', 'parent_id' => 29707, 'sort' => 5],
            ['id' => 29822, 'category_name' => '商用平板', 'parent_id' => 29707, 'sort' => 6],
            ['id' => 29823, 'category_name' => '商用穿戴', 'parent_id' => 29707, 'sort' => 7],
            ['id' => 29824, 'category_name' => '商用智慧屏', 'parent_id' => 29707, 'sort' => 8],

            // 一级分类：生态周边
            ['id' => 32255, 'category_name' => '生态周边', 'parent_id' => 0, 'sort' => 39],
            ['id' => 32256, 'category_name' => '家用电器', 'parent_id' => 32255, 'sort' => 1],
            ['id' => 32257, 'category_name' => '智能家装', 'parent_id' => 32255, 'sort' => 2],
            ['id' => 32258, 'category_name' => '个护健康', 'parent_id' => 32255, 'sort' => 3],
            ['id' => 32259, 'category_name' => '智慧出行', 'parent_id' => 32255, 'sort' => 4],
            ['id' => 32260, 'category_name' => '家庭娱乐', 'parent_id' => 32255, 'sort' => 5],
            ['id' => 32261, 'category_name' => '运动健康', 'parent_id' => 32255, 'sort' => 6],

            // 一级分类：美食酒饮
            ['id' => 29713, 'category_name' => '美食酒饮', 'parent_id' => 0, 'sort' => 40],
            ['id' => 29850, 'category_name' => '甄选美酒', 'parent_id' => 29713, 'sort' => 31],
            ['id' => 29851, 'category_name' => '冲调品', 'parent_id' => 29713, 'sort' => 35],
            ['id' => 29852, 'category_name' => '严选良食', 'parent_id' => 29713, 'sort' => 36],
        ];
    }
}
