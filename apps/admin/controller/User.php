<?php
// 用户管理控制器
// +----------------------------------------------------------------------
// | PHP version 5.4+                
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2016 http://www.eacoomall.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 心云间、凝听 <981248356@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;
use app\admin\builder\Builder;

use app\common\model\User as UserModel;

class User extends Admin {

    function _initialize()
    {
        parent::_initialize();

        $this->user_model = new UserModel;
    }

    //用户列表
    public function index(){
        // 搜索
        $keyword = $this->input('keyword');
        if ($keyword) {
            $this->user_model->where('uid|username|nickname','like','%'.$keyword.'%');
        }
        // 获取所有用户
        $map['status'] = ['egt', '0']; // 禁用和正常状态
        list($data_list,$page) = $this->user_model->getListByPage($map,'reg_time desc','*',20);
        foreach($data_list as $k=>$user){
            $data_list[$k]['role_name']= isset(get_role_info($user['uid'],'title')[0]['title']) ? get_role_info($user['uid'],'title')[0]['title']: '无';//获取角色名
        }
        
        $send_message_attr['title']   = '<i class="fa fa-comment-o"></i> 发送消息';
        $send_message_attr['class']   = 'btn btn-info btn-raised btn-sm';
        $send_message_attr['onclick'] ='send_msg()';
        $message_html                 = $this->sendMessageHtml();

        //移动按钮属性
        $move_role_attr['title'] = '<i class="fa fa-users"></i> 变更角色';
        $move_role_attr['class'] = 'btn btn-info btn-sm';
        $move_role_attr['onclick'] = 'role_move()';
        $Role_html=$this->moveRoleHtml();//添加移动按钮html

        $extra_html=$message_html.$Role_html;

        Builder::run('List')
                ->setMetaTitle('用户管理') // 设置页面标题
                ->addTopButton('addnew')  // 添加新增按钮
                // ->addTopButton('resume')  // 添加启用按钮
                // ->addTopButton('forbid')  // 添加禁用按钮
                ->addTopButton('delete')  // 添加删除按钮
                ->addTopButton('self', $send_message_attr) //发送消息按钮按钮
                ->addTopButton('self', $move_role_attr)//添加移动角色按钮
                ->setSearch('请输入ID/用户名/昵称','')
                ->keyListItem('uid', 'UID')
                ->keyListItem('avatar', '头像', 'avatar')
                ->keyListItem('nickname', '昵称')
                ->keyListItem('username', '用户名')
                ->keyListItem('role_name', '角色')
                ->keyListItem('email', '邮箱')
                ->keyListItem('mobile', '手机号')
                ->keyListItem('reg_time', '注册时间')
                ->keyListItem('status', '状态', 'array',[0=>'禁用',1=>'正常',2=>'待验证'])
                ->keyListItem('right_button', '操作', 'btn')
                ->setListDataKey('uid')
                ->setExtraHtml($extra_html)
                ->setListData($data_list)    // 数据列表
                ->setListPage($page) // 数据列表分页
                ->addRightButton('edit')//->addRightButton('forbid')
                ->addRightButton('delete')  // 添加编辑按钮
                ->fetch();
    }

    /**
     * 编辑用户
     */
    public function edit($uid = 0) {
        $title = $uid ? "编辑" : "新增";
        if (IS_POST) {
            // 密码为空表示不修改密码
            if ($_POST['password'] === '') {
                unset($_POST['password']);
            }

            $data = $this->input('post.');
            $uid  = isset($data['uid']) ? intval($data['uid']) : false;
            // 提交数据
            $result = $this->user_model->editData($data,$uid,'uid');

            if ($result) {
                if ($uid>0) {//如果是编辑状态下
                    $this->user_model->update_login_session($uid);
                }
                $this->success($title.'成功', url('index'));
            } else {
                $this->error($this->user_model->getError());
            }

        } else {
            $info=[];
            // 获取账号信息
            if ($uid!=0) {
                $info = $this->user_model->get($uid);
                unset($info['password']);
            }

            // 使用FormBuilder快速建立表单页面。
            $builder = Builder::run('Form');
            $builder->setMetaTitle($title.'用户');  // 设置页面标题
            if ($uid!=0) {
                $builder->addFormItem('uid', 'hidden', 'ID', '');
            }

            $builder->addFormItem('nickname', 'text', '昵称', '填写一个有个性的昵称吧','','required')
                    ->addFormItem('username', 'text', '用户名', '登录账户所用名称','','required')
                    ->addFormItem('password', 'password', '密码', '','','','placeholder="留空则不修改密码"')
                    ->addFormItem('email', 'email', '邮箱', '','','required')
                    ->addFormItem('mobile', 'left_icon_number', '手机号', '',['icon'=>'<i class="fa fa-phone"></i>'],'','placeholder="填写手机号"')
                    ->addFormItem('sex', 'radio', '性别', '',[0=>'保密',1=>'男',2=>'女'])
                    ->addFormItem('description', 'textarea', '个人说明', '请填写个人说明');
                    if ($uid>0) {
            $builder->addFormItem('avatar', 'avatar', '头像', '用户头像默认随机分配',['uid'=>$info['uid']],'required');
                    }
            $builder->setFormData($info)
                    ->addButton('submit')->addButton('back')    // 设置表单按钮
                    ->fetch();
        }
    }
    /**
     * 构建列表发送消息按钮
     * @author 心云间、凝听 <981248356@qq.com>
     */
    protected function sendMessageHtml(){
        //$sendmsg_url=url('user/AdminUser/send_message',['from_uid'=>is_login()]);
        $sendmsg_url='';
        return <<<EOF
            <div class="modal fade mt100" id="sendmsgModal">
                <div class="modal-dialog ">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">关闭</span></button>
                            <p class="modal-title tc">发送站内信</p>
                        </div>
                        <div class="modal-body">
                        <form action="{$sendmsg_url}" method="post" class="form-msg form-horizontal">
                            <fieldset>
                            <div class="form-group item_uids ">
                                <label for="to_uids" class="col-md-3 control-label">发送对象：</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" name="to_uids" value="" placeholder="留空则为群发所有用户">                          
                                </div>
                            </div>
                            <div class="form-group item_title ">
                                <label for="title" class="col-md-3 control-label">标题：</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" name="title" value="" placeholder="必填">                          
                                </div>
                            </div>
                            <div class="form-group item_content ">
                                <label for="content" class="col-md-3 control-label">消息内容：</label>
                                <div class="col-md-8">
                                    <textarea name="content" class="form-control" length="120" rows="5" placeholder="填写消息内容"></textarea>
                                
                                </div>
                            </div>
                            <div class="form-group tc">
                                <input type="hidden" name="ids">
                                <button class="btn btn-primary submit ajax-post" type="submit" target-form="form-msg">发 送</button>

                          </div>

                         </fieldset>
                    </form>
                        </div>
                    </div>
                </div>
            </div>
            <script type="text/javascript">
                function send_msg(){
                    var ids = '';
                    $('input[name="ids[]"]:checked').each(function(){
                       ids += ',' + $(this).val();
                    });
                    if(ids != ''){
                        ids = ids.substr(1);
                        $('input[name="to_uids"]').val(ids);
                        $('.modal-title').html('发送站内信');
                        $('#sendmsgModal').modal('show', 'fit')
                    }else{
                        updateAlert('请选择要发送的用户', 'warning');
                    }
                }
            </script>
EOF;
    }

    /**
     * 构建列表移动角色按钮
     * @author 心云间、凝听 <981248356@qq.com>
     */
    protected function moveRoleHtml(){
            $auth_group = db('auth_group')->where(['status'=>1])->column('title','id');
            //构造移动文档的目标分类列表
            $options = '';
            foreach ($auth_group as $key => $val) {
                $options .= '<option value="'.$key.'">'.$val.'</option>';
            }
            //文档移动POST地址
            $move_url = url('Auth/addToGroup');

            return <<<EOF
            <div class="modal fade mt100" id="moveroleModal">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">关闭</span></button>
                            <p class="modal-title">移动至</p>
                        </div>
                        <div class="modal-body">
                            <form action="{$move_url}" method="post" class="form-move" enctype="application/x-www-form-urlencoded">
                                <div class="form-group">
                                    <select name="group_id" class="form-control">{$options}</select>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="uids">
                                    <input type="hidden" name="batch">
                                    <button class="btn btn-primary btn-block submit ajax-post" type="submit" target-form="form-move">确 定</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script type="text/javascript">
                function role_move(){
                    var ids = '';
                    $('input[name="ids[]"]:checked').each(function(){
                       ids += ',' + $(this).val();
                    });
                    if(ids != ''){
                        ids = ids.substr(1);
                        $('input[name="uids"]').val(ids);
                        $('.modal-title').html('移动选中的用户至：');
                        $('#moveroleModal').modal('show', 'fit')
                    }else{
                        updateAlert('请选择需要修改角色的用户', 'warning');
                    }
                }
            </script>
EOF;
    }
    
    /**
     * 个人资料
     * @param  integer $uid [description]
     * @return [type] [description]
     * @date   2017-08-20
     * @author 赵俊峰 <981248356@qq.com>
     */
    public function profile($uid = 0) {
        $this->assign('meta_title','个人资料');
        $this->assign('hide_panel',true);//隐藏base模板面板
        if (IS_POST) {
            // 密码为空表示不修改密码
            // if ($_POST['password'] === '') {
            //     unset($_POST['password']);
            // }
            $data = $this->input('post.');
            
            // 提交数据
            $result = $this->user_model->editData($data,$uid,'uid');
            if ($result) {
                if ($uid) {//如果是编辑状态下
                    $this->user_model->updateLoginSession($uid);
                }

                $this->success('提交成功', url('profile',['uid'=>$uid]));
            } else {

                $this->error($this->user_model->getError());
            }
        } else {
            // 获取账号信息
            if ($uid>0) {
                $user_info = get_user_info($uid);
                unset($user_info['password']);
                unset($user_info['auth_group']['max']);
            }
            $this->assign('user_info',$user_info);
            return $this->fetch();
        }
    }

    /**
     * 个人资料修改密码
     */  
    public function resetPassword(){
        if (IS_POST) {
            //$oldpassword=input('post.oldpassword',false);
            $newpassword = $this->input('post.newpassword',false);
            $repassword  = $this->input('post.repassword',false);
            if ($newpassword == $repassword) {
                $uid          =$this->input('post.uid',is_login(),'intval');
                $new_password =encrypt($newpassword);
                $res= model('users')->where(['uid'=>$uid])->setField('password',$new_password);
                if ($res) {
                    session(null);
                    $this->success('密码修改成功', url('admin/public/login'));
                }
            }
        } else {
            // 获取账号信息
            $info = $this->user_model->find(is_login());

            Builder::run('Form')->setMetaTitle('重置密码')  // 设置页面标题
                    //->addFormItem('oldpassword', 'password', '原密码', '','','','placeholder="填写旧密码"')
                    ->addFormItem('newpassword', 'password', '新密码', '','','','placeholder="填写新密码"')
                    ->addFormItem('repassword', 'password', '重复密码', '','','','placeholder="填写重复密码"')
                    ->setFormData($info)
                    ->addButton('submit')->addButton('back')    // 设置表单按钮
                    ->fetch();
        }
    }

    /**
     * 设置用户的状态
     */
    public function setStatus($model = CONTROLLER_NAME,$script=false){
        $ids = $this->input('request.ids/a');
        if (is_array($ids)) {
            if(in_array('1', $ids)) {
                $this->error('超级管理员不允许操作');
            }
        }else{
            if($ids === '1') {
                $this->error('超级管理员不允许操作');
            }
        }
        parent::setStatus($model);
    }

}