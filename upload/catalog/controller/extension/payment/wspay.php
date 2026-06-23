<?php
class ControllerExtensionPaymentWSPay extends Controller {
  public function index() {
      $data['button_confirm'] = $this->language->get('button_confirm');

    $this->load->model('checkout/order');

        $this->load->language('extension/payment/wspay');

       $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

       $this->load->model('localisation/currency');
      //$this->data['allcurrencies'] = array();
      $results = $this->model_localisation_currency->getCurrencies();



    //live url
    if (!$this->config->get('payment_wspay_test')){
      $data['action'] = 'https://form.wspay.biz/Authorization.aspx';
    }else{
    //test url
      $data['action'] = 'https://formtest.wspay.biz/Authorization.aspx';
    }

    //$data['callback'] = $this->config->get('callback');





      $data['merchant'] = $this->config->get('payment_wspay_merchant');
      $data['password'] = $this->config->get('payment_wspay_password');
        $data['order_id'] = $order_info['order_id'];
        $data['currency'] = $order_info['currency_code'];
         $data['tecaj'] = $order_info['currency_value'];
        $data['description'] = $this->config->get('config_name') . ' - #' . $order_info['order_id'];
        $data['total'] = number_format($order_info['total'],2, ',', '');

        $data['address'] = $order_info['payment_address_1'];
        $data['city'] = $order_info['payment_city'];
        $data['firstname'] = $order_info['payment_firstname'];
        $data['lastname'] = $order_info['payment_lastname'];
        $data['postcode'] = $order_info['payment_postcode'];
        $data['country'] = $order_info['payment_iso_code_2'];
        $data['telephone'] = $order_info['telephone'];
        $data['email'] = $order_info['email'];
          //rate
        $data['creditcardname'] = $this->session->data['payment_method']['credit_card_name'];
        $data['paymentplan'] = $this->session->data['payment_method']['payment_plan'];

        $data['return_url'] = $this->url->link('checkout/success');
    $data['cancel_url'] = $this->url->link('checkout/checkout', '', true);
    $data['return_url'] = $this->url->link('extension/payment/wspay/callback');


        $a= $data['total'];
        $b = str_replace( ',', '', $a );

        //readability

      $keym = $data['merchant'] ;
      $wpass = $data['password'];

      $data['md5']  = md5($keym.$wpass.$data['order_id'].$wpass.$b.$wpass);


      return $this->load->view('extension/payment/wspay', $data);


        $this->render();
    }

    public function callback() {

       $this->load->model('checkout/order');

         $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
             // Lets get wspay response parameters
       $posted = $_REQUEST;

    //print_r($posted);


      // Variables for readability

                $ShopID = $data['merchant'] = $this->config->get('payment_wspay_merchant');
                $SecretKey = $data['password'] = $this->config->get('payment_wspay_password');
                $ShoppingCartID = $posted['ShoppingCartID'];

                $Success = $posted['Success'];

                $ApprovalCode = $posted['ApprovalCode'];



            $str = $ShopID.$SecretKey.$ShoppingCartID.$SecretKey.$Success.$SecretKey.$ApprovalCode.$SecretKey;
            $hash = md5($str);

        if( ($posted['Success'] == 1) && (!empty($posted['ApprovalCode'])) && ($hash == $posted['Signature']) ) {

             $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_wspay_order_status_id'), '', true);

             $order_id =$this->session->data['order_id'];



               $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));

        } else if( $posted['Success'] == 1 && $hash !== $posted['Signature'] ){

            // Kill futher operations
            die( 'Illegal access detected!' );
            /**
             * Transaction Rejected
             */
        } else if( ($posted['ErrorMessage']) == 'ODBIJENO' ) {

            $this->response->redirect($this->url->link('checkout/checkout', '', 'SSL'));
        }
    }







}
?>
