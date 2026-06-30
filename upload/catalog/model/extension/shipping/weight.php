<?php
class ModelExtensionShippingWeight extends Model {
	public function getQuote($address) {
		$this->load->language('extension/shipping/weight');

		$quote_data = array();
		$is_croatia_island_address = $this->isCroatiaIslandAddress($address);
		$croatia_island_geo_zone_id = 9;

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "geo_zone ORDER BY name");

		foreach ($query->rows as $result) {
			if ($is_croatia_island_address) {
				if ((int)$result['geo_zone_id'] != $croatia_island_geo_zone_id) {
					continue;
				}

				$status = (bool)$this->config->get('shipping_weight_' . $croatia_island_geo_zone_id . '_status');
			} else {
				if ($this->config->get('shipping_weight_' . $result['geo_zone_id'] . '_status')) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$result['geo_zone_id'] . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

					if ($query->num_rows) {
						$status = true;
					} else {
						$status = false;
					}
				} else {
					$status = false;
				}
			}

			if ($status) {
				$cost = '';
				$weight = $this->cart->getWeight();

				$rates = explode(',', $this->config->get('shipping_weight_' . $result['geo_zone_id'] . '_rate'));

				foreach ($rates as $rate) {
					$data = explode(':', $rate);

					if ($data[0] >= $weight) {
						if (isset($data[1])) {
							$cost = $data[1];
						}

						break;
					}
				}

				if ((string)$cost != '') {
					$quote_data['weight_' . $result['geo_zone_id']] = array(
						'code'         => 'weight.weight_' . $result['geo_zone_id'],
						'title'        => $result['name'] . '  (' . $this->language->get('text_weight') . ' ' . $this->weight->format($weight, $this->config->get('config_weight_class_id')) . ')',
						'cost'         => $cost,
						'tax_class_id' => $this->config->get('shipping_weight_tax_class_id'),
						'text'         => $this->currency->format($this->tax->calculate($cost, $this->config->get('shipping_weight_tax_class_id'), $this->config->get('config_tax')), $this->session->data['currency'])
					);
				}
			}
		}


		$method_data = array();

		if ($quote_data) {
			$method_data = array(
				'code'       => 'weight',
				'title'      => $this->language->get('text_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_weight_sort_order'),
				'error'      => false
			);
		}

		return $method_data;
	}

	private function isCroatiaIslandAddress($address) {
		$postcode = isset($address['postcode']) ? preg_replace('/[^0-9]/', '', (string)$address['postcode']) : '';

		if (strlen($postcode) > 5) {
			$postcode = substr($postcode, 0, 5);
		}

		if (strlen($postcode) !== 5) {
			return false;
		}

		$island_postcodes = array_flip($this->getCroatiaIslandPostcodes());

		if (isset($island_postcodes[$postcode])) {
			return true;
		}

		$city_postcodes = $this->getCroatiaIslandCityPostcodes();

		if (!isset($city_postcodes[$postcode]) || empty($address['city'])) {
			return false;
		}

		$city = $this->normalizeCroatiaIslandCity($address['city']);

		foreach ($city_postcodes[$postcode] as $island_city) {
			if ($city === $island_city) {
				return true;
			}
		}

		return false;
	}

	private function getCroatiaIslandPostcodes() {
		return array(
			'20221', '20222', '20223', '20224', '20225', '20226',
			'20260', '20263', '20264', '20270', '20271', '20272', '20273', '20274', '20275', '20289', '20290',
			'21223', '21224', '21225',
			'21400', '21403', '21404', '21405', '21410', '21412', '21413', '21414', '21420', '21423', '21424', '21425', '21426',
			'21430', '21432', '21450', '21454', '21460', '21462', '21463', '21465', '21466', '21467', '21468', '21469', '21480', '21483', '21485',
			'22231', '22232', '22233', '22234', '22235', '22236', '22242', '22243', '22244',
			'23212', '23234', '23249', '23250', '23251', '23262', '23263', '23264', '23271', '23272', '23273', '23274', '23275',
			'23281', '23282', '23283', '23284', '23285', '23286', '23287', '23291', '23292', '23293', '23294', '23295', '23296',
			'51280', '51281', '51282', '51283',
			'51500', '51511', '51512', '51513', '51514', '51515', '51516', '51517', '51521', '51522', '51523',
			'51542', '51550', '51551', '51552', '51553', '51554', '51555', '51556', '51557', '51559', '51561', '51562', '51564',
			'53291', '53294', '53296', '53297'
		);
	}

	private function getCroatiaIslandCityPostcodes() {
		return array(
			'21220' => array('MASTRINKA', 'ZEDNO'),
			'22240' => array('TISNO'),
			'23211' => array('VRGADA')
		);
	}

	private function normalizeCroatiaIslandCity($value) {
		$value = strtr(trim((string)$value), array(
			'č' => 'c',
			'ć' => 'c',
			'đ' => 'd',
			'š' => 's',
			'ž' => 'z',
			'Č' => 'C',
			'Ć' => 'C',
			'Đ' => 'D',
			'Š' => 'S',
			'Ž' => 'Z'
		));

		return preg_replace('/\s+/', ' ', strtoupper($value));
	}
}
