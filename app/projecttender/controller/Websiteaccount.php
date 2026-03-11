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

declare (strict_types = 1);

namespace app\projecttender\controller;

use app\base\BaseController;
use app\projecttender\model\OaWebsiteAccount as OaWebsiteAccountModel;
use app\projecttender\validate\WebsiteaccountValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\View;


// 引入Excel相关类（需确保项目已安装phpoffice/phpspreadsheet）
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\response\Download;


class Websiteaccount extends BaseController
{
    /**
     * 构造函数
     */
    protected $model;
    public function __construct()
    {
        parent::__construct(); // 调用父类构造函数
        $this->model = new OaWebsiteAccountModel();
    }

    public function datalist()
    {
        if (request()->isAjax()) {
            $param = get_params();
            $where = ['delete_time' => 0];
            if (!empty($param['website_url'])) {
                $where[] = ['website_url', 'like', "%{$param['website_url']}%"];
            }

            // 直接在控制器里做分页和排序
            $page = isset($param['page']) ? $param['page'] : 1;
            $limit = isset($param['limit']) ? $param['limit'] : 10;

            $list = $this->model
                ->where($where)
                ->order('sort asc') // 排序
                ->paginate([
                    'page' => $page,
                    'list_rows' => $limit
                ]);

            return json([
                'code'  => 0,
                'msg'   => '',
                'count' => $list->total(),
                'data'  => $list->items()
            ]);
        } else {
            return view();
        }
    }

    /**
     * 子分类（保留但本业务用不到，可忽略）
     * $id
     * $is_self=1包含自己
     */
    public function sonlist($id = 0, $is_self = 1)
    {
        $cates = $this->model->where('delete_time',0)->order('sort asc')->select()->toArray();
        $cates_list = get_data_node($cates, $id);
        $cates_array = array_column($cates_list, 'id');
        if ($is_self == 1) {
            //包括自己在内
            $cates_array[] = $id;
        }
        return $cates_array;
    }

    /**
     * 添加/编辑（核心修复：新增时初始化空的$detail）
     */
    public function add()
    {
        $param = get_params();
        if (request()->isAjax()) {
            if (!empty($param['id']) && $param['id'] > 0) {
                try {
                    validate(WebsiteaccountValidate::class)->scene('edit')->check($param);
                } catch (ValidateException $e) {
                    return to_assign(1, $e->getError());
                }
                // 移除：分类pid校验逻辑（本业务不需要）
                $this->model->edit($param);
            } else {
                try {
                    validate(WebsiteaccountValidate::class)->scene('add')->check($param);
                } catch (ValidateException $e) {
                    return to_assign(1, $e->getError());
                }
                $this->model->add($param);
            }
            return to_assign(0, '操作成功');
        } else {
            $id = isset($param['id']) ? $param['id'] : 0;
            // 核心修复：无论新增/编辑，都给模板传递$detail变量
            if ($id > 0) {
                $detail = $this->model->getById($id);
            } else {
                // 新增时初始化空数组，避免模板报错
                $detail = [
                    'id'           => 0,
                    'website_name' => '',
                    'website_url'  => '',
                    'username'     => '',
                    'password'     => '',
                    'has_uk'       => '',
                    'sort'         => 0,
                    'remark'       => ''
                ];
            }
            View::assign('detail', $detail);
            return view('websiteaccount/add');
        }
    }

    /**
     * 查看
     */
    public function view($id)
    {
        $detail = $this->model->getById($id);
        if (!empty($detail)) {
            View::assign('detail', $detail);
            return view();
        } else {
            return view(EEEOR_REPORTING,['code'=>404,'warning'=>'找不到页面']);
        }
    }

    /**
     * 删除
     */
    public function del()
    {
        if (request()->isDelete()) {
            $param = get_params();
            $id = isset($param['id']) ? $param['id'] : 0;
            $count_list = 0;
            if ($count_list > 0) {
                return to_assign(1, "该分类下还有内容，无法删除");
            }
            $this->model->delById($id);
            return to_assign(0, '删除成功'); // 补充返回成功信息
        } else {
            return to_assign(1, "错误的请求");
        }
    }

    /**
     * 设置（本业务用不到，可忽略）
     */
    public function set()
    {
        if (request()->isAjax()) {
            $param = get_params();
            if($param['status'] == 0){
                $count_cate = $this->model->where(["pid"=>$param['id'],"delete_time"=>0])->count();
                if ($count_cate > 0) {
                    return to_assign(1, "该分类下还有子分类，无法禁用");
                }
                $count_list = 0;
                if ($count_list > 0) {
                    return to_assign(1, "该分类下还有内容，无法禁用");
                }
                $this->model->strict(false)->field('id,status')->update($param);
                add_log('disable', $param['id'], $param);
            }
            else if($param['status'] == 1){
                $res = $this->model->strict(false)->field('id,status')->update($param);
                add_log('recovery', $param['id'], $param);
            }
            return to_assign();
        } else {
            return to_assign(1, "错误的请求");
        }
    }

    /**
     * 下载Excel模板
     * @return Download
     */
    public function exportTemplate()
    {
        // 创建Excel对象
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头
        $header = [
            '网站名称', '网址', '用户名', '密码', '是否有UK', '排序', '说明'
        ];
        $sheet->fromArray($header, null, 'A1');

        // 设置列宽
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(30);

        // 下载设置
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="网站账号信息导入模板.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * Excel导入接口
     * @return \think\response\Json
     */
    public function importExcel()
    {
        if (!request()->isPost()) {
            return to_assign(1, '请求方式错误');
        }

        // 获取上传文件
        $file = request()->file('file');
        if (!$file) {
            return to_assign(1, '请选择要导入的Excel文件');
        }

        // 验证文件类型
        $fileInfo = $file->getInfo();
        $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return to_assign(1, '仅支持.xlsx/.xls格式的Excel文件');
        }

        try {
            // 读取Excel文件
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow(); // 获取总行数

            if ($highestRow < 2) {
                return to_assign(1, 'Excel文件中无有效数据（需跳过表头）');
            }

            // 解析数据
            $dataList = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $data = [
                    'website_name' => $sheet->getCell("A{$row}")->getValue() ?: '',
                    'website_url'  => $sheet->getCell("B{$row}")->getValue() ?: '',
                    'username'     => $sheet->getCell("C{$row}")->getValue() ?: '',
                    'password'     => $sheet->getCell("D{$row}")->getValue() ?: '',
                    'has_uk'       => $sheet->getCell("E{$row}")->getValue() ?: '',
                    'sort'         => $sheet->getCell("F{$row}")->getValue() ?: 0,
                    'remark'       => $sheet->getCell("G{$row}")->getValue() ?: ''
                ];
                // 空行跳过
                if (empty($data['website_name']) && empty($data['website_url']) && empty($data['username']) && empty($data['password'])) {
                    continue;
                }
                $dataList[] = $data;
            }

            // 调用模型批量导入
            return $this->model->importBatch($dataList);
        } catch (\Exception $e) {
            return to_assign(1, '解析Excel失败：' . $e->getMessage());
        }
    }

}