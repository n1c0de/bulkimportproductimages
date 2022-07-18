<?php
declare(strict_types=1);

class BulkImportProductImagesImportModuleFrontController extends ModuleFrontController
{
	/**
	 * Imports image to product.
	 *
	 * @param string $filename
	 * @param string $reference
	 * @param string $source_path
	 *
	 * @return array $result
	 */
	private function importImage($filename, $reference, $source_path)
	{
		$result = [
			'status' => null,
			'path' => null,
			'message' => null,
		];
		$req = new DbQuery();
		$req->select('id_product');
		$req->from('product', 'p');
		$req->where('p.reference = \'' . bqSQL($reference) . '\'');
		$res = Db::getInstance()->executeS($req);
		if (empty($res)) {
			$result['status'] = false;
			$result['path'] = $filename;
			$result['message'] = $this->trans(
				'%filename%: Product was not found at reference "%reference%".',
				[
					'%filename%' => $filename,
					'%reference%' => $reference
				],
				'Modules.Bulkimportproductimages.Notifications'
			);
			return $result;
		}
		if (!ImageManager::checkImageMemoryLimit($filename)) {
			$result['status'] = false;
			$result['path'] = $filename;
			$result['message'] = $this->trans(
				'%filename%: Image exceed %memorylimit% Mo.',
				[
					'%filename%' => $filename,
					'%memorylimit%' => Tools::getMemoryLimit()
				],
				'Modules.Bulkimportproductimages.Notifications'
			);
			return $result;
		}
		$id_product = (int) $res[0]['id_product'];
		$image = new Image();
		$image->id_product = $id_product;
		$image->cover = true;
		$image->position = -1;
		foreach (Language::getLanguages() as $language) {
			foreach ($image->getImages($language, $id_product) as $product_image) {
				if ($product_image['cover'] === '1') {
					$image->legend[$language['id_lang']] = $product_image['legend'];
				}
			}
		}
		Image::deleteCover($id_product);
		$image->add();
		$destination_path = $image->getPathForCreation();
		$tmp_file = _PS_TMP_IMG_DIR_ . '/' . $filename;
		$is_copy = Tools::copy($source_path . '/' . $filename, $tmp_file);
		if (!$is_copy) {
			unlink($tmp_file);
			$image->delete();
			$result['status'] = false;
			$result['path'] = $filename;
			$result['message'] = $this->trans(
				'%filename%: Image failed to copy to %destination%.',
				[
					'%filename%' => $filename,
					'%destination%' => $destination_path
				],
				'Modules.Bulkimportproductimages.Notifications'
			);
			return $result;
		}
		ImageManager::resize($tmp_file, $destination_path . '.jpg');
		$images_types = ImageType::getImagesTypes('products');
		foreach ($images_types as $image_type) {
			ImageManager::resize($tmp_file, $destination_path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
		}
		unlink($source_path . '/' . $filename);
		unlink($tmp_file);
		$result['status'] = true;
		$result['path'] = $destination_path . '.jpg';
		$result['message'] = $this->trans(
			'%filename% successfully imported.',
			[
				'%filename%' => $filename
			],
			'Modules.Bulkimportproductimages.Notifications'
		);
		return $result;
	}

	/**
	 * Processes the image import.
	 *
	 * @return array $response
	 */
	private function importProcess()
	{
		$data = unserialize(Configuration::get('BULKIMPORTPRODUCTIMAGES'));
		$path = $data['form']['bulkimportproductimages_configuration_form_path_source'];
		$images = Tools::scandir($path, 'jpg');
		$response = [
			'status' => null,
			'message' => null,
			'detail' => [
				'succeed' => [],
				'failed' => []
			]
		];
		if (empty($images)) {
			$response['status'] = false;
			$response['message'] = $this->trans(
				'There is no image to import.',
				[],
				'Modules.Bulkimportproductimages.Notifications'
			);
			return $response;
		}
		$filenames = array_map(
			function($a) {
				return pathinfo($a)['filename'];
			},
			$images
		);
		$combined_images = array_combine($images, $filenames);
		foreach ($combined_images as $filename => $reference) {
			$file_import_result = $this->importImage($filename, $reference, $path);
			if ($file_import_result['status']) {
				$response['detail']['succeed'][] = $file_import_result['path'];
			} else {
				$response['detail']['failed'][] = $file_import_result['message'];
			}
		}
		if (count($images) > 0 && count($images) === count($response['detail']['succeed'])) {
			$response['status'] = true;
			$response['message'] = $this->trans(
				'Import successfully completed.',
				[],
				'Modules.Bulkimportproductimages.Notifications'
			);
		} else {
			$response['status'] = false;
			if (count($response['detail']['succeed']) > 0) {
				$response['message'] = $this->trans(
					'Import completed at %percent%%: %count% image(s) failed to import.',
					[
						'%percent%' => (count($response['detail']['succeed']) / count($images)) * 100,
						'%count%' => count($images) - count($response['detail']['succeed']),
					],
					'Modules.Bulkimportproductimages.Notifications'
				);
			} else {
				$response['message'] = $this->trans(
					'Import failed.',
					[],
					'Modules.Bulkimportproductimages.Notifications'
				);
			}
		}
		return $response;
	}

	/**
	 * Displays the import result of product images as JSON.
	 *
	 * @return void
	 */
	public function display()
	{
		$this->ajax = true; // prevent the page template to be displayed
		try {
			$response = $this->importProcess();
		} catch (exception $e) {
			$response = [
				'status' => false,
				'message' => $this->trans(
					'Unable to process import: %error%.',
					['%error%' => $e],
					'Modules.Bulkimportproductimages.Notifications'
				)
			];
		}
		header('Content-Type: application/json; charset=utf-8');
		echo $this->ajaxRender(json_encode($response) . PHP_EOL);
	}
}
