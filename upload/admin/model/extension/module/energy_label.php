<?php
class ModelExtensionModuleEnergyLabel extends Model
{
    public function createTables() {
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "product_energy_info
               (
              `energy_info_id` int(11) NOT NULL AUTO_INCREMENT,
              `product_id` int(11) NOT NULL,
              `energy_class_id` int(11) NOT NULL,
              `energy_image` varchar(255),
              `energy_pdf_file` varchar(255),
              PRIMARY KEY (`energy_info_id`))
              ENGINE=MyISAM DEFAULT CHARSET=utf8");




        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "product_attribute_view
            (
                `product_id` int(11) NOT NULL,
                `attribute_id` int(11) NOT NULL,
                `sort_order` int(11) NOT NULL,
                `view_spec` tinyint(1) NOT NULL,
                PRIMARY KEY (`product_id`,`attribute_id`))
                ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }

    public function dropTables()
    {
        $this->db->query("DROP TABLE `" . DB_PREFIX . "product_attribute_view`");
        $this->db->query("DROP TABLE `" . DB_PREFIX . "product_energy_info`");
    }
}