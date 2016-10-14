<?php

class ModelModuleConvead extends Model {
  
  public function updateCart() {
    if (!($tracker = $this->_includeTracker())) return;
    $products_info = array();
    if ($this->cart->hasProducts()) {
      $products = $this->cart->getProducts();
      foreach ($products as $product) {
        $products_info[] = array(
          'product_id' => $product['product_id'],
          'product_name' => htmlspecialchars_decode($product['name']),
          'price' => $product['price'],
          'qnt' => $product['quantity'],
          'product_url' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
        );
      }        
    }
    $tracker->eventUpdateCart($products_info);
  }

  public function productViewed($product_id) {  
    if ($product_id != 0) {
      $this->load->model('catalog/product');
      $product_info = $this->model_catalog_product->getProduct($product_id);
      $this->session->cnv_view_product = array(
        'product_id' => $product_id,
        'product_name' => htmlspecialchars_decode($product_info['name']),
        'price' => $product_info['price'],
        'product_url' => htmlspecialchars_decode($this->url->link('product/product', 'product_id=' . $product_id))
      );
    }
  }

  public function orderDelete($order_id) {
    if (!($tracker = $this->_includeTrackerAnonym())) return;
    $this->visitor_info = false;
    $tracker->webHookOrderUpdate($order_id, 'cancelled');
  }

  public function orderState($order_id, $state) {
    if (!($tracker = $this->_includeTrackerAnonym())) return;
    $this->visitor_info = false;
    $tracker->webHookOrderUpdate($order_id, $this->_switchState($state));
  }

  public function orderAdd($order_id) {        
    if ($order_id == 0) return false;

    $this->load->model('account/custom_field');
    $this->load->model('checkout/order');
    $this->load->model('account/order');  
    $this->load->model('catalog/product');
      
    $order = $this->model_checkout_order->getOrder($order_id);
    
    $revenue = $order['total'];
    $items = array();

    if (trim($order['firstname'])) $this->visitor_info['first_name'] = trim($order['firstname']);
    if (trim($order['lastname'])) $this->visitor_info['last_name'] = trim($order['lastname']);
    if (trim($order['email'])) $this->visitor_info['email'] = trim($order['email']);
    if (trim($order['telephone'])) $this->visitor_info['phone'] = trim($order['telephone']);
  
    $products = $this->model_account_order->getOrderProducts($order_id); 
    foreach($products as $product) {
      $items[] = array(
        'product_id' => $product['product_id'],
        'product_name' => $product['name'],
        'qnt' => $product['quantity'],
        'price' => $product['price'],
        'product_url' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
      );
    }

    // признак нового заказа
    if ($order['order_status_id'] == $this->config->get('config_order_status_id')) {

      if (isset($this->session->data['cnv_last_order_id']) and $this->session->data['cnv_last_order_id'] == $order_id) return false;
      else $this->session->data['cnv_last_order_id'] = $order_id;
      
      if (!($tracker = $this->_includeTracker())) return;
      $tracker->eventOrder($order_id, $revenue, $items, $this->_switchState($order['order_status_id']));
    }
    else if ($order['order_status_id'] > 0) {
      if (!($tracker = $this->_includeTrackerAnonym())) return;
      $tracker->webHookOrderUpdate($order_id, $this->_switchState($order['order_status_id']), $revenue, $items);
    }
  }

  private function _switchState($state) {
    switch ($state) {
      case 1:
        $state = 'new';
        break;
      case 3:
        $state = 'shipped';
        break;
      case 0:
        $state = 'cancelled';
        break;
      case 7:
        $state = 'cancelled';
        break;
    }
    return $state;
  }

  private function _getBaseURL() {     
    return $_SERVER['HTTP_HOST'];
  }
  
  private function _includeTrackerAnonym() {
    include_once 'conveadTracker.php';
    $this->load->model('account/customer');
    $app_key = $this->config->get('convead_app_key');
    if (!$app_key) return false;
    $tracker = new ConveadTracker($app_key);
    return $tracker;
  }

  private function _includeTracker() {
    include_once 'conveadTracker.php';
    $this->load->model('account/customer');
    $app_key = $this->config->get('convead_app_key');
    if (!$app_key) return false;
    if ($this->customer->isLogged()) {
      $guest_uid = false;
      $uid = $this->customer->getId();
      $this->getVisitorInfo();
    }
    else {
      $guest_uid = !empty($_COOKIE['convead_guest_uid']) ? $_COOKIE['convead_guest_uid'] : false;
      $uid = false;
    }
    $tracker = new ConveadTracker($app_key, $this->_getBaseURL(), $guest_uid, $uid, (count($this->visitor_info) > 0 ? $this->visitor_info : false));
    return $tracker;
  }

  public $visitor_info = array();
  
  public function getVisitorInfo() {
    if ($this->customer->isLogged()) {
      $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
      if (!empty($customer_info['firstname'])) $this->visitor_info['first_name'] = $customer_info['firstname'];
      if (!empty($customer_info['lastname'])) $this->visitor_info['last_name'] = $customer_info['lastname'];
      if (!empty($customer_info['email'])) $this->visitor_info['email'] = $customer_info['email'];
      if (!empty($customer_info['telephone'])) $this->visitor_info['phone'] = $customer_info['telephone'];
    }
    return $this->visitor_info;
  }
}
