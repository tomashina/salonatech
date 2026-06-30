<?php
class ControllerExtensionTotalPaycharge extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/total/paycharge');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('total_paycharge', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true));
		}

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error) && !isset($this->error['warning'])) {
			foreach ($this->error as $error_name => $rows) {
				foreach ($rows as $row => $error_text) {
					$data['error_' . $error_name][$row] = $error_text;
				}
			}
		}

   		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),      		
   		);

   		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true)
		);

   		$data['breadcrumbs'][] = array(
       		'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/total/paycharge', 'user_token=' . $this->session->data['user_token'], true),
   		);

		$data['action'] = $this->url->link('extension/total/paycharge', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true);

		$this->load->model('setting/extension');

		$data['payments'] = array();

		foreach ($this->model_setting_extension->getInstalled('payment') as $payment) {
			if (file_exists(DIR_APPLICATION . 'controller/extension/payment/' . $payment . '.php')) {
				$this->load->language('extension/payment/' . $payment);
				$data['payments'][] = array(
					'name' => $this->language->get('heading_title'),
					'code' => $payment,
				);
			}
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('localisation/tax_class');

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['total_paycharge_status'])) {
			$data['total_paycharge_status'] = $this->request->post['total_paycharge_status'];
		} else {
			$data['total_paycharge_status'] = $this->config->get('total_paycharge_status');
		}

		if (isset($this->request->post['total_paycharge_sort_order'])) {
			$data['total_paycharge_sort_order'] = $this->request->post['total_paycharge_sort_order'];
		} else {
			$data['total_paycharge_sort_order'] = $this->config->get('total_paycharge_sort_order');
		}

		if (isset($this->request->post['total_paycharge'])) {
			$data['total_paycharges'] = $this->request->post['total_paycharge'];
		} elseif ($this->config->get('total_paycharge')) {
			$data['total_paycharges'] = $this->config->get('total_paycharge');
		} else {
			$data['total_paycharges'] = array();
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/total/paycharge', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/total/paycharge')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['total_paycharge'])) {
			foreach ($this->request->post['total_paycharge'] as $row => $value) {
				if (isset($value['cart_min']) && !is_numeric($value['cart_min']) || isset($value['cart_max']) && !is_numeric($value['cart_max']) || $value['cart_min'] > $value['cart_max']) {
					$this->error['cart_range'][$row] = $this->language->get('error_cart_range');
				}

				if (!empty($value['valuep']) && !is_numeric($value['valuep']) || !empty($value['valuef']) && !is_numeric($value['valuef'])) {
					$this->error['values'][$row] = $this->language->get('error_values');
				}

				foreach ($value['total_paycharge_description'] as $language_id => $text) {
					if ((utf8_strlen($text['name']) < 3) || (utf8_strlen($text['name']) > 120)) {
						$this->error['name'][$row][$language_id] = $this->language->get('error_name');
					}
				}
			}
		}

		return !$this->error;
	}
}