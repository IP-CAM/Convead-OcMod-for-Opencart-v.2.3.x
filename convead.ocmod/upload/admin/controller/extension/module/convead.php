<?php

class ControllerExtensionModuleConvead extends Controller {
	
	private $error = array();
	
	public function install() {
		if (strpos(VERSION, '3.') === 0) {
			$this->load->model('setting/event');
			$modelEvent = $this->model_setting_event;
			
			$this->load->model('setting/module');
			$this->model_setting_module->addModule('convead', array('name'=>'Convead', 'status'=>1));
		}
		else if (strpos(VERSION, '2.0.0.0') === false) {
			$this->load->model('extension/event');
			$modelEvent = $this->model_extension_event;
		}
		else {
			$this->load->model('tool/event');
			$modelEvent = $this->model_tool_event;
		}
		
		if (strpos(VERSION, '2.0.') === 0 || strpos(VERSION,'2.1.') === 0) {
			$modelEvent->addEvent('convead', 'post.order.add', 'extension/module/convead/order_add');
			$modelEvent->addEvent('convead', 'post.order.history.add', 'extension/module/convead/order_history_add');
			$modelEvent->addEvent('convead', 'post.order.delete', 'extension/module/convead/order_delete');
		}
		else {
			$modelEvent->addEvent('convead', 'catalog/model/checkout/order/addOrder/after' , 'extension/module/convead/order_add_2_2');
			$modelEvent->addEvent('convead', 'catalog/model/checkout/order/editOrder/after' , 'extension/module/convead/order_update_2_2');
			$modelEvent->addEvent('convead', 'catalog/model/checkout/order/addOrderHistory/after' , 'extension/module/convead/order_update_2_2');
		}
				
		$this->addCustomField();
	}
	
	public function uninstall() {
		if (strpos(VERSION, '3.') === 0) {
			$this->load->model('setting/event');
			$modelEvent = $this->model_setting_event;
		}
		else if (strpos(VERSION,'2.0.0.0') === false) {
			$this->load->model('extension/event');
			$modelEvent = $this->model_extension_event;
		}
		else {
			$this->load->model('tool/event');
			$modelEvent = $this->model_tool_event;
		}
		$modelEvent->deleteEvent('convead');
		
		$this->deleteCustomField();
	}
	
	public function customErrorHandler($errno, $errstr, $errfile, $errline) {
		if (!(error_reporting() & $errno)) return;
		
		throw new \Exception($errstr);
	}
	
	private function addCustomField() {
		$old_handler = set_error_handler(array($this, 'customErrorHandler'), E_USER_NOTICE);
		
		try {
			$this->load->model('sale/custom_field');
			$model_custom_field = $this->model_sale_custom_field;
		}
		catch(\Exception $e) {		
			try {
				$this->load->model('customer/custom_field');
				$model_custom_field = $this->model_customer_custom_field;
			}
			catch(\Exception $ex){}				
		}
		set_error_handler($old_handler);
		
		if (isset($model_custom_field)) {
			$custom_fields = $model_custom_field->getCustomFields();
			
			$convead_uid_field = array_filter($custom_fields, function($custom_field){
				if ($custom_field['name'] == 'convead_uid')
				return $custom_field;
			});
			
			if (!$convead_uid_field) {
				$data =array("type"=>"text", "location"=>"account", "value"=>"convead", "status"=>"0", "sort_order"=>"0");
				set_error_handler(array($this, 'customErrorHandler'), E_USER_NOTICE);
				try {
					$this->load->model('sale/customer_group');
					$model_customer_group = $this->model_sale_customer_group;
				}
				catch(\Exception $e) {
					try {
						$this->load->model('customer/customer_group');
						$model_customer_group = $this->model_customer_customer_group;
					}
					catch(\Exception $ex){}
				}
				set_error_handler($old_handler);

				$customer_groups = $model_customer_group->getCustomerGroups();
				foreach ($customer_groups as $customer_group) {
					$data['custom_field_customer_group'][] = array('customer_group_id' => $customer_group['customer_group_id']);
				}
				
				$this->load->model('localisation/language');
				$languages = $this->model_localisation_language->getLanguages();
				foreach ($languages as $language) {
					$data['custom_field_description'][$language['language_id']] = array("name"=>"convead_uid");
				}
				
				$custom_fields = $model_custom_field->addCustomField($data);
			}
		}
	}
	
	private function deleteCustomField()
	{
		$old_handler = set_error_handler(array($this,'customErrorHandler'), E_USER_NOTICE);
		
		try {
			$this->load->model('sale/custom_field');
			$model_custom_field = $this->model_sale_custom_field;
		}
		catch(\Exception $e) {
			try {
				$this->load->model('customer/custom_field');
				$model_custom_field = $this->model_customer_custom_field;
			}
			catch(\Exception $ex){}
		}
		set_error_handler($old_handler);
		
		if ($model_custom_field) {
			$custom_fields = $model_custom_field->getCustomFields();
			
			$convead_uid_field = array_filter($custom_fields, function($custom_field) {
				if ($custom_field['name'] == 'convead_uid')
				return $custom_field;
			});			
			
			if ($convead_uid_field) {
				$model_custom_field->deleteCustomField($convead_uid_field);
			}
		}
	}
	
	public function index() {
		$this->load->language('extension/module/convead');
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('convead', $this->request->post);
			$this->response->redirect($this->url->link('extension/module/convead', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}
		
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');		
		
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_app_key'] = $this->language->get('entry_app_key');
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		}
		else {
			$data['error_warning'] = '';
		}
		
		if (!function_exists('curl_exec')) $data['error_warning'] .= $this->language->get('curl_disable');
		
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_module'),
			'href' => $this->url->link('extension/module', 'user_token=' . $this->session->data['user_token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('module/category', 'user_token=' . $this->session->data['user_token'], 'SSL')
		);
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		
		$data['action'] = $this->url->link('extension/module/convead', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/module', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		if (isset($this->request->post['convead_status'])) {
			$data['convead_status'] = $this->request->post['convead_status'];
		}
		else {
			$data['convead_status'] = $this->config->get('convead_status');
		}
		
		if (isset($this->request->post['convead_app_key'])) {
			$data['convead_app_key'] = $this->request->post['convead_app_key'];
		}
		else {
			$data['convead_app_key'] = $this->config->get('convead_app_key');
		}
		
		$data['curl_exec'] = function_exists('curl_exec');
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$this->response->setOutput($this->load->view('extension/module/convead', $data));
	}
	
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/convead')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}
