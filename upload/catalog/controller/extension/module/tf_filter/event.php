<?php
class ControllerExtensionModuleTfFilterEvent extends Controller {
        public function getProducts($route, $param) {
                $this->load->model('extension/maza/tf_product');
                
                if(empty($param[0])){
                    return $this->model_extension_maza_tf_product->getProducts();
                } else {
                    $param[0] = $this->applyManufacturerCategoryFilter($param[0]);

                    return $this->model_extension_maza_tf_product->getProducts($param[0]);
                }
                
        }
        
        public function getTotalProducts($route, $param) {
                $this->load->model('extension/maza/tf_product');
                
                if(empty($param[0])){
                    return $this->model_extension_maza_tf_product->getTotalProducts();
                } else {
                    $param[0] = $this->applyManufacturerCategoryFilter($param[0]);

                    return $this->model_extension_maza_tf_product->getTotalProducts($param[0]);
                }
                
        }

        private function applyManufacturerCategoryFilter($filter_data) {
                if (!is_array($filter_data)) {
                    $filter_data = array();
                }

                if (
                    isset($this->request->get['route'])
                    && $this->request->get['route'] === 'product/manufacturer/info'
                    && !empty($this->request->get['tf_fsc'])
                ) {
                    $filter_data['filter_category_id'] = explode('.', $this->request->get['tf_fsc']);
                    $filter_data['filter_sub_category'] = $this->config->get('module_tf_filter_sub_category');
                }

                return $filter_data;
        }
}
