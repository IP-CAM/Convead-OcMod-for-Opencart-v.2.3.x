<?php

class ModelModuleConvead extends Model {

    public $visitor_info = array();

    public function _getBaseURL() {         
        return $_SERVER['HTTP_HOST'];
    }
    
    public function _includeApi() {
        $this->load->model('account/customer');
        if (!($api_key = $this->config->get('convead_apikey'))) return false;
        if ($this->customer->isLogged()) {
            $guest_uid = false;
            $uid = $this->customer->getId();
            $this->getVisitorInfo();
        }
        else {
            $guest_uid = $_COOKIE['convead_guest_uid'];
            $uid = false;
        }
        $convead = new ConveadTracker($api_key, $this->_getBaseURL(), $guest_uid, $uid, (count($this->visitor_info) > 0 ? $this->visitor_info : false));
        return $convead;
    }
    
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
    
    public function updateCart() {
        if (!($convead = $this->_includeApi())) return;
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
        $convead->eventUpdateCart($products_info);
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

    public function orderDelete($order_id)
    {
        // TODOD удаление заказа
    }

    public function orderAdd($order_id)
    {               
        if ($order_id == 0) return false;

        $this->load->model('account/custom_field');
        $this->load->model('checkout/order');
        $this->load->model('account/order');    
        $this->load->model('catalog/product');
            
        $order = $this->model_checkout_order->getOrder($order_id);
        
        // признак нового заказа
        if ($order['order_status_id'] == $this->config->get('config_order_status_id')) {

            if (isset($this->session->data['cnv_last_order_id']) and $this->session->data['cnv_last_order_id'] == $order_id) return false;
            else $this->session->data['cnv_last_order_id'] = $order_id;
        
            $revenue = $order['total'];
            $order_products = array();

            if (trim($order['firstname'])) $this->visitor_info['first_name'] = trim($order['firstname']);
            if (trim($order['lastname'])) $this->visitor_info['last_name'] = trim($order['lastname']);
            if (trim($order['email'])) $this->visitor_info['email'] = trim($order['email']);
            if (trim($order['telephone'])) $this->visitor_info['phone'] = trim($order['telephone']);
        
            if (!($convead = $this->_includeApi())) return;
        
            $products = $this->model_account_order->getOrderProducts($order_id); 
            foreach($products as $product) {
                $order_products[] = array(
                    'product_id' => $product['product_id'],
                    'product_name' => $product['name'],
                    'qnt' => $product['quantity'],
                    'price' => $product['price'],
                    'product_url' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                );
            }
            $convead->eventOrder($order_id, $revenue, $order_products);
        }
    }
}

/**
 * Класс для работы с сервисом convead.io
 */
class ConveadTracker {
    public $version = '1.1.10';

    private $browser;
    private $api_key;
    private $guest_uid;
    private $visitor_info = false;
    private $visitor_uid = false;
    private $referrer = false;
    private $api_page = "https://tracker.convead.io/watch/event";
    private $url = false;
    private $domain = false;
    public $charset = 'utf-8';
    public $debug = false;

    /**
     * 
     * @param type $api_key
     * @param type $domain
     * @param type $guest_uid
     * @param type $visitor_uid
     * @param type $visitor_info структура с параметрами текущего визитора (все параметры опциональные) следующего вида:
      {
        first_name: 'Name',
        last_name: 'Surname',
        email: 'email',
        phone: '1-111-11-11-11',
        date_of_birth: '1984-06-16',
        gender: 'male',
        language: 'ru',
        custom_field_1: 'custom value 1',
        custom_field_2: 'custom value 2',
        ...
      }
     * @param type $referrer
     * @param type $url
     */
    public function __construct($api_key, $domain, $guest_uid, $visitor_uid = false, $visitor_info = false, $referrer = false, $url = false) {
        $this->browser = new ConveadBrowser();
        $this->api_key = (string) $api_key;
        
        $domain_encoding = mb_detect_encoding($domain, array('UTF-8', 'windows-1251'));
        $this->domain = (string) mb_strtolower( (($domain_encoding == 'UTF-8') ? $domain : iconv($domain_encoding, 'UTF-8', $domain)) , 'UTF-8');
        
        $this->guest_uid = (string) $guest_uid;
        $this->visitor_info = $visitor_info;
        $this->visitor_uid = (string) $visitor_uid;
        $this->referrer = (string) $referrer;
        $this->url = (string) $url;
    }

    private function getDefaultPost() {
        $post = array();
        $post["app_key"] = $this->api_key;
        $post["domain"] = $this->domain;

        if ($this->guest_uid)
            $post["guest_uid"] = $this->guest_uid;
        else
            $post["guest_uid"] = "";

        if ($this->visitor_uid)
            $post["visitor_uid"] = $this->visitor_uid;
        else
            $post["visitor_uid"] = "";

        if ($this->referrer) $post["referrer"] = $this->referrer;
        if (is_array($this->visitor_info) and count($this->visitor_info) > 0) $post["visitor_info"] = $this->visitor_info;
        if ($this->url) {
            $post["url"] = "http://" . $this->url;
            $post["host"] = $this->url;
        }
        return $post;
    }

    /**
     * 
     * @param type $product_id ID товара в магазине (такой же, как в XML-фиде Яндекс.Маркет/Google Merchant)
     * @param type $product_name наименование товара
     * @param type $product_url постоянный URL товара
     */
    public function eventProductView($product_id, $product_name = false, $product_url = false) {
        $post = $this->getDefaultPost();
        $post["type"] = "view_product";
        $post["properties"]["product_id"] = (string) $product_id;
        if ($product_name) $post["properties"]["product_name"] = (string) $product_name;
        if ($product_url) $post["properties"]["product_url"] = (string) $product_url;
        $post = $this->post_encode($post);
        $this->putLog($post);
        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param type $product_id - ID товара в магазине (такой же, как в XML-фиде Яндекс.Маркет/Google Merchant)
     * @param type $qnt количество ед. добавляемого товара
     * @param type $price стоимость 1 ед. добавляемого товара
     * @param type $product_name наименование товара
     * @param type $product_url постоянный URL товара
     * @return boolean
     */
    public function eventAddToCart($product_id, $qnt, $price, $product_name = false, $product_url = false) {
        $post = $this->getDefaultPost();
        $post["type"] = "add_to_cart";
        $post["properties"]["product_id"] = (string) $product_id;
        $post["properties"]["qnt"] = $qnt;
        $post["properties"]["price"] = $price;
        if ($product_name) $post["properties"]["product_name"] = (string) $product_name;
        if ($product_url) $post["properties"]["product_url"] = (string) $product_url;

        $post = $this->post_encode($post);
        $this->putLog($post);
        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param type $product_id ID товара в магазине (такой же, как в XML-фиде Яндекс.Маркет/Google Merchant)
     * @param type $qnt количество ед. добавляемого товара
     * @param type $product_name наименование товара
     * @param type $product_url постоянный URL товара
     * @return boolean
     */
    public function eventRemoveFromCart($product_id, $qnt, $product_name = false, $product_url = false) {
        $post = $this->getDefaultPost();
        $post["type"] = "remove_from_cart";
        $post["properties"]["product_id"] = (string) $product_id;
        $post["properties"]["qnt"] = $qnt;
        if ($product_name) $post["properties"]["product_name"] = (string) $product_name;
        if ($product_url) $post["properties"]["product_url"] = (string) $product_url;

        $post = $this->post_encode($post);
        $this->putLog($post);
        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param type $order_id - ID заказа в интернет-магазине
     * @param type $revenue - общая сумма заказа
     * @param type $order_array массив вида:
      [
          {product_id: <product_id>, qnt: <product_count>, price: <product_price>},
          {...}
      ]
     * @return boolean
     */
    public function eventOrder($order_id, $revenue = false, $order_array = false) {
        $post = $this->getDefaultPost();
        $post["type"] = "purchase";
        $properties = array();
        $properties["order_id"] = (string) $order_id;

        if ($revenue == false) return false;
        else $properties["revenue"] = $revenue;

        if (is_array($order_array)) $properties["items"] = $order_array;

        $post["properties"] = $properties;
        unset($post["url"]);
        unset($post["host"]);
        unset($post["path"]);
        $post = $this->post_encode($post);
        $this->putLog($post);

        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param array $order_array JSON-структура вида:
      [
          {product_id: <product_id>, qnt: <product_count>, price: <product_price>},
          {...}
      ]
     * @return boolean
     */
    public function eventUpdateCart($order_array) {
        $post = $this->getDefaultPost();
        $post["type"] = "update_cart";
        $properties = array();

        $properties["items"] = $order_array;

        $post["properties"] = $properties;

        $post = $this->post_encode($post);
        $this->putLog($post);

        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param string $key - имя кастомного ключа
     * @param array $properties - передаваемые свойства
     * @return boolean
     */
    public function eventCustom($key, $properties = array()) {
        $post = $this->getDefaultPost();
        $post["type"] = "custom";
        $properties["key"] = (string) $key;
        $post["properties"] = $properties;

        $post = $this->post_encode($post);
        $this->putLog($post);

        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     *
     * @return boolean
     */
    public function eventUpdateInfo() {
        $post = $this->getDefaultPost();
        $post["type"] = "update_info";
        $post = $this->post_encode($post);
        $this->putLog($post);
        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    /**
     * 
     * @param string $url - url адрес страницы
     * @param string $title - заголовок страницы
     * @return boolean
     */
    public function view($url, $title) {
        $url = (string) $url;
        $post = $this->getDefaultPost();
        $post["type"] = "link";
        $post["title"] = (string) $title;
        $post["url"] = "http://" . $this->url . $url;
        $post["path"] = $url;

        $post = $this->post_encode($post);

        $this->putLog($post);

        if ($this->browser->get($this->api_page, $post) === true)
            return true;
        else
            return $this->browser->error;
    }

    private function putLog($message) {
        if (!$this->debug) return true;
        $message = date("Y.m.d H:i:s") . " - " . $message . "\n";
        $filename = dirname(__FILE__) . "/debug.log";
        file_put_contents($filename, $message, FILE_APPEND);
    }

    private function post_encode($post) {
        $ret_post = array(
            'app_key' => $post['app_key'],
            'visitor_uid' => $post['visitor_uid'],
            'guest_uid' => $post['guest_uid'],
            'data' => $this->json_encode($post)
          );
        return $this->build_http_query($ret_post);
    }
  
    private function build_http_query($query) {
        $query_array = array();
        foreach( $query as $key => $key_value ){
            $query_array[] = urlencode( $key ) . '=' . urlencode( $key_value );
        }
        return implode('&', $query_array);
    }

    private function json_encode($text) {
        if ($this->charset == "windows-1251") {
            return json_encode($this->json_fix($text));
        } else {
            return json_encode($text);
        }
    }

    private function json_fix($data) {
        # Process arrays
        if (is_array($data)) {
            $new = array();
            foreach ($data as $k => $v) {
                $new[$this->json_fix($k)] = $this->json_fix($v);
            }
            $data = $new;
        }
        # Process objects
        else if (is_object($data)) {
            $datas = get_object_vars($data);
            foreach ($datas as $m => $v) {
                $data->$m = $this->json_fix($v);
            }
        }
        # Process strings
        else if (is_string($data)) {
            $data = iconv('cp1251', 'utf-8', $data);
        }
        return $data;
    }

}


/**
 * Класс для работы с post запросами
 */
class ConveadBrowser {
    public $version = '1.1.3';

    protected $config = array();
    public $error = false;

    public function __initialize() {
        $this->resetConfig();
    }

    public function setopt($const, $val) {
        $this->settings[$const] = $val;
    }

    public function resetConfig() {
        $this->referer = false;
        $this->useragent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:32.0) Gecko/20100101 Firefox/32.0";
        $this->cookie = false;
        $this->userpwd = false;

        $this->timeout = 5;

        $this->proxy = false;
        $this->proxyuserpwd = false;

        $this->followlocation = false;
        $this->maxsize = 0;
        $this->maxredirs = 5;

        $this->encode = false;

        $this->settings = array();
    }

    public function postToString($post) {
        $result = "";
        $i = 0;
        foreach ($post as $varname => $varval) {
            $result .= ($i > 0 ? "&" : "") . urlencode($varname) . "=" . urlencode($varval);
            $i++;
        }

        return $result;
    }

    public function postEncode($post) {
        $result = array();
        foreach ($post as $varname => $varval) {
            $result[urlencode($varname)] = urlencode($varval);
        }

        return $result;
    }

    public function isUAAbandoned($user_agent){
        if(!$user_agent)
            return true;
        $re = "/bot|crawl(er|ing)|google|yandex|rambler|yahoo|bingpreview|alexa|facebookexternalhit|bitrix/i"; 
        
        $matches = array(); 
        preg_match($re, $user_agent, $matches);

        if(count($matches) > 0)
            return true;
        else
            return false;
    }

    public function get($url, $post = false) {
        if($this->isUAAbandoned($_SERVER['HTTP_USER_AGENT']))
            return true;

        if(isset($_COOKIE['convead_track_disable']))
            return 'Convead tracking disabled';

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        } else {
            curl_setopt($curl, CURLOPT_POST, false);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded; charset=utf-8", "Accept:application/json, text/javascript, */*; q=0.01"));

        curl_exec($curl);

        $this->error = curl_error($curl);

        if ($this->error) {

            return $this->error;
        }

        curl_close($curl);

        return true;
    }

}

?>
