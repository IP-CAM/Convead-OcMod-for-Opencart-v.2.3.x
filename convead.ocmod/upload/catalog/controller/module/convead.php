<?php  

class ControllerModuleConvead extends Controller {

	public function index() {
		$this->load->language('module/convead');
		$this->load->model('setting/setting');
		$this->load->model('module/convead');
		$this->load->model('account/customer');
		
		if (!($this->config->get('convead_status') && $apikey = $this->config->get('convead_apikey'))) return false;
		
		$data['apikey'] = $this->config->get('convead_apikey');
		
		$data['visitor_uid'] = false;
		
		if ($this->customer->isLogged()) {
			$data['visitor_uid'] = $this->customer->getId();
			$data['visitor_info'] = $this->model_module_convead->getVisitorInfo();
		}
		
		$data['view_product'] = isset($this->session->cnv_view_product) ? $this->session->cnv_view_product : false;
		
		$template_path = '/template/module/convead.tpl';
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template').$template_path )) return $this->load->view($this->config->get('config_template').$template_path, $data);
		else return $this->load->view('default/'.$template_path, $data);
	}
	
	
	// создан заказ, но не подтвержден посетителем
	public function order_add($order_id) {
	}
	
	public function order_add_2_2($route, $order_id) {	
		if ($order_id > 0) {		
			$this->load->model('module/convead');	
			$this->model_module_convead->orderAdd($order_id);
		}
	}
	
	// заказ подтвержден или изменен его статус
	public function order_history_add($order_id) {
		if ($order_id > 0) {	
			$this->load->model('module/convead');
			$this->model_module_convead->orderAdd($order_id);
		}
	}
	
	// заказ удален
	public function order_delete($order_id) {
		if ($order_id > 0) {	
			$this->load->model('module/convead');		
			$this->model_module_convead->orderDelete($order_id);
		}
	}

}
