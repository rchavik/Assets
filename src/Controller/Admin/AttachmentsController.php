<?php

namespace Xintesa\Assets\Controller\Admin;

use Cake\Event\Event;
use Xintesa\Assets\Controller\Admin\AppController;

/**
 * Attachments Controller
 *
 * This file will take care of file uploads (with rich text editor integration).
 *
 * @category Assets.Controller
 * @package  Assets.Controller
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @author   Rachman Chavik <contact@xintesa.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class AttachmentsController extends AppController {

/**
 * Helpers used by the Controller
 *
 * @var array
 * @access public
 */
	public $helpers = [
		'Croogo/FileManager.FileManager',
		'Text',
		'Xintesa/Assets.AssetsImage'
	];

	public $paginate = array(
		'paramType' => 'querystring',
		'limit' => 5,
	);

	public $components = array(
		'Search.Prg' => array(
			'presetForm' => array(
				'paramType' => 'querystring',
			),
			'commonProcess' => array(
				'paramType' => 'querystring',
				'filterEmpty' => 'true',
			),
		),
	);

	public $presetVars = true;

	public function initialize() {
		parent::initialize();
		$this->loadModel('Xintesa/Assets.Attachments');
	}

/**
 * Before executing controller actions
 *
 * @return void
 * @access public
 */
	public function beforeFilter(Event $event) {
		parent::beforeFilter($event);

		$noCsrfCheck = array('add', 'resize');
		if (in_array($this->action, $noCsrfCheck)) {
			$this->Security->csrfCheck = false;
		}
		if ($this->action == 'resize') {
			$this->Security->validatePost = false;
		}
	}

/**
 * Admin index
 *
 * @return void
 * @access public
 */
	public function index() {
		$this->set('title_for_layout', __d('croogo', 'Attachments'));

		$query = $this->Attachments->find('search', [
			'search' => $this->request->query
		]);

		$this->set('attachments', $this->paginate($query));

/*
		$this->Prg->commonProcess();
		$isChooser = false;


		if (isset($this->request->params['named']['links']) || isset($this->request->query['chooser'])) {
			$isChooser = true;
		}

		$criteria = $this->Attachments->parseCriteria($this->Prg->parsedParams());

		if (empty($this->request->query)) {
			$this->Attachments->recursive = 0;
			$this->paginate['Attachments']['order'] = 'Attachments.created DESC';
		} else {
			if (isset($this->request->query['asset_id']) ||
				isset($this->request->query['all'])
			) {
				$this->paginate = array_merge(array('versions'), $this->paginate);
				if (!$this->request->query('sort') && empty($this->request->params['named']['sort'])) {
					$this->paginate['Attachments']['order'] = array(
						'id' => 'desc',
					);
				}

				if (isset($this->request->query['asset_id'])) {
					$this->paginate['asset_id'] = $this->request->query['asset_id'];
				}
				if (isset($this->request->query['all'])) {
					$this->paginate['all'] = true;
				}
			} else {
				$this->paginate = array_merge(array('modelAttachments'), $this->paginate);
			}
			if (isset($this->request->query['model'])) {
				$this->paginate['model'] = $this->request->query['model'];
			}
			if (isset($this->request->query['foreign_key'])) {
				$this->paginate['foreign_key'] = $this->request->query['foreign_key'];
			};

		}
		if ($isChooser) {
			if ($this->request->query['chooser_type'] == 'image') {
				$this->paginate['Attachments']['conditions']['AssetsAsset.mime_type LIKE'] = 'image/%';
			} else {
				$this->paginate['Attachments']['conditions']['AssetsAsset.mime_type NOT LIKE'] = 'image/%';
			}
		}
		$this->set('attachments', $criteria));
*/

		if (isset($this->request->params['named']['links']) || isset($this->request->query['chooser'])) {
			$this->layout = 'admin_popup';
			$this->render('admin_chooser');
		}
	}

/**
 * Admin add
 *
 * @return void
 * @access public
 */
	public function add() {
		$this->set('title_for_layout', __d('croogo', 'Add Attachment'));

		if (isset($this->request->params['named']['editor'])) {
			$this->layout = 'admin_popup';
		}

		if ($this->request->is('post') || !empty($this->request->data)) {

			if (empty($this->data['Attachments'])) {
				$this->Attachments->invalidate('file', __d('croogo', 'Upload failed. Please ensure size does not exceed the server limit.'));
				return;
			}

			$this->Attachments->create();
			$saved = $this->Attachments->saveAll($this->request->data);

			if ($saved) {
				$attachmentId = $this->Attachments->id;
				$attachment = $this->Attachments->findById($attachmentId);
				$eventKey = 'Controller.AssetsAttachment.newAttachment';
				Croogo::dispatchEvent($eventKey, $this, compact('attachment'));
			}

			if ($this->request->is('ajax')) {
				$files = array();
				$error = false;
				if ($saved) {
					$this->viewClass = 'Json';
					$files = array(array(
						'url' => $attachment['AssetsAsset']['path'],
						'thumbnail_url' => $attachment['AssetsAsset']['path'],
						'name' => $attachment['Attachments']['title'],
						'type' => $attachment['AssetsAsset']['mime_type'],
						'size' => $attachment['AssetsAsset']['filesize'],
					));
				} else {
					if (!empty($this->Attachments->validationErrors)) {
						$errors = Hash::extract(
							$this->Attachments->validationErrors,
							'{s}.{s}.{n}'
						);
						$files = array(array('error' => $errors));
						$error = implode("\n", $errors);
					}
				}
				$this->set(compact('files', 'error'));
				$this->set('_serialize', array('files', 'error'));
				return true;
			}

			if ($saved) {
				$this->Session->setFlash(__d('croogo', 'The Attachment has been saved'), 'flash', array('class' => 'success'));
				$url = array();
				if (isset($this->request->data['AssetsAsset']['AssetsAssetUsage'][0])) {
					$usage = $this->request->data['AssetsAsset']['AssetsAssetUsage'][0];
					if (!empty($usage['model']) && !empty($usage['foreign_key'])) {
						$url['?']['model'] = $usage['model'];
						$url['?']['foreign_key'] = $usage['foreign_key'];
					}
				}
				if (isset($this->request->params['named']['editor'])) {
					$url = array_merge($url, array('action' => 'browse'));
				} else {
					$url = array_merge($url, array('action' => 'index'));
				}
				return $this->redirect($url);
			} else {
				$this->Session->setFlash(__d('croogo', 'The Attachment could not be saved. Please, try again.'), 'flash', array('class' => 'error'));
			}
		}
	}

/**
 * Admin edit
 *
 * @param int $id
 * @return void
 * @access public
 */
	public function edit($id = null) {
		$this->set('title_for_layout', __d('croogo', 'Edit Attachment'));

		if (isset($this->request->params['named']['editor'])) {
			$this->layout = 'admin_popup';
		}

		$redirect = array('action' => 'index');
		if (!empty($this->request->query)) {
			$redirect = array_merge(
				$redirect,
				array('action' => 'browse', '?' => $this->request->query)
			);
		}

		if (!$id && empty($this->request->data)) {
			$this->Session->setFlash(__d('croogo', 'Invalid Attachment'), 'flash', array('class' => 'error'));
			return $this->redirect($redirect);
		}
		if (!empty($this->request->data)) {
			if ($this->Attachments->save($this->request->data)) {
				$this->Session->setFlash(__d('croogo', 'The Attachment has been saved'), 'flash', array('class' => 'success'));
				return $this->redirect($redirect);
			} else {
				$this->Session->setFlash(__d('croogo', 'The Attachment could not be saved. Please, try again.'), 'flash', array('class' => 'error'));
			}
		}
		if (empty($this->request->data)) {
			$this->request->data = $this->Attachments->read(null, $id);
		}
	}

/**
 * Admin delete
 *
 * @param int $id
 * @return void
 * @access public
 */
	public function delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('croogo', 'Invalid id for Attachment'), 'flash', array('class' => 'error'));
			return $this->redirect(array('action' => 'index'));
		}

		$redirect = array('action' => 'index');
		if (!empty($this->request->query)) {
			$redirect = array_merge(
				$redirect,
				array('action' => 'browse', '?' => $this->request->query)
			);
		}

		$this->Attachments->begin();
		if ($this->Attachments->delete($id)) {
			$this->Attachments->commit();
			$this->Session->setFlash(__d('croogo', 'Attachment deleted'), 'flash', array('class' => 'success'));
			return $this->redirect($redirect);
		} else {
			$this->Session->setFlash(__d('croogo', 'Invalid id for Attachment'), 'flash', array('class' => 'error'));
			return $this->redirect($redirect);
		}
	}

/**
 * Admin browse
 *
 * @return void
 * @access public
 */
	public function browse() {
		$this->layout = 'admin_popup';
		$this->index();
	}

	public function list() {
		$this->paginate = array(
			'modelAttachments',
			'model' => $this->request->query['model'],
			'foreign_key' => $this->request->query['foreign_key'],
		);
		if ($this->request->is('ajax')) {
			$this->layout = 'ajax';
			$this->paginate['limit'] = 100;
		}
		$attachments = $this->paginate();
		$this->set(compact('attachments'));
	}

	public function resize($id = null) {
		if (empty($id)) {
			throw new NotFoundException('Missing Asset Id to resize');
		}

		$result = false;
		if (!empty($this->request->data)) {
			$width = $this->request->data['width'];
			try {
				$result = $this->Attachments->createResized($id, $width, null);
			} catch (Exception $e) {
				$result = $e->getMessage();
			}
		}

		$this->set(compact('result'));
		$this->set('_serialize', 'result');
	}

}
