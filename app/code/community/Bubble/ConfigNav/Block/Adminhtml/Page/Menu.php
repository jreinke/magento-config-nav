<?php
/**
 * Override default menu generation to include config.
 *
 * @category    Bubble
 * @package     Bubble_ConfigNav
 * @version     1.0.0
 * @copyright   Copyright (c) 2014 BubbleShop (https://www.bubbleshop.net)
 */
class Bubble_ConfigNav_Block_Adminhtml_Page_Menu extends Mage_Adminhtml_Block_Page_Menu
{
    /**
     * Add config to menu array
     *
     * @return array
     */
    public function getMenuArray()
    {
        $menu = parent::getMenuArray();

        if (!isset($menu['system']) || !isset($menu['system']['children']['config'])) {
            return $menu; // stop here, no access to configuration
        }

        $menu['config'] = array(
            'label'         => Mage::helper('adminhtml')->__('Config'),
            'sort_order'    => 1000,
            'url'           => '#',
            'active'        => false,
            'level'         => 0,
            'click'         => 'return false',
            'children'      => array(),
        );

        $url            = Mage::getModel('adminhtml/url');
        $websiteCode    = $this->getRequest()->getParam('website');
        $storeCode      = $this->getRequest()->getParam('store');
        $configFields   = Mage::getSingleton('adminhtml/config');
        $sections       = (array) $configFields->getSections();
        $tabs           = (array) $configFields->getTabs()->children();

        usort($sections, array($this, '_sort'));
        usort($tabs, array($this, '_sort'));

        foreach ($tabs as $i => $tab) {
            if (strlen(trim((string) $tab->label)) === 0) {
                continue;
            }
            $helperName = $configFields->getAttributeModule($tab);
            $label = Mage::helper($helperName)->__((string) $tab->label);
            $menu['config']['children'][$tab->getName()] = array(
                'label'         => $label,
                'sort_order'    => $i,
                'url'           => '#',
                'active'        => false,
                'level'         => 1,
                'click'         => 'return false',
                'children'      => array(),
            );
        }

        foreach ($sections as $i => $section) {
            Mage::dispatchEvent('adminhtml_block_system_config_init_tab_sections_before', array('section' => $section));

            $tab = (string) $section->tab;

            if (!isset($menu['config']['children'][$tab]) || strlen(trim((string) $section->label)) === 0) {
                continue;
            }

            $hasChildren    = $configFields->hasChildren($section, $websiteCode, $storeCode);
            $code           = $section->getName();
            $sectionAllowed = $this->checkSectionPermissions($code);
            $helperName     = $configFields->getAttributeModule($section);
            $label          = Mage::helper($helperName)->__((string) $section->label);

            if ($sectionAllowed && $hasChildren) {
                $menu['config']['children'][$tab]['children'][$code] = array(
                    'label'         => $label,
                    'sort_order'    => $i,
                    'url'           => $url->getUrl('*/system_config/', array('section' => $code)),
                    'active'        => false,
                    'level'         => 2,
                );
            }
        }

        end($menu['config']['children']);
        $menu['config']['children'][key($menu['config']['children'])]['last'] = true;

        foreach ($menu['config']['children'] as $code => &$tab) {
            if (empty($tab['children'])) {
                unset($menu['config']['children'][$code]);
                continue;
            }
            end($tab['children']);
            $tab['children'][key($tab['children'])]['last'] = true;
        }

        // Mark the new Config tab as active (and System as inactive) if we are in configuration section
        if ($menu['system']['active']) {
            $menu['system']['active'] = false;
            $menu['config']['active'] = true;
        }

        return $menu;
    }

    /**
     * @param null $code
     * @return bool
     */
    public function checkSectionPermissions($code = null)
    {
        static $permissions;

        if (!$code || trim($code) == '') {
            return false;
        }

        if (!$permissions) {
            $permissions = Mage::getSingleton('admin/session');
        }

        $showTab = false;
        if ($permissions->isAllowed('system/config/' . $code)) {
            $showTab = true;
        }

        return $showTab;
    }

    /**
     * @param $a
     * @param $b
     * @return int
     */
    protected function _sort($a, $b)
    {
        return (int) $a->sort_order < (int) $b->sort_order ? -1 : ((int) $a->sort_order > (int) $b->sort_order ? 1 : 0);
    }
}