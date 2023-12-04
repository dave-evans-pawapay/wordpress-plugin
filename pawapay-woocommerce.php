<?php
/*
Plugin Name: pawaPay
Description: pawaPay Payment Gateway for WooCommerce
Author: Dave Evans
Author URI: https://www.pawapay.io

This is a plugin for use with wooCommerce, and integrates pawaPay Mobile Money interface into wooCommerce Payment services.
This plugin is provided as is, as a demonstration of how to integrated to the pawaPay RESTful APIs.  
Refer here for latest pawapay docs https://docs.pawapay.co.uk/

to use this plugin
Install Wordpress
Install woocommerce plugin
Install this plugin and activate it
Go into woocommerce settings -> payment options and finish setup
You will be asked for your sandbox and production tokens - please get these from the pawaPay Customer Panel.
Enter the return URL - this is the web site address of your wodpress instance in form of https://www.yoursite.com
You can enable sandbox mode to test the implmentation

Mobile Money payment options should then be available in the woocommerce check out page


*/
add_filter( 'woocommerce_payment_gateways', 'pawapay_class' );
  function pawapay_class( $gateways ) {
  $gateways[] = 'WC_pawapay_Gateway';
  return $gateways;
}
add_action( 'plugins_loaded', 'pawapay_init_gateway_class' );
function pawapay_init_gateway_class() {
  class WC_pawapay_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $this->id = 'pawapay';
      $this->icon = '';
      $this->has_fields = false;
      $this->method_title = 'pawaPay';
      $this->method_description = 'Accepts payments with the pawaPay Mobile Money for WooCommerce';
      $this->supports = array('products');
      $this->init_form_fields();
      $this->init_settings();
      $this->enabled = $this->get_option( 'enabled' );
      $this->title = $this->get_option( 'title' );
      $this->description = $this->get_option( 'description' );
      $this->sandBoxApiToken = $this->get_option( 'sandBoxApiToken' );
      $this->productionApiToken = $this->get_option( 'productionApiToken' );
      $this->returnUrl = $this->get_option('returnUrl');
      $this->environment = $this->get_option( 'environment' );
      
      
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_api_callback', array( $this, 'webhook' ) );
    }
    public function init_form_fields(){
      $this->form_fields = array(
        'enabled' => array(
          'title'       => 'Enable/Disable',
          'label'       => 'Enable pawaPay',
          'type'        => 'checkbox',
          'description' => '',
          'default'     => 'no'
        ),
        'title' => array(
          'title'       => 'Title',
          'type'        => 'text',
          'description' => 'pawaPay Mobile Money',
          'default'     => 'pawaPay'
        ),
        'description' => array(
          'title'       => 'Description',
          'type'        => 'textarea',
          'description' => 'Integrate to mobile money across Africa',
          'default'     => 'Integrate to mobile money across Africa.'
        ),
        'sandBoxApiToken' => array(
          'title'       => 'Sandbox API Token',
          'type'        => 'text',
          'description'       => 'Enter your pawaPay Sandbox API Token'
        ),
        'productionApiToken' => array(
          'title'       => 'Production API Token',
          'type'        => 'text',
          'description'       => 'Enter your pawaPay Production API Token'
        ),
        'environment' => array(
          'title'		=> __( 'pawaPay Sandbox Mode', 'pawapay' ),
          'label'		=> __( 'Enable Sandbox Mode', 'pawapay' ),
          'type'		=> 'checkbox',
          'description' => __( 'This is the sandbox mode of gateway.', 'pawapay' ),
          'default'	=> 'no',
        )

      );
    }
    public function process_payment( $order_id ) {
      
      global $woocommerce;
      $order = new WC_Order( $order_id );
     	// checking for transaction Envrionment
		  $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
      	// Decide which URL to post to
		  $environment_url = ( "FALSE" == $environment ) 
                        ? 'https://stage.paywith.pawapay.io/api/v1/sessions'
                        : 'https://stage.paywith.pawapay.io/api/v1/sessions';
      $key =    ( "FALSE" == $environment ) 
      ? $this->productionBoxApiToken
      : $this->sandBoxApiToken; 

      $data = random_bytes(16);
   
      // Set version to 0100
      $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
      // Set bits 6-7 to 10
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
  
      // Output the 36 character UUID.
      $uuid =  vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));


     /* 
     IMPORTANT - the return url is your wordpress site address in form https://www.yoursite.com
     */

      add_post_meta($order->id, '_deposit_id', $uuid);
      $payload = array(
                          "depositId"				=> $uuid,
                          "returnUrl"				=> $this->returnUrl . "/?wc-api=CALLBACK&id=" . $order_id
                      );
                        
      if ($order->get_billing_phone() != null ) {
        $payload["msisdn"]=$order->get_billing_phone();
      }  
      if ($order->get_total() != null ) {
        $payload["amount"]=strval($order->get_total());
      }  
      if ($order->get_billing_country() != null ) {
        switch (strtoupper($order->get_billing_country())){
          case 'GH': 
            $payload["country"]='GHA';
            break;
          case 'BJ': 
              $payload["country"]='BEN';
              break;  
          case 'CM': 
              $payload["country"]='CMR';
              break; 
          case 'CI': 
              $payload["country"]='CIV';
              break;      
          case 'DEMOCRATIC REPUBLIC OF THE CONGO': 
              $payload["country"]='CIV';
              break;    
          case 'KE': 
              $payload["country"]='KEN';
              break;   
          case 'MW': 
              $payload["country"]='MAL';
              break;          
          case 'RW': 
              $payload["country"]='RWA';
              break;                  
          case 'SN': 
              $payload["country"]='SEN';
              break;            
          case 'TZ': 
              $payload["country"]='TZA';
              break;     
          case 'UG': 
              $payload["country"]='UGA';
              break;     
          case 'ZM': 
              $payload["country"]='ZAM';
              break;     
          default: 
            $payload["country"]='GHA';
            break;
          
        }
      }                  
      // Send to pawaPay payment page
      $response = wp_remote_post( $environment_url, array(
                          'method'    => 'POST',
                          'body'      => json_encode( $payload , JSON_UNESCAPED_SLASHES),
                          'headers'   => 'Authorization: Bearer ' . $key,
                          'timeout'   => 90,
                          'sslverify' => false,
                        ) );
      if ( is_wp_error( $response ) ) {
        wc_add_notice(  'Error from pawaPay' , 'error' ); 
        throw new Exception( __( 'pawaPay\'s Response was not get any data.', 'pawapay' ) );
      }
                    
      if ( empty( $response['body'] ) ){
          wc_add_notice(  'Error from pawaPay' , 'error' ); 
          throw new Exception( __( 'pawaPay\'s Response was not get any data.', 'pawapay' ) );
      }
                          
      // get body response while get not error
      $response_body = wp_remote_retrieve_body( $response );    
      $data = json_decode($response_body);
      if ($data->redirectUrl == null){
        wc_add_notice(  'Error:' . $data->errorMessage , 'error' ); 
        throw new Exception( __( 'pawaPay\'s Response was not get any data.', 'pawapay' ) );
      }
      return [
        'result' => 'success', // return success status
        'redirect' => $data->redirectUrl, // web page url to redirect
    ];
    }


    public function webhook() {
      global $woocommerce;
      header( 'HTTP/1.1 200 OK' );
      $order_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
      if (!$order_id){
        wc_add_notice(  'Error: No order id' , 'error' ); 
        wp_redirect(wc_get_checkout_url() );
        return ;
      }

     
      $order = new WC_Order( $order_id );
      $deposit_id = isset($_REQUEST['depositId']) ? $_REQUEST['depositId'] : null;
      $order_deposit_id = implode(get_post_meta($order->id, '_deposit_id'));

      
      if ( $order_deposit_id != $deposit_id) {
        wc_add_notice(  'Error Transactions does not match' , 'error' ); 
        wp_redirect(wc_get_checkout_url());
        return ;
      }
      $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
      $environment_url = ( "FALSE" == $environment ) 
      ? 'https://api.pawapay.cloud/deposits/'
      : 'https://api.sandbox.pawapay.cloud/deposits/';

      $key =    ( "FALSE" == $environment ) 
      ? $this->productionBoxApiToken
      : $this->sandBoxApiToken; 
      $response = wp_remote_get( $environment_url . $deposit_id, array(
        'headers'   => 'Authorization: Bearer ' . $key,
        'timeout'   => 90,
        'sslverify' => false,
      ) );

      if ( is_wp_error( $response ) ) {
          wc_add_notice(  'Please try again. Error connecting to pawaPay' , 'error' );      
          wp_redirect(wc_get_checkout_url() );  
          return ;
        }

      if ( empty( $response['body'] ) ){
          wc_add_notice(  'Error Transaction not found' , 'error' ); 
          wp_redirect(wc_get_checkout_url() );
          return ;
      }
                          
      // get body response while get not error
      $response_body = wp_remote_retrieve_body( $response );     
      $data = json_decode($response_body);
      if (strlen($response_body) == 0 or empty($data) or $data == null or empty($data[0]) or empty($data[0]->status)) {
        wc_add_notice(  'Please try again. Error: no response returned' , 'error' );
        wp_redirect(wc_get_checkout_url() );
        return ;
      }
    
      $status = $data[0]->status;
    

      if ($status != "COMPLETED"){
        wc_add_notice(  'Please try again. Error: ' . $data[0].failureReason , 'error' );
        wp_redirect(wc_get_checkout_url() );
        return ;
      }
      $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
      $order->payment_complete(); 
      $woocommerce->cart->empty_cart();
      wp_redirect($this->get_return_url( $order ) );
    }
  }
}