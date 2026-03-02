<?php
namespace app\projecttender\validate;

use think\Validate;

class IndexValidate extends Validate
{
    // 验证规则
    protected $rule = [
        'month' => 'require|chsAlphaNum|max:50',
        'tender_leader' => 'require|chsAlphaNum|max:100',
        'customer_name' => 'require|chsAlphaNum|max:200',
        'project_name' => 'require|chsAlphaNum|max:200',
        'is_tender_submitted' => 'require|in:0,1',
        'sort' => 'integer|egt:0',
        'id' => 'integer|egt:1'
    ];

    // 错误提示
    protected $message = [
        'month.require' => '月份不能为空',
        'month.chsAlphaNum' => '月份只能包含中文、字母和数字',
        'month.max' => '月份长度不能超过50个字符',

        'tender_leader.require' => '投标负责人不能为空',
        'tender_leader.chsAlphaNum' => '投标负责人只能包含中文、字母和数字',
        'tender_leader.max' => '投标负责人长度不能超过100个字符',

        'customer_name.require' => '客户名称不能为空',
        'customer_name.chsAlphaNum' => '客户名称只能包含中文、字母和数字',
        'customer_name.max' => '客户名称长度不能超过200个字符',

        'project_name.require' => '项目名称不能为空',
        'project_name.chsAlphaNum' => '项目名称只能包含中文、字母和数字',
        'project_name.max' => '项目名称长度不能超过200个字符',

        'is_tender_submitted.require' => '是否提交标书不能为空',
        'is_tender_submitted.in' => '是否提交标书只能是0或1',

        'sort.integer' => '排序值必须是整数',
        'sort.egt' => '排序值不能小于0',

        'id.integer' => 'ID必须是整数',
        'id.egt' => 'ID必须大于0'
    ];

    // 场景验证
    protected $scene = [
        'add' => ['month', 'tender_leader', 'customer_name', 'project_name', 'is_tender_submitted', 'sort'],
        'edit' => ['id', 'month', 'tender_leader', 'customer_name', 'project_name', 'is_tender_submitted', 'sort']
    ];
}