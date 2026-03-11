<?php
/**
+-----------------------------------------------------------------------------------------------
 * GouGuOPEN [ 左手研发，右手开源，未来可期！]
+-----------------------------------------------------------------------------------------------
 * @Copyright (c) 2021~2024 http://www.gouguoa.com All rights reserved.
+-----------------------------------------------------------------------------------------------
 * @Licensed 勾股OA，开源且可免费使用，但并不是自由软件，未经授权许可不能去除勾股OA的相关版权信息
+-----------------------------------------------------------------------------------------------
 * @Author 勾股工作室 <hdm58@qq.com>
+-----------------------------------------------------------------------------------------------
 */

namespace app\projecttender\model;

use think\Model;
use think\facade\Db;
use think\model\concern\SoftDelete;

class OaWebsiteAccount extends Model
{
    // 定义表名（和数据库表名保持一致）
    protected $name = 'website_account';

    // 定义主键
    protected $pk = 'id';

    // 自动写入时间戳（关闭自动写入，保持原有控制器手动赋值逻辑）
    protected $autoWriteTimestamp = false;

    // 字段类型转换（对应数据库字段类型，包含新增的排序字段）
    protected $type = [
        'id' => 'integer',
        'sort' => 'integer', // 排序字段，整数类型
        'delete_time' => 'integer', // 软删除字段
        'create_time' => 'integer', // 创建时间（时间戳）
        'update_time' => 'integer', // 更新时间（时间戳）
    ];

    // 软删除配置（和原有逻辑中 delete_time=time() 对齐）
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 获取分页列表
     * @param array $param 分页/排序参数
     * @param array $where 普通查询条件
     * @param array $whereOr 或查询条件
     * @return \think\Paginator|array
     */
    public function datalist($param = [], $where = [], $whereOr = [])
    {
        $rows = empty($param['limit']) ? get_config('app.page_size') : $param['limit'];
        $order = empty($param['order']) ? 'id desc' : $param['order'];

        try {
            $list = $this->where($where)
                ->where(function ($query) use ($whereOr) {
                    if (!empty($whereOr)) {
                        $query->whereOr($whereOr);
                    }
                })
                ->order($order)
                ->paginate(['list_rows' => $rows]);

            return $list;
        } catch (\Exception $e) {
            return ['code' => 1, 'data' => [], 'msg' => $e->getMessage()];
        }
    }

    /**
     * 添加数据
     * @param array $param 新增数据
     * @return array
     */
    public function add($param)
    {
        $insertId = 0;
        try {
            $param['create_time'] = time();
            $insertId = $this->strict(false)->field(true)->insertGetId($param);
            add_log('add', $insertId, $param);
        } catch (\Exception $e) {
            return to_assign(1, '操作失败，原因：'.$e->getMessage());
        }

        return to_assign(0, '操作成功', ['return_id' => $insertId]);
    }

    /**
     * 编辑信息
     * @param array $param 编辑数据
     * @return array
     */
    public function edit($param)
    {
        try {
            $param['update_time'] = time();
            $this->where('id', $param['id'])
                ->strict(false)
                ->field(true)
                ->update($param);

            add_log('edit', $param['id'], $param);
        } catch (\Exception $e) {
            return to_assign(1, '操作失败，原因：'.$e->getMessage());
        }

        return to_assign(0, '操作成功', ['return_id' => $param['id']]);
    }

    /**
     * 根据id获取信息
     * @param int $id 主键ID
     * @return \think\Model|null
     */
    public function getById($id)
    {
        return $this->find($id);
    }

    /**
     * 删除信息
     * @param int $id 主键ID
     * @param int $type 删除类型：0=逻辑删除，1=物理删除
     * @return array
     */
    public function delById($id, $type = 0)
    {
        if ($type == 0) {
            // 逻辑删除（复用SoftDelete特性，和原有逻辑一致）
            try {
                $this->destroy($id);
                add_log('delete', $id);
            } catch (\Exception $e) {
                return to_assign(1, '操作失败，原因：'.$e->getMessage());
            }
        } else {
            // 物理删除
            try {
                parent::destroy($id, true); // true 表示强制物理删除
                add_log('delete', $id);
            } catch (\Exception $e) {
                return to_assign(1, '操作失败，原因：'.$e->getMessage());
            }
        }

        return to_assign(0, '操作成功');
    }
}