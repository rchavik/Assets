<style>
.popover-content { word-wrap: break-word; }
a i[class^=icon]:hover { text-decoration: none; }
</style>
<?php
$this->Html->script('Croogo.jquery/thickbox-compressed', array('inline' => false));
$this->Html->css('Croogo.thickbox', array('inline' => false));

$model = $foreignKey = $assetId = $filter = $filename = $type = $all = null;
if (!empty($this->request->query['model'])):
	$model = $this->request->query['model'];
endif;
if (!empty($this->request->query['foreign_key'])):
	$foreignKey = $this->request->query['foreign_key'];
endif;
if (!empty($this->request->query['asset_id'])):
	$assetId = $this->request->query['asset_id'];
endif;
if (!empty($this->request->query['type'])):
	$type = $this->request->query['type'];
endif;
if (!empty($this->request->query['filter'])):
	$filter = $this->request->query['filter'];
endif;
if (!empty($this->request->query['filename'])):
	$filename = $this->request->query['filename'];
endif;
if (!empty($this->request->query['all'])):
	$all = $this->request->query['all'];
endif;

$extractPath = "AssetsAsset.AssetsAssetUsage.{n}[model=$model][foreign_key=$foreignKey]";
?>
<div class="attachments index">

	<?php if ($this->layout != 'admin_popup'): ?>
	<h2><?php echo $title_for_layout; ?></h2>
	<?php endif; ?>

	<div class="row-fluid">
		<div class="span12 actions">
			<ul class="nav-buttons">
			<?php
				echo $this->Croogo->adminAction(
					__d('croogo', 'New Attachment'),
					array_merge(
						array('controller' => 'assets_attachments', 'action' => 'add', 'editor' => 1),
						array('?' => $this->request->query)
					)
				);

				$listUrl = array(
					'controller' => 'assets_attachments',
					'action' => 'browse',
					'?' => array(
						'model' => $model,
						'foreign_key' => $foreignKey,
					),
				);

				if (!$all):
					$listUrl['?']['all'] = true;
					$listTitle = __d('assets', 'List All Attachments');
				else:
					$listTitle = __d('assets', 'List Attachments');
				endif;
				echo $this->Croogo->adminAction($listTitle, $listUrl, array(
					'button' => 'success',
				));
			?>
			</ul>
		</div>
	</div>

<?php
	$filters = $this->Form->create('AssetsAttachment');
	$filters .= $this->Form->input('filter', array(
		'label' => false,
		'placeholder' => true,
		'div' => 'input text span4',
	));
	$filters .= $this->Form->input('filename', array(
		'label' => false,
		'placeholder' => true,
		'div' => 'input text span4',
	));
	$filters .= $this->Form->submit(__d('croogo', 'Filter'), array(
		'div' => 'input submit span2',
	));
	$filters .= $this->Form->end();
	$filterRow = sprintf('<div class="clearfix filter">%s</div>', $filters);

?>
	<div class="row-fluid">
		<div class="span12">
			<?php echo $filterRow; ?>
		</div>
	</div>

	<table class="table table-striped">
	<?php
		$tableHeaders = $this->Html->tableHeaders(array(
			$this->Paginator->sort('AssetsAsset.id', __d('croogo', 'Id')),
			'&nbsp;',
			$this->Paginator->sort('title', __d('croogo', 'Title')) . ' ' .
			$this->Paginator->sort('filename', __d('croogo', 'Filename')) . ' ' .
			$this->Paginator->sort('width', __d('assets', 'Width')) . ' ' .
			$this->Paginator->sort('height', __d('assets', 'Height')) . ' ' .
			$this->Paginator->sort('filesize', __d('croogo', 'Size')),
			__d('croogo', 'Actions'),
		));
		echo $tableHeaders;

		$query = array('?' => $this->request->query);
		$rows = array();
		foreach ($attachments as $attachment):
			$actions = array();
			$mimeType = explode('/', $attachment['AssetsAsset']['mime_type']);
			$mimeType = $mimeType['0'];

			if (isset($this->request->query['editor'])):
				$actions[] = $this->Html->link('', '#', array(
					'onclick' => "Croogo.Wysiwyg.choose('" . $attachment['AssetsAttachment']['slug'] . "');",
					'icon' => 'paper-clip',
					'tooltip' => __d('croogo', 'Insert')
				));
			endif;

			$editUrl = Hash::merge($query, array(
				'controller' => 'assets_attachments',
				'action' => 'edit',
				$attachment['AssetsAttachment']['id'],
				'editor' => 1,
			));
			$actions[] = $this->Croogo->adminRowAction('', $editUrl,
				array('icon' => 'pencil', 'tooltip' => __d('croogo', 'Edit'))
			);

			$deleteUrl = Hash::merge($query, array(
				'controller' => 'assets_attachments',
				'action' => 'delete',
				$attachment['AssetsAttachment']['id'],
				'editor' => 1,
			));
			$actions[] = $this->Croogo->adminRowAction('', $deleteUrl, array(
				'icon' => 'trash',
				'tooltip' => __d('croogo', 'Delete')
				),
				__d('croogo', 'Are you sure?')
			);

			if (isset($this->request->query['asset_id']) ||
				isset($this->request->query['all'])
			):
				unset($query['?']['asset_id']);

				$usage = Hash::extract($attachment, $extractPath);
				if (!empty($usage)):
					$addUrl = Hash::merge(array(
						'controller' => 'assets_asset_usages',
						'action' => 'add',
						'?' => array(
							'asset_id' => $attachment['AssetsAsset']['id'],
							'model' => $model,
							'foreign_key' => $foreignKey,
						)
					), $query);
					$actions[] = $this->Croogo->adminRowAction('', $addUrl, array(
						'icon' => 'plus',
						'method' => 'post',
					));
				endif;
			elseif ($mimeType === 'image'):
				$detailUrl = Hash::merge(array(
					'action' => 'browse',
					'?' => array(
						'asset_id' => $attachment['AssetsAsset']['id'],
					)
				), $query);
				$actions[] = $this->Html->link('', $detailUrl, array(
					'icon' => 'suitcase',
				));
			endif;

			if ($mimeType == 'image') {
				$img = $this->AssetsImage->resize(
					$attachment['AssetsAsset']['path'], 100, 200,
					array('adapter' => $attachment['AssetsAsset']['adapter']),
					array('class' => 'img-polaroid')
				);
				$thumbnail = $this->Html->link($img,
					$attachment['AssetsAsset']['path'],
					array(
						'class' => 'thickbox',
						'escape' => false,
						'title' => $attachment['AssetsAttachment']['title'],
					)
				);
				if (!empty($attachment['AssetsAssetUsage']['type']) &&
					$attachment['AssetsAssetUsage']['foreign_key'] === $foreignKey &&
					$attachment['AssetsAssetUsage']['model'] === $model
				):
					$thumbnail .= $this->Html->div(null,
						$this->Html->link(
							$this->Html->tag('span',
								$attachment['AssetsAssetUsage']['type'],
								array('class' => 'badge badge-info')
							),
							array(
								'action' => 'browse',
								'?' => array(
									'type' => $attachment['AssetsAssetUsage']['type']
								) + $this->request->query,
							),
							array(
								'escape' => false,
							)
						)
					);
				endif;
			} else {
				$thumbnail = $this->Html->image('/croogo/img/icons/page_white.png') . ' ' . $attachment['AssetsAsset']['mime_type'] . ' (' . $this->Filemanager->filename2ext($attachment['AssetsAttachment']['slug']) . ')';
				$thumbnail = $this->Html->link($thumbnail, '#', array(
					'escape' => false,
				));
			}

			$actions = $this->Html->div('item-actions', implode(' ', $actions));

			$url = $this->Html->link(
				Router::url($attachment['AssetsAsset']['path']),
				$attachment['AssetsAsset']['path'],
				array(
					'onclick' => "Croogo.Wysiwyg.choose('" . $attachment['AssetsAttachment']['slug'] . "');",
					'target' => '_blank',
				)
			);
			$urlPopover = $this->Croogo->adminRowAction('', '#', array(
				'class' => 'popovers',
				'icon' => 'link',
				'iconSize' => 'small',
				'data-title' => __d('croogo', 'URL'),
				'data-html' => true,
				'data-placement' => 'top',
				'data-content' => $url,
			));

			$title = $this->Html->para(null, $attachment['AssetsAttachment']['title']);
			$title .= $this->Html->para(null,
				$this->Text->truncate(
					$attachment['AssetsAsset']['filename'], 30
				) . '&nbsp;' . $urlPopover,
				array('title' => $attachment['AssetsAsset']['filename'])
			);

			$title .= $this->Html->para(null, 'Dimension: ' .
				$attachment['AssetsAsset']['width'] . ' x ' .
				$attachment['AssetsAsset']['height']
			);

			$title .= $this->Html->para(null,
				'Size: ' . $this->Number->toReadableSize($attachment['AssetsAsset']['filesize'])
			);

			$rows[] = array(
				$attachment['AssetsAsset']['id'],
				$thumbnail,
				$title,
				$actions,
			);
		endforeach;

		echo $this->Html->tableCells($rows);
		echo $tableHeaders;
	?>
	</table>
</div>

<div class="row-fluid">
	<div class="span12">
		<div class="pagination">
		<ul>
			<?php echo $this->Paginator->first('< ' . __d('croogo', 'first')); ?>
			<?php echo $this->Paginator->prev('< ' . __d('croogo', 'prev')); ?>
			<?php echo $this->Paginator->numbers(); ?>
			<?php echo $this->Paginator->next(__d('croogo', 'next') . ' >'); ?>
			<?php echo $this->Paginator->last(__d('croogo', 'last') . ' >'); ?>
		</ul>
		</div>
		<div class="counter"><?php echo $this->Paginator->counter(array('format' => __d('croogo', 'Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%'))); ?></div>
	</div>
</div>
<?php

$this->Js->buffer("$('.popovers').popover().on('click', function() { return false; });");
