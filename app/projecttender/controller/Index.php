<?php
namespace app\projecttender\controller;
use app\base\BaseController;
use app\projecttender\model\ProjectTender;
use think\facade\View;
use think\facade\Request;
use think\Request as RequestInstance;
use app\projecttender\model\ProjectTenderAttachment;
use think\facade\Db;



class Index extends BaseController
{
    protected $model;
    public function initialize()
    {
        parent::initialize();
        $this->model = new ProjectTender(); // 现在模型文件存在，可正常实例化
    }

    // 替代 DB 门面的写法（推荐）
    public function datalist()
    {
        // 调试信息写入日志，不影响前端响应
        if (Request::isAjax()) {
            $page  = Request::param('page', 1, 'intval');
            $limit = Request::param('limit', 10, 'intval');

            if ($limit <= 0) $limit = 10;
            if ($page < 1) $page = 1;
            $offset = ($page - 1) * $limit;

            // 搜索条件
            $searchWhere = [];

            // 原有筛选条件
            $projectName = trim(Request::param('project_name', ''));
            if (!empty($projectName)) {
                $searchWhere[] = ['project_name', 'like', "%{$projectName}%"];
            }
            $tenderLeader = trim(Request::param('tender_leader', ''));
            if (!empty($tenderLeader)) {
                $searchWhere[] = ['tender_leader', 'like', "%{$tenderLeader}%"];
            }
            $tenderAgency = trim(Request::param('tender_agency', ''));
            if (!empty($tenderAgency)) {
                $searchWhere[] = ['tender_agency', 'like', "%{$tenderAgency}%"];
            }

            // 新增筛选条件1：开标日期（起止时间）
            $bidOpeningDateStart = trim(Request::param('bid_opening_date_start', ''));
            if (!empty($bidOpeningDateStart)) {
                $searchWhere[] = ['bid_opening_date', '>=', $bidOpeningDateStart];
            }
            $bidOpeningDateEnd = trim(Request::param('bid_opening_date_end', ''));
            if (!empty($bidOpeningDateEnd)) {
                // 兼容日期时间格式：匹配到结束日期的 23:59:59
                $searchWhere[] = ['bid_opening_date', '<=', date('Y-m-d 23:59:59', strtotime($bidOpeningDateEnd))];
            }

            // 新增筛选条件2：是否投标
            $isTenderSubmitted = trim(Request::param('is_tender_submitted', ''));
            if (!empty($isTenderSubmitted) && in_array($isTenderSubmitted, ['是', '否'])) {
                $searchWhere[] = ['is_tender_submitted', '=', $isTenderSubmitted];
            }

            // 新增筛选条件3：中标结果
            $bidResult = trim(Request::param('bid_result', ''));
            if (!empty($bidResult) && in_array($bidResult, ['中标', '未中标', '待开标'])) {
                $searchWhere[] = ['bid_result', '=', $bidResult];
            }

            // 新增：获取排序类型（默认正序asc）
            $sortType = trim(Request::param('sort_type', 'asc'));
            // 安全校验：只允许asc/desc两种值
            $sortType = in_array($sortType, ['asc', 'desc']) ? $sortType : 'asc';

            // 【终极修复1】每次查询都新建模型实例，彻底隔离上下文
            // 1. 列表数据
            $listModel = new \app\projecttender\model\ProjectTender();
            $list = $listModel
                ->where($searchWhere)
                ->limit($offset, $limit)
                ->order('bid_opening_date ' . $sortType) // 使用前端传递的排序类型
                ->select()
                ->toArray();

            // 【终极修复2】新建独立的模型实例查总数，完全不带之前的limit/order
            $countModel = new \app\projecttender\model\ProjectTender();
            $total = $countModel
                ->where($searchWhere)
                ->removeOption('limit')  // 强制移除所有limit条件
                ->removeOption('order')  // 强制移除所有order条件
                ->count();

            // 【兜底方案】如果还是有问题，直接用原生SQL查总数（100%纯净）
            // $total = \think\facade\Db::query("SELECT COUNT(*) AS total FROM oa_project_tender WHERE delete_time = '0'")[0]['total'];

            return json([
                'code'  => 0,
                'msg'   => '',
                'count' => (int)$total,
                'data'  => $list
            ]);
        }
        return View::fetch('datalist');
    }

    public function add()
    {
        try {
            $id = Request::param('id', 0, 'intval');

            // 初始化模板字段（包含所有表单字段，避免未定义报错）
            $info = [
                'id' => 0,
                'month' => '',
                'tender_leader' => '',
                'purchase_date' => '',
                'customer_name' => '',
                'project_name' => '',
                'tender_agency' => '',
                'project_cycle' => '',
                'shortlisted_countries' => '',
                'budget_amount' => '0.00',
                'bid_opening_date' => '',
                'deposit_paid_time'=>'',
                'is_tender_submitted' => '',
                'non_tender_reason' => '',
                'tender_document_fee' => '0.00',
                'has_tender_invoice' => '',       // 补充缺失字段
                'is_deposit_paid' => '',          // 补充缺失字段
                'tender_deposit' => '0.00',
                'deposit_account_name' => '',
                'deposit_bank' => '',
                'deposit_account_no' => '',
                'is_deposit_refunded' => '',
                'bid_result' => '',
                'bid_service_fee' => '0.00',
                'sort' => 0,
                'create_time' => '',             // 补充字段（详情页用）
                'update_time' => ''              // 补充字段（详情页用）
            ];
            $attachments = [];

            // 编辑场景：加载已有数据
            if ($id > 0) {
                $info = $this->model->where(['id' => $id, 'delete_time' => 0])->find();
                if (empty($info)) {
                    return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">数据不存在</div>';
                }

                // 转换为数组，方便后续处理
                $info = $info->toArray();

                // 兼容所有字段的空值，避免模板报错
                $info['month'] = $info['month'] ?? '';
                $info['tender_leader'] = $info['tender_leader'] ?? '';
                $info['purchase_date'] = $info['purchase_date'] ?? '';
                $info['customer_name'] = $info['customer_name'] ?? '';
                $info['project_name'] = $info['project_name'] ?? '';
                $info['tender_agency'] = $info['tender_agency'] ?? '';
                $info['project_cycle'] = $info['project_cycle'] ?? '';
                $info['shortlisted_countries'] = $info['shortlisted_countries'] ?? '';
                $info['budget_amount'] = $info['budget_amount'] ?? '0.00';
                $info['bid_opening_date'] = $info['bid_opening_date'] ?? '';
                $info['deposit_paid_time'] = $info['deposit_paid_time'] ?? '';
                $info['is_tender_submitted'] = $info['is_tender_submitted'] ?? '';
                $info['non_tender_reason'] = $info['non_tender_reason'] ?? '';
                $info['tender_document_fee'] = $info['tender_document_fee'] ?? '0.00';
                $info['has_tender_invoice'] = $info['has_tender_invoice'] ?? '';
                $info['is_deposit_paid'] = $info['is_deposit_paid'] ?? '';
                $info['tender_deposit'] = $info['tender_deposit'] ?? '0.00';
                $info['deposit_account_name'] = $info['deposit_account_name'] ?? '';
                $info['deposit_bank'] = $info['deposit_bank'] ?? '';
                $info['deposit_account_no'] = $info['deposit_account_no'] ?? '';
                $info['is_deposit_refunded'] = $info['is_deposit_refunded'] ?? '';
                $info['bid_result'] = $info['bid_result'] ?? '';
                $info['bid_service_fee'] = $info['bid_service_fee'] ?? '0.00';
                $info['sort'] = $info['sort'] ?? 0;
                $info['create_time'] = $info['create_time'] ?? '';
                $info['update_time'] = $info['update_time'] ?? '';

                // 修复附件查询：delete_time=0 而非 null
                $attachments = ProjectTenderAttachment::where([
                    'project_tender_id' => $id,
                    'delete_time' => 0 // 关键修正：和软删除配置一致
                ])->order('sort asc')->select()->toArray();
            }
//            dd($info);
            // 赋值模板变量
            View::assign([
                'id' => $id,
                'info' => $info,
                'detail' => $info, // 兼容详情页的变量名
                'attachments' => $attachments
            ]);

            return View::fetch('add');
        } catch (\Exception $e) {
            // 友好的错误提示，方便调试
            return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">页面加载失败：'.$e->getMessage().'（行号：'.$e->getLine().'）</div>';
        }
    }



    protected function toAssign($code, $msg, $data = [])
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'action' => '',
            'url' => '',
            'data' => $data
        ]);
    }

    public function del()
    {
        $id = Request::param('id', 0, 'intval');
        if ($id <= 0) {
            return $this->toAssign(1, '请选择要删除的数据');
        }

        \think\facade\Db::startTrans();
        try {
            // 软删除主表
            $res1 = $this->model->where(['id' => $id, 'delete_time' => 0])->update([
                'delete_time' => time(),
                'update_time' => date('Y-m-d H:i:s')
            ]);
            // 软删除附件
            $res2 = ProjectTenderAttachment::where('project_tender_id', $id)->update([
                'delete_time' => time(),
                'update_time' => date('Y-m-d H:i:s')
            ]);

            if ($res1 !== false || $res2 !== false) { // 修正判断逻辑
                \think\facade\Db::commit();
                return $this->toAssign(0, '删除成功');
            } else {
                \think\facade\Db::rollback();
                return $this->toAssign(1, '删除失败，数据不存在或已删除');
            }
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            return $this->toAssign(1, '删除异常：' . $e->getMessage());
        }
    }

    public function detail()
    {
        try {
            $id = Request::param('id', 0, 'intval');
            if ($id <= 0) {
                return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">参数错误，ID不能为空</div>';
            }
            $info = $this->model->where(['id' => $id, 'delete_time' => 0])->find();
            if (empty($info)) {
                return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">数据不存在</div>';
            }
            $info = $info->toArray();

            $info['budget_amount'] = $info['budget_amount'] ?? '0.00';
            $info['tender_document_fee'] = $info['tender_document_fee'] ?? '0.00';
            $info['tender_deposit'] = $info['tender_deposit'] ?? '0.00';
            $info['bid_service_fee'] = $info['bid_service_fee'] ?? '0.00';
            $info['sort'] = $info['sort'] ?? 0;

            // 修复附件查询
            $attachments = ProjectTenderAttachment::where([
                'project_tender_id' => $id,
                'delete_time' => 0
            ])->order('sort asc')->select()->toArray();

            View::assign([
                'id' => $id,
                'info' => $info,
                'detail' => $info,
                'attachments' => $attachments
            ]);
            return View::fetch('detail');
        } catch (\Exception $e) {
            return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">详情加载失败：'.$e->getMessage().'</div>';
        }
    }

    public function view()
    {
        try {
            $id = Request::param('id', 0, 'intval');
            if ($id <= 0) {
                return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">参数错误，ID不能为空</div>';
            }
            $info = $this->model->where(['id' => $id, 'delete_time' => 0])->find();
            if (empty($info)) {
                return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">数据不存在</div>';
            }
            $infoArr = $info->toArray();
            $infoArr['budget_amount'] = $infoArr['budget_amount'] ?? '0.00';
            $infoArr['tender_document_fee'] = $infoArr['tender_document_fee'] ?? '0.00';
            $infoArr['tender_deposit'] = $infoArr['tender_deposit'] ?? '0.00';
            $infoArr['bid_service_fee'] = $infoArr['bid_service_fee'] ?? '0.00';
            $infoArr['sort'] = $infoArr['sort'] ?? 0;

            // 修复附件查询
            $attachments = ProjectTenderAttachment::where([
                'project_tender_id' => $id,
                'delete_time' => 0
            ])->order('sort asc')->select()->toArray();

            View::assign([
                'info' => $infoArr,
                'attachments' => $attachments
            ]);
            return View::fetch('view');
        } catch (\Exception $e) {
            return '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">详情加载失败：'.$e->getMessage().'</div>';
        }
    }

    /**
     * 招投标附件上传接口
     */


    public function getAttachments()
    {
        $tenderId = Request::param('project_tender_id', 0, 'intval');
        if ($tenderId <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        // 修复附件查询
        $list = ProjectTenderAttachment::where([
            'project_tender_id' => $tenderId,
            'delete_time' => 0
        ])->order('sort asc')->select()->toArray();

        return json(['code' => 0, 'data' => $list]);
    }


    /**
     * 招投标附件上传接口（终极稳定版，解决文件移动/扩展名/权限问题）
     */
    public function uploadAttachment()
    {
        // 1. 仅允许POST请求
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json(['code' => 1, 'msg' => '请求方式错误，仅支持POST']);
        }

        // 2. 检查是否有文件上传
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['file']['error'] ?? -1;
            $errorMsg = $this->getUploadErrorMsg($errorCode);
            return json(['code' => 1, 'msg' => '文件上传失败：' . $errorMsg]);
        }

        // 3. 获取原生文件信息
        $file = $_FILES['file'];
        $originalFileName = $file['name']; // 原始文件名
        $tmpFilePath = $file['tmp_name'];  // PHP临时文件路径

        try {
            // 4. 定义上传目录（绝对路径，兼容Windows）
            $uploadRelativeDir = 'uploads/tender/'; // 相对于public的目录
            // 手动拼接项目根目录（100%兼容）
            $projectRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/../';
            $publicRoot = realpath($projectRoot . 'public') . '/';
            $targetDir = $publicRoot . $uploadRelativeDir;

            // 5. 强制创建目录 + 检查权限（核心修复）
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
                // Windows系统强制赋权
                @chmod($targetDir, 0755);
            }
            // 检查目录是否可写
            if (!is_writable($targetDir)) {
                return json(['code' => 1, 'msg' => '上传目录无写入权限，请检查：' . $targetDir]);
            }

            // 6. 修复扩展名解析（解决PDF变tmp问题）
            $fileExt = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            // 容错：如果解析不到扩展名，从MIME类型反推
            if (empty($fileExt)) {
                $mime = mime_content_type($tmpFilePath);
                $mimeMap = [
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    'application/zip' => 'zip',
                    'application/x-rar-compressed' => 'rar'
                ];
                $fileExt = $mimeMap[$mime] ?? 'pdf'; // 默认兜底为pdf
            }

            // 7. 生成唯一文件名（确保扩展名正确）
            $uniqueFileName = date('YmdHis') . '_' . uniqid() . '.' . $fileExt;
            $targetFilePath = $targetDir . $uniqueFileName;

            // 8. 安全移动文件（核心：确保移动成功）
            // 先清空目标文件（避免覆盖失败）
            if (file_exists($targetFilePath)) {
                @unlink($targetFilePath);
            }
            // 移动临时文件到目标目录
            $moveResult = move_uploaded_file($tmpFilePath, $targetFilePath);
            if (!$moveResult || !file_exists($targetFilePath)) {
                $error = error_get_last();
                $errorMsg = $error['message'] ?? '未知错误';
                return json(['code' => 1, 'msg' => '文件移动失败：' . $errorMsg . '（目标路径：' . $targetFilePath . '）']);
            }

            // 9. 组装返回数据（前端需要的字段）
            $fileUrl = '/' . $uploadRelativeDir . $uniqueFileName;
            $fileUrl = str_replace('\\', '/', $fileUrl); // 兼容Windows路径

            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'file_name' => $originalFileName, // 原始文件名（如发布测试报告1.pdf）
                    'file_path' => $fileUrl,          // 前端访问路径
                    'file_size' => $file['size'],     // 文件大小（字节）
                    'file_ext'  => $fileExt,          // 正确的扩展名（pdf/jpg等）
                    'file_mime' => mime_content_type($targetFilePath), // MIME类型
                    'sort'      => 0                  // 默认排序
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '上传异常：' . $e->getMessage() . '（行号：' . $e->getLine() . '）'
            ]);
        }
    }

    /**
     * 转换PHP上传错误码为中文提示（保留）
     * @param int $errorCode 上传错误码
     * @return string
     */
    private function getUploadErrorMsg($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return '文件大小超过php.ini限制（当前限制：' . ini_get('upload_max_filesize') . '）';
            case UPLOAD_ERR_FORM_SIZE:
                return '文件大小超过表单限制';
            case UPLOAD_ERR_PARTIAL:
                return '文件仅部分上传';
            case UPLOAD_ERR_NO_FILE:
                return '未选择上传文件';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '缺少PHP临时文件夹，请检查php.ini的upload_tmp_dir配置';
            case UPLOAD_ERR_CANT_WRITE:
                return '临时文件写入失败，请检查临时目录权限';
            case UPLOAD_ERR_EXTENSION:
                return '文件上传被PHP扩展中断';
            case UPLOAD_ERR_OK:
                return '无错误';
            default:
                return '未知上传错误（错误码：' . $errorCode . '）';
        }
    }






    public function save(RequestInstance $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误，仅支持POST', 'data' => []]);
        }

        try {
            // 1. 获取所有 POST 参数
            $param = $request->post();

            // 2. 获取附件数据并从主参数中移除
            $attachments = $param['attachments'] ?? [];
            if (!is_array($attachments)) {
                $attachments = [];
            }
            unset($param['attachments']);

            // 【新增】获取前端显式传递的已删除附件 ID 列表
            $deletedIdsStr = $param['_deleted_attach_ids'] ?? '';
            $explicitDelIds = [];
            if (!empty($deletedIdsStr)) {
                // 将逗号分隔的字符串转为整数数组
                $explicitDelIds = array_map('intval', explode(',', $deletedIdsStr));
                $explicitDelIds = array_filter($explicitDelIds); // 过滤掉 0
            }
            unset($param['_deleted_attach_ids']); // 清理参数，避免写入主表

            // 3. 获取 ID (兼容新增和修改)
            $tenderId = (int)($param['id'] ?? $request->param('id', 0));

            // 4. 构建更新/插入数据
            $saveData = [];
            $fieldsConfig = [
                'month' => 'string', 'tender_leader' => 'string', 'customer_name' => 'string',
                'project_name' => 'string', 'tender_agency' => 'string', 'project_cycle' => 'string',
                'shortlisted_countries' => 'string', 'non_tender_reason' => 'string',
                'deposit_account_name' => 'string', 'deposit_bank' => 'string', 'deposit_account_no' => 'string',
                'bid_result' => 'string',
                'budget_amount' => 'float', 'tender_document_fee' => 'float', 'tender_deposit' => 'float',
                'bid_service_fee' => 'float', 'sort' => 'int',
                'purchase_date' => 'date', 'bid_opening_date' => 'date','deposit_paid_time'=>'date',
                'is_tender_submitted' => 'enum', 'has_tender_invoice' => 'enum',
                'is_deposit_paid' => 'enum', 'is_deposit_refunded' => 'enum',
            ];

            foreach ($fieldsConfig as $field => $type) {
                if (!array_key_exists($field, $param)) {
                    continue;
                }

                $val = $param[$field];
                if (is_string($val)) {
                    $val = trim($val);
                }

                switch ($type) {
                    case 'float':
                        $saveData[$field] = ($val === '' || $val === null) ? 0.00 : (float)$val;
                        break;
                    case 'int':
                        $saveData[$field] = ($val === '' || $val === null) ? 0 : (int)$val;
                        break;
                    case 'date':
                        $saveData[$field] = ($val === '' || $val === null) ? null : $val;
                        break;
                    case 'enum':
                        $saveData[$field] = in_array($val, ['是', '否']) ? $val : null;
                        break;
                    default:
                        $saveData[$field] = $val;
                        break;
                }
            }

            // 5. 执行数据库操作 (区分新增和修改)
            $success = false;
            $msg = '';
            $finalId = $tenderId;

            if ($tenderId > 0) {
                // --- 修改逻辑 ---
                $oldData = \think\facade\Db::name('project_tender')->where('id', $tenderId)->find();
                if (!$oldData) {
                    return json(['code' => 1, 'msg' => '数据不存在，无法更新', 'data' => []]);
                }

                $saveData['update_time'] = date('Y-m-d H:i:s');
                $res = \think\facade\Db::name('project_tender')->where('id', $tenderId)->update($saveData);

                if ($res === false) {
                    $msg = '数据库更新失败';
                } else {
                    $success = true;
                    $msg = $res > 0 ? '编辑成功' : '保存成功 (数据无变更)';
                }

            } else {
                // --- 新增逻辑 ---
                $saveData['create_time'] = date('Y-m-d H:i:s');
                $saveData['update_time'] = date('Y-m-d H:i:s');
                $saveData['delete_time'] = 0;

                $newId = \think\facade\Db::name('project_tender')->insertGetId($saveData);

                if ($newId) {
                    $success = true;
                    $finalId = $newId;
                    $msg = '新增成功';
                } else {
                    $msg = '数据库新增失败';
                }
            }

            // 6. 附件同步逻辑 (只要有了 finalId 就处理)
            if ($finalId > 0) {
                // 获取数据库中当前所有的有效附件 ID
                $oldAttachIds = \app\projecttender\model\ProjectTenderAttachment::where([
                    'project_tender_id' => $finalId,
                    'delete_time' => 0
                ])->column('id');

                $submitAttachIds = [];

                // 处理提交的附件 (新增或更新)
                if (is_array($attachments) && !empty($attachments)) {
                    foreach ($attachments as $attach) {
                        if (!is_array($attach)) continue;

                        $attachId = isset($attach['id']) ? (int)$attach['id'] : 0;
                        $filePath = trim($attach['file_path'] ?? '');

                        if (empty($filePath)) continue;

                        if ($attachId > 0) {
                            $submitAttachIds[] = $attachId;
                        }

                        $data = [
                            'file_name' => trim($attach['file_name'] ?? ''),
                            'file_path' => $filePath,
                            'file_size' => (int)($attach['file_size'] ?? 0),
                            'file_ext' => trim($attach['file_ext'] ?? ''),
                            'file_mime' => trim($attach['file_mime'] ?? ''),
                            'sort' => (int)($attach['sort'] ?? 0),
                            'update_time' => date('Y-m-d H:i:s')
                        ];

                        if ($attachId > 0) {
                            // 更新已有附件
                            \app\projecttender\model\ProjectTenderAttachment::where('id', $attachId)->update($data);
                        } else {
                            // 新增附件
                            $data['project_tender_id'] = $finalId;
                            $data['create_time'] = date('Y-m-d H:i:s');
                            $data['delete_time'] = 0;
                            \app\projecttender\model\ProjectTenderAttachment::create($data);
                            // 新插入的 ID 不需要加入 submitAttachIds 进行 diff 判断，因为它本来就不在 oldAttachIds 里
                        }
                    }
                }

                // --- 核心删除逻辑 ---

                // 1. 计算隐式删除 (前端没传过来的旧数据，即传统的 array_diff 逻辑)
                $implicitDelIds = array_diff($oldAttachIds, $submitAttachIds);

                // 2. 合并显式删除 (前端明确标记要删的) 和 隐式删除
                $finalDelIds = array_unique(array_merge($explicitDelIds, $implicitDelIds));

                // 3. 执行软删除
                if (!empty($finalDelIds)) {
                    // 安全校验：确保只删除属于当前 project_tender_id 的记录
                    $count = \app\projecttender\model\ProjectTenderAttachment::where('id', 'in', $finalDelIds)
                        ->where('project_tender_id', $finalId)
                        ->update([
                            'delete_time' => time(),
                            'update_time' => date('Y-m-d H:i:s')
                        ]);

                    \think\facade\Log::info('【投标保存】删除附件 ID: ' . implode(',', $finalDelIds) . ', 实际影响行数:' . $count);
                }
            }

            return json([
                'code' => $success ? 0 : 1,
                'msg' => $msg,
                'data' => ['id' => $finalId]
            ]);

        } catch (\Exception $e) {
            \think\facade\Log::error('【投标保存】异常：' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return json(['code' => 1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => []]);
        }
    }




    /**
     * 下载Excel导入模板
     */
    public function exportTemplate()
    {
        try {
            // 创建新的Excel对象 - 修复：使用完整命名空间
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置工作表名称
            $sheet->setTitle('项目投标信息模板');

            // 定义表头
            $headers = [
                '月份', '标书负责人', '购买日期(yyyy-mm-dd)', '客户名称', '项目名称',
                '招标机构', '项目周期', '入围家数', '预算金额（元）', '开标日期(yyyy-mm-dd)',
                '是否投标(是/否)', '未投原因', '标书款（元）', '标书款发票(是/否)',
                '是否缴纳保证金(是/否)', '投标保证金（元）', '保证金账户名', '保证金开户行',
                '保证金账号', '是否退回保证金(是/否)', '中标结果(中标/未中标/待开标)', '中标服务费（元）'
            ];

            // 设置表头
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                // 设置表头样式
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getColumnDimension($col)->setWidth(20);
                $col++;
            }

            // 添加示例数据行
            $exampleData = [
                '2024-01', '张三', '2024-01-05', '测试客户', '测试项目',
                '测试招标机构', '6个月', '5', '100000.00', '2024-02-01',
                '是', '', '500.00', '是',
                '是', '50000.00', '张三', '中国工商银行',
                '622208XXXXXXXXXXXX', '否', '待开标', '0.00'
            ];

            $col = 'A';
            foreach ($exampleData as $value) {
                $sheet->setCellValue($col . '2', $value);
                $col++;
            }

            // 下载文件
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="项目投标信息导入模板.xlsx"');
            header('Cache-Control: max-age=0');
            header('Pragma: public'); // 修复IE浏览器下载问题
            header('Expires: 0');

            // 修复：使用完整命名空间创建Writer对象
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            // 友好的错误提示
            return json(['code' => 1, 'msg' => '模板下载失败：' . $e->getMessage()]);
        }
    }

    /**
     * 导入Excel数据
     */
    /**
     * 导入Excel数据
     */
    public function importExcel()
    {
        try {
            // 检查是否有文件上传
            $file = Request::file('file');
            if (!$file) {
                return json(['code' => 1, 'msg' => '请选择要导入的Excel文件']);
            }

            // 验证文件类型 - 修复：从原始文件名中提取扩展名
            $originalName = $file->getOriginalName(); // 获取原始文件名
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)); // 从原始文件名解析扩展名

            // 兼容.xls和.xlsx格式
            if (!in_array($ext, ['xlsx', 'xls'])) {
                return json(['code' => 1, 'msg' => '请选择Excel格式的文件（.xlsx/.xls），当前文件格式：.' . $ext]);
            }

            // 读取Excel文件 - 使用完整命名空间
            $filePath = $file->getRealPath(); // 获取临时文件路径
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow(); // 获取总行数
            $highestColumn = $sheet->getHighestColumn(); // 获取总列数

            // 验证表头
            $headers = [
                'A' => '月份', 'B' => '标书负责人', 'C' => '购买日期(yyyy-mm-dd)', 'D' => '客户名称',
                'E' => '项目名称', 'F' => '招标机构', 'G' => '项目周期', 'H' => '入围家数',
                'I' => '预算金额（元）', 'J' => '开标日期(yyyy-mm-dd)', 'K' => '是否投标(是/否)',
                'L' => '未投原因', 'M' => '标书款（元）', 'N' => '标书款发票(是/否)',
                'O' => '是否缴纳保证金(是/否)', 'P' => '投标保证金（元）', 'Q' => '保证金账户名',
                'R' => '保证金开户行', 'S' => '保证金账号', 'T' => '是否退回保证金(是/否)',
                'U' => '中标结果(中标/未中标/待开标)', 'V' => '中标服务费（元）'
            ];

            // 验证表头是否正确
            foreach ($headers as $col => $header) {
                $cellValue = $sheet->getCell($col . '1')->getValue();
                $cellValue = $cellValue ?? ''; // 先处理null
                $cellValue = trim($cellValue);
                if ($cellValue !== $header) {
                    return json(['code' => 1, 'msg' => 'Excel模板格式错误，第'.$col.'列应为：'.$header.'，实际为：'.$cellValue]);
                }
            }

            // 开始导入数据
            $successCount = 0;
            $errorMsg = '';

            // 开启事务（现在Db已导入，可正常使用）
            Db::startTrans();

            try {
                // 从第二行开始读取数据（跳过表头）
                for ($row = 2; $row <= $highestRow; $row++) {
                    // 跳过空行
                    $projectNameCell = $sheet->getCell('E' . $row)->getValue();
                    $projectName = trim($projectNameCell ?? ''); // 先处理null再trim
                    if (empty($projectName)) {
                        continue; // 跳过空行，不报错
                    }

                    // 读取一行数据
                    $data = [
                        'month' => trim($sheet->getCell('A' . $row)->getValue() ?? ''),
                        'tender_leader' => trim($sheet->getCell('B' . $row)->getValue() ?? ''),
                        'purchase_date' => $this->formatDate(trim($sheet->getCell('C' . $row)->getValue() ?? '')),
                        'customer_name' => trim($sheet->getCell('D' . $row)->getValue() ?? ''),
                        'project_name' => $projectName,
                        'tender_agency' => trim($sheet->getCell('F' . $row)->getValue() ?? ''),
                        'project_cycle' => trim($sheet->getCell('G' . $row)->getValue() ?? ''),
                        'shortlisted_countries' => $this->formatInt(trim($sheet->getCell('H' . $row)->getValue() ?? '')),
                        'budget_amount' => $this->formatFloat(trim($sheet->getCell('I' . $row)->getValue() ?? '')),
                        'bid_opening_date' => $this->formatDate(trim($sheet->getCell('J' . $row)->getValue() ?? '')),
                        'is_tender_submitted' => $this->formatEnum(trim($sheet->getCell('K' . $row)->getValue() ?? '')),
                        'non_tender_reason' => trim($sheet->getCell('L' . $row)->getValue() ?? ''),
                        'tender_document_fee' => $this->formatFloat(trim($sheet->getCell('M' . $row)->getValue() ?? '')),
                        'has_tender_invoice' => $this->formatEnum(trim($sheet->getCell('N' . $row)->getValue() ?? '')),
                        'is_deposit_paid' => $this->formatEnum(trim($sheet->getCell('O' . $row)->getValue() ?? '')),
                        'tender_deposit' => $this->formatFloat(trim($sheet->getCell('P' . $row)->getValue() ?? '')),
                        'deposit_account_name' => trim($sheet->getCell('Q' . $row)->getValue() ?? ''),
                        'deposit_bank' => trim($sheet->getCell('R' . $row)->getValue() ?? ''),
                        'deposit_account_no' => trim($sheet->getCell('S' . $row)->getValue() ?? ''),
                        'is_deposit_refunded' => $this->formatEnum(trim($sheet->getCell('T' . $row)->getValue() ?? '')),
                        'bid_result' => $this->formatBidResult(trim($sheet->getCell('U' . $row)->getValue() ?? '')),
                        'bid_service_fee' => $this->formatFloat(trim($sheet->getCell('V' . $row)->getValue() ?? '')),
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'delete_time' => 0,
                        'sort' => 0
                    ];

                    // 插入数据（Db已导入，可正常使用）
                    $result = Db::name('project_tender')->insert($data);
                    if ($result) {
                        $successCount++;
                    }
                }

                // 提交事务
                Db::commit();

                return json([
                    'code' => 0,
                    'msg' => '导入成功，共导入 '.$successCount.' 条有效数据',
                    'data' => ['success_count' => $successCount]
                ]);

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['code' => 1, 'msg' => '导入失败：' . $e->getMessage()]);
            }

        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '导入异常：' . $e->getMessage()]);
        }
    }

// 同时修复formatDate方法中的Excel日期转换
    private function formatDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // 处理Excel日期格式 - 修复：使用完整命名空间
        if (is_numeric($value)) {
            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        // 验证日期格式
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
    /**
     * 格式化数字
     */
    private function formatFloat($value)
    {
        if (empty($value) || $value === '-') {
            return 0.00;
        }

        return (float)$value;
    }

    /**
     * 格式化整数
     */
    private function formatInt($value)
    {
        if (empty($value) || $value === '-') {
            return 0;
        }

        return (int)$value;
    }

    /**
     * 格式化枚举值（是/否）
     */
    private function formatEnum($value)
    {
        $value = trim($value);
        if (in_array($value, ['是', '否'])) {
            return $value;
        }

        return null;
    }

    /**
     * 格式化中标结果
     */
    private function formatBidResult($value)
    {
        $value = trim($value);
        if (in_array($value, ['中标', '未中标', '待开标'])) {
            return $value;
        }

        return null;
    }

}