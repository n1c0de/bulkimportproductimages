<?php
declare(strict_types = 1);

if (!defined('_PS_VERSION_')) {
	exit;
}

class BulkImportProductImages extends Module
{
	/** @var array Array filled with module warnings */
	private $_warnings = [];

	/** @var array Array filled with module informations */
	private $_informations = [];

	/** @var string The configuration key to store data */
	private $_configuration_key = '';

	/** @var array The configuration data saved */
	private $_configuration_data = [];

	/** @var string The configuration form name */
	private $_configuration_form_name = '';

	/**
	 * Sets module's configuration.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->name = 'bulkimportproductimages';
		$this->tab = 'quick_bulk_update';
		$this->version = '1.0.0';
		$this->author = 'n1c0de';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = [
			'min' => '1.7.7.0',
			'max' => _PS_VERSION_
		];
		$this->bootstrap = true;
		$this->_configuration_key = strtoupper($this->name);
		$this->_configuration_form_name = $this->name . '_configuration_form';
		parent::__construct();
		$this->displayName = $this->trans(
			'Bulk Import Product Images',
			[],
			'Modules.Bulkimportproductimages.Admin'
		);
		$this->description = $this->trans(
			'Bulk import your product images by simply calling the trigger URL.',
			[],
			'Modules.Bulkimportproductimages.Admin'
		);
		$this->confirmUninstall = $this->trans(
			'Are you sure to uninstall %moduleName%?',
			['%moduleName%' => $this->displayName],
			'Modules.Bulkimportproductimages.Admin'
		);
		if (!Configuration::get($this->_configuration_key)) {
			$this->_errors = $this->trans(
				'The configuration data of %module_name% failed to load.',
				[
					'%module_name%' => $this->name
				],
				'Modules.Bulkimportproductimages.Admin'
			);
		} else {
			$this->_configuration_data = unserialize(Configuration::get($this->_configuration_key));
		}
	}

	/**
	 * Installs module.
	 *
	 * @return bool
	 */
	public function install()
	{
		if (Shop::isFeatureActive()) {
			Shop::setContext(Shop::CONTEXT_ALL);
		}
		if (!parent::install()
			|| !Configuration::updateValue(
				$this->_configuration_key,
				serialize([
					'form' => [
						$this->_configuration_form_name . '_path_source' => ''
					]
				])
			)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Uninstalls module.
	 *
	 * @return bool
	 */
	public function uninstall()
	{
		if (!parent::uninstall()
			&& Configuration::deleteByName($this->_configuration_key)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Makes module compatible with new translation system.
	 *
	 * @return bool
	 */
	public function isUsingNewTranslationSystem()
	{
		return true;
	}

	/**
	 * Retrieves the configuration form values.
	 *
	 * @return array
	 */
	public function getFieldsValue() {
		return Tools::isSubmit($this->_configuration_form_name . '_submit')
			? [
				$this->_configuration_form_name . '_path_source' => Tools::getValue($this->_configuration_form_name . '_path_source')
			]
			: $this->_configuration_data['form'];
	}

	/**
	 * Displays the configuration page.
	 *
	 * @return string $output
	 */
	public function getContent()
	{
		$output = '';
		$this->_configuration_data['form'] = $this->getFieldsValue();
		if ($this->_configuration_data['form'][$this->_configuration_form_name . '_path_source']) {
			if (!file_exists($this->_configuration_data['form'][$this->_configuration_form_name . '_path_source'])) {
				$this->_errors[] = $this->trans(
					'The folder "%path%" doesn\'t exists.',
					[
						'%path%' => $this->_configuration_data['form'][$this->_configuration_form_name . '_path_source']
					],
					'Modules.Bulkimportproductimages.Admin'
				);
			} else {
				$this->_informations[] = $this->trans(
					'There is %count% image(s) to import.',
					[
						'%count%' => count(Tools::scandir($this->_configuration_data['form'][$this->_configuration_form_name . '_path_source'], 'jpg'))
					],
					'Modules.Bulkimportproductimages.Admin'
				);
				$this->_informations[] = $this->trans(
					'You can manually import images by going to this URL: <a href="%url%" target="_blank">%url%</a>',
					[
						'%url%' => $this->context->link->getModuleLink($this->name, 'import')
					],
					'Modules.Bulkimportproductimages.Admin'
				);
			}
		}
		if (Tools::isSubmit($this->_configuration_form_name . '_submit')) {
			if (!Configuration::updateValue(
				$this->_configuration_key,
				serialize($this->_configuration_data)
			)) {
				$this->trans(
					'Form data failed to save.',
					[],
					'Modules.Bulkimportproductimages.Admin'
				);
			}
		}
		if (!empty($this->_errors)) {
			$output .= $this->displayError($this->_errors);
		}
		if (!empty($this->_warnings)) {
			$output .= $this->displayWarning($this->_warnings);
		}
		if (!empty($this->_informations)) {
			$output .= $this->displayInformation($this->_informations);
		}
		foreach ($this->_confirmations as $confirmation) {
			$output .= $this->displayConfirmation($confirmation);
		}
		return $output . $this->buildForm();
	}

	/**
	 * Builds the configuration form.
	 *
	 * @return string $form
	 */
	public function buildForm()
	{
		$form = [
			'form' => [
				'legend' => [
					'title' => $this->trans(
						'Configure import',
						[],
						'Modules.Bulkimportproductimages.Admin'
					),
					'icon' => 'icon-edit'
				],
				'warning' => $this->trans(
					'You have to name images with the product reference and have to be a JPG format (reference.jpg). '
					. 'All images will be imported as product cover images.',
					[],
					'Modules.Bulkimportproductimages.Admin'
				),
				'input' => [
					[
						'type' => 'text',
						'label' => $this->trans(
							'Source path',
							[],
							'Modules.Bulkimportproductimages.Admin'
						),
						'name' => $this->_configuration_form_name . '_path_source',
						'required' => true,
						'desc' => $this->trans(
							'The folder where the images will be imported.',
							[],
							'Modules.Bulkimportproductimages.Admin'
						),
						'hint' => $this->trans(
							'Absolute path',
							[],
							'Modules.Bulkimportproductimages.Admin'
						)
					]
				],
				'submit' => [
					'title' => $this->trans(
						'Save',
						[],
						'Modules.Bulkimportproductimages.Admin'
					),
					'class' => 'btn btn-default pull-right'
				]
			]
		];
		$helper = new HelperForm();
		$helper->table = $this->table;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->submit_action = $this->_configuration_form_name . '_submit';
		$helper->default_form_language = $this->context->language->id;
		$helper->tpl_vars = [
			'fields_value' => $this->getFieldsValue()
		];
		return $helper->generateForm([$form]);
	}
}
