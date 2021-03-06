<?php
namespace app\common\behavior;

use think\Hook;
class InitHook {

	public function run(&$request) {
		//未安装时不执行
		if (substr(request()->pathinfo(), 0, 7) != 'install' && is_file(APP_PATH . 'database.php')) {

			//扩展插件
			\think\Loader::addNamespace('addons', ROOT_PATH . '/addons/');

			$this->setHook();

			//设置模型内容路由
			//$this->setRoute();
		}
	}

	protected function setHook() {
		$data = cache('hooks');
		if (!$data) {
			$hooks = db('Hooks')->column('name,addons');
			foreach ($hooks as $key => $value) {
				if ($value) {
					$map['status'] = 1;
					$names         = explode(',', $value);
					$map['name']   = array('IN', $names);
					$data          = db('Addons')->where($map)->column('id,name');
					if ($data) {
						$addons = array_intersect($names, $data);
						Hook::add($key, array_map('get_addon_class', $addons));
					}
				}
			}
			cache('hooks', Hook::get());
		} else {
			Hook::import($data, false);
		}
	}

	protected function setRoute() {
		$list = db('Rewrite')->where('status',1)->select();
		foreach ($list as $key => $value) {
			$route[$value['rule']] = $value['url'];
		}
		$map   = array(
			'status' => array('gt', 0),
			'extend' => array('gt', 0),
		);
		//$list = db('Module')->where($map)->field("name,id,title,'' as 'style'")->select();
		// foreach ($list as $key => $value) {
		// 	$route["admin/" . $value['name'] . "/index"]  = "admin/content/index?model_id=" . $value['id'];
		// 	$route["admin/" . $value['name'] . "/add"]    = "admin/content/add?model_id=" . $value['id'];
		// 	$route["admin/" . $value['name'] . "/edit"]   = "admin/content/edit?model_id=" . $value['id'];
		// 	$route["admin/" . $value['name'] . "/del"]    = "admin/content/del?model_id=" . $value['id'];
		// 	$route["admin/" . $value['name'] . "/status"] = "admin/content/status?model_id=" . $value['id'];
		// 	$route[$value['name'] . "/index"]             = "index/content/index?model=" . $value['name'];
		// 	$route[$value['name'] . "/list/:id"]          = "index/content/lists?model=" . $value['name'];
		// 	$route[$value['name'] . "/detail/:id"]        = "index/content/detail?model_id=" . $value['id'];
		// 	$route["/list/:id"]                           = "index/content/category";
		// 	$route["user/" . $value['name'] . "/index"]   = "user/content/index?model_id=" . $value['id'];
		// 	$route["user/" . $value['name'] . "/add"]     = "user/content/add?model_id=" . $value['id'];
		// 	$route["user/" . $value['name'] . "/edit"]    = "user/content/edit?model_id=" . $value['id'];
		// 	$route["user/" . $value['name'] . "/del"]     = "user/content/del?model_id=" . $value['id'];
		// 	$route["user/" . $value['name'] . "/status"]  = "user/content/status?model_id=" . $value['id'];
		// }
		// \think\Route::rule($route);
	}
}