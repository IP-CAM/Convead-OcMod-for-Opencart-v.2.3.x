<?php  

class ControllerExtensionModuleConvead extends Controller {

	public function index() {
		$this->load->language('extension/module/convead');
		$this->load->model('setting/setting');
		$this->load->model('extension/module/convead');
		$this->load->model('account/customer');
		
		if (!($app_key = $this->config->get('convead_app_key'))) return false;
		
		$data['app_key'] = $app_key;
		
		$data['visitor_uid'] = false;
		
		if ($this->customer->isLogged()) {
			$data['visitor_uid'] = $this->customer->getId();
			$data['visitor_info'] = $this->model_extension_module_convead->getVisitorInfo();
		}
		
		$data['view_product'] = isset($this->session->cnv_view_product) ? $this->session->cnv_view_product : false;
		
		$template_path = 'extension/module/convead.tpl';
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template').$template_path )) return $this->load->view($this->config->get('config_template').$template_path, $data);
		else return $this->load->view($template_path, $data);
	}
	
	// создан заказ, но не подтвержден посетителем
	public function order_add($order_id) {
	}
	
	public function order_add_2_2($route, $data, $order_id) {	
		/*
		Если заказ создан модулем simplecheckout, то игнорируем этот вызов
		т.к. создание заказа происходит до окончания его оформления.
		Отправка события покупки будет выполнено через код-модификатор
		внутри кода simplecheckout.
		*/
		if ($order_id > 0 && empty($this->session->data['simple_cart_id'])) {		
			$this->load->model('extension/module/convead');	
			$this->model_extension_module_convead->orderAdd($order_id);
		}
	}
	
	// изменен заказ
	public function order_update_2_2($route, $data) {	
		if (!empty($data[0])) {
			$order_id = $data[0];
			$this->load->model('extension/module/convead');	
			$this->model_extension_module_convead->orderState($order_id);
		}
	}
	
	// заказ подтвержден или изменен его статус
	public function order_history_add($order_id) {
		if ($order_id > 0) {	
			$this->load->model('extension/module/convead');
			$this->model_extension_module_convead->orderAdd($order_id);
		}
	}
	
	// заказ удален
	public function order_delete($order_id) {
		if ($order_id > 0) {	
			$this->load->model('extension/module/convead');		
			$this->model_extension_module_convead->orderDelete($order_id);
		}
	}

}
