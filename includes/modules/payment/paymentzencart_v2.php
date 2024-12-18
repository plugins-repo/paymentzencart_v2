<?php
/**
 * Paymentzencart_v2 Payment Module
 *
 * @copyright Copyright
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
class paymentzencart_v2 {

    protected $_check;
    public $code;
    public $description;
    public $enabled;
    public $order_status;
    public $title;
    public $sort_order;

    // Constructor
    function __construct() {
        $this->code = 'Paymentzencart_v2';
        $this->title = MODULE_PAYMENT_PAYMENTZENCART_V2_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYMENTZENCART_V2_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_PAYMENTZENCART_V2_SORT_ORDER') ? MODULE_PAYMENT_PAYMENTZENCART_V2_SORT_ORDER : null;
        $this->enabled = (defined('MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS') && MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS == 'True');
        if (null === $this->sort_order) return false;
        if (defined('MODULE_PAYMENT_PAYMENTZENCART_V2_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_PAYMENTZENCART_V2_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_PAYMENTZENCART_V2_ORDER_STATUS_ID;
        }
    }

    // Validation methods and processing steps
    function javascript_validation() { return false; }
    function selection() { return array('id' => $this->code, 'module' => $this->title); }
    function pre_confirmation_check() { return false; }
    function confirmation() { return false; }

    function process_button() {
        global $order;

        $merchant_id = MODULE_PAYMENT_PAYMENTZENCART_V2_MERCHANT_ID;
        $secret_key = MODULE_PAYMENT_PAYMENTZENCART_V2_SECRET_KEY;
        $partner_name = MODULE_PAYMENT_PAYMENTZENCART_V2_PARTNER_NAME;
        $redirect_url = MODULE_PAYMENT_PAYMENTZENCART_V2_REDIRECT_URL;
        $test_url = MODULE_PAYMENT_PAYMENTZENCART_V2_TEST_URL;

        $order_id = $order->info['orders_id'];
        $amount = number_format($order->info['total'], 2);
        $currency = $order->info['currency'];

        // Hidden form fields that will be sent on form submission
        $process_button_string = zen_draw_hidden_field('toid', $merchant_id) .
                                 zen_draw_hidden_field('totype', $partner_name) .
                                 zen_draw_hidden_field('secret_key', $secret_key) .
                                 zen_draw_hidden_field('redirect_url', $redirect_url) .
                                 zen_draw_hidden_field('currency', $currency) .
                                 zen_draw_hidden_field('amount', $amount);

        return $process_button_string;
    }

    function before_process() { return false; }

    private function generateRandomString($length = 6): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function after_process() { 
        global $insert_id, $db;
        
        if ($insert_id) { 
            $test_url = MODULE_PAYMENT_PAYMENTZENCART_V2_TEST_URL;
            $merchant_id = MODULE_PAYMENT_PAYMENTZENCART_V2_MERCHANT_ID;
            $secret_key = MODULE_PAYMENT_PAYMENTZENCART_V2_SECRET_KEY;
            $partner_name = MODULE_PAYMENT_PAYMENTZENCART_V2_PARTNER_NAME;
            $redirect_url = MODULE_PAYMENT_PAYMENTZENCART_V2_REDIRECT_URL;
            $order_id = $insert_id;
            
            // Fetch the order total amount
            $order_query = $db->Execute("SELECT value FROM " . TABLE_ORDERS_TOTAL . " WHERE orders_id = '" . (int)$order_id . "' AND class = 'ot_total' LIMIT 1");
            $amount = number_format($order_query->fields['value'], 2);

            $currency_query = $db->Execute("SELECT currency FROM " . TABLE_ORDERS . " WHERE orders_id = '" . (int)$order_id . "' LIMIT 1");
            $currency = $currency_query->fields['currency'];

            $merchantTransactionId = $this->generateRandomString(); 

            $checksum_maker = $merchant_id . '|' . $partner_name . '|' . $amount . '|' . $merchantTransactionId . '|' . $redirect_url. '|' . $secret_key;
                
            $checksum = md5($checksum_maker);

            // Prepare the form for a POST request
            $form = '<form action="' . htmlspecialchars($test_url) . '" method="post" id="paymentzencart_v2_form">';

            $form .= '<input type="hidden" name="toid" value="' . htmlspecialchars($merchant_id) . '">';
            $form .= '<input type="hidden" name="totype" value="' . htmlspecialchars($partner_name) . '">';
            $form .= '<input type="hidden" name="currency" value="' . htmlspecialchars($currency) . '">';
            $form .= '<input type="hidden" name="merchantRedirectUrl" value="' . htmlspecialchars($redirect_url) . '">'; 
            $form .= '<input type="hidden" name="description" value="' . htmlspecialchars($merchantTransactionId) . '">'; 
            $form .= '<input type="hidden" name="checksum" value="' . htmlspecialchars($checksum) . '">'; 
            $form .= '<input type="hidden" name="amount" value="' . htmlspecialchars($amount) . '">';
            
            $form .= '</form>';

            $form .= '<script type="text/javascript">document.getElementById("paymentzencart_v2_form").submit();</script>';
            
            echo $form; 
            exit; 
            
        }
    }
    

    function get_error() { return false; }


    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function install() {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS')) {
            $messageStack->add_session('Paymentzencart_v2 module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=paymentzencart_v2', 'NONSSL'));
            return 'failed';
        }
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable Paymentzencart_v2 Module', 'MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS', 'True', 'Enable the Paymentzencart_v2 payment module?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Merchant ID', 'MODULE_PAYMENT_PAYMENTZENCART_V2_MERCHANT_ID', '', 'Enter your Merchant ID for Paymentzencart_v2.', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Secret Key', 'MODULE_PAYMENT_PAYMENTZENCART_V2_SECRET_KEY', '', 'Enter your Secret Key for Paymentzencart_v2.', '6', '3', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Partner Name', 'MODULE_PAYMENT_PAYMENTZENCART_V2_PARTNER_NAME', '', 'Enter your Partner Name for Paymentzencart_v2.', '6', '4', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Redirect Url', 'MODULE_PAYMENT_PAYMENTZENCART_V2_REDIRECT_URL', '', 'Enter your Redirect Url for Paymentzencart_v2.', '6', '5', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Live/Test URL', 'MODULE_PAYMENT_PAYMENTZENCART_V2_TEST_URL', '', 'Enter the Live/Test URL for payment gateway.', '6', '6', now())");
    }

    // Remove configuration settings
    function remove() {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    // Return module keys
    function keys() {
        return array('MODULE_PAYMENT_PAYMENTZENCART_V2_STATUS', 'MODULE_PAYMENT_PAYMENTZENCART_V2_MERCHANT_ID','MODULE_PAYMENT_PAYMENTZENCART_V2_SECRET_KEY','MODULE_PAYMENT_PAYMENTZENCART_V2_PARTNER_NAME','MODULE_PAYMENT_PAYMENTZENCART_V2_REDIRECT_URL', 'MODULE_PAYMENT_PAYMENTZENCART_V2_TEST_URL');
    }
}
?>

