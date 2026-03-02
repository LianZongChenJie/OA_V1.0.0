<?php
namespace app\projecttender\model;

use think\Model;

class ProjectTender extends Model
{
    // 定义表名（必须和数据库表名一致）
    protected $name = 'project_tender';

    // 定义主键
    protected $pk = 'id';

    // 自动写入时间戳（datetime格式，和控制器逻辑对齐）
    protected $autoWriteTimestamp = false; // 控制器手动赋值create/update_time，这里关闭自动写入

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'budget_amount' => 'float',
        'tender_document_fee' => 'float',
        'tender_deposit' => 'float',
        'bid_service_fee' => 'float',
        'sort' => 'integer',
        'delete_time' => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 软删除配置（和控制器中 delete_time=0 逻辑对齐）
    use \think\model\concern\SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 关联附件表（一对多）
     */
    public function attachments()
    {
        return $this->hasMany(ProjectTenderAttachment::class, 'project_tender_id', 'id');
    }
}