<?php
namespace app\projecttender\validate;

use think\Validate;

class WebsiteaccountValidate extends Validate
{
    protected $rule = [
        'website_name' => 'require|max:255',
        'website_url'  => 'require|url|max:512',
        'username'     => 'require|max:100',
        'password'     => 'require|max:100',
        'sort'         => 'integer|egt:0',
        'has_uk'       => 'max:20',
    ];

    protected $message = [
        'website_name.require' => '请输入网站名称',
        'website_url.require'  => '请输入网址',
        'website_url.url'      => '请输入合法的网址',
        'username.require'     => '请输入用户名',
        'password.require'     => '请输入密码',
        'sort.integer'         => '排序必须为整数',
        'sort.egt'             => '排序不能为负数',
    ];

    protected $scene = [
        'add'  => ['website_name', 'website_url', 'username', 'password', 'sort', 'has_uk'],
        'edit' => ['website_name', 'website_url', 'username', 'password', 'sort', 'has_uk'],
    ];
}