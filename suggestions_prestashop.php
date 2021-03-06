<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    Yuri Denisov <contact@splashmart.ru>
 *  @copyright 2014-2017 Yuri Denisov
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_'))
    exit;

class suggestions_prestashop extends Module
{

    private $valid_fields = array('id_state','id_country');

    public function __construct()
    {
        $this->name = 'suggestions_prestashop';
        $this->tab = 'checkout';
        $this->version = '1.6.0';
        $this->author = 'Yuri Denisov';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DaData Suggestions');
        $this->description = $this->l('Module that suggest addresses on checkout page via DaData.ru SaaS');

        $this->confirmUninstall = $this->l('Are you sure want to uninstall?');

    }

    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('createAccountTop') ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_TOKEN','') ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_COUNT',5) ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_HIDE',true) ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_FIO',true) ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_ADDRESS',true) ||
            !Configuration::updateValue('DADATA_SUGGESTIONS_REGION_FIELD','id_state'))
            return false;
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_TOKEN') ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_COUNT') ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_HIDE') ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_FIO') ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_ADDRESS') ||
            !Configuration::deleteByName('DADATA_SUGGESTIONS_REGION_FIELD'))
            return false;
        return true;
    }

    protected function wrapScriptOnLoad() {
        $output = null;
        $output .= '<script type="text/javascript">';
        $output .= '$(document).ready(function() {';
        $output .= 'dadataSuggestions.configuration.suggest_fio= "'.$this->context->customer->firstname.' '.$this->context->customer->lastname.'";';
        $output .= 'dadataSuggestions.configuration.suggest_fio_label= "'.$this->l('Full Name').'";';
        $output .= 'dadataSuggestions.configuration.suggest_address_label= "'.$this->l('Full Address').'";';
        $output .= 'dadataSuggestions.configuration.DADATA_SUGGESTIONS_TOKEN= "'.strval(Configuration::get('DADATA_SUGGESTIONS_TOKEN')).'";';
        $output .= 'dadataSuggestions.configuration.DADATA_SUGGESTIONS_COUNT= '.(Configuration::get('DADATA_SUGGESTIONS_COUNT')>0?strval(Configuration::get('DADATA_SUGGESTIONS_COUNT')):'5').';';
        $output .= 'dadataSuggestions.configuration.DADATA_SUGGESTIONS_REGION_FIELD= "'.strval(Configuration::get('DADATA_SUGGESTIONS_REGION_FIELD')).'";';
        $output .= 'dadataSuggestions.configuration.DADATA_SUGGESTIONS_FIO= '.(Configuration::get('DADATA_SUGGESTIONS_FIO')==1?'true':'false').';';
        $output .= 'dadataSuggestions.configuration.DADATA_SUGGESTIONS_ADDRESS= '.(Configuration::get('DADATA_SUGGESTIONS_ADDRESS')==1?'true':'false').';';
        $output .= 'dadataSuggestions.init();';
        $output .= '});';
        $output .= '</script>';
        return $output;
    }


    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $dadata_token = strval(Tools::getValue('DADATA_SUGGESTIONS_TOKEN'));
            $dadata_count = strval(Tools::getValue('DADATA_SUGGESTIONS_COUNT'));
            $dadata_fio = strval(Tools::getValue('DADATA_SUGGESTIONS_FIO'));
            $dadata_address = strval(Tools::getValue('DADATA_SUGGESTIONS_ADDRESS'));
            $dadata_region_field = strval(Tools::getValue('DADATA_SUGGESTIONS_REGION_FIELD'));
            if (!$dadata_token
                || empty($dadata_token)
                || !Validate::isSha1($dadata_token)
            )
                $output .= $this->displayError($this->l('Invalid').' '.$this->l('DaData.ru API Token'));
            elseif (!Validate::isBool($dadata_fio))
                $output .= $this->displayError($this->l('Invalid hide selection'));
            elseif (!Validate::isBool($dadata_address))
                $output .= $this->displayError($this->l('Invalid hide selection'));
            elseif (!in_array($dadata_region_field,$this->valid_fields))
                $output .= $this->displayError($this->l('Invalid field name'));
            elseif (!$dadata_count
                || empty($dadata_count)
                || !Validate::isUnsignedInt($dadata_count)
                || $dadata_count=='0'

            )
                $output .= $this->displayError($this->l('Invalid').' '.$this->l('Maximum suggestions count in list'));
            else {
                Configuration::updateValue('DADATA_SUGGESTIONS_TOKEN', $dadata_token);
                Configuration::updateValue('DADATA_SUGGESTIONS_COUNT', $dadata_count);
                Configuration::updateValue('DADATA_SUGGESTIONS_FIO', $dadata_fio);
                Configuration::updateValue('DADATA_SUGGESTIONS_ADDRESS', $dadata_address);
                Configuration::updateValue('DADATA_SUGGESTIONS_REGION_FIELD', $dadata_region_field);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }


    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('DaData.ru API Token'),
                    'name' => 'DADATA_SUGGESTIONS_TOKEN',
                    'size' => 50,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Maximum suggestions count in list'),
                    'name' => 'DADATA_SUGGESTIONS_COUNT',
                    'size' => 5,
                    'required' => true
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Disable name fields'),
                    'name' => 'DADATA_SUGGESTIONS_FIO',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )

                    )
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Disable address fields'),
                    'name' => 'DADATA_SUGGESTIONS_ADDRESS',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )

                    )
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Region field id'),
                    'name' => 'DADATA_SUGGESTIONS_REGION_FIELD',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => false,
                    'values' => array(
                        array(
                            'id' => $this->valid_fields[0],
                            'value' => $this->valid_fields[0],
                            'label' => $this->l($this->valid_fields[0])
                        ),
                        array(
                            'id' => $this->valid_fields[1],
                            'value' => $this->valid_fields[1],
                            'label' => $this->l($this->valid_fields[1])
                        ),

                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['DADATA_SUGGESTIONS_TOKEN'] = Configuration::get('DADATA_SUGGESTIONS_TOKEN');
        $helper->fields_value['DADATA_SUGGESTIONS_COUNT'] = Configuration::get('DADATA_SUGGESTIONS_COUNT');
        $helper->fields_value['DADATA_SUGGESTIONS_FIO'] = Configuration::get('DADATA_SUGGESTIONS_FIO');
        $helper->fields_value['DADATA_SUGGESTIONS_ADDRESS'] = Configuration::get('DADATA_SUGGESTIONS_ADDRESS');
        $helper->fields_value['DADATA_SUGGESTIONS_HIDE'] = Configuration::get('DADATA_SUGGESTIONS_HIDE');
        $helper->fields_value['DADATA_SUGGESTIONS_REGION_FIELD'] = Configuration::get('DADATA_SUGGESTIONS_REGION_FIELD');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS('https://cdn.jsdelivr.net/npm/suggestions-jquery@17.12.0/dist/css/suggestions.min.css','all');
        $this->context->controller->addJs('https://cdn.jsdelivr.net/npm/suggestions-jquery@17.12.0/dist/js/jquery.suggestions.min.js','all');

        if (Tools::version_compare(_PS_VERSION_, '1.6', '<')) {
            $this->context->controller->addJs($this->_path.'views/js/suggestions_prestashop.js', 'all');
        } else {
            $this->context->controller->addJs($this->_path.'views/js/suggestions_prestashop_1.6.js', 'all');
        }

        return $this->wrapScriptOnLoad();
    }

    public function hookCreateAccountTop()
    {
        return $this->wrapScriptOnLoad();
    }
}
