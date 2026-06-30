<?php 
class ModelExtensionTotalPaycharge extends Model {
	public function getTotal($total) {
		if ($this->config->get('total_paycharge_status') && isset($this->session->data['payment_method']['code']) && $this->cart->getSubTotal()) {
			$cart_total = $total['total'];

			foreach ($this->config->get('total_paycharge') as $paycharge) {
				$status = true;

				if ($paycharge['payment_method'] != $this->session->data['payment_method']['code']) {
					$status = false;
				} elseif ($cart_total < $paycharge['cart_min']) {
					$status = false;
				} elseif ($cart_total > $paycharge['cart_max']) {
					$status = false;
				} elseif ($paycharge['customer_group_id'] && $paycharge['customer_group_id'] != $this->customer->getGroupId()) {
					$status = false;
				//} elseif (!$paycharge['customer_group_id'] && $this->customer->isLogged()) {
					//$status = false;
				} elseif ($paycharge['geo_zone_id'] && isset($this->session->data['payment_address'])) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$paycharge['geo_zone_id'] . "' AND country_id = '" . (int)$this->session->data['payment_address']['country_id'] . "' AND (zone_id = '" . (int)$this->session->data['payment_address']['zone_id'] . "' OR zone_id = '0')");

					if (!$query->num_rows) {
						$status = false;
					}
				}

				if ($status) {
					$text = '';
					$value = 0;
					$title = $paycharge['total_paycharge_description'][$this->config->get('config_language_id')]['name'];



					$paymentplan = $this->session->data['payment_method']['payment_plan'];
					$creditcard = $this->session->data['payment_method']['credit_card_name'];

					if($creditcard=='VISA' && $paymentplan=='0200' || $paymentplan=='0300' || $paymentplan=='0400' || $paymentplan=='0500' || $paymentplan=='0600' ){

						$charge_p = '6';


					}

					else if($creditcard=='VISA' && $paymentplan=='0700' || $paymentplan=='0800' || $paymentplan=='0900' || $paymentplan=='1000' || $paymentplan=='1100'  || $paymentplan=='1200'){

							$charge_p = '7.4';
					}


					else if($creditcard=='MAESTRO' && $paymentplan=='0200' || $paymentplan=='0300' || $paymentplan=='0400' || $paymentplan=='0500' || $paymentplan=='0600' ){

						$charge_p = '5.5';


					}

					else if($creditcard=='MAESTRO' && $paymentplan=='0700' || $paymentplan=='0800' || $paymentplan=='0900' || $paymentplan=='1000' || $paymentplan=='1100'  || $paymentplan=='1200'){

							$charge_p = '6.5';
					}

					else if($creditcard=='MASTERCARD' && $paymentplan=='0200' || $paymentplan=='0300' || $paymentplan=='0400' || $paymentplan=='0500' || $paymentplan=='0600' ){

						$charge_p = '5.5';


					}

					else if($creditcard=='MASTERCARD' && $paymentplan=='0700' || $paymentplan=='0800' || $paymentplan=='0900' || $paymentplan=='1000' || $paymentplan=='1100'  || $paymentplan=='1200'){

							$charge_p = '6.5';
					}

					else if($creditcard=='MASTERCARD' && $paymentplan=='1300' || $paymentplan=='1400' || $paymentplan=='1500' || $paymentplan=='1600' || $paymentplan=='1700'  || $paymentplan=='1800' || $paymentplan=='1900' || $paymentplan=='2000' || $paymentplan=='2100' || $paymentplan=='2200' || $paymentplan=='2300' || $paymentplan=='2400'){

							$charge_p = '9';
					}

					else if($creditcard=='DINERS' && $paymentplan=='0200' || $paymentplan=='0300' || $paymentplan=='0400' || $paymentplan=='0500' || $paymentplan=='0600' ){

						$charge_p = '5.5';


					}

					else if($creditcard=='DINERS' && $paymentplan=='0700' || $paymentplan=='0800' || $paymentplan=='0900' || $paymentplan=='1000' || $paymentplan=='1100'  || $paymentplan=='1200'){

							$charge_p = '6.5';
					}

					else if($creditcard=='DINERS' && $paymentplan=='1300' || $paymentplan=='1400' || $paymentplan=='1500' || $paymentplan=='1600' || $paymentplan=='1700'  || $paymentplan=='1800' || $paymentplan=='1900' || $paymentplan=='2000' || $paymentplan=='2100' || $paymentplan=='2200' || $paymentplan=='2300' || $paymentplan=='2400'){

							$charge_p = '9';
					}

					else{

						$charge_p = $paycharge['valuep'];
					}


					
					$charge_f = $paycharge['valuef'];

					if ($charge_p) {
						if (isset($paycharge['formula'])) {
							$text .= round((($charge_p / 100) +1) * ($charge_p / 100) * 100, 2) . '%';
							$value += (($charge_p / 100) * $cart_total) / (1- ($charge_p / 100));
						} else {
							$text .= $charge_p . '%';
							$value += ($cart_total / 100 * $charge_p);
						}
					}

					if ($charge_p && $charge_f && substr($charge_f, 0, 1) != '-') {
						$text .= ' + '; // Add separator
					}

					if ($charge_f) {
						$text .= $charge_f; // Or: $this->currency->format($charge_f);
						$value += $charge_f;
					}

					if ($charge_p && isset($paycharge['step6'])) {
						$title .= ' (' . $text . ')';
					}

					$total['totals'][] = array(
						'code'       => 'paycharge',
						'title'      => $title,
        				'value'      => $value,
						'sort_order' => $this->config->get('paycharge_sort_order')
					);

					if ($paycharge['tax_class_id']) {
						$tax_rates = $this->tax->getRates($value, $paycharge['tax_class_id']);

						foreach ($tax_rates as $tax_rate) {
							if (!isset($total['taxes'][$tax_rate['tax_rate_id']])) {
								$total['taxes'][$tax_rate['tax_rate_id']] = $tax_rate['amount'];
							} else {
								$total['taxes'][$tax_rate['tax_rate_id']] += $tax_rate['amount'];
							}
						}
					}

					$total['total'] += $value;
				}
			}
		}
	}
}