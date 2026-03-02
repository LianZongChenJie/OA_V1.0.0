<?php
namespace app\projecttender\model;

use think\Model;

class ProjectTenderAttachment extends Model
{
    // 定义表名
    protected $name = 'project_tender_attachment';

    // 定义主键
    protected $pk = 'id';

    // 关闭自动时间戳（控制器手动赋值，避免冲突）
    protected $autoWriteTimestamp = false;

    // 字段类型转换
    protected $type = [
        'id'                => 'integer',
        'project_tender_id' => 'integer',
        'file_size'         => 'integer',
        'sort'              => 'integer',
        'create_time'       => 'datetime',
        'update_time'       => 'datetime',
        'delete_time'       => 'integer',
    ];

    // 软删除配置（和控制器 delete_time=time() 逻辑对齐）
    use \think\model\concern\SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 关联项目投标主表（补充正确命名空间）
     */
    public function projectTender()
    {
        return $this->belongsTo(ProjectTender::class, 'project_tender_id', 'id');
    }

    /**
     * 获取指定项目的所有附件（修正软删除判断）
     * @param int $projectTenderId 项目投标ID
     * @return array
     */
    public static function getAttachmentsByProjectId($projectTenderId)
    {
        if (!is_numeric($projectTenderId) || $projectTenderId <= 0) {
            throw new \InvalidArgumentException('投标ID必须为正整数');
        }

        return self::where([
            'project_tender_id' => $projectTenderId,
            'delete_time' => 0 // 和控制器逻辑一致，不用null
        ])
            ->order('sort asc, id asc') // 和控制器排序一致
            ->select()
            ->toArray();
    }
}