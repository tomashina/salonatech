<?php

class ControllerExtensionModuleEnergyLabel extends Controller
{
    public function install()
    {
        //$this->log->write("Installed");
        $this->load->model('extension/module/energy_label');
        $this->model_extension_module_energy_label->createTables();

    }

    public function uninstall()
    {
        //$this->log->write("unInstalled");
        $this->load->model('extension/module/energy_label');
        $this->model_extension_module_energy_label->dropTables();

    }
    public function index()
    {
        $data = array();

        $data['module_code'] = 'module_energy_label';
        $config_key = "license_portal_key_" . $data['module_code'];
        $data['LICENSE_PORTAL_URL'] = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $this->load->language('extension/module/energy_label');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

            $key_from_post[$config_key] = $this->request->post[$config_key] ?? '';
            if ($key_from_post[$config_key]) {
                $this->model_setting_setting->editSetting('license_portal', $this->request->post);
                $this->response->redirect($data['LICENSE_PORTAL_URL']);
            }

            $this->model_setting_setting->editSetting('module_energy_label', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            //  $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $text_strings = array(
            'heading_title',
            'energy_label_title',
            'text_extension',
            'text_success',
            'text_edit',
            'text_button_default',
            'text_button_settings',
            'text_pages_settings',
            'text_j3_pages_settings',
            'text_settings',
            'text_settings_cart',
            'text_settings_product',
            'text_settings_category',
            'text_settings_search',
            'text_settings_compare',
            'text_settings_wishlist',
            'text_settings_featured',
            'text_settings_j3',
            'text_settings_manufacturer',
            'text_settings_catalog',
            'entry_status',
            'entry_status_product_product',
            'entry_status_checkout_cart',
            'entry_status_common_cart',
            'entry_status_product_compare',
            'entry_status_account_wishlist',
            'entry_status_product_category',
            'entry_status_product_search',
            'entry_status_module_featured',
            'entry_status_j3_side_products',
            'entry_status_j3_products',
            'entry_status_product_related',
            'entry_status_datasheet',
            'entry_status_manufacturer',
            'entry_status_product_catalog',
            'entry_datasheet',
            'entry_status_pdf',
            'entry_print',
            'entry_status_pop_up',
            //opencart
            'text_enabled',
            'text_disabled',
            //'language.code',
            //'language.name',
            //'language.language_id',
            //'module_energy_label_datasheet[language.language_id]',
            //'error_button[language.language_id]',

        );

        foreach ($text_strings as $text) {
            $data[$text] = $this->language->get($text);
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/energy_label', 'user_token=' . $this->session->data['user_token'], true)
        );


        $data['action'] = $this->url->link('extension/module/energy_label', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['user_token'] = $this->session->data['user_token'];

        if (isset($this->request->post['module_energy_label_status'])) {
            $data['module_energy_label_status'] = $this->request->post['module_energy_label_status'];
        } else {
            $data['module_energy_label_status'] = $this->config->get('module_energy_label_status');
        }
        if (isset($this->request->post['module_energy_label_product_product_status'])) {
            $data['module_energy_label_product_product_status'] = $this->request->post['module_energy_label_product_product_status'];
        } else {
            $data['module_energy_label_product_product_status'] = $this->config->get('module_energy_label_product_product_status');
        }
        if (isset($this->request->post['module_energy_label_checkout_cart_status'])) {
            $data['module_energy_label_checkout_cart_status'] = $this->request->post['module_energy_label_checkout_cart_status'];
        } else {
            $data['module_energy_label_checkout_cart_status'] = $this->config->get('module_energy_label_checkout_cart_status');
        }
        if (isset($this->request->post['module_energy_label_common_cart_status'])) {
            $data['module_energy_label_common_cart_status'] = $this->request->post['module_energy_label_common_cart_status'];
        } else {
            $data['module_energy_label_common_cart_status'] = $this->config->get('module_energy_label_common_cart_status');
        }
        if (isset($this->request->post['module_energy_label_product_compare_status'])) {
            $data['module_energy_label_product_compare_status'] = $this->request->post['module_energy_label_product_compare_status'];
        } else {
            $data['module_energy_label_product_compare_status'] = $this->config->get('module_energy_label_product_compare_status');
        }
        if (isset($this->request->post['module_energy_label_account_wishlist_status'])) {
            $data['module_energy_label_account_wishlist_status'] = $this->request->post['module_energy_label_account_wishlist_status'];
        } else {
            $data['module_energy_label_account_wishlist_status'] = $this->config->get('module_energy_label_account_wishlist_status');
        }
        if (isset($this->request->post['module_energy_label_product_category_status'])) {
            $data['module_energy_label_product_category_status'] = $this->request->post['module_energy_label_product_category_status'];
        } else {
            $data['module_energy_label_product_category_status'] = $this->config->get('module_energy_label_product_category_status');
        }
        if (isset($this->request->post['module_energy_label_product_search_status'])) {
            $data['module_energy_label_product_search_status'] = $this->request->post['module_energy_label_product_search_status'];
        } else {
            $data['module_energy_label_product_search_status'] = $this->config->get('module_energy_label_product_search_status');
        }
        if (isset($this->request->post['module_energy_label_module_featured_status'])) {
            $data['module_energy_label_module_featured_status'] = $this->request->post['module_energy_label_module_featured_status'];
        } else {
            $data['module_energy_label_module_featured_status'] = $this->config->get('module_energy_label_module_featured_status');
        }
        if (isset($this->request->post['module_energy_label_j3_products_status'])) {
            $data['module_energy_label_j3_products_status'] = $this->request->post['module_energy_label_j3_products_status'];
        } else {
            $data['module_energy_label_j3_products_status'] = $this->config->get('module_energy_label_j3_products_status');
        }
        if (isset($this->request->post['module_energy_label_j3_side_products_status'])) {
            $data['module_energy_label_j3_side_products_status'] = $this->request->post['module_energy_label_j3_side_products_status'];
        } else {
            $data['module_energy_label_j3_side_products_status'] = $this->config->get('module_energy_label_j3_side_products_status');
        }
        if (isset($this->request->post['module_energy_label_datasheet_status'])) {
            $data['module_energy_label_datasheet_status'] = $this->request->post['module_energy_label_datasheet_status'];
        } else {
            $data['module_energy_label_datasheet_status'] = $this->config->get('module_energy_label_datasheet_status');
        }
        if (isset($this->request->post['module_energy_label_pdf_status'])) {
            $data['module_energy_label_pdf_status'] = $this->request->post['module_energy_label_pdf_status'];
        } else {
            $data['module_energy_label_pdf_status'] = $this->config->get('module_energy_label_pdf_status');
        }
        if (isset($this->request->post['module_energy_label_manufacturer_status'])) {
            $data['module_energy_label_manufacturer_status'] = $this->request->post['module_energy_label_manufacturer_status'];
        } else {
            $data['module_energy_label_manufacturer_status'] = $this->config->get('module_energy_label_manufacturer_status');
        }
        if (isset($this->request->post['module_energy_label_product_related_status'])) {
            $data['module_energy_label_product_related_status'] = $this->request->post['module_energy_label_product_related_status'];
        } else {
            $data['module_energy_label_product_related_status'] = $this->config->get('module_energy_label_product_related_status');
        }
        if (isset($this->request->post['module_energy_label_product_catalog_status'])) {
            $data['module_energy_label_product_catalog_status'] = $this->request->post['module_energy_label_product_catalog_status'];
        } else {
            $data['module_energy_label_product_catalog_status'] = $this->config->get('module_energy_label_product_catalog_status');
        }
        if (isset($this->request->post['module_energy_label_status_pop_up'])) {
            $data['module_energy_label_status_pop_up'] = $this->request->post['module_energy_label_status_pop_up'];
        } else {
            $data['module_energy_label_status_pop_up'] = $this->config->get('module_energy_label_status_pop_up');
        }


        $this->load->model('localisation/language');

        $data['languages'] = $this->model_localisation_language->getLanguages();
        if (isset($this->request->post['module_energy_label_datasheet'])) {
            $data['module_energy_label_datasheet'] = $this->request->post['module_energy_label_datasheet'];
        } else if ($this->config->get('module_energy_label_datasheet')) {
            $data['module_energy_label_datasheet'] = $this->config->get('module_energy_label_datasheet');
        } else {
            foreach ($data['languages'] as $language) {
                $data['module_energy_label_datasheet'][$language['language_id']] = $this->language->get('text_button_default');
            }
        }
        if (isset($this->request->post['module_energy_label_datasheet_print'])) {
            $data['module_energy_label_datasheet_print'] = $this->request->post['module_energy_label_datasheet_print'];
        } else if ($this->config->get('module_energy_label_datasheet_print')) {
            $data['module_energy_label_datasheet_print'] = $this->config->get('module_energy_label_datasheet_print');
        } else {
            foreach ($data['languages'] as $language) {
                $data['module_energy_label_datasheet_print'][$language['language_id']] = $this->language->get('text_button_default');
            }
        }
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        $license_key = $this->config->get($config_key) ? $this->config->get($config_key) : 'license';

        $data['LICENSE_PORTAL_DEV_DOMAIN'] = !!$this->config->get("license_portal_dev_domain_" . $data['module_code']);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://licenseportal.partneris.net/api/template/$license_key",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json, text/plain, */*'
            ),
        ));

        $response = curl_exec($curl);
        echo html_entity_decode($response);
    }
}