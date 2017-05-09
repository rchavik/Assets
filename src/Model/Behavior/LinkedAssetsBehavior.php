<?php

namespace Xintesa\Assets\Model\Behavior;

use ArrayObject;
use Cake\Collection\Collection;
use Cake\Event\Event;
use Cake\Log\LogTrait;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;

class LinkedAssetsBehavior extends Behavior {

	use LogTrait;

	protected $_defaultConfig = [
		'key' => 'LinkedAsset',
	];

	public function initialize(array $config = array()) {
		parent::initialize($config);
		$this->_table->addAssociations([
			'hasMany' => [
				'AssetUsages' => [
					'className' => 'Xintesa/Assets.AssetUsages',
					'foreignKey' => 'foreign_key',
					'dependent' => true,
					'conditions' => [
						'AssetUsages.model' => $this->_table->alias(),
					],
				],
			],
		]);
	}

	public function beforeFind(Event $event, Query $query, ArrayObject $options, $primary) {
		//if ($model->findQueryType == 'list') {
			//return $query;
		//}

		/*
		if (!isset($query['contain'])) {
			$contain = array();
			$relationCheck = array('belongsTo', 'hasMany', 'hasOne', 'hasAndBelongsToMany');
			foreach ($relationCheck as $relation) {
				if ($model->{$relation}) {
					$contain = Hash::merge($contain, array_keys($model->{$relation}));
				}
			}
			if ($model->recursive >= 0 || $query['recursive'] >= 0 ) {
				$query = Hash::merge(array('contain' => $contain), $query);
			}
		}
		if (isset($query['contain'])) {
			if (!isset($query['contain']['AssetsAssetUsage'])) {
				$query['contain']['AssetsAssetUsage'] = 'AssetsAsset';
			}
		}
		*/

		$query->contain('AssetUsages.Assets');

		$query->formatResults(function ($resultSet) {
			return $this->_formatResults($resultSet);
		});
		return $query;
	}

	protected function _formatResults($results) {
		$key = 'linked_assets';

		if (isset($model->Assets)) {
			$Assets = $model->Assets;
		} else {
			$Assets = TableRegistry::get('Xintesa/Assets.Assets');
		}

		foreach ($results as $result) {
			$result->$key = array();
			if (!$result->has('asset_usages')) {
				continue;
			}
			foreach ($result->asset_usages as &$assetUsage) {
				if (!$assetUsage->has('asset')) {
					continue;
				}
				if (empty($assetUsage->type)) {
					$result->$key['DefaultAsset'][] = $assetUsage->asset;
				} elseif ($assetUsage->type === 'FeaturedImage') {
					$result[$key][$assetUsage->type] = $assetUsage->asset;

					$seedId = isset($assetUsage->asset->parent_asset_id) ?
						$assetUsage->asset->parent_asset_id :
						$assetUsage->asset->id;
					$relatedAssets = $Assets->find()
						->where([
							'Assets.parent_asset_id' => $seedId,
						])
						->cache('linked_assets_' . $assetUsage->asset->id, 'nodes')
						->order(['width' => 'DESC']);
					foreach ($relatedAssets as $related) {
						$result[$key]['FeaturedImage']['Versions'][] = $related->asset;
					}

				} else {
					$result[$key][$assetUsage->type][] = $assetUsage->asset;
				}
			}
			unset($result->asset_usages);
		}
		return $results;
	}

/**
 * Import $path as $model's asset and automatically registers its usage record
 *
 * This method is intended for importing an existing file in the local
 * filesystem into Assets plugin with automatic usage record with the calling
 * model.
 *
 * Eg:
 *
 *   $Book = ClassRegistry::init('Book');
 *   $Book->Behaviors->load('Assets.LinkedAssets');
 *   $Book->importAsset('LocalAttachment', '/path/to/file');
 *
 * @param string $adapter Adapter name
 * @param string $path Path to file, relative from WWW_ROOT
 * @return bool
 */
	public function importAsset(Model $model, $adapter, $path, $options = array()) {
		$options = Hash::merge(array(
			'usage' => array(),
		), $options);
		$Attachment = ClassRegistry::init('Assets.Attachments');
		$attachment = $Attachment->createFromFile(WWW_ROOT . $path);

		if (!is_array($attachment)) {
			return false;
		}

		$originalPath = WWW_ROOT . $path;
		$fp = fopen($originalPath, 'r');
		$stat = fstat($fp);
		$finfo = new finfo(FILEINFO_MIME_TYPE);

		$attachment['AssetsAsset'] = array(
			'model' => $Attachment->alias,
			'adapter' => $adapter,
			'file' => array(
				'name' => basename($originalPath),
				'tmp_name' => $originalPath,
				'type' => $finfo->file($originalPath),
				'size' => $stat['size'],
				'error' => UPLOAD_ERR_OK,
			),
		);
		$attachment = $Attachment->saveAll($attachment);

		$Attachment->AssetsAsset->recursive = -1;
		$asset = $Attachment->AssetsAsset->find('first', array(
			'conditions' => array(
				'model' => $Attachment->alias,
				'foreign_key' => $Attachment->id,
			),
		));

		$Usage = $Attachment->AssetsAsset->AssetsAssetUsage;

		$usage = Hash::merge($options['usage'], array(
			'asset_id' => $asset['AssetsAsset']['id'],
			'model' => $model->alias,
			'foreign_key' => $model->id,
		));
		$usage = $Usage->create($usage);

		$usage = $Usage->save($usage);
		if ($usage) {
			return true;
		}

		return false;
	}

}
