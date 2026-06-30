<?php
class ControllerExtensionModuleExcetraImport extends Controller {
	private $route = 'extension/module/excetra_import';
	private $feed_endpoint = 'https://excetrashop.hr/modules/exportproducts/files/EXCETRA_exported_product.xml';

	public function index() {
		$this->load->language($this->route);
		$this->load->model('catalog/category');
		$this->load->model('extension/module/excetra_import');
		$this->load->model('localisation/tax_class');
		$this->load->model('setting/setting');

		$this->document->setTitle($this->language->get('heading_title'));

		$settings = $this->getSettings();

		if (empty($settings['cron_token'])) {
			$settings['cron_token'] = token(32);
			$this->saveSettings($settings);
		}

		$filter_search = isset($this->request->get['filter_search']) ? trim($this->request->get['filter_search']) : '';
		$filter_supplier_category_id = isset($this->request->get['filter_supplier_category_id']) ? trim($this->request->get['filter_supplier_category_id']) : '';
		$filter_import_status = isset($this->request->get['filter_import_status']) ? trim($this->request->get['filter_import_status']) : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = 50;

		if ($page < 1) {
			$page = 1;
		}

		$force_refresh = !empty($this->request->get['refresh']);
		$products = array();
		$supplier_categories = array();
		$feed_error = '';

		try {
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, $force_refresh));
			$supplier_categories = $this->buildCategoriesFromProducts($products);
		} catch (Exception $e) {
			$feed_error = $e->getMessage();
		}

		if (!$supplier_categories && $products) {
			$supplier_categories = $this->buildCategoriesFromProducts($products);
		}

		$local_categories = $this->model_catalog_category->getCategories(array(
			'sort'  => 'name',
			'order' => 'ASC'
		));

		$local_category_names = array();

		foreach ($local_categories as $category) {
			$local_category_names[(int)$category['category_id']] = html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8');
		}

		$filtered_products = $this->filterProducts($products, $filter_search, $filter_supplier_category_id);

		if ($filter_import_status !== '') {
			$existing_for_filter = $this->model_extension_module_excetra_import->getProductsByModels($this->pluckCodes($filtered_products));
			$tmp = array();

			foreach ($filtered_products as $product) {
				$exists = isset($existing_for_filter[$product['code']]);

				if (($filter_import_status === 'imported' && $exists) || ($filter_import_status === 'new' && !$exists)) {
					$tmp[] = $product;
				}
			}

			$filtered_products = $tmp;
		}

		$product_total = count($filtered_products);
		$page_products = array_slice($filtered_products, ($page - 1) * $limit, $limit);
		$existing_products = $this->model_extension_module_excetra_import->getProductsByModels($this->pluckCodes($page_products));
		$category_map = !empty($settings['category_map']) && is_array($settings['category_map']) ? $settings['category_map'] : array();

		$data['products'] = array();

		foreach ($page_products as $product) {
			$existing = isset($existing_products[$product['code']]) ? $existing_products[$product['code']] : array();
			$mapped_category_id = !empty($category_map[$product['supplier_category_id']]) ? (int)$category_map[$product['supplier_category_id']] : 0;

			$data['products'][] = array(
				'id'                   => $product['id'],
				'code'                 => $product['code'],
				'barcode'              => $product['barcode'],
				'name'                 => $product['name'],
				'brand'                => $product['brand'],
				'quantity'             => $product['quantity'],
				'price_mpc'            => $this->formatPrice($product['prices']['mpc']),
				'price_vpc'            => $this->formatPrice($product['prices']['vpc']),
				'supplier_category_id' => $product['supplier_category_id'],
				'supplier_category'    => $product['supplier_category_name'],
				'image'                => !empty($product['images'][0]['thumb']) ? $product['images'][0]['thumb'] : (!empty($product['images'][0]['url']) ? $product['images'][0]['url'] : ''),
				'is_imported'          => !empty($existing),
				'product_id'           => !empty($existing['product_id']) ? (int)$existing['product_id'] : 0,
				'local_quantity'       => isset($existing['quantity']) ? (int)$existing['quantity'] : '',
				'local_price'          => isset($existing['price']) ? $this->formatPrice($existing['price']) : '',
				'local_name'           => !empty($existing['name']) ? $existing['name'] : '',
				'mapped_category_id'   => $mapped_category_id,
				'mapped_category'      => ($mapped_category_id && isset($local_category_names[$mapped_category_id])) ? $local_category_names[$mapped_category_id] : '',
				'edit'                 => !empty($existing['product_id']) ? $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . (int)$existing['product_id'], true) : ''
			);
		}

		$url = $this->buildFilterUrl(array('page'));

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['user_token'] = $this->session->data['user_token'];
		$data['filter_search'] = $filter_search;
		$data['filter_supplier_category_id'] = $filter_supplier_category_id;
			$data['filter_import_status'] = $filter_import_status;
			$data['filter_action'] = 'index.php';
			$data['import'] = $this->url->link($this->route . '/import', 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true);
			$data['update_quantities'] = $this->url->link($this->route . '/updateQuantities', 'user_token=' . $this->session->data['user_token'] . '&return_route=products' . $this->buildFilterUrl(), true);
			$data['update_prices'] = $this->url->link($this->route . '/updatePrices', 'user_token=' . $this->session->data['user_token'] . '&return_route=products' . $this->buildFilterUrl(), true);
			$data['update_products'] = $this->url->link($this->route . '/updateProducts', 'user_token=' . $this->session->data['user_token'] . '&return_route=products' . $this->buildFilterUrl(), true);
			$data['refresh'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(array('refresh')) . '&refresh=1', true);
			$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
			$data['products_page'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
			$data['categories_page'] = $this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true);
			$data['settings_page'] = $this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true);
			$data['active_tab'] = 'products';

			$data['supplier_categories'] = $supplier_categories;
			$data['local_categories'] = $local_categories;
			$data['local_category_names'] = $local_category_names;
			$data['category_map'] = $category_map;
			$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
			$data['settings'] = $settings;
			$data['price_modes'] = array(
				'mpc' => $this->language->get('text_price_mpc'),
				'vpc' => $this->language->get('text_price_vpc')
			);

		if ($feed_error) {
			$data['error_warning'] = $feed_error;
		} elseif (!empty($this->session->data['warning'])) {
			$data['error_warning'] = $this->session->data['warning'];
			unset($this->session->data['warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (!empty($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

			$this->response->setOutput($this->load->view($this->route, $data));
		}

		public function categories() {
			$this->load->language($this->route);
			$this->load->model('catalog/category');
			$this->load->model('setting/setting');

			$this->document->setTitle($this->language->get('heading_categories'));

			$settings = $this->getSettings();
			$force_refresh = !empty($this->request->get['refresh']);
			$supplier_categories = array();
			$feed_error = '';

			try {
				$supplier_categories = $this->buildCategoriesFromProducts($this->normaliseProducts($this->fetchXml('products', $settings, $force_refresh)));
			} catch (Exception $e) {
				$feed_error = $e->getMessage();
			}

			$data['breadcrumbs'] = array(
				array(
					'text' => $this->language->get('text_home'),
					'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
				),
				array(
					'text' => $this->language->get('heading_title'),
					'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true)
				),
				array(
					'text' => $this->language->get('heading_categories'),
					'href' => $this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true)
				)
			);

			$data['user_token'] = $this->session->data['user_token'];
			$data['products_page'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
			$data['categories_page'] = $this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true);
			$data['settings_page'] = $this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true);
			$data['active_tab'] = 'categories';
			$data['save'] = $this->url->link($this->route . '/save', 'user_token=' . $this->session->data['user_token'], true);
			$data['create_categories'] = $this->url->link($this->route . '/createCategories', 'user_token=' . $this->session->data['user_token'], true);
			$data['refresh'] = $this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'] . '&refresh=1', true);
			$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
			$data['supplier_categories'] = $supplier_categories;
			$data['local_categories'] = $this->model_catalog_category->getCategories(array(
				'sort'  => 'name',
				'order' => 'ASC'
			));
			$data['settings'] = $settings;
			$data['category_map'] = !empty($settings['category_map']) && is_array($settings['category_map']) ? $settings['category_map'] : array();

			if ($feed_error) {
				$data['error_warning'] = $feed_error;
			} elseif (!empty($this->session->data['warning'])) {
				$data['error_warning'] = $this->session->data['warning'];
				unset($this->session->data['warning']);
			} else {
				$data['error_warning'] = '';
			}

			if (!empty($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];
				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			$this->response->setOutput($this->load->view($this->route . '_categories', $data));
		}

		public function settings() {
			$this->load->language($this->route);
			$this->load->model('localisation/tax_class');
			$this->load->model('setting/setting');

			$this->document->setTitle($this->language->get('heading_settings'));

			$settings = $this->getSettings();

			if (empty($settings['cron_token'])) {
				$settings['cron_token'] = token(32);
				$this->saveSettings($settings);
			}

			$data['breadcrumbs'] = array(
				array(
					'text' => $this->language->get('text_home'),
					'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
				),
				array(
					'text' => $this->language->get('heading_title'),
					'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true)
				),
				array(
					'text' => $this->language->get('heading_settings'),
					'href' => $this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true)
				)
			);

			$data['user_token'] = $this->session->data['user_token'];
			$data['products_page'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
			$data['categories_page'] = $this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true);
			$data['settings_page'] = $this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true);
			$data['active_tab'] = 'settings';
			$data['save'] = $this->url->link($this->route . '/save', 'user_token=' . $this->session->data['user_token'], true);
			$data['update_quantities'] = $this->url->link($this->route . '/updateQuantities', 'user_token=' . $this->session->data['user_token'] . '&return_route=settings', true);
			$data['update_prices'] = $this->url->link($this->route . '/updatePrices', 'user_token=' . $this->session->data['user_token'] . '&return_route=settings', true);
			$data['update_products'] = $this->url->link($this->route . '/updateProducts', 'user_token=' . $this->session->data['user_token'] . '&return_route=settings', true);
			$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
			$data['settings'] = $settings;
			$data['cron_url'] = HTTPS_SERVER . 'index.php?route=' . $this->route . '/cron&token=' . $settings['cron_token'];
			$data['feed_url'] = $this->feed_endpoint;
			$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
			$data['price_modes'] = array(
				'mpc' => $this->language->get('text_price_mpc'),
				'vpc' => $this->language->get('text_price_vpc')
			);

			if (!empty($this->session->data['warning'])) {
				$data['error_warning'] = $this->session->data['warning'];
				unset($this->session->data['warning']);
			} else {
				$data['error_warning'] = '';
			}

			if (!empty($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];
				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			$this->response->setOutput($this->load->view($this->route . '_settings', $data));
		}

		public function save() {
			$this->load->language($this->route);
			$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$settings = $this->settingsFromPost($this->getSettings());
			$this->saveSettings($settings);

			$this->session->data['success'] = $this->language->get('text_settings_success');
			}

			$return_route = isset($this->request->post['return_route']) ? $this->request->post['return_route'] : 'settings';

			if ($return_route == 'categories') {
				$this->response->redirect($this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true));
			} else {
				$this->response->redirect($this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

	public function import() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$selected = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : array();

			if (!$selected) {
				$this->session->data['warning'] = $this->language->get('error_selected');
				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
				return;
			}

		$settings = $this->settingsFromPost($this->getSettings());
		$category_map = !empty($settings['category_map']) && is_array($settings['category_map']) ? $settings['category_map'] : array();
		$default_category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;
		$create_parent_category_id = isset($this->request->post['create_parent_category_id']) ? (int)$this->request->post['create_parent_category_id'] : 0;
		$use_category_map = !empty($this->request->post['use_category_map']);
		$auto_create_category = !empty($this->request->post['auto_create_category']);
		$settings['update_existing_descriptions'] = !empty($this->request->post['update_existing_descriptions']);

		try {
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, false));
			$supplier_categories = $this->buildCategoriesFromProducts($products);
			} catch (Exception $e) {
				$this->session->data['warning'] = $e->getMessage();
				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
				return;
			}

		$products_by_code = array();
		foreach ($products as $product) {
			$products_by_code[$product['code']] = $product;
		}

		$supplier_categories_by_id = array();
		foreach ($supplier_categories as $category) {
			$supplier_categories_by_id[$category['id']] = $category;
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$map_changed = false;

		foreach ($selected as $code) {
			$code = trim($code);

			if (!$code || empty($products_by_code[$code])) {
				$skipped++;
				continue;
			}

			$product = $products_by_code[$code];
			$category_id = 0;

			if ($use_category_map && !empty($category_map[$product['supplier_category_id']])) {
				$category_id = (int)$category_map[$product['supplier_category_id']];
			}

			if (!$category_id && $auto_create_category && $product['supplier_category_id']) {
				$supplier_category = !empty($supplier_categories_by_id[$product['supplier_category_id']]) ? $supplier_categories_by_id[$product['supplier_category_id']] : array(
					'id'      => $product['supplier_category_id'],
					'name_hr' => $product['supplier_category_name'],
					'name_en' => $product['supplier_category_name']
				);

				$category_id = $this->model_extension_module_excetra_import->createCategoryFromSupplier($supplier_category, $create_parent_category_id);

				if ($category_id) {
					$category_map[$product['supplier_category_id']] = $category_id;
					$map_changed = true;
				}
			}

			if (!$category_id && $default_category_id) {
				$category_id = $default_category_id;
			}

			if (!$category_id) {
				$skipped++;
				continue;
			}

			$result = $this->model_extension_module_excetra_import->importProduct($product, $category_id, $settings);

			if ($result == 'created') {
				$created++;
			} elseif ($result == 'updated') {
				$updated++;
			} else {
				$skipped++;
			}
		}

		if ($map_changed) {
			$settings['category_map'] = $category_map;
			$this->saveSettings($settings);
		}

			$this->session->data['success'] = sprintf($this->language->get('text_import_success'), $created, $updated, $skipped);
			$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
		}

	public function updateQuantities() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$settings = $this->getSettings();

		try {
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, true));
			$result = $this->model_extension_module_excetra_import->updateQuantities($products);

			$this->session->data['success'] = sprintf($this->language->get('text_quantity_success'), $result['updated'], $result['not_found']);
		} catch (Exception $e) {
			$this->session->data['warning'] = $e->getMessage();
		}

			$return_route = isset($this->request->get['return_route']) ? $this->request->get['return_route'] : 'settings';

			if ($return_route == 'products') {
				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
			} else {
				$this->response->redirect($this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

	public function updatePrices() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$settings = $this->getSettings();

		try {
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, true));
			$result = $this->model_extension_module_excetra_import->updatePrices($products, $settings);

			$this->session->data['success'] = sprintf($this->language->get('text_price_success'), $result['updated'], $result['not_found'], $result['skipped']);
		} catch (Exception $e) {
			$this->session->data['warning'] = $e->getMessage();
		}

			$return_route = isset($this->request->get['return_route']) ? $this->request->get['return_route'] : 'settings';

			if ($return_route == 'products') {
				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
			} else {
				$this->response->redirect($this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

	public function updateProducts() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$settings = $this->getSettings();

		try {
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, true));
			$result = $this->model_extension_module_excetra_import->updateProducts($products, $settings);

			$this->session->data['success'] = sprintf($this->language->get('text_update_success'), $result['updated'], $result['not_found'], $result['skipped']);
		} catch (Exception $e) {
			$this->session->data['warning'] = $e->getMessage();
		}

			$return_route = isset($this->request->get['return_route']) ? $this->request->get['return_route'] : 'settings';

			if ($return_route == 'products') {
				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
			} else {
				$this->response->redirect($this->url->link($this->route . '/settings', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

	public function updateProductImages() {
		$this->load->language($this->route);
		$this->load->model('catalog/product');
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$redirect = $product_id ? $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id, true) : $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'], true);
		$product_info = $product_id ? $this->model_catalog_product->getProduct($product_id) : array();

		if (!$product_info) {
			$this->session->data['warning'] = $this->language->get('error_product_not_found');
			$this->response->redirect($redirect);
			return;
		}

		try {
			$settings = $this->getSettings();
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, false));
			$supplier_product = $this->findSupplierProductForLocalProduct($products, $product_info);

			if (!$supplier_product) {
				$this->session->data['warning'] = $this->language->get('error_excetra_product_not_found');
				$this->response->redirect($redirect);
				return;
			}

			$count = $this->model_extension_module_excetra_import->updateProductImagesFromSupplier($product_id, $supplier_product);

			if ($count) {
				$this->session->data['success'] = sprintf($this->language->get('text_image_update_success'), $count);
			} else {
				$this->session->data['warning'] = $this->language->get('error_excetra_images_not_found');
			}
		} catch (Exception $e) {
			$this->session->data['warning'] = $e->getMessage();
		}

		$this->response->redirect($redirect);
	}

	public function cron() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$settings = $this->getSettings();
		$token = isset($this->request->get['token']) ? $this->request->get['token'] : '';

		$this->response->addHeader('Content-Type: application/json');

		if (empty($settings['cron_token']) || !hash_equals($settings['cron_token'], $token)) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
			$this->response->setOutput(json_encode(array(
				'success' => false,
				'error'   => 'Invalid token'
			)));
			return;
		}

		try {
			$action = isset($this->request->get['action']) ? $this->request->get['action'] : 'products';
			$products = $this->normaliseProducts($this->fetchXml('products', $settings, true));

			if ($action == 'prices') {
				$result = $this->model_extension_module_excetra_import->updatePrices($products, $settings);
			} elseif ($action == 'quantities') {
				$result = $this->model_extension_module_excetra_import->updateQuantities($products);
			} else {
				$action = 'products';
				$result = $this->model_extension_module_excetra_import->updateProducts($products, $settings);
			}

			$this->response->setOutput(json_encode(array(
				'success'   => true,
				'action'    => $action,
				'updated'   => $result['updated'],
				'not_found' => $result['not_found'],
				'skipped'   => isset($result['skipped']) ? $result['skipped'] : 0
			)));
		} catch (Exception $e) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
			$this->response->setOutput(json_encode(array(
				'success' => false,
				'error'   => $e->getMessage()
			)));
		}
	}

	public function createCategories() {
		$this->load->language($this->route);
		$this->load->model('extension/module/excetra_import');
		$this->load->model('setting/setting');

		$selected = isset($this->request->post['supplier_category_ids']) ? (array)$this->request->post['supplier_category_ids'] : array();

			if (!$selected) {
				$this->session->data['warning'] = $this->language->get('error_category_selected');
				$this->response->redirect($this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}

		$settings = $this->settingsFromPost($this->getSettings());
		$category_map = !empty($settings['category_map']) && is_array($settings['category_map']) ? $settings['category_map'] : array();
		$create_parent_category_id = isset($this->request->post['create_parent_category_id']) ? (int)$this->request->post['create_parent_category_id'] : 0;
		$created = 0;
		$linked = 0;

		try {
			$supplier_categories = $this->buildCategoriesFromProducts($this->normaliseProducts($this->fetchXml('products', $settings, false)));
			} catch (Exception $e) {
				$this->session->data['warning'] = $e->getMessage();
				$this->response->redirect($this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}

		$supplier_categories_by_id = array();
		foreach ($supplier_categories as $category) {
			$supplier_categories_by_id[$category['id']] = $category;
		}

		foreach ($selected as $supplier_category_id) {
			$supplier_category_id = trim($supplier_category_id);

			if (!$supplier_category_id || empty($supplier_categories_by_id[$supplier_category_id])) {
				continue;
			}

			$category_id = !empty($category_map[$supplier_category_id]) ? (int)$category_map[$supplier_category_id] : 0;

			if (!$category_id) {
				$category_id = $this->model_extension_module_excetra_import->createCategoryFromSupplier($supplier_categories_by_id[$supplier_category_id], $create_parent_category_id);
				$created++;
			}

			if ($category_id) {
				$category_map[$supplier_category_id] = $category_id;
				$linked++;
			}
		}

		$settings['category_map'] = $category_map;
		$this->saveSettings($settings);

			$this->session->data['success'] = sprintf($this->language->get('text_category_create_success'), $created, $linked);
			$this->response->redirect($this->url->link($this->route . '/categories', 'user_token=' . $this->session->data['user_token'], true));
		}

		private function fetchXml($cache_key, $settings, $force_refresh) {
			$cache_file = DIR_CACHE . 'excetra_import_' . $cache_key . '.xml';
			$cache_lifetime = 1800;

		if (!$force_refresh && is_file($cache_file) && (filemtime($cache_file) > (time() - $cache_lifetime))) {
			$content = file_get_contents($cache_file);
		} else {
			$content = $this->requestFeed();

			if ($content) {
				file_put_contents($cache_file, $content);
			}
		}

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE);

		if (!$xml) {
			throw new Exception($this->language->get('error_xml'));
		}

		return $xml;
	}

	private function requestFeed() {
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->feed_endpoint);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_TIMEOUT, 90);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'SalonaTech Excetra Import');

			$content = curl_exec($ch);
			$error = curl_error($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($content === false || $status_code < 200 || $status_code >= 300) {
				throw new Exception(sprintf($this->language->get('error_feed'), $status_code, $error));
			}

			return $content;
		}

		$context = stream_context_create(array(
			'http' => array(
				'method'  => 'GET',
				'header'  => 'User-Agent: SalonaTech Excetra Import',
				'timeout' => 90
			)
		));

		$content = @file_get_contents($this->feed_endpoint, false, $context);

		if ($content === false) {
			throw new Exception(sprintf($this->language->get('error_feed'), 0, 'file_get_contents'));
		}

		return $content;
	}

	private function normaliseProducts($xml) {
		$products = array();

		foreach ($xml->product as $node) {
			$code = trim((string)$node->Sifraartikla);
			$ean = trim((string)$node->EANCODE);
			$name = $this->normaliseText((string)$node->Nazivartikla);
			$category_name = $this->normaliseText((string)$node->Kategorija);
			$active = trim((string)$node->Aktivniartikli) !== '0';

			if ($code === '' || $name === '') {
				continue;
			}

			$products[] = array(
				'id'                     => $code,
				'code'                   => $code,
				'barcode'                => $ean,
				'name'                   => $name,
				'name_hr'                => $name,
				'name_en'                => $name,
				'brand'                  => $this->normaliseText((string)$node->Brand),
				'quantity'               => $active ? (int)$this->parseDecimal((string)$node->Kolicina) : 0,
				'status'                 => $active ? 1 : 0,
				'availability_display'   => $active ? 'Aktivan' : 'Neaktivan',
				'items_in_package'       => '',
				'supplier_category_id'   => $category_name,
				'supplier_category_name' => $category_name,
				'supplier_parent_id'     => '',
				'supplier_parent_name'   => '',
				'supplier_leaf_id'       => $category_name,
				'supplier_leaf_name'     => $category_name,
				'description_hr'         => $this->normaliseText((string)$node->Opis),
				'description_en'         => $this->normaliseText((string)$node->Opis),
				'attributes_hr'          => $this->normaliseDescriptionAttributes($node),
				'attributes_en'          => $this->normaliseDescriptionAttributes($node),
				'images'                 => $this->normaliseImages($node),
				'prices'                 => $this->normalisePrices($node)
			);
		}

		return $products;
	}

	private function normaliseAvailability($xml) {
		return $this->normaliseProducts($xml);
	}

	private function buildCategoriesFromProducts($products) {
		$categories = array();

		foreach ($products as $product) {
			if ($product['supplier_category_id'] && empty($categories[$product['supplier_category_id']])) {
				$categories[$product['supplier_category_id']] = array(
					'id'          => $product['supplier_category_id'],
					'map_key'     => rawurlencode($product['supplier_category_id']),
					'name'        => $product['supplier_category_name'],
					'name_hr'     => $product['supplier_category_name'],
					'name_en'     => $product['supplier_category_name'],
					'parent_id'   => $product['supplier_parent_id'],
					'parent_name' => $product['supplier_parent_name'],
					'leaf_id'     => $product['supplier_leaf_id'],
					'leaf_name'   => $product['supplier_leaf_name']
				);
			}
		}

		usort($categories, function($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		});

		return $categories;
	}

	private function normaliseDescriptionAttributes($node) {
		$attributes = array();
		$fields = array(
			'Jamstvo' => 'Jamstvo',
			'PDV'     => 'PDV (%)',
			'FOKUS'   => 'Fokus'
		);

		foreach ($fields as $field => $title) {
			$value = isset($node->{$field}) ? $this->normaliseText((string)$node->{$field}) : '';

			if ($value !== '') {
				$attributes[] = array(
					'title' => $title,
					'value' => $value
				);
			}
		}

		return $attributes;
	}

	private function normaliseImages($node) {
		$images = array();
		$seen = array();
		$candidates = array();

		if (!empty($node->Slikeartikla)) {
			foreach (explode(',', (string)$node->Slikeartikla) as $image_url) {
				$candidates[] = $this->normaliseImageUrl($image_url);
			}
		}

		if (!empty($node->Naslovnaslika)) {
			$candidates[] = $this->normaliseImageUrl((string)$node->Naslovnaslika);
		}

		foreach ($candidates as $url) {
			if (!$this->isUsableImageUrl($url) || isset($seen[$url])) {
				continue;
			}

			$seen[$url] = true;
			$images[] = array(
				'url'   => $url,
				'thumb' => $url,
				'title' => ''
			);
		}

		return $images;
	}

	private function isUsableImageUrl($url) {
		if ($url === '' || substr($url, -1) === '/' || stripos($url, '/null') !== false) {
			return false;
		}

		$extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

		return in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
	}

	private function normalisePrices($node) {
		return array(
			'mpc' => isset($node->MPCsapdv) ? $this->parseDecimal((string)$node->MPCsapdv) : 0,
			'vpc' => isset($node->VPCbezpdv) ? $this->parseDecimal((string)$node->VPCbezpdv) : 0
		);
	}

	private function normaliseImageUrl($url) {
		$url = trim($url);

		if ($url === '') {
			return '';
		}

		if (strpos($url, '//') === 0) {
			return 'https:' . $url;
		}

		if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
			return $url;
		}

		$local_prefix = '/home/excetra/excetrashop.hr/';

		if (strpos($url, $local_prefix) === 0) {
			return 'https://excetrashop.hr/' . ltrim(substr($url, strlen($local_prefix)), '/');
		}

		if (isset($url[0]) && $url[0] === '/') {
			return 'https://excetrashop.hr' . $url;
		}

		return $url;
	}

	private function normaliseText($value) {
		$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		$value = str_replace("\xc2\xa0", ' ', $value);
		$value = preg_replace('/[ \t]+/', ' ', $value);

		return trim($value);
	}

	private function parseDecimal($value) {
		$value = trim((string)$value);
		$value = str_replace(array("\xc2\xa0", ' '), '', $value);

		if (strpos($value, ',') !== false) {
			$value = str_replace('.', '', $value);
			$value = str_replace(',', '.', $value);
		}

		return (float)$value;
	}

	private function filterProducts($products, $search, $supplier_category_id) {
		$filtered = array();
		$search = utf8_strtolower($search);

		foreach ($products as $product) {
			if ($supplier_category_id && $product['supplier_category_id'] !== $supplier_category_id) {
				continue;
			}

			if ($search) {
				$haystack = utf8_strtolower($product['code'] . ' ' . $product['barcode'] . ' ' . $product['name'] . ' ' . $product['brand'] . ' ' . $product['supplier_category_name']);

				if (strpos($haystack, $search) === false) {
					continue;
				}
			}

			$filtered[] = $product;
		}

		return $filtered;
	}

	private function pluckCodes($products) {
		$codes = array();

		foreach ($products as $product) {
			if (!empty($product['code'])) {
				$codes[] = $product['code'];
			}
		}

		return $codes;
	}

	private function findSupplierProductForLocalProduct($products, $product_info) {
		foreach ($products as $product) {
			if (!empty($product['code']) && !empty($product_info['model']) && $product['code'] === $product_info['model']) {
				return $product;
			}
		}

		foreach ($products as $product) {
			if (!empty($product['id']) && !empty($product_info['sku']) && $product['id'] === $product_info['sku']) {
				return $product;
			}

			if (!empty($product['barcode']) && !empty($product_info['ean']) && $product['barcode'] === $product_info['ean']) {
				return $product;
			}
		}

		return array();
	}

	private function getSettings() {
		if (!isset($this->model_setting_setting)) {
			$this->load->model('setting/setting');
		}

		$setting = $this->model_setting_setting->getSetting('excetra_import');

		return array(
			'price_mode'     => !empty($setting['excetra_import_price_mode']) ? $setting['excetra_import_price_mode'] : 'mpc',
			'margin'         => isset($setting['excetra_import_margin']) ? (float)$setting['excetra_import_margin'] : 0,
			'tax_class_id'   => isset($setting['excetra_import_tax_class_id']) ? (int)$setting['excetra_import_tax_class_id'] : 0,
			'category_map'   => !empty($setting['excetra_import_category_map']) && is_array($setting['excetra_import_category_map']) ? $setting['excetra_import_category_map'] : array(),
			'cron_token'     => !empty($setting['excetra_import_cron_token']) ? $setting['excetra_import_cron_token'] : ''
		);
	}

	private function settingsFromPost($current) {
		$settings = $current;

		if (isset($this->request->post['excetra_import_price_mode']) && in_array($this->request->post['excetra_import_price_mode'], array('mpc', 'vpc'))) {
			$settings['price_mode'] = $this->request->post['excetra_import_price_mode'];
		}

		if (isset($this->request->post['excetra_import_margin'])) {
			$settings['margin'] = (float)str_replace(',', '.', $this->request->post['excetra_import_margin']);
		}

		if (isset($this->request->post['excetra_import_tax_class_id'])) {
			$settings['tax_class_id'] = (int)$this->request->post['excetra_import_tax_class_id'];
		}

		if (isset($this->request->post['excetra_import_cron_token']) && trim($this->request->post['excetra_import_cron_token']) !== '') {
			$settings['cron_token'] = trim($this->request->post['excetra_import_cron_token']);
		} elseif (empty($settings['cron_token'])) {
			$settings['cron_token'] = token(32);
		}

		if (isset($this->request->post['excetra_import_category_map']) && is_array($this->request->post['excetra_import_category_map'])) {
			$settings['category_map'] = array();

			foreach ($this->request->post['excetra_import_category_map'] as $supplier_category_id => $category_id) {
				if ((int)$category_id > 0) {
					$settings['category_map'][trim(rawurldecode($supplier_category_id))] = (int)$category_id;
				}
			}
		}

		return $settings;
	}

	private function saveSettings($settings) {
		if (!isset($this->model_setting_setting)) {
			$this->load->model('setting/setting');
		}

		$this->model_setting_setting->editSetting('excetra_import', array(
			'excetra_import_price_mode'     => $settings['price_mode'],
			'excetra_import_margin'         => $settings['margin'],
			'excetra_import_tax_class_id'   => $settings['tax_class_id'],
			'excetra_import_category_map'   => $settings['category_map'],
			'excetra_import_cron_token'     => $settings['cron_token']
		));
	}

	private function buildFilterUrl($exclude = array()) {
		$url = '';
		$params = array('filter_search', 'filter_supplier_category_id', 'filter_import_status', 'page');

		foreach ($params as $param) {
			if (in_array($param, $exclude)) {
				continue;
			}

			if (isset($this->request->get[$param]) && $this->request->get[$param] !== '') {
				$url .= '&' . $param . '=' . urlencode(html_entity_decode($this->request->get[$param], ENT_QUOTES, 'UTF-8'));
			}
		}

		return $url;
	}

	private function clearFeedCache() {
		foreach (array('products') as $cache_key) {
			$cache_file = DIR_CACHE . 'excetra_import_' . $cache_key . '.xml';

			if (is_file($cache_file)) {
				@unlink($cache_file);
			}
		}
	}

	private function formatPrice($price) {
		return number_format((float)$price, 2, '.', '');
	}
}
