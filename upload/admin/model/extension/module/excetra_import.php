<?php
class ModelExtensionModuleExcetraImport extends Model {
	private $supplier_name = 'Excetra';

	public function getProductsByModels($models) {
		$products = array();
		$models = array_values(array_unique(array_filter($models)));

		if (!$models) {
			return $products;
		}

		foreach (array_chunk($models, 100) as $chunk) {
			$escaped = array();

			foreach ($chunk as $model) {
				$escaped[] = "'" . $this->db->escape($model) . "'";
			}

			$query = $this->db->query("SELECT p.product_id, p.model, p.quantity, p.price, p.image, p.location, pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE p.model IN (" . implode(',', $escaped) . ")");

			foreach ($query->rows as $row) {
				$products[$row['model']] = $row;
			}
		}

		return $products;
	}

	public function importProduct($product, $category_id, $settings) {
		if (empty($product['code']) || !$category_id) {
			return 'skipped';
		}

		$existing = $this->getProductByModel($product['code']);
		$manufacturer_id = $this->ensureManufacturer($product['brand']);
		$images = $this->downloadImages($product['images'], $product['code']);
		$price = $this->calculatePrice($product['prices'], $settings);
		$stock_status_id = (int)$this->config->get('config_stock_status_id');

		if (!$stock_status_id) {
			$stock_status_id = 5;
		}

		if ($existing) {
			$this->updateExistingProduct($existing, $product, $category_id, $manufacturer_id, $images, $price, $settings, $stock_status_id);
			return 'updated';
		}

		$this->createProduct($product, $category_id, $manufacturer_id, $images, $price, $settings, $stock_status_id);

		return 'created';
	}

	public function updateQuantities($products) {
		$updated = 0;
		$not_found = 0;

		foreach ($products as $product) {
			if (empty($product['code'])) {
				continue;
			}

			$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE model = '" . $this->db->escape($product['code']) . "'");

			if ($query->num_rows) {
				$this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . (int)$product['quantity'] . "', date_modified = NOW() WHERE model = '" . $this->db->escape($product['code']) . "'");
				$updated += $query->num_rows;
			} else {
				$not_found++;
			}
		}

		$this->cache->delete('product');

		return array(
			'updated'   => $updated,
			'not_found' => $not_found
		);
	}

	public function updatePrices($products, $settings) {
		$updated = 0;
		$not_found = 0;
		$skipped = 0;

		foreach ($products as $product) {
			if (empty($product['code'])) {
				continue;
			}

			$price = $this->calculatePrice($product['prices'], $settings);

			if ($price <= 0) {
				$skipped++;
				continue;
			}

			$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE model = '" . $this->db->escape($product['code']) . "'");

			if ($query->num_rows) {
				$this->db->query("UPDATE " . DB_PREFIX . "product SET price = '" . (float)$price . "', date_modified = NOW() WHERE model = '" . $this->db->escape($product['code']) . "'");
				$updated += $query->num_rows;
			} else {
				$not_found++;
			}
		}

		$this->cache->delete('product');

		return array(
			'updated'   => $updated,
			'not_found' => $not_found,
			'skipped'   => $skipped
		);
	}

	public function updateProducts($products, $settings) {
		$updated = 0;
		$not_found = 0;
		$skipped = 0;

		foreach ($products as $product) {
			if (empty($product['code'])) {
				continue;
			}

			$price = $this->calculatePrice($product['prices'], $settings);
			$price_sql = '';

			if ($price > 0) {
				$price_sql = ", price = '" . (float)$price . "'";
			} else {
				$skipped++;
			}

			$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE model = '" . $this->db->escape($product['code']) . "'");

			if ($query->num_rows) {
				$this->db->query("UPDATE " . DB_PREFIX . "product SET sku = '" . $this->db->escape($product['id']) . "', ean = '" . $this->db->escape($product['barcode']) . "', location = '" . $this->db->escape($this->supplier_name) . "', quantity = '" . (int)$product['quantity'] . "'" . $price_sql . ", date_modified = NOW() WHERE model = '" . $this->db->escape($product['code']) . "'");
				$updated += $query->num_rows;
			} else {
				$not_found++;
			}
		}

		$this->cache->delete('product');

		return array(
			'updated'   => $updated,
			'not_found' => $not_found,
			'skipped'   => $skipped
		);
	}

	public function updateProductImagesFromSupplier($product_id, $product) {
		$images = $this->downloadImages($product['images'], $product['code'], true);

		if (!$images) {
			return 0;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape($images[0]) . "', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

		foreach ($this->buildAdditionalImages($images) as $image) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($image['image']) . "', sort_order = '" . (int)$image['sort_order'] . "'");
		}

		$this->cache->delete('product');

		return count($images);
	}

	public function createCategoryFromSupplier($supplier_category, $parent_id = 0) {
		if (!empty($supplier_category['parent_name']) && !empty($supplier_category['leaf_name'])) {
			$root_id = $this->ensureCategory($supplier_category['parent_name'], $parent_id);

			return $this->ensureCategory($supplier_category['leaf_name'], $root_id);
		}

		$name = !empty($supplier_category['name_hr']) ? $supplier_category['name_hr'] : (!empty($supplier_category['name']) ? $supplier_category['name'] : $supplier_category['id']);

		return $this->ensureCategory($name, $parent_id);
	}

	private function ensureCategory($name, $parent_id = 0) {
		$existing_category_id = $this->getCategoryIdByName($name, $parent_id);

		if ($existing_category_id) {
			return $existing_category_id;
		}

		$this->load->model('catalog/category');

		$category_description = array();

		foreach ($this->getLanguages() as $language) {
			$category_description[$language['language_id']] = array(
				'name'             => $name,
				'description'      => '',
				'meta_title'       => $name,
				'meta_description' => '',
				'meta_keyword'     => ''
			);
		}

		$data = array(
			'parent_id'            => (int)$parent_id,
			'top'                  => 0,
			'column'               => 1,
			'sort_order'           => 0,
			'status'               => 1,
			'image'                => '',
			'category_description' => $category_description,
			'category_store'       => array(0),
			'category_filter'      => array(),
			'category_seo_url'     => $this->buildEntitySeoUrls($name),
			'category_layout'      => array()
		);

		return $this->model_catalog_category->addCategory($data);
	}

	private function createProduct($product, $category_id, $manufacturer_id, $images, $price, $settings, $stock_status_id) {
		$this->load->model('catalog/product');

		$product_description = array();

		foreach ($this->getLanguages() as $language) {
			$product_description[$language['language_id']] = array(
				'name'             => $this->getProductNameForLanguage($product, $language['code']),
				'description'      => $this->buildDescription($product, $language['code']),
				'tag'              => '',
				'meta_title'       => $this->getProductNameForLanguage($product, $language['code']),
				'meta_description' => '',
				'meta_keyword'     => ''
			);
		}

		$data = array(
			'model'               => $product['code'],
			'sku'                 => $product['id'],
			'upc'                 => '',
			'ean'                 => $product['barcode'],
			'jan'                 => '',
			'isbn'                => '',
			'mpn'                 => '',
			'location'            => $this->supplier_name,
			'quantity'            => (int)$product['quantity'],
			'minimum'             => 1,
			'subtract'            => 1,
			'stock_status_id'     => $stock_status_id,
			'date_available'      => date('Y-m-d'),
			'manufacturer_id'     => $manufacturer_id,
			'shipping'            => 1,
			'price'               => $price,
			'points'              => 0,
			'weight'              => 0,
			'weight_class_id'     => (int)$this->config->get('config_weight_class_id'),
			'length'              => 0,
			'width'               => 0,
			'height'              => 0,
			'length_class_id'     => (int)$this->config->get('config_length_class_id'),
			'status'              => isset($product['status']) ? (int)$product['status'] : 1,
			'tax_class_id'        => !empty($settings['tax_class_id']) ? (int)$settings['tax_class_id'] : 0,
			'sort_order'          => 0,
			'image'               => !empty($images[0]) ? $images[0] : '',
			'product_description' => $product_description,
			'product_store'       => array(0),
			'product_category'    => array((int)$category_id),
			'product_image'       => $this->buildAdditionalImages($images),
			'product_filter'      => array(),
			'product_related'     => array(),
			'product_download'    => array(),
			'product_layout'      => array(),
			'product_seo_url'     => $this->buildProductSeoUrls($product)
		);

		$this->model_catalog_product->addProduct($data);
	}

	private function updateExistingProduct($existing, $product, $category_id, $manufacturer_id, $images, $price, $settings, $stock_status_id) {
		$image_sql = '';

		if (!empty($images[0]) && empty($existing['image'])) {
			$image_sql = ", image = '" . $this->db->escape($images[0]) . "'";
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product SET sku = '" . $this->db->escape($product['id']) . "', ean = '" . $this->db->escape($product['barcode']) . "', location = '" . $this->db->escape($this->supplier_name) . "', quantity = '" . (int)$product['quantity'] . "', subtract = '1', stock_status_id = '" . (int)$stock_status_id . "', manufacturer_id = '" . (int)$manufacturer_id . "', shipping = '1', price = '" . (float)$price . "', tax_class_id = '" . (!empty($settings['tax_class_id']) ? (int)$settings['tax_class_id'] : 0) . "', status = '" . (isset($product['status']) ? (int)$product['status'] : 1) . "', date_modified = NOW()" . $image_sql . " WHERE product_id = '" . (int)$existing['product_id'] . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$existing['product_id'] . "' AND category_id = '" . (int)$category_id . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$existing['product_id'] . "', category_id = '" . (int)$category_id . "'");

		$store_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$existing['product_id'] . "' AND store_id = '0'");

		if (!$store_query->num_rows) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$existing['product_id'] . "', store_id = '0'");
		}

		if (!empty($images[0]) && empty($existing['image'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$existing['product_id'] . "'");

			foreach ($this->buildAdditionalImages($images) as $image) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$existing['product_id'] . "', image = '" . $this->db->escape($image['image']) . "', sort_order = '" . (int)$image['sort_order'] . "'");
			}
		}

		if (!empty($settings['update_existing_descriptions'])) {
			$this->updateProductDescriptions($existing['product_id'], $product);
		}

		$this->ensureProductSeoUrl($existing['product_id'], $product);

		$this->cache->delete('product');
	}

	private function updateProductDescriptions($product_id, $product) {
		foreach ($this->getLanguages() as $language) {
			$language_id = (int)$language['language_id'];
			$name = $this->getProductNameForLanguage($product, $language['code']);
			$description = $this->buildDescription($product, $language['code']);
			$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . $language_id . "' LIMIT 1");

			if ($query->num_rows) {
				if ($description !== '') {
					$this->db->query("UPDATE " . DB_PREFIX . "product_description SET description = '" . $this->db->escape($description) . "' WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . $language_id . "'");
				}
			} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . $language_id . "', name = '" . $this->db->escape($name) . "', description = '" . $this->db->escape($description) . "', tag = '', meta_title = '" . $this->db->escape($name) . "', meta_description = '', meta_keyword = ''");
			}
		}
	}

	private function ensureManufacturer($name) {
		$name = trim($name);

		if ($name === '') {
			return 0;
		}

		$query = $this->db->query("SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer WHERE LCASE(name) = '" . $this->db->escape(utf8_strtolower($name)) . "' LIMIT 1");

		if ($query->num_rows) {
			return (int)$query->row['manufacturer_id'];
		}

		$this->load->model('catalog/manufacturer');

		return (int)$this->model_catalog_manufacturer->addManufacturer(array(
			'name'                 => $name,
			'image'                => '',
			'sort_order'           => 0,
			'manufacturer_store'   => array(0),
			'manufacturer_seo_url' => $this->buildEntitySeoUrls($name)
		));
	}

	private function getProductByModel($model) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE model = '" . $this->db->escape($model) . "' LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	private function getCategoryIdByName($name, $parent_id = 0) {
		$query = $this->db->query("SELECT cd.category_id FROM " . DB_PREFIX . "category_description cd LEFT JOIN " . DB_PREFIX . "category c ON (cd.category_id = c.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c.parent_id = '" . (int)$parent_id . "' AND LCASE(cd.name) = '" . $this->db->escape(utf8_strtolower($name)) . "' LIMIT 1");

		return $query->num_rows ? (int)$query->row['category_id'] : 0;
	}

	private function getLanguages() {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "language WHERE status = '1' ORDER BY sort_order, name");

		return $query->rows;
	}

	private function getProductNameForLanguage($product, $language_code) {
		$language_code = strtolower($language_code);

		if (strpos($language_code, 'hr') === 0 && !empty($product['name_hr'])) {
			return $product['name_hr'];
		}

		if (!empty($product['name_en'])) {
			return $product['name_en'];
		}

		return $product['name'];
	}

	private function buildDescription($product, $language_code) {
		$language_code = strtolower($language_code);
		$attributes = (strpos($language_code, 'hr') === 0 && !empty($product['attributes_hr'])) ? $product['attributes_hr'] : $product['attributes_en'];
		$description = (strpos($language_code, 'hr') === 0 && !empty($product['description_hr'])) ? $product['description_hr'] : (!empty($product['description_en']) ? $product['description_en'] : '');

		if (!$attributes && !empty($product['attributes_hr'])) {
			$attributes = $product['attributes_hr'];
		}

		if (!$attributes && $description === '') {
			return '';
		}

		$html = '';

		if ($description !== '') {
			$html .= '<p>' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</p>';
		}

		if (!$attributes) {
			return $html;
		}

		$html .= '<table class="table table-bordered table-striped table-hover table-sm excetra-spec-table"><tbody>';

		foreach ($attributes as $attribute) {
			$html .= '<tr><td>' . htmlspecialchars($attribute['title'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($attribute['value'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	private function buildAdditionalImages($images) {
		$product_images = array();
		$sort_order = 0;

		foreach ($images as $index => $image) {
			if ($index === 0) {
				continue;
			}

			$product_images[] = array(
				'image'      => $image,
				'sort_order' => $sort_order++
			);
		}

		return $product_images;
	}

	private function downloadImages($images, $code, $force = false) {
		$local_images = array();

		if (!$images) {
			return $local_images;
		}

		$directory = DIR_IMAGE . 'catalog/excetra/';

		if (!is_dir($directory)) {
			mkdir($directory, 0775, true);
		}

		foreach ($images as $index => $image) {
			if (empty($image['url'])) {
				continue;
			}

			$extension = strtolower(pathinfo(parse_url($image['url'], PHP_URL_PATH), PATHINFO_EXTENSION));

			if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
				$extension = 'jpg';
			}

			$filename = $this->safeFilename($code) . '-' . ($index + 1) . '.' . $extension;
			$relative_path = 'catalog/excetra/' . $filename;
			$absolute_path = DIR_IMAGE . $relative_path;

			if ($force || !is_file($absolute_path)) {
				$content = $this->downloadRemoteFile($image['url']);

				if (!$content) {
					continue;
				}

				file_put_contents($absolute_path, $content);
			}

			$local_images[] = $relative_path;
		}

		return $local_images;
	}

	private function downloadRemoteFile($url) {
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'SalonaTech Excetra Import');

			$content = curl_exec($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($content === false || $status_code < 200 || $status_code >= 300) {
				return '';
			}

			return $content;
		}

		return @file_get_contents($url);
	}

	private function safeFilename($value) {
		$value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);
		$value = trim($value, '-');

		return $value ?: 'excetra-product';
	}

	private function buildProductSeoUrls($product) {
		$language_id = (int)$this->config->get('config_language_id');
		$language_code = $this->getLanguageCodeById($language_id);
		$name = $this->getProductNameForLanguage($product, $language_code);
		$keyword = $this->makeUniqueSeoKeyword($this->buildSeoKeyword($name, $product['code']));

		return array(
			0 => array(
				$language_id => $keyword
			)
		);
	}

	private function buildEntitySeoUrls($name, $suffix = '') {
		$language_id = (int)$this->config->get('config_language_id');
		$keyword = $this->makeUniqueSeoKeyword($this->buildSeoKeyword($name, $suffix));

		return array(
			0 => array(
				$language_id => $keyword
			)
		);
	}

	private function ensureProductSeoUrl($product_id, $product) {
		$query_key = 'product_id=' . (int)$product_id;
		$query = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($query_key) . "' LIMIT 1");

		if ($query->num_rows) {
			return;
		}

		$seo_urls = $this->buildProductSeoUrls($product);

		foreach ($seo_urls as $store_id => $language) {
			foreach ($language as $language_id => $keyword) {
				if ($keyword !== '') {
					$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = '" . $this->db->escape($query_key) . "', keyword = '" . $this->db->escape($keyword) . "'");
				}
			}
		}
	}

	private function buildSeoKeyword($name, $suffix = '') {
		$keyword = $this->slugify($name);
		$suffix = $this->slugify($suffix);

		if ($suffix !== '' && substr($keyword, -strlen($suffix)) !== $suffix) {
			$keyword = $this->limitSeoKeyword($keyword, strlen($suffix) + 1) . '-' . $suffix;
		}

		return $this->limitSeoKeyword($keyword ?: 'excetra-artikl');
	}

	private function makeUniqueSeoKeyword($base_keyword) {
		$base_keyword = $this->limitSeoKeyword($base_keyword ?: 'excetra-artikl');
		$keyword = $base_keyword;
		$index = 2;

		while ($this->seoKeywordExists($keyword)) {
			$suffix = '-' . $index++;
			$keyword = $this->limitSeoKeyword($base_keyword, strlen($suffix)) . $suffix;
		}

		return $keyword;
	}

	private function seoKeywordExists($keyword) {
		$query = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($keyword) . "' LIMIT 1");

		return $query->num_rows > 0;
	}

	private function limitSeoKeyword($keyword, $reserved_length = 0) {
		$max_length = max(1, 240 - (int)$reserved_length);
		$keyword = trim($keyword, '-');

		if (strlen($keyword) > $max_length) {
			$keyword = trim(substr($keyword, 0, $max_length), '-');
		}

		return $keyword ?: 'excetra-artikl';
	}

	private function slugify($value) {
		$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		$value = strtr($value, array(
			'Š' => 'S', 'Đ' => 'D', 'Č' => 'C', 'Ć' => 'C', 'Ž' => 'Z',
			'š' => 's', 'đ' => 'd', 'č' => 'c', 'ć' => 'c', 'ž' => 'z'
		));

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

			if ($converted !== false) {
				$value = $converted;
			}
		}

		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', '-', $value);
		$value = trim($value, '-');

		return $value;
	}

	private function getLanguageCodeById($language_id) {
		foreach ($this->getLanguages() as $language) {
			if ((int)$language['language_id'] === (int)$language_id) {
				return $language['code'];
			}
		}

		return 'hr-hr';
	}

	private function calculatePrice($prices, $settings) {
		$mode = !empty($settings['price_mode']) ? $settings['price_mode'] : 'mpc';

		if (!in_array($mode, array('mpc', 'vpc'))) {
			$mode = 'mpc';
		}

		$price = isset($prices[$mode]) ? (float)$prices[$mode] : 0;
		$margin = isset($settings['margin']) ? (float)$settings['margin'] : 0;

		if ($margin) {
			$price = $price * (1 + ($margin / 100));
		}

		return round($price, 4);
	}
}
