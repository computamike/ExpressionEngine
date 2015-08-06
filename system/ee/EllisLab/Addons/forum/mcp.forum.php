<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use EllisLab\ExpressionEngine\Service\Validation\Result as ValidationResult;
use EllisLab\ExpressionEngine\Library\CP\Table;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// --------------------------------------------------------------------

/**
 * ExpressionEngine Discussion Forum Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */

class Forum_mcp extends CP_Controller {

	public $base				= 'addons/settings/forum/';
	public $prefs				= array();
	public $permmissions		= array();
	public $boards				= array();
	public $fmt_options			= array();

	public $show_nav			= TRUE;
	public $is_table_open		= FALSE;
	public $final_row			= FALSE;

	public $current_category	= 0;
	public $table_row_ct		= 0;
	public $_add_crumb			= array();

	// These let us translate the base member groups
	public $english = array('Guests', 'Banned', 'Members', 'Pending', 'Super Admins');

	public $UPD				= NULL;


	/**
	 * Constructor
	 */
	public function __construct()
	{
		ee()->lang->loadfile('forum_cp');
		ee()->load->helper('form');

		// Set the base path for convenience

		$this->board_id = (ee()->input->get_post('board_id') == FALSE OR ! is_numeric(ee()->input->get_post('board_id'))) ? 1 : round(ee()->input->get_post('board_id'));

		$this->id_base	 = $this->base.AMP.'board_id='.$this->board_id;
		$this->form_base = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'board_id='.$this->board_id;

		// Fetch the forum preferences

		$query = ee()->db->get_where('forum_boards', array('board_id' => $this->board_id));

		if ($query->num_rows() == 0)
		{
			$this->prefs = $this->getDefaultPrefs();
		}
		else
		{
			foreach ($query->row_array() as $key => $val)
			{
				$this->prefs[$key] = $val;
			}
		}

		$this->prefs['board_theme_url']  = URL_THEMES.'forum/';

		ee()->load->model('addons_model');
		$this->fmt_options = ee()->addons_model->get_plugin_formatting(TRUE);

		// Garbage collection.  Delete old read topic data

		$year_ago = ee()->localize->now - (60*60*24*365);
		ee()->db->where('last_visit <', $year_ago);
		ee()->db->delete('forum_read_topics');
	}

	private function generateSidebar($active = NULL)
	{
		$sidebar = ee('Sidebar')->make();

		$boards = $sidebar->addHeader(lang('forum_boards'))
			->withButton(lang('new'), ee('CP/URL', $this->base . 'create/board'));

		$all_boards = ee('Model')->get('forum:Board')
			->fields('board_id', 'board_label')
			->all();

		if (count($all_boards))
		{
			$board_list = $boards->addFolderList('boards')
				->withRemoveUrl(ee('CP/URL', $this->base . 'remove/board'));

			foreach ($all_boards as $board)
			{
				$item = $board_list->addItem($board->board_label, ee('CP/URL', $this->base . 'index/' . $board->board_id))
					->withEditUrl(ee('CP/URL', $this->base . 'edit/board/' . $board->board_id))
					->withRemoveConfirmation(lang('forum_board') . ': <b>' . $board->board_label . '</b>')
					->identifiedBy($board->board_id);

				if ($board->board_id == $active)
				{
					$item->isActive();
				}
			}
		}

		$sidebar->addHeader(lang('templates'))
			->withUrl(ee('CP/URL', 'design/forum'));

		$sidebar->addHeader(lang('member_ranks'))
			->withUrl(ee('CP/URL', $this->base . 'ranks'));

		return $sidebar;
	}

	private function getStatusWidget($status)
	{
		$html = '';

		switch ($status)
		{
			case 'o': $html = '<b class="yes">' . lang('live') . '</b>'; break;
			case 'c': $html = '<b class="no">' . lang('hidden') . '</b>'; break;
			case 'a': $html = '<i>' . lang('read_only') . '</i>'; break;
		}

		return strtolower($html);
	}

	/**
	 * Forum Home Page
	 */
	public function index($id = NULL)
	{
		$board = ee('Model')->get('forum:Board', $id)
			->order('board_id', 'asc')
			->first();

		$id = $board->board_id; // in case $id was NULL

		if ( ! $board)
		{
			// We have no boards! Display something useful here.
		}

		$categories = array();
		$forum_id = ee()->session->flashdata('forum_id');

		$boards_categories = ee('Model')->get('forum:Forum')
			->filter('board_id', $id)
			->filter('forum_is_cat', 'y')
			->all();

		foreach ($boards_categories as $i => $category)
		{
			$manage = array(
				'toolbar_items' => array(
					'edit' => array(
						'href' => ee('CP/URL', $this->base . 'edit/category/' . $category->forum_id),
						'title' => lang('edit'),
					),
					'settings' => array(
						'href' => ee('CP/URL', $this->base . 'settings/category/' . $category->forum_id),
						'title' => lang('settings'),
					)
				)
			);
			$manage = ee('View')->make('ee:_shared/toolbar')->render($manage);

			$class = ($i == count($boards_categories) - 1) ? '' : 'mb';

			$table_config = array(
				'limit'             => 0,
				'reorder'           => TRUE,
				'no_reorder_header' => TRUE,
				'sortable'          => FALSE,
				'class'             => $class,
				'wrap'              => FALSE,
			);

			$table = ee('CP/Table', $table_config);
			$table->setColumns(
				array(
					$category->forum_name => array(
						'encode' => FALSE
					),
					$this->getStatusWidget($category->forum_status) => array(
						'encode' => FALSE
					),
					$manage => array(
						'type'	=> Table::COL_TOOLBAR,
					),
					array(
						'type'	=> Table::COL_CHECKBOX
					)
				)
			);
			$table->setNoResultsText('no_forums', 'create_new_forum', ee('CP/URL', $this->base . 'create/forum/' . $category->forum_id));
			$table->addActionButton(ee('CP/URL', $this->base . 'create/forum/' . $category->forum_id), lang('new_forum'));

			$data = array();
			foreach ($category->Forums->sortBy('forum_order') as $forum)
			{
				$row = array(
					$forum->forum_name.form_hidden('order[]', $forum->forum_order),
					$this->getStatusWidget($forum->forum_status),
					array('toolbar_items' => array(
							'edit' => array(
								'href' => ee('CP/URL', $this->base . 'edit/forum/' . $forum->forum_id),
								'title' => lang('edit'),
							),
							'settings' => array(
								'href' => ee('CP/URL', $this->base . 'settings/forum/' . $forum->forum_id),
								'title' => lang('settings'),
							)
						)
					),
					array(
						'name' => 'selection[]',
						'value' => $forum->forum_id,
						'data'	=> array(
							'confirm' => lang('fourm') . ': <b>' . htmlentities($forum->forum_name, ENT_QUOTES) . '</b>'
						)
					)
				);

				$attrs = array();

				if ($forum_id && $forum->forum_id == $forum_id)
				{
					$attrs = array('class' => 'selected');
				}

				$data[] = array(
					'attrs'		=> $attrs,
					'columns'	=> $row
				);
			}
			$table->setData($data);
			$categories[] = $table->viewData(ee('CP/URL', $this->base . 'index/' . $id));
		}

		$vars = array(
			'board' => $board,
			'categories' => $categories,
		);

		$body = ee('View')->make('forum:index')->render($vars);

		return array(
			'body'    => $body,
			'heading' => lang('forum_manager'),
			'sidebar' => $this->generateSidebar($id)
		);

		if ($this->prefs['board_install_date'] < 1)
		{
			ee()->session->set_flashdata('message', ee()->lang->line('forum_new_install_msg'));
			ee()->functions->redirect(ee('CP/URL', $this->base . '/list_boards'));
		}

		// Compile the stats
		ee()->db->where('board_id', $this->board_id);
		$total_forums = ee()->db->count_all_results('forums');

		ee()->db->where('board_id', $this->board_id);
		$total_mods = ee()->db->count_all_results('forum_moderators');

		$one_day = 60*60*24;
		$total_days = (time() - $this->prefs['board_install_date']);
		$total_days = ($total_days <= $one_day) ? 1 : abs($total_days / $one_day);

		ee()->db->select('forum_id, forum_name, forum_total_topics, forum_total_posts');
		ee()->db->where('board_id', $this->board_id);
		ee()->db->where('forum_is_cat', 'n');
		ee()->db->order_by('forum_order');
		$query = ee()->db->get('forums');

		ee()->load->library('table');
		$vars['forums'] = array();

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
	 		{
				$row['topics_perday']	= ($row['forum_total_topics'] == 0) ? 0 : round($row['forum_total_topics'] / $total_days, 2);
				$row['posts_perday']	= ($row['forum_total_posts'] == 0)  ? 0 : round($row['forum_total_posts'] / $total_days, 2);

				$vars['forums'][] = $row;
			}
		}

		$vars['board_forum_url'] = $this->prefs['board_forum_url'];

		return $this->_content_wrapper('index', 'forum_board_home', $vars);
	}

	/**
	 * Dispatch method for the various things that can be created
	 */
	public function create($type)
	{
		$parameters = array_slice(func_get_args(), 1);
		$method = 'create' . ucfirst($type);

		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), $parameters);
		}

		show_404();
	}

	/**
	 * Dispatch method for the various things that can be edit
	 */
	public function edit($type)
	{
		$parameters = array_slice(func_get_args(), 1);
		$method = 'edit' . ucfirst($type);

		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), $parameters);
		}

		show_404();
	}

	/**
	 * Dispatch method for the various things that can be edit
	 */
	public function settings($type)
	{
		$parameters = array_slice(func_get_args(), 1);
		$method = 'settingsFor' . ucfirst($type);

		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), $parameters);
		}

		show_404();
	}

	// --------------------------------------------------------------------

	private function createBoard()
	{
		$errors = NULL;
		$board = ee('Model')->make('forum:Board', $this->getDefaultPrefs());

		$result = $this->validateBoard($board);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveBordAndRedirect($board);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => lang('create_forum_board'),
			'base_url' => ee('CP/URL', $this->base . 'create/board'),
			'save_btn_text' => 'btn_save_board',
			'save_btn_text_working' => 'btn_saving',
			'tabs' => array(
				'board' => $this->getBoardForm($board),
				'forums' => $this->getBoardForumsForm($board),
				'permissions' => $this->getBoardPermissionsForm($board)
			),
			'sections' => array(),
			'required' => TRUE
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => lang('forum_listing')
			),
			'heading'    => lang('create_forum_board'),
			'sidebar'    => $this->generateSidebar()
		);
	}

	private function editBoard($id)
	{
		$errors = NULL;

		$board = ee('Model')->get('forum:Board', $id)->first();
		if ( ! $board)
		{
			show_404();
		}

		$result = $this->validateBoard($board);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveBordAndRedirect($board);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => sprintf(lang('edit_forum_board'), $board->board_label),
			'base_url' => ee('CP/URL', $this->base . 'edit/board/' . $id),
			'save_btn_text' => 'btn_save_board',
			'save_btn_text_working' => 'btn_saving',
			'tabs' => array(
				'board' => $this->getBoardForm($board),
				'forums' => $this->getBoardForumsForm($board),
				'permissions' => $this->getBoardPermissionsForm($board)
			),
			'sections' => array(),
			'required' => TRUE
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => $vars['cp_page_title'],
			'sidebar'    => $this->generateSidebar($id)
		);
	}

	private function validateBoard($board)
	{
		if (empty($_POST))
		{
			return FALSE;
		}

		$action = ($board->isNew()) ? 'create' : 'edit';

		$board->set($_POST);
		$result = $board->validate();

		if ($response = $this->ajaxValidation($result))
		{
			ee()->output->send_ajax_response($response);
		}

		if ($result->failed())
		{
			ee('Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang($action . '_forum_board_error'))
				->addToBody(lang($action . '_forum_board_error_desc'))
				->now();
		}

		return $result;
	}

	private function saveBordAndRedirect($board)
	{
		$action = ($board->isNew()) ? 'create' : 'edit';

		foreach ($_POST['permissions'] as $key => $value)
		{
			$board->setPermission($key, $value);
		}

		$board->save();

		ee('Alert')->makeInline('shared-form')
			->asSuccess()
			->withTitle(lang($action . '_forum_board_success'))
			->addToBody(sprintf(lang($action . '_forum_board_success_desc'), $board->board_label))
			->defer();

		ee()->functions->redirect(ee('CP/URL', $this->base . '/index/' . $board->board_id));
	}

	private function getBoardForm($board)
	{
		$html = '';

		$site = '';

		if (ee()->config->item('multiple_sites_enabled') == 'y')
		{
			$site = array(
				'title' => 'site',
				'desc' => 'site_desc',
				'fields' => array(
					'board_site_id' => array(
						'type' => 'select',
						'choices' => ee('Model')->get('Site')->all()->getDictionary('site_id', 'site_label'),
						'value' => $board->board_site_id,
					)
				)
			);
		}

		$sections = array(
			array(
				array(
					'title' => 'enable_board',
					'desc' => 'enable_board_desc',
					'fields' => array(
						'board_enabled' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $board->board_enabled,
						)
					)
				),
				array(
					'title' => 'name',
					'desc' => 'name_desc',
					'fields' => array(
						'board_label' => array(
							'type' => 'text',
							'value' => $board->board_label,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'short_name',
					'desc' => 'short_name_desc',
					'fields' => array(
						'board_name' => array(
							'type' => 'text',
							'value' => $board->board_name,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'forum_directory',
					'desc' => 'forum_directory_desc',
					'fields' => array(
						'board_forum_url' => array(
							'type' => 'text',
							'value' => $board->board_forum_url,
							'required' => TRUE
						)
					)
				),
				$site,
				array(
					'title' => 'forum_url_segment',
					'desc' => 'forum_url_segment_desc',
					'fields' => array(
						'board_forum_trigger' => array(
							'type' => 'text',
							'value' => $board->board_forum_trigger,
						)
					)
				),
				array(
					'title' => 'default_theme',
					'desc' => 'default_theme_desc',
					'fields' => array(
						'board_default_theme' => array(
							'type' => 'select',
							'choices' => $this->getForumThemes(),
							'value' => $board->board_default_theme,
						)
					)
				),
			),
			'php_parsing' => array(
				ee('Alert')->makeInline('permissions-warn')
					->asWarning()
					->addToBody(lang('php_in_templates_warning'))
					->addToBody(
						sprintf(lang('php_in_templates_warning2'), '<span title="excercise caution"></span>'),
						'caution'
					)
					->cannotClose()
					->render(),
				array(
					'title' => 'allow_php',
					'desc' => 'allow_php_desc',
					'caution' => TRUE,
					'fields' => array(
						'board_allow_php' => array(
							'type' => 'yes_no',
							'value' => $board->board_allow_php,
						)
					)
				),
				array(
					'title' => 'php_parsing_stage',
					'desc' => 'php_parsing_stage_desc',
					'fields' => array(
						'board_php_stage' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'i' => 'input',
								'o' => 'output'
							),
							'value' => $board->board_php_stage,
						)
					)
				),
			),
			'attachment_settings' => array(
				array(
					'title' => 'attachments_per_post',
					'desc' => 'attachments_per_post_desc',
					'fields' => array(
						'board_max_attach_perpost' => array(
							'type' => 'text',
							'value' => $board->board_max_attach_perpost,
						)
					)
				),
				array(
					'title' => 'upload_directory',
					'desc' => 'upload_directory_desc',
					'fields' => array(
						'board_upload_path' => array(
							'type' => 'text',
							'value' => $board->board_upload_path,
							'attrs' => 'placeholder="http://"'
						)
					)
				),
				array(
					'title' => 'allowed_file_types',
					'desc' => 'allowed_file_types_desc',
					'fields' => array(
						'board_attach_types' => array(
							'type' => 'select',
							'choices' => array(
								'img' => lang('images_only'),
								'all' => lang('all_files')
							),
							'value' => $board->board_attach_types,
						)
					)
				),
				array(
					'title' => 'file_size',
					'desc' => 'file_size_desc',
					'fields' => array(
						'board_max_attach_size' => array(
							'type' => 'text',
							'value' => $board->board_max_attach_size,
						)
					)
				),
				array(
					'title' => 'image_width',
					'desc' => 'image_width_desc',
					'fields' => array(
						'board_max_width' => array(
							'type' => 'text',
							'value' => $board->board_max_width,
						)
					)
				),
				array(
					'title' => 'image_height',
					'desc' => 'image_height_desc',
					'fields' => array(
						'board_max_height' => array(
							'type' => 'text',
							'value' => $board->board_max_height,
						)
					)
				),
				array(
					'title' => 'enable_thumbnail_creation',
					'desc' => 'enable_thumbnail_creation_desc',
					'fields' => array(
						'board_use_img_thumbs' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $board->board_use_img_thumbs,
						)
					)
				),
				array(
					'title' => 'thumbnail_width',
					'desc' => 'thumbnail_width_desc',
					'fields' => array(
						'board_thumb_width' => array(
							'type' => 'text',
							'value' => $board->board_thumb_width,
						)
					)
				),
				array(
					'title' => 'thumbnail_height',
					'desc' => 'thumbnail_height_desc',
					'fields' => array(
						'board_thumb_height' => array(
							'type' => 'text',
							'value' => $board->board_thumb_height,
						)
					)
				),
			)
		);

		foreach ($sections as $name => $settings)
		{
			$html .= ee('View')->make('ee:_shared/form/section')
				->render(array('name' => $name, 'settings' => $settings));
		}

		return $html;
	}

	private function getBoardForumsForm($board)
	{
		$html = '';

		$sections = array(
			array(
				array(
					'title' => 'topics_per_page',
					'desc' => 'topics_per_page_desc',
					'fields' => array(
						'board_topics_perpage' => array(
							'type' => 'text',
							'value' => $board->board_topics_perpage,
						)
					)
				),
				array(
					'title' => 'posts_per_page',
					'desc' => 'posts_per_page_desc',
					'fields' => array(
						'board_posts_perpage' => array(
							'type' => 'text',
							'value' => $board->board_posts_perpage,
						)
					)
				),
				array(
					'title' => 'topic_ordering',
					'desc' => 'topic_ordering_desc',
					'fields' => array(
						'board_topic_order' => array(
							'type' => 'select',
							'choices' => array(
								'r' => lang('most_recent_post'),
								'a' => lang('most_recent_first'),
								'd' => lang('most_recent_last'),
							),
							'value' => $board->board_topic_order,
						)
					)
				),
				array(
					'title' => 'post_ordering',
					'desc' => 'post_ordering_desc',
					'fields' => array(
						'board_post_order' => array(
							'type' => 'select',
							'choices' => array(
								'a' => lang('most_recent_first'),
								'd' => lang('most_recent_last'),
							),
							'value' => $board->board_post_order,
						)
					)
				),
				array(
					'title' => 'hot_topics',
					'desc' => 'hot_topics_desc',
					'fields' => array(
						'board_hot_topic' => array(
							'type' => 'text',
							'value' => $board->board_hot_topic,
						)
					)
				),
				array(
					'title' => 'allowed_characters',
					'desc' => 'allowed_characters_desc',
					'fields' => array(
						'board_max_post_chars' => array(
							'type' => 'text',
							'value' => $board->board_max_post_chars,
						)
					)
				),
				array(
					'title' => 'posting_throttle',
					'desc' => 'posting_throttle_desc',
					'fields' => array(
						'board_post_timelock' => array(
							'type' => 'text',
							'value' => $board->board_post_timelock,
						)
					)
				),
				array(
					'title' => 'show_editing_dates',
					'desc' => 'show_editing_dates_desc',
					'fields' => array(
						'board_display_edit_date' => array(
							'type' => 'yes_no',
							'value' => $board->board_display_edit_date,
						)
					)
				),
			),
			'notification_settings' => array(
				array(
					'title' => 'topic_notifications',
					'desc' => 'topic_notifications_desc',
					'fields' => array(
						'board_enable_notify_emails_topics' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $board->board_enable_notify_emails_topics,
						),
						'board_notify_emails_topics' => array(
							'type' => 'text',
							'value' => $board->board_notify_emails_topics,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
				array(
					'title' => 'reply_notification',
					'desc' => 'reply_notification_desc',
					'fields' => array(
						'board_enable_notify_emails' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $board->board_enable_notify_emails,
						),
						'board_notify_emails' => array(
							'type' => 'text',
							'value' => $board->board_notify_emails,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
			),
			'text_and_html_formatting' => array(
				array(
					'title' => 'text_formatting',
					'desc' => 'text_formatting_desc',
					'fields' => array(
						'board_text_formatting' => array(
							'type' => 'select',
							'choices' => $this->fmt_options,
							'value' => $board->board_text_formatting,
						)
					)
				),
				array(
					'title' => 'html_formatting',
					'desc' => 'html_formatting_desc',
					'fields' => array(
						'board_html_formatting' => array(
							'type' => 'select',
							'choices' => array(
								'all'  => lang('html_all'),
								'safe' => lang('html_safe'),
								'none' => lang('html_none'),
							),
							'value' => $board->board_html_formatting,
						)
					)
				),
				array(
					'title' => 'autolink_urls',
					'desc' => 'autolink_urls_desc',
					'fields' => array(
						'board_auto_link_urls' => array(
							'type' => 'yes_no',
							'value' => $board->board_auto_link_urls,
						)
					)
				),
				array(
					'title' => 'allow_image_hotlinking',
					'desc' => 'allow_image_hotlinking_desc',
					'fields' => array(
						'board_allow_img_urls' => array(
							'type' => 'yes_no',
							'value' => $board->board_allow_img_urls,
						)
					)
				),
			),
			'rss_settings' => array(
				array(
					'title' => 'enable_rss',
					'desc' => 'enable_rss_desc',
					'fields' => array(
						'board_enable_rss' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $board->board_enable_rss,
						)
					)
				),
				array(
					'title' => 'enable_http_auth_for_rss',
					'desc' => 'enable_http_auth_for_rss_desc',
					'fields' => array(
						'board_use_http_auth' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $board->board_use_http_auth,
						)
					)
				),
			),
		);

		foreach ($sections as $name => $settings)
		{
			$html .= ee('View')->make('ee:_shared/form/section')
				->render(array('name' => $name, 'settings' => $settings));
		}

		return $html;
	}

	private function getBoardPermissionsForm($board)
	{
		$html = '';

		$member_groups = ee('Model')->get('MemberGroup')
			->fields('group_id', 'group_title')
			->filter('site_id', ee()->config->item('site_id'))
			->filter('group_id', '!=', '1')
			->order('group_title', 'asc')
			->all()
			->getDictionary('group_id', 'group_title');

		$sections = array(
			array(
				ee('Alert')->makeInline('permissions-warn')
					->asWarning()
					->addToBody(lang('permissions_warning'))
					->cannotClose()
					->render(),
				array(
					'title' => 'enable_default_permissions',
					'desc' => 'enable_default_permissions_desc',
					'fields' => array(
						'board_use_deft_permissions' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $board->board_use_deft_permissions,
						)
					)
				),
				array(
					'title' => 'view_forum',
					'desc' => 'view_forum_desc',
					'fields' => array(
						'permissions[can_view_forum]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_view_forum'),
						)
					)
				),
				array(
					'title' => 'view_hidden_forum',
					'desc' => 'view_hidden_forum_desc',
					'fields' => array(
						'permissions[can_view_hidden]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_view_hidden'),
						)
					)
				),
				array(
					'title' => 'view_posts',
					'desc' => 'view_posts_desc',
					'fields' => array(
						'permissions[can_view_topics]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_view_topics'),
						)
					)
				),
				array(
					'title' => 'start_topics',
					'desc' => 'start_topics_desc',
					'fields' => array(
						'permissions[can_post_topics]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_post_topics'),
						)
					)
				),
				array(
					'title' => 'reply_to_topics',
					'desc' => 'reply_to_topics_desc',
					'fields' => array(
						'permissions[can_post_reply]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_post_reply'),
						)
					)
				),
				array(
					'title' => 'upload',
					'desc' => 'upload_desc',
					'fields' => array(
						'permissions[upload_files]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('upload_files'),
						)
					)
				),
				array(
					'title' => 'report',
					'desc' => 'report_desc',
					'fields' => array(
						'permissions[can_report]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_report'),
						)
					)
				),
				array(
					'title' => 'search',
					'desc' => 'search_desc',
					'fields' => array(
						'permissions[can_search]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $board->getPermission('can_search'),
						)
					)
				),
			)
		);

		foreach ($sections as $name => $settings)
		{
			$html .= ee('View')->make('ee:_shared/form/section')
				->render(array('name' => $name, 'settings' => $settings));
		}

		return $html;
	}

	// --------------------------------------------------------------------

	private function createCategory($board_id)
	{
		$errors = NULL;

		$board = ee('Model')->get('forum:Board', $board_id)->first();
		if ( ! $board)
		{
			show_404();
		}

		if ( ! empty($board->board_forum_permissions)
			&& $board->board_use_deft_permissions)
		{
			$default_permissions = $board->board_forum_permissions;
		}
		else
		{
			$default_permissions = $this->forum_set_base_permissions();
		}

		$defaults = array(
			'board_id' => $board_id,
			'forum_is_cat' => TRUE,
			'forum_permissions' => $default_permissions,
			// These cannot be NULL in the DB....
			'forum_topics_perpage' => 25,
			'forum_posts_perpage' => 15,
			'forum_hot_topic' => 10,
			'forum_max_post_chars' => 6000,
		);

		$category = ee('Model')->make('forum:Forum', $defaults);

		$result = $this->validateCategory($category);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveCategoryAndRedirect($category);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => lang('create_category'),
			'base_url' => ee('CP/URL', $this->base . 'create/category/' . $board_id),
			'save_btn_text' => 'btn_save_category',
			'save_btn_text_working' => 'btn_saving',
			'sections' => $this->categoryForm($category),
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => lang('create_forum_board'),
			'sidebar'    => $this->generateSidebar($board_id)
		);
	}

	private function editCategory($id)
	{
		$errors = NULL;

		$category = ee('Model')->get('forum:Forum', $id)->with('Board')->first();
		if ( ! $category)
		{
			show_404();
		}

		$result = $this->validateBoard($category);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveCategoryAndRedirect($category);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => lang('edit_category'),
			'base_url' => ee('CP/URL', $this->base . 'edit/category/' . $id),
			'save_btn_text' => 'btn_save_category',
			'save_btn_text_working' => 'btn_saving',
			'sections' => $this->categoryForm($category),
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $category->Board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => $vars['cp_page_title'],
			'sidebar'    => $this->generateSidebar($category->Board->board_id)
		);
	}

	private function validateCategory($category)
	{
		if (empty($_POST))
		{
			return FALSE;
		}

		$action = ($category->isNew()) ? 'create' : 'edit';

		$category->set($_POST);
		$result = $category->validate();

		if ($response = $this->ajaxValidation($result))
		{
			ee()->output->send_ajax_response($response);
		}

		if ($result->failed())
		{
			ee('Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang($action . '_category_error'))
				->addToBody(lang($action . '_category_error_desc'))
				->now();
		}

		return $result;
	}

	private function saveCategoryAndRedirect($category)
	{
		$action = ($category->isNew()) ? 'create' : 'edit';

		$category->save();

		ee('Alert')->makeInline('shared-form')
			->asSuccess()
			->withTitle(lang($action . '_category_success'))
			->addToBody(sprintf(lang($action . '_category_success_desc'), $category->forum_name))
			->defer();

		ee()->functions->redirect(ee('CP/URL', $this->base . '/index/' . $category->board_id));
	}

	private function categoryForm($category)
	{
		$sections = array(
			array(
				array(
					'title' => 'name',
					'desc' => 'name_desc',
					'fields' => array(
						'forum_name' => array(
							'type' => 'text',
							'required' => TRUE,
							'value' => $category->forum_name,
						)
					)
				),
				array(
					'title' => 'description',
					'desc' => 'description_desc',
					'fields' => array(
						'forum_description' => array(
							'type' => 'textarea',
							'value' => $category->forum_description,
						)
					)
				),
				array(
					'title' => 'status',
					'desc' => 'status_desc',
					'fields' => array(
						'forum_status' => array(
							'type' => 'select',
							'choices' => array(
								'o' => lang('live'),
								'c' => lang('hidden'),
								'a' => lang('read_only'),
							),
							'value' => $category->forum_status,
						)
					)
				),
				array(
					'title' => 'topic_notifications',
					'desc' => 'topic_notifications_desc',
					'fields' => array(
						'forum_enable_notify_emails' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $category->forum_enable_notify_emails,
						),
						'forum_notify_emails' => array(
							'type' => 'text',
							'value' => $category->forum_notify_emails,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
				array(
					'title' => 'reply_notifications',
					'desc' => 'reply_notifications_desc',
					'fields' => array(
						'forum_enable_notify_emails_topics' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $category->forum_enable_notify_emails_topics,
						),
						'forum_notify_emails_topics' => array(
							'type' => 'text',
							'value' => $category->forum_notify_emails_topics,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
			)
		);

		return $sections;
	}

	private function settingsForCategory($id)
	{
		$errors = NULL;

		$category = ee('Model')->get('forum:Forum', $id)->with('Board')->first();
		if ( ! $category)
		{
			show_404();
		}

		if ( ! empty($_POST))
		{
			foreach ($_POST['permissions'] as $key => $value)
			{
				$category->setPermission($key, $value);
			}

			$category->save();

			ee('Alert')->makeInline('shared-form')
				->asSuccess()
				->withTitle(lang('edit_category_settings_success'))
				->addToBody(sprintf(lang('edit_category_settings_success_desc'), $category->forum_name))
				->defer();

			ee()->functions->redirect(ee('CP/URL', $this->base . '/index/' . $category->board_id));
		}

		$vars = array(
			'errors' => $errors,
			'cp_page_title' => sprintf(lang('category_permissions'), $category->forum_name),
			'base_url' => ee('CP/URL', $this->base . 'settings/category/' . $id),
			'save_btn_text' => 'btn_save_permissions',
			'save_btn_text_working' => 'btn_saving',
		);

		$member_groups = ee('Model')->get('MemberGroup')
			->fields('group_id', 'group_title')
			->filter('site_id', ee()->config->item('site_id'))
			->filter('group_id', '!=', '1')
			->order('group_title', 'asc')
			->all()
			->getDictionary('group_id', 'group_title');

		$vars['sections'] = array(
			array(
				ee('Alert')->makeInline('permissions-warn')
					->asWarning()
					->addToBody(lang('permissions_warning'))
					->cannotClose()
					->render(),
				array(
					'title' => 'view_category',
					'desc' => 'view_category_desc',
					'fields' => array(
						'permissions[can_view_forum]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $category->getPermission('can_view_forum'),
						)
					)
				),
				array(
					'title' => 'view_hidden_category',
					'desc' => 'view_hidden_category_desc',
					'fields' => array(
						'permissions[can_view_hidden]' => array(
							'type' => 'checkbox',
							'choices' => $member_groups,
							'value' => $category->getPermission('can_view_hidden'),
						)
					)
				),
			)
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $category->Board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => $vars['cp_page_title'],
			'sidebar'    => $this->generateSidebar($category->Board->board_id)
		);
	}

	// --------------------------------------------------------------------

	private function createForum($cat_id)
	{
		$errors = NULL;

		$category = ee('Model')->get('forum:Forum', $cat_id)
			->with('Board')
			->first();

		if ( ! $category)
		{
			show_404();
		}

		$board = $category->Board;

		if ( ! empty($board->board_forum_permissions)
			&& $board->board_use_deft_permissions)
		{
			$default_permissions = $board->board_forum_permissions;
		}
		else
		{
			$default_permissions = $this->forum_set_base_permissions();
		}

		$defaults = array(
			'board_id' => $board->board_id,
			'forum_parent' => $cat_id,
			'forum_is_cat' => FALSE,
			'forum_permissions' => $default_permissions,
			'forum_topics_perpage' => 25,
			'forum_posts_perpage' => 15,
			'forum_hot_topic' => 10,
			'forum_max_post_chars' => 6000,
		);

		$forum = ee('Model')->make('forum:Forum', $defaults);

		$result = $this->validateForum($forum);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveForumAndRedirect($forum);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => lang('create_forum'),
			'base_url' => ee('CP/URL', $this->base . 'create/forum/' . $cat_id),
			'save_btn_text' => 'btn_save_forum',
			'save_btn_text_working' => 'btn_saving',
			'sections' => $this->forumForm($forum),
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => lang('create_forum_board'),
			'sidebar'    => $this->generateSidebar($board->board_id)
		);
	}

	private function editForum($id)
	{
		$errors = NULL;

		$forum = ee('Model')->get('forum:Forum', $id)->with('Board')->first();
		if ( ! $forum)
		{
			show_404();
		}

		$result = $this->validateBoard($forum);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$this->saveForumAndRedirect($forum);
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'errors' => $errors,
			'cp_page_title' => lang('edit_forum'),
			'base_url' => ee('CP/URL', $this->base . 'edit/forum/' . $id),
			'save_btn_text' => 'btn_save_forum',
			'save_btn_text_working' => 'btn_saving',
			'sections' => $this->forumForm($forum),
		);

		$body = ee('View')->make('ee:_shared/form')->render($vars);

		return array(
			'body'       => '<div class="box">' . $body . '</div>',
			'breadcrumb' => array(
				ee('CP/URL', $this->base)->compile() => $forum->Board->board_label . ' '. lang('forum_listing')
			),
			'heading'    => $vars['cp_page_title'],
			'sidebar'    => $this->generateSidebar($id)
		);
	}

	private function validateForum($forum)
	{
		if (empty($_POST))
		{
			return FALSE;
		}

		$action = ($forum->isNew()) ? 'create' : 'edit';

		$forum->set($_POST);
		$result = $forum->validate();

		if ($response = $this->ajaxValidation($result))
		{
			ee()->output->send_ajax_response($response);
		}

		if ($result->failed())
		{
			ee('Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang($action . '_forum_error'))
				->addToBody(lang($action . '_forum_error_desc'))
				->now();
		}

		return $result;
	}

	private function saveForumAndRedirect($forum)
	{
		$action = ($forum->isNew()) ? 'create' : 'edit';

		$forum->save();

		if ($action == 'create')
		{
			ee()->session->set_flashdata('forum_id', $forum->forum_id);
		}

		ee('Alert')->makeInline('shared-form')
			->asSuccess()
			->withTitle(lang($action . '_forum_success'))
			->addToBody(sprintf(lang($action . '_forum_success_desc'), $forum->forum_name))
			->defer();

		ee()->functions->redirect(ee('CP/URL', $this->base . '/index/' . $forum->board_id));
	}

	private function forumForm($forum)
	{
		$sections = array(
			array(
				array(
					'title' => 'name',
					'desc' => 'name_desc',
					'fields' => array(
						'forum_name' => array(
							'type' => 'text',
							'required' => TRUE,
							'value' => $forum->forum_name,
						)
					)
				),
				array(
					'title' => 'description',
					'desc' => 'description_desc',
					'fields' => array(
						'forum_description' => array(
							'type' => 'textarea',
							'value' => $forum->forum_description,
						)
					)
				),
				array(
					'title' => 'status',
					'desc' => 'status_desc',
					'fields' => array(
						'forum_status' => array(
							'type' => 'select',
							'choices' => array(
								'o' => lang('live'),
								'c' => lang('hidden'),
								'a' => lang('read_only'),
							),
							'value' => $forum->forum_status,
						)
					)
				),
			),
			'topic_and_post_settings' => array(
				array(
					'title' => 'topics_per_page',
					'desc' => 'topics_per_page_desc',
					'fields' => array(
						'forum_topics_perpage' => array(
							'type' => 'text',
							'value' => $forum->forum_topics_perpage,
						)
					)
				),
				array(
					'title' => 'posts_per_page',
					'desc' => 'posts_per_page_desc',
					'fields' => array(
						'forum_posts_perpage' => array(
							'type' => 'text',
							'value' => $forum->forum_posts_perpage,
						)
					)
				),
				array(
					'title' => 'topic_ordering',
					'desc' => 'topic_ordering_desc',
					'fields' => array(
						'forum_topic_order' => array(
							'type' => 'select',
							'choices' => array(
								'r' => lang('most_recent_post'),
								'a' => lang('most_recent_first'),
								'd' => lang('most_recent_last'),
							),
							'value' => $forum->forum_topic_order,
						)
					)
				),
				array(
					'title' => 'post_ordering',
					'desc' => 'post_ordering_desc',
					'fields' => array(
						'forum_post_order' => array(
							'type' => 'select',
							'choices' => array(
								'a' => lang('most_recent_first'),
								'd' => lang('most_recent_last'),
							),
							'value' => $forum->forum_post_order,
						)
					)
				),
				array(
					'title' => 'hot_topics',
					'desc' => 'hot_topics_desc',
					'fields' => array(
						'forum_hot_topic' => array(
							'type' => 'text',
							'value' => $forum->forum_hot_topic,
						)
					)
				),
				array(
					'title' => 'allowed_characters',
					'desc' => 'allowed_characters_desc',
					'fields' => array(
						'forum_max_post_chars' => array(
							'type' => 'text',
							'value' => $forum->forum_max_post_chars,
						)
					)
				),
				array(
					'title' => 'posting_throttle',
					'desc' => 'posting_throttle_desc',
					'fields' => array(
						'forum_post_timelock' => array(
							'type' => 'text',
							'value' => $forum->forum_post_timelock,
						)
					)
				),
				array(
					'title' => 'show_editing_dates',
					'desc' => 'show_editing_dates_desc',
					'fields' => array(
						'forum_display_edit_date' => array(
							'type' => 'yes_no',
							'value' => $forum->forum_display_edit_date,
						)
					)
				),
			),
			'notification_settings' => array(
				array(
					'title' => 'topic_notifications',
					'desc' => 'topic_notifications_desc',
					'fields' => array(
						'forum_enable_notify_emails' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $forum->forum_enable_notify_emails,
						),
						'forum_notify_emails' => array(
							'type' => 'text',
							'value' => $forum->forum_notify_emails,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
				array(
					'title' => 'reply_notifications',
					'desc' => 'reply_notifications_desc',
					'fields' => array(
						'forum_enable_notify_emails_topics' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							// 'value' => $forum->forum_enable_notify_emails_topics,
						),
						'forum_notify_emails_topics' => array(
							'type' => 'text',
							'value' => $forum->forum_notify_emails_topics,
							'attrs' => 'placeholder="' . lang('recipients'). '"'
						),
					)
				),
			),
			'text_and_html_formatting' => array(
				array(
					'title' => 'text_formatting',
					'desc' => 'text_formatting_desc',
					'fields' => array(
						'forum_text_formatting' => array(
							'type' => 'select',
							'choices' => $this->fmt_options,
							'value' => $forum->forum_text_formatting,
						)
					)
				),
				array(
					'title' => 'html_formatting',
					'desc' => 'html_formatting_desc',
					'fields' => array(
						'forum_html_formatting' => array(
							'type' => 'select',
							'choices' => array(
								'all'  => lang('html_all'),
								'safe' => lang('html_safe'),
								'none' => lang('html_none'),
							),
							'value' => $forum->forum_html_formatting,
						)
					)
				),
				array(
					'title' => 'autolink_urls',
					'desc' => 'autolink_urls_desc',
					'fields' => array(
						'forum_auto_link_urls' => array(
							'type' => 'yes_no',
							'value' => $forum->forum_auto_link_urls,
						)
					)
				),
				array(
					'title' => 'allow_image_hotlinking',
					'desc' => 'allow_image_hotlinking_desc',
					'fields' => array(
						'forum_allow_img_urls' => array(
							'type' => 'yes_no',
							'value' => $forum->forum_allow_img_urls,
						)
					)
				),
			),
			'rss_settings' => array(
				array(
					'title' => 'enable_rss',
					'desc' => 'enable_rss_desc',
					'fields' => array(
						'forum_enable_rss' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $forum->forum_enable_rss,
						)
					)
				),
				array(
					'title' => 'enable_http_auth_for_rss',
					'desc' => 'enable_http_auth_for_rss_desc',
					'fields' => array(
						'forum_use_http_auth' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'y' => 'enable',
								'n' => 'disable'
							),
							'value' => $forum->forum_use_http_auth,
						)
					)
				),
			),
		);

		return $sections;
	}

	// --------------------------------------------------------------------

	/**
	 * Load Default Prefs
	 *
	 * Loads default preferences for a newly created forum
	 *
	 * @return	void
	 */
	private function getDefaultPrefs()
	{
		return array(
			'board_id'						=> '',
			'board_label'					=> '',
			'board_name'					=> '',
			'board_enabled'					=> 'y',
			'board_forum_trigger'			=> 'forums',
			'board_site_id'					=> 1,
			'board_alias_id'				=> 0,
			'board_allow_php'				=> 'n',
			'board_php_stage'				=> 'o',
			'board_install_date'			=> 0,
			'board_forum_url'				=> ee()->functions->create_url('forums'),
			'board_default_theme'			=> 'default',
			'board_upload_path'				=> '',
			'board_topics_perpage'			=> 25,
			'board_posts_perpage'			=> 15,
			'board_topic_order'				=> 'r',
			'board_post_order'				=> 'a',
			'board_hot_topic'				=> 10,
			'board_max_post_chars'			=> 6000,
			'board_post_timelock'			=> 0,
			'board_display_edit_date'		=> 'n',
			'board_text_formatting'			=> 'xhtml',
			'board_html_formatting'			=> 'safe',
			'board_allow_img_urls'			=> 'n',
			'board_auto_link_urls'			=> 'y',
			'board_notify_emails'			=> '',
			'board_notify_emails_topics'	=> '',
			'board_max_attach_perpost'		=> 3,
			'board_max_attach_size'			=> 75,
			'board_max_width'				=> 800,
			'board_max_height'				=> 600,
			'board_attach_types'			=> 'img',
			'board_use_img_thumbs'			=> ($this->gd_loaded() == TRUE) ? 'y' : 'n',
			'board_thumb_width'				=> 100,
			'board_thumb_height'			=> 100,
			'board_forum_permissions'		=> $this->forum_set_base_permissions(),
			'board_use_deft_permissions'	=> 'n',
			'board_recent_poster_id'		=> '0',
			'board_recent_poster'			=> '',
			'board_enable_rss'				=> 'y',
			'board_use_http_auth'			=> 'n',
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Is GD installed?
	 *
	 * @access	private
	 * @return	void
	 */
	function gd_loaded()
	{
		if (! extension_loaded('gd'))
		{
			if (! function_exists('dl') OR ! @dl('gd.so'))
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Content Wrapper
	 *
	 * This is a helper function that builds the forum control panel output.
	 * Each function that generates a UI will call this function.
	 *
	 * @access	private
	 * @return	void
	 */
	function _content_wrapper($content_view, $title, $vars = array(), $crumb = '')
	{
		$message = ee()->session->flashdata('message');

		$vars['_show_nav']	= $this->show_nav;
		$vars['_board_id']	= $this->board_id;
		$vars['_base']		= $this->base;
		$vars['_id_base']	= $this->id_base;
		$vars['_form_base']	= $this->form_base;

		$vars['message'] = $message;
		$vars['reduced_nav'] = FALSE;

		$vars['board_forum_url'] = $this->prefs['board_forum_url'];

		if ($this->prefs['board_install_date'] < 1)
		{
			$vars['_show_nav'] = FALSE;
		}

		if (ee()->input->get_post('alias') == 'y' OR $this->prefs['board_alias_id'] != '0')
		{
			$vars['reduced_nav'] = TRUE;
		}

		ee()->db->select('board_id, board_label, board_alias_id');
		ee()->db->order_by('board_label');
		$query = ee()->db->get('forum_boards');

		foreach($query->result_array() as $row)
		{
			$vars['_boards'][$row['board_id']] = form_prep($row['board_label']);
		}

		ee()->view->cp_page_title = ee()->lang->line($title);
		ee()->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum', ee()->lang->line('forum_module_name'));

		// Using _add_crumb for templates means we end with a breadcrumb after the page title...
		// we fix this by adding the current page to the path and setting a bogus cp title

		if (count($this->_add_crumb))
		{
			$root = array_shift($this->_add_crumb);

			ee()->cp->set_breadcrumb(key($root), current($root));

			foreach($this->_add_crumb as $key => $crumb)
			{
				if ($key == (count($this->_add_crumb) - 1))
				{
					ee()->view->cp_page_title = current($crumb);
				}
				else
				{
					ee()->cp->set_breadcrumb(key($crumb), current($crumb));
				}
			}
		}

		$highlight = array(
			'index'								=> 'forum_board_home',
			'forum_edit'						=> 'forum_management',
			'forum_prefs'						=> 'forum_management',
			'forum_permissions'					=> 'forum_management',
			'forum_management'					=> 'forum_management',
			'forum_admins'						=> 'forum_admins',
			'forum_moderators'					=> 'forum_moderators',
			'add_edit_moderator'				=> 'forum_moderators'
		);

		$vars['_current_tab'] = (isset($highlight[$content_view]) ? $highlight[$content_view] : '');

		// Switch boards
		ee()->javascript->output('
		$("select[name=board_id]", "#forum_global_nav").change(function() {
			window.location = "'.str_replace(AMP, '&', $this->base).'&board_id="+$(this).val();
		});
		');

		return ee('View')->make('forum:' . $content_view)->render($vars);
		return ee()->view->render($content_view, $vars, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Create a new Forum Board
	 *
	 * @access	public
	 * @return	void
	 */
	function new_board()
	{
		$this->prefs['board_id'] = '';
		return $this->forum_prefs(TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * List forum boards and aliases
	 *
	 * Allows for adding, editing and deleting of forum boards/aliases
	 *
	 * @access	public
	 * @return	void
	 */
	function list_boards()
	{
		// List Forum Boards

		ee()->load->library('table');

		ee()->db->select('board_label, board_name, board_enabled, board_id');
		ee()->db->where('board_alias_id', '0');
		ee()->db->order_by('board_label');
		$query = ee()->db->get('forum_boards');

		$vars['boards'] = $query->result_array();


		// List Forum Aliases

		ee()->db->select('board_label, board_name, board_enabled, board_id');
		ee()->db->where('board_alias_id !=', '0');
		ee()->db->order_by('board_label');
		$query = ee()->db->get('forum_boards');

		$vars['aliases'] = $query->result_array();
 		$this->show_nav = FALSE;

		return $this->_content_wrapper('list_boards', 'edit_forum_boards', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Board Confirmation
	 *
	 * @access	public
	 * @return	void
	 */
	function delete_board_confirm()
	{
		if ( ! ee()->cp->allowed_group('can_admin_boards'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		if ( ! $board_id = ee()->input->get_post('board_id'))
		{
			return FALSE;
		}

		if ($board_id == 1)
		{
			return FALSE;
		}

		ee()->db->select('board_label');
		$query = ee()->db->get_where('forum_boards', array('board_id' => $board_id));

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		ee()->view->cp_page_title = ee()->lang->line('delete_board_confirmation');

		$vars['form_action'] = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=delete_board';
		$vars['hidden'] = array('board_id' => $board_id);

		return $this->_content_wrapper('delete_board_confirmation', 'delete_board_confirmation', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Forum Board
	 *
	 * @access	public
	 * @return	void
	 */
	function delete_board()
	{
		if ( ! ee()->cp->allowed_group('can_admin_boards'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		if ( ! $board_id = ee()->input->post('board_id'))
		{
			return FALSE;
		}

		if ( ! is_numeric($board_id))
		{
			return FALSE;
		}

		if ($board_id == 1)
		{
			return FALSE;
		}

		ee()->db->select('board_id, board_label, board_upload_path');
		$query = ee()->db->get_where('forum_boards', array('board_id' => $board_id));

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$board_id = $query->row('board_id') ;
		$upload_path = $query->row('board_upload_path') ;
		$board_label = $query->row('board_label') ;

		/** ---------------------------------------
		/**  Delete Attachment Files
		/** ---------------------------------------*/

		ee()->db->select('filehash, extension');
		$query = ee()->db->get_where('forum_attachments', array('board_id' => $board_id));

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$file  = $upload_path.$row['filehash'].$row['extension'];
				$thumb = $upload_path.$row['filehash'].'_t'.$row['extension'];

				@unlink($file);
				@unlink($thumb);
			}
		}

		/** ---------------------------------------
		/**  Delete Polls
		/** ---------------------------------------*/

		ee()->db->select('topic_id');
		ee()->db->where('board_id', $board_id);
		ee()->db->where('poll', 'y');
		$query = ee()->db->get('forum_topics');

		if ($query->num_rows() > 0)
		{
			$topic_ids = array();

			foreach ($query->result_array() as $row)
			{
				$topic_ids[] = $row['topic_id'];
			}

			$TOPIC_IDS = implode(',', $topic_ids);

			ee()->db->query("DELETE FROM exp_forum_polls WHERE topic_id IN ({$TOPIC_IDS})");
			ee()->db->query("DELETE FROM exp_forum_pollvotes WHERE topic_id IN ({$TOPIC_IDS})");
		}

		$tables = array('exp_forum_boards',
						'exp_forums',
						'exp_forum_administrators',
						'exp_forum_search',
						'exp_forum_moderators',
						'exp_forum_subscriptions',
						'exp_forum_read_topics',
						'exp_forum_topics',
						'exp_forum_posts',
						'exp_forum_attachments');

		foreach ($tables as $table)
		{
			ee()->db->where('board_id', $board_id);
			ee()->db->delete($table);
		}

		$this->update_triggers();

		ee()->logger->log_action(ee()->lang->line('board_deleted').':'.NBS.NBS.$board_label);

		ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=list_boards');
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Management
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_management()
	{
		ee()->load->library('table');
		ee()->db->order_by('forum_order');
		$query = ee()->db->get_where('forums', array('board_id' => $this->board_id));

		$vars = array();
		$vars['forums'] = ($query->num_rows() > 0) ? $query->result_array() : array();

		return $this->_content_wrapper('forum_management', 'forum_management', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Create/Edit forum/category
	 *
	 * Note:  There is really no difference between a category and a forum.
	 * Both utilize the same "exp_forums" table.  The only difference is that
	 * a category acts simply as a heading for the cluster of forums it
	 * contains.  You obviously can't post messages into a category, only into
	 * the forums it contains.  I condidered running categories as their own table,
	 * but it would require one more query and I didn't see any real advantage.
	 * Internally, the module will know to treat categories slightly different, even though
	 * they are essentially a forum that acts as a heading and does not accept posts.
	 * Clear as mud?  -- Rick
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_edit()
	{
		$forum_id 	= ee()->input->get_post('forum_id');
		$parent_id 	= ee()->input->get_post('parent_id');
		$default_parent = FALSE;

		//  What type of request are we processing?

		// If $_GET['forum_id'] is missing we are creating a new item
		// rather then editing an existing item

		$is_new = ($forum_id == FALSE) ? TRUE : FALSE;

		// Similarly, if the "is_cat" item is missing we are handling a forum
		// rather than a category

		$is_forum = ( ! ee()->input->get_post('is_cat')) ? TRUE : FALSE;


		// Build the data matrix

		$cat_prefs = array('forum_preferences', 'forum_prefs_notification');
		$hidden_prefs = array('forum_prefs_notification', 'forum_prefs_topics', 'forum_prefs_formatting');

		$data = array(
						'forum_preferences' => array(
											'forum_name'		=> array('t', '200'),
											'forum_description'	=> array('x', array('rows' => '8')),
											'forum_parent'		=> array('f', '_forum_fetch_categories'),
											'forum_status'		=> array('d', array('o' => 'forum_open', 'c' => 'forum_closed', 'a' => 'forum_archived'))
													),

						'forum_prefs_notification' => array(
											'forum_notify_moderators_topics'	=> array('r', array('y' => 'yes', 'n' => 'no')),
											'forum_notify_moderators_replies'	=> array('r', array('y' => 'yes', 'n' => 'no')),
											'forum_notify_emails_topics'		=> array('t', '255'),
											'forum_notify_emails'				=> array('t', '255')
											),

						'forum_prefs_topics' => array(
											'forum_topics_perpage'		=> array('t', '4'),
											'forum_posts_perpage'		=> array('t', '4'),
											'forum_topic_order'			=> array('d', array('d' => 'descending', 'a' => 'ascending', 'r' => 'most_recent_topic')),
											'forum_post_order'			=> array('d', array('d' => 'descending', 'a' => 'ascending')),
											'forum_hot_topic'			=> array('t', '4'),
											'forum_max_post_chars'		=> array('t', '5'),
											'forum_post_timelock'		=> array('t', '4'),
											'forum_display_edit_date'	=> array('r', array('y' => 'yes', 'n' => 'no'))
											),

						'forum_prefs_formatting' => array(
											'forum_text_formatting'		=> array('d', $this->fmt_options),
											'forum_html_formatting'		=> array('d', array('safe' => 'safe', 'none' => 'none', 'all' => 'all')),
											'forum_auto_link_urls'		=> array('r', array('y' => 'yes', 'n' => 'no')),
											'forum_allow_img_urls'		=> array('r', array('y' => 'yes', 'n' => 'no'))
											),

						'forum_prefs_rss' => array(
											'forum_enable_rss'			=> array('r', array('y' => 'yes', 'n' => 'no')),
											'forum_use_http_auth'		=> array('r', array('y' => 'yes', 'n' => 'no'))
											)

					);



		$subtext = array(
			'forum_post_timelock'			=> 'pref_post_timelock_more',
			'forum_notify_emails'			=> 'pref_notify_emails_forums',
			'forum_notify_emails_topics'	=> 'pref_notify_emails_topics_more'
		);

		// Category Exceptions

		// Some of the items in the above matrix don't
		// apply to categories so we'll create a list of things
		// that should not appear when editing a category
		$item_exceptions = array('forum_parent');

		$hidden['forum_is_cat'] = ($is_forum === TRUE) ? 'n' : 'y';

		if ($forum_id !== FALSE)
		{
			$hidden['forum_id'] = $forum_id;
		}

		// Fetch the forum data if we are editing

		if ($is_new === FALSE)
		{
			$query = ee()->db->get_where('forums', array('forum_id' => $forum_id));
			$row = $query->row_array();
		}
		else
		{
			$default_parent = ee()->input->get_post('parent_id');

			$query = ee()->db->get_where('forum_boards', array('board_id' => $this->board_id));
			$row = $query->row_array();

			foreach($query->row_array() as $key => $value)
			{
				if ($key == 'board_name')
				{
					continue;
				}

				$row[str_replace('board_', 'forum_', $key)] = $value;
			}

			$row['board_notify_moderators']  = 'n';
		}

		//  Build out the tables
		$P = array();

		foreach($data as $title => $cluster)
		{
			if ($is_forum == FALSE AND ! in_array($title, $cat_prefs))
			{
				continue;
			}

			foreach ($cluster as $item => $val)
			{
				// Skip category exceptions

				if (in_array($item, $item_exceptions) AND $is_forum == FALSE)
				{
					continue;
				}

				$default_value = (isset($query) AND is_object($query) AND isset($row[$item])) ? $row[$item] : '';

				$label = ($title == 'forum_preferences') ? $item : str_replace('forum_', 'pref_', $item);

				if ($is_forum == FALSE)
				{
					switch ($item)
					{
						case 'forum_name' 			: $label = 'forum_cat_name';
							break;
						case 'forum_description'	: $label = 'forum_cat_description';
							break;
						case 'forum_status'			: $label = 'forum_cat_status';
							break;
					}
				}

				$form = '';

				if ($val['0'] == 't')								// text input fields
				{
					$label = lang($label, $item);
					$form = form_input(array(
						'name'		=> $item,
						'id'		=> $item,
						'value'		=> set_value($item, $default_value),
						'maxlength'	=> $val['1'],
						'class'		=> 'field',
						'style'		=> 'width: 98%'
					));
				}
				elseif ($val['0'] == 'r')							// radio buttons
				{
					$label = lang($label);

					if ($default_value == '')
					{
						$default_value = 'n';
					}

					foreach ($val['1'] as $k => $v)
					{
						$form .= lang($v, $v).NBS;
						$form .= form_radio(array(
							'name'		=> $item,
							'id'		=> $v,
							'value'		=> $k,
							'checked'	=> ($k == $default_value)
						)).NBS.NBS.NBS;
					}
				}
				elseif ($val['0'] == 'd' || $val['0'] == 'f')		// drop-down menus
				{
					$label = lang($label, $item);

					if ($val['0'] == 'f')
					{
						if ($default_parent && $item == 'forum_parent')
						{
							$default_value = $default_parent;
						}

						$items = $this->$val['1']();
					}
					else
					{
						$items = array();

						foreach ($val['1'] as $k => $v)
						{
							if (isset($img_prots[$k]))
							{
								$items[$k] = $img_prots[$k];
							}
							else
							{
								$items[$k] = isset($this->fmt_options[$k]) ? $v : ee()->lang->line($v);
							}
						}
					}

					$form = form_dropdown($item, $items, $default_value);
				}
				elseif ($val['0'] == 'x')							// Textarea fields
				{
					$label = lang($label, $item);

					$form = form_textarea(array(
						'name'		=> $item,
						'id'		=> $item,
						'value'		=> set_value($item, $default_value),
						'rows'		=> (isset($val['1']['rows'])) ? $val['1']['rows'] : '20',
						'class'		=> 'field',
						'style'		=> 'width: 98%'
					));
				}

				$P[$title][$item] = array(
					'label'		=> $label,
					'field'		=> $form,
					'subtext'	=> ( ! isset($subtext[$item])) ? '' : BR.ee()->lang->line($subtext[$item])
				);
			}
		}


		// Define page title based on the request type

		$title = ($is_new === TRUE) ? 'forum_create' : 'forum_edit';
		$title = ($is_forum == TRUE) ? $title : $title.'_category';

		$this->_accordion_js();

		// Define breadcrumb based on the request type
	/*
		$crumb = array(
						ee()->lang->line('forum_manager') => $this->id_base.AMP.'method=forum_management',
						$title => ''
					  );
	*/
		return $this->_content_wrapper('forum_edit', $title, array(
																'P' => $P,
																'hidden' => $hidden,
																'button' => ($is_new) ? 'submit' : 'update'
																));
	}

	// --------------------------------------------------------------------

	/**
	 * Create Pull-down list of categories
	 *
	 * @access	private
	 * @return	void
	 */
	function _forum_fetch_categories()
	{
		ee()->db->select('forum_id, forum_name');
		ee()->db->where('board_id', $this->board_id);
		ee()->db->where('forum_is_cat', 'y');
		$query = ee()->db->get('forums');

		$values = array();

		foreach ($query->result_array() as $row)
		{
			$values[$row['forum_id']] = $row['forum_name'];
		}

		return $values;
	}

	// --------------------------------------------------------------------

	/**
	 * New/Update Forum Handler
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_update()
	{
		$forum_id = ee()->input->get_post('forum_id');
		unset($_POST['forum_id'], $_POST['update'], $_POST['submit']);

		if ( ! ee()->input->post('forum_name'))
		{
			show_error(ee()->lang->line('forum_missing_name'));
		}


		// Insert the new forum

		if ($forum_id === FALSE)
		{
			// Fetch the base permissions which we'll apply to the forum
			ee()->db->select('board_forum_permissions, board_use_deft_permissions');
			$query = ee()->db->get_where('forum_boards', array('board_id' => $this->board_id));

			$_POST['forum_permissions'] = ($query->row('board_forum_permissions')  != '' AND $query->row('board_use_deft_permissions')  == 'y') ? $query->row('board_forum_permissions')  : serialize($this->forum_set_base_permissions());
			$_POST['board_id'] = $this->board_id;

			// set some defaults for required fields
			$_POST['forum_topics_perpage'] = 25;
			$_POST['forum_posts_perpage'] = 15;
			$_POST['forum_hot_topic'] = 10;
			$_POST['forum_max_post_chars'] = 6000;

			ee()->db->insert('forums', $_POST);

			$this->_forum_update_order(ee()->db->insert_id(), (( ! isset($_POST['forum_parent'])) ? 0 : $_POST['forum_parent']));

			$message = (isset($_POST['forum_parent'])) ? 'forum_new_forum_added' : 'forum_new_cat_added';
		}
		else	// Update an existing forum
		{
			ee()->db->select('forum_parent');
			$query = ee()->db->get_where('forums', array('forum_id' => $forum_id));

			ee()->db->where('forum_id', $forum_id);
			ee()->db->update('forums', $_POST);

			if (isset($_POST['forum_parent']))
			{
				if ($query->row('forum_parent')  != $_POST['forum_parent'])
				{
					$this->_forum_update_order($forum_id, $_POST['forum_parent']);
				}
			}

			$message = (isset($_POST['forum_parent'])) ? 'forum_prefs_updated' : 'forum_cat_prefs_updated';

		}

		ee()->session->set_flashdata('message_success', ee()->lang->line($message));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
	}

	// --------------------------------------------------------------------

	/**
	 * Update order of forums
	 *
	 * @access	private
	 * @return	void
	 */
	function _forum_update_order($forum_id = 0, $forum_parent = 0, $insert_new = TRUE)
	{
		// Update category order

		// If the $forum_parent is zero we are dealing with a new
		// category so we'll just tack it onto the end.

		if ($forum_parent == 0 AND $insert_new == TRUE)
		{
			ee()->db->where('board_id', $this->board_id);
			$count = ee()->db->count_all_results('forums');

			ee()->db->where('forum_id', $forum_id);
			ee()->db->update('forums', array('forum_order' => $count));

			return;
		}


		// Re-order all the forums

		ee()->db->select('forum_id');
		ee()->db->where('board_id', $this->board_id);
		ee()->db->where('forum_is_cat', 'y');
		ee()->db->order_by('forum_order');
		$query = ee()->db->get('forums');

		$new_order = array();

		$used = FALSE;

		foreach ($query->result_array() as $row)
		{
			$new_order[] = $row['forum_id'];

			ee()->db->select('forum_id');
			ee()->db->where('forum_parent', $row['forum_id']);
			ee()->db->order_by('forum_order');

			if ($forum_parent > 0 AND $insert_new == TRUE AND $forum_id > 0)
			{
				ee()->db->where('forum_id !=', $forum_id);
			}

			$res = ee()->db->get('forums');


			if ($res->num_rows() > 0)
			{
				foreach ($res->result_array() as $row2)
				{
					$new_order[] = $row2['forum_id'];
				}
			}

			if ($insert_new == TRUE AND $forum_parent == $row['forum_id'] AND $used == FALSE)
			{
				$new_order[] = $forum_id;
				$used = TRUE;
			}
		}

		$i = 1;
		foreach ($new_order as $id)
		{
			ee()->db->where('forum_id', $id);
			ee()->db->update('forums', array('forum_order' => $i));
			$i++;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Forum Confirmation
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_delete_confirm()
	{
		$forum_id = ee()->input->get_post('forum_id');

		ee()->db->select('forum_name, forum_is_cat');
		$query = ee()->db->get_where('forums', array('forum_id' => $forum_id));

		$vars = array(
						'url'		=> 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=forum_delete',
						'heading'	=> ($query->row('forum_is_cat')  == 'n') ? 'forum_delete_confirm' : 'forum_delete_cat_confirm',
						'message'	=> ($query->row('forum_is_cat')  == 'n') ? 'forum_delete_msg' : 'forum_delete_cat_msg',
						'item'		=> $query->row('forum_name') ,
						'extra'		=> ($query->row('forum_is_cat')  == 'n') ? 'forum_delete_warning' : 'forum_delete_cat_warning',
						'hidden'	=> array('forum_is_cat' => $query->row('forum_is_cat') , 'forum_id' => $forum_id),
						'msg'		=> ee()->lang->line('forum_delete_confirm')
				);

		$title = ($query->row('forum_is_cat')  == 'n') ? ee()->lang->line('forum_delete_confirm') : ee()->lang->line('forum_delete_cat_confirm');

		$crumb = array(
						ee()->lang->line('forum_manager') => $this->id_base.AMP.'method=forum_management',
						$title => ''
					  );

		return $this->_content_wrapper('confirm', $title, $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Forum
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_delete()
	{
		$forum_id	= ee()->input->get_post('forum_id');
		$is_cat		= ee()->input->get_post('forum_is_cat');

		$del_ids[] = $forum_id;

		if ($is_cat == 'y')
		{
			ee()->db->select('forum_id');
			$query = ee()->db->get_where('forums', array('forum_parent' => $forum_id));

			if ($query->num_rows() > 0)
			{
				foreach ($query->result_array() as $row)
				{
					$del_ids[] = $row['forum_id'];
				}
			}
		}

		$topic_ids = array();

		foreach ($del_ids as $id)
		{
			// Fetch the topic IDs so we can delete any subscriptions

			ee()->db->select('topic_id');
			$t_query = ee()->db->get_where('forum_topics', array('forum_id' => $id));

			if ($t_query->num_rows() > 0)
			{
				foreach ($t_query->result_array() as $row)
				{
					$topic_ids[] = $row['topic_id'];
				}
			}

			// Kill everything!!!

			ee()->db->where('forum_id', $id);
			ee()->db->delete(array('forums', 'forum_topics', 'forum_posts'));
			ee()->db->delete('forum_moderators', array('mod_forum_id' => $id));
		}

		// Kill subscriptions, attachments, and poll votes

		if (count($topic_ids) > 0)
		{
			ee()->db->where_in('topic_id', $topic_ids);
			ee()->db->delete(array('forum_subscriptions', 'forum_attachments', 'forum_polls', 'forum_pollvotes'));
		}

		/** -------------------------------------
		/**  Recount member stats
		/** -------------------------------------*/

		$member_entries = array();
		ee()->db->select('COUNT(*) as count, author_id');
		ee()->db->group_by('author_id');
		ee()->db->order_by('count', 'desc');
		$forum_topics_count = ee()->db->get('forum_topics');

		ee()->db->select('COUNT(*) as count, author_id');
		ee()->db->group_by('author_id');
		ee()->db->order_by('count', 'desc');
		$forum_posts_count = ee()->db->get('forum_posts');

		if ($forum_topics_count->num_rows() > 0)
		{
			foreach($forum_topics_count->result() as $row)
			{
				$member_entries[$row->author_id]['member_id'] = $row->author_id;
				$member_entries[$row->author_id]['total_forum_topics'] = $row->count;
				$member_entries[$row->author_id]['total_forum_posts'] = 0;
			}
		}

		if ($forum_posts_count->num_rows() > 0)
		{
			foreach($forum_posts_count->result() as $row)
			{
				if (isset($member_entries[$row->author_id]['member_id']))
				{
					$member_entries[$row->author_id]['total_forum_topics'] = $row->count;
				}
				else
				{
					$member_entries[$row->author_id]['member_id'] = $row->author_id;
					$member_entries[$row->author_id]['total_forum_topics'] = 0;
					$member_entries[$row->author_id]['total_forum_posts'] = $row->count;
				}
			}
		}

		if (count($member_entries) > 0)
		{
			ee()->db->update_batch('exp_members', $member_entries, 'member_id');
		}

		/** -------------------------------------
		/**  Update global forum stats
		/** -------------------------------------*/

		ee()->db->select('forum_id');
		$query = ee()->db->get('forums');
		$total_topics = 0;
		$total_posts  = 0;

		foreach ($query->result_array() as $row)
		{
			ee()->db->where('forum_id', $row['forum_id']);
			$total_topics += ee()->db->count_all_results('forum_topics');

			ee()->db->where('forum_id', $row['forum_id']);
			$total_posts += ee()->db->count_all_results('forum_posts');
		}

		$d = array(
				'total_forum_topics'	=> $total_topics,
				'total_forum_posts'		=> $total_posts
			);

		ee()->db->update('stats', $d);

		// Optimize the tables just to be nice
		ee()->load->dbutil();
		ee()->dbutil->optimize_table('forums');
		ee()->dbutil->optimize_table('forum_topics');
		ee()->dbutil->optimize_table('forum_posts');
		ee()->dbutil->optimize_table('forum_subscriptions');
		ee()->dbutil->optimize_table('forum_attachments');

		$this->_forum_update_order(0,0,FALSE);

		ee()->session->set_flashdata('message', ee()->lang->line('forum_deleted'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Re-count Utility
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_resync()
	{
		$this->_forum_update_order(0,0,FALSE);

		ee()->session->set_flashdata('message', ee()->lang->line('forum_resynched'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
	}

	// --------------------------------------------------------------------

	/**
	 * Move a Forum!
	 *
	 * This function is invoked by clicking the
	 * "up" or "down" arrows in the forum manager.
	 *
	 * This code is no fun - you've been warned
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_move()
	{
  		// Define some initial values

		$forum_id  = ee()->input->get_post("forum_id");
		$direction = ee()->input->get_post("dir");

		ee()->db->where('board_id', $this->board_id);
		$total = ee()->db->count_all_results('forums');

		ee()->db->select('forum_order, forum_parent, forum_is_cat');
		$query = ee()->db->get_where('forums', array('forum_id' => $forum_id));

		$is_category = ($query->row('forum_is_cat')  == 'y') ? TRUE : FALSE;
		$parent_id = $query->row('forum_parent') ;

		$cur_position = $query->row('forum_order') ;
		$new_position = ($direction == 'up') ? ($cur_position - 1) : ($cur_position + 1);

		$min = ($cur_position == 1) ? 1 : ($cur_position == 2 AND $is_category == FALSE) ? 2 : ($cur_position - 1);
		$max = ($cur_position == $total) ? $total : ($total + 1);

  		// Do we even need to move the forum?

		// Possibly not...

		if (($direction == 'up' AND $cur_position == $min) OR
			($direction == 'dn' AND $cur_position == $max))
		{
			ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
		}


		// Are we moving a category?

		// If so, all we need to do is swap the order of the category directly
		// above (or below depending on direction) with the one being moved

		if ($is_category == TRUE)
		{
			// Build the query

			ee()->db->select('forum_id, forum_order');
			ee()->db->where('board_id', $this->board_id);
			ee()->db->where('forum_is_cat', 'y');
			ee()->db->where('forum_order '.(($direction == 'up') ? '<' : '>'), $cur_position);
			ee()->db->order_by('forum_order', ($direction == 'up') ? 'DESC' : 'ASC');
			ee()->db->limit('1');
			$result = ee()->db->get('forums');

			if ($result->num_rows() == 0)
			{
				ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
			}

			$temp_id	= $result->row('forum_id') ;
			$temp_pos	= $result->row('forum_order') ;

			// Swap the numbers...
			ee()->db->where('forum_id', $temp_id);
			ee()->db->update('forums', array('forum_order' => $cur_position));

			ee()->db->where('forum_id', $forum_id);
			ee()->db->update('forums', array('forum_order' => $temp_pos));

			// Now that we've made the swap, the order of the forums is messed up so we'll re-synchronize them
			$this->_forum_update_order(0, 0, FALSE);

			ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
		}

  		// Re-order the forum!

		// First we'll create an array with the correct order...

		ee()->db->select('forum_id');
		ee()->db->where('board_id', $this->board_id);
		ee()->db->where('forum_id !=', $forum_id);
		ee()->db->order_by('forum_order', 'ASC');
		$query = ee()->db->get('forums');

		$new_order = array();
		$flag = FALSE;
		$i = 1;
		foreach ($query->result_array() as $row)
		{
			if ($i == $new_position)
			{
				$new_order[] = $forum_id;
				$flag = TRUE;
			}

			$new_order[] = $row['forum_id'];
			$i++;
		}

		if ($flag == FALSE)
		{
			$new_order[] = $forum_id;
		}



		// Do we need to change the parent assignment?

		// If the top forum in a category gets moved up, or if the bottom forum
		// in a category gets moved down we need to re-assign its parent.
		// There are a couple different conditions that we have to test for, however,
		// so we'll build the query in pieces

		ee()->db->start_cache();
		ee()->db->select('forum_id, forum_parent, forum_is_cat');
		ee()->db->where('board_id', $this->board_id);
		ee()->db->where('forum_order '.(($direction == 'up') ? '<' : '>'), $cur_position);
		ee()->db->order_by('forum_order', ($direction == 'up') ? 'DESC' : 'ASC');
		ee()->db->limit('1');
		ee()->db->stop_cache();

		$query = ee()->db->get('forums');

		if ($query->num_rows() > 0)
		{
			if ($query->row('forum_id')  == $parent_id)
			{
				ee()->db->where('forum_id !=', $parent_id);
				$query = ee()->db->get('forums');
			}
		}

		ee()->db->flush_cache();


		if ($query->num_rows() > 0)
		{
			if ($query->row('forum_parent')  != $parent_id)
			{
				$new_parent = ($query->row('forum_is_cat')  == 'y') ? $query->row('forum_id')  : $query->row('forum_parent') ;

				$new_order  =  ($direction == 'up') ? 100 : 0;

				$d = array(
					'forum_parent'	=> $new_parent,
					'forum_order'	=> $new_order
				);
				ee()->db->where('forum_id', $forum_id);
				ee()->db->update('forums', $d);

				$this->_forum_update_order(0,0,FALSE);

				ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
			}
		}


		// Lastly we'll update each forum...

		$i = 1;
		foreach ($new_order as $id)
		{
			ee()->db->where('forum_id', $id);
			ee()->db->update('forums', array('forum_order' => $i));
			$i++;
		}

		// Back whence you came Binky!
		ee()->functions->redirect($this->id_base.AMP.'method=forum_management');
	}

	// --------------------------------------------------------------------

	/**
	 * Member Ranks Manager
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_ranks()
	{
		ee()->load->library('table');

		$this->show_nav = FALSE;

		ee()->db->order_by('rank_min_posts');
		$query = ee()->db->get('forum_ranks');

		$vars['ranks']	= $query->result_array();
		$vars['star']	= $this->prefs['board_theme_url'].$this->prefs['board_default_theme'].'/images/rank.gif';

		return $this->_content_wrapper('forum_ranks', 'forum_ranks', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Edit Member Ranks
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_edit_rank()
	{
		if ( ! $rank_id = ee()->input->get_post('rank_id'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->show_nav = FALSE;

		$query = ee()->db->get_where('forum_ranks', array('rank_id' => $rank_id));

		$vars['rank']	= $query->row_array();
		$vars['star']	= $this->prefs['board_theme_url'].$this->prefs['board_default_theme'].'/images/rank.gif';

		return $this->_content_wrapper('rank_form', 'forum_ranks', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Create/Update Member Rank
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_update_rank()
	{
		unset($_POST['submit']);

		// Error correction
		$required = array('rank_title', 'rank_min_posts');

		foreach ($required as $val)
		{
			if (ee()->input->post($val) == '')
			{
				show_error(ee()->lang->line('forum_missing_ranks'));
			}

			if ($val == 'rank_min_posts' OR $val == 'rank_stars')
			{
				$_POST[$val] = trim(str_replace(',', '', $_POST[$val]));

				if ( ! is_numeric(ee()->input->post($val)))
				{
					$_POST[$val] = 0;
				}
			}
		}

		// Are we updatting or inserting?
		if ( ! ee()->input->get_post('rank_id'))
		{
			ee()->db->insert('forum_ranks', $_POST);

			$msg = 'forum_rank_added';
		}
		else
		{
			ee()->db->where('rank_id', ee()->input->get_post('rank_id'));
			ee()->db->update('forum_ranks', $_POST);

			$msg = 'forum_rank_updated';
		}

		// Send Binky back whence Binky came...
		ee()->session->set_flashdata('message_success', ee()->lang->line($msg));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_ranks');
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Member Rank Confirmation
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_delete_rank_confirm()
	{
		$rank_id = ee()->input->get_post('rank_id');

		if ( ! $rank_id = ee()->input->get_post('rank_id'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		ee()->db->select('rank_title');
		$query = ee()->db->get_where('forum_ranks', array('rank_id' => $rank_id));

		$vars = array(
			'url'		=> 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=forum_delete_rank',
			'msg'		=> 'forum_delete_rank_msg',
			'item'		=> $query->row('rank_title'),
			'hidden'	=> array('rank_id' => $rank_id)
		);

		return $this->_content_wrapper('confirm', 'forum_delete_rank_confirm', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Member Rank
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_delete_rank()
	{
		$rank_id = ee()->input->get_post('rank_id');

		if ( ! $rank_id = ee()->input->post('rank_id'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		ee()->db->where('rank_id', $rank_id);
		ee()->db->delete('forum_ranks');

		ee()->session->set_flashdata('message_success', ee()->lang->line('forum_rank_deleted'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_ranks');
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Base Permissions
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_set_base_permissions()
	{
		if (is_null($this->UPD))
		{
			require_once PATH_ADDONS.'forum/upd.forum.php';

			$this->UPD = new Forum_upd();
		}

		return $this->UPD->forum_set_base_permissions();
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Permissions
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_permissions()
	{
		ee()->load->library('table');

		$forum_id = ee()->db->escape_str(ee()->input->get_post('forum_id'));
		$is_category = (ee()->input->get_post('is_cat') == 1) ? TRUE : FALSE;

  		// Fetch master permissions in case needed

		ee()->db->select('board_forum_permissions, board_use_deft_permissions');
		$query = ee()->db->get_where('forum_boards', array('board_id' => $this->board_id));

	 	$default_perms	= ($query->row('board_forum_permissions') != '') ? unserialize($query->row('board_forum_permissions')) : $this->forum_set_base_permissions();
		$use_default	= $query->row('board_use_deft_permissions');


		// Set local permissions

		$vars['permissions'] = $default_perms;

		if ($forum_id != 'global')
		{
			ee()->db->select('forum_name, forum_permissions');
			$query = ee()->db->get_where('forums', array('forum_id' => $forum_id));

			$vars['forum_name'] = $query->row('forum_name');
			$vars['permissions'] = ($query->row('forum_permissions')  == '') ? $default_perms : unserialize($query->row('forum_permissions'));
		}

		$vars['hidden'] = array(
			'forum_id'	=> $forum_id,
			'is_cat'	=> ($is_category === TRUE) ? 1 : 0
		);

		$vars['is_category'] = $is_category;
		$vars['forum_id'] = $forum_id;

		// Fetch Member Groups
		ee()->db->select('group_id, group_title');
		ee()->db->where('group_id !=', '1');
		ee()->db->where('site_id', ee()->config->item('site_id'));
		ee()->db->order_by('group_title');
		$query = ee()->db->get('member_groups');

		$vars['groups'] = array();

		foreach($query->result_array() as $row)
		{
			$group_name = $row['group_title'];

			if (in_array($group_name, $this->english))
			{
				$group_name = ee()->lang->line(strtolower(str_replace(" ", "_", $group_name)));
			}

			$group_name = str_replace(' ', NBS, $group_name);

			$checkboxes = array();

			if ($is_category === TRUE)
			{
				$checkboxes = array('can_view_forum', 'can_view_hidden');
			}
			else
			{
				$checkboxes = array('can_view_forum', 'can_view_hidden', 'can_view_topics', 'can_post_topics', 'can_post_reply', 'can_upload_files', 'can_report', 'can_search');
			}

			$fields = array();

			foreach($checkboxes as $name)
			{
				$fields[$name] = FALSE;

				if (in_array($name, array('can_post_topics', 'can_post_reply', 'can_upload_files', 'can_report')) &&
					$row['group_id'] > 1 && $row['group_id'] < 5)
				{
					$fields[$name] = '-';
				}
				elseif (isset($vars['permissions'][$name]))
				{
					$fields[$name] = (strpos($vars['permissions'][$name], '|'.$row['group_id'].'|') === FALSE) ? FALSE : TRUE;
				}
			}

			$vars['groups'][] = array(
				'group_id'		=> $row['group_id'],
				'group_name'	=> $group_name,
				'fields'		=> $fields
			);
		}

		if ($forum_id == 'global')
		{
			$vars['use_default'] = $use_default;
		}

		return $this->_content_wrapper('forum_permissions', 'forum_permissions', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Update Forum Permissions
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_update_permissions()
	{
		if ( ! ($forum_id = ee()->input->get_post('forum_id')))
		{
			return FALSE;
		}

		/** ------------------------------------
		/**  Define the permission array
		/** ------------------------------------*/

		$perms = array(
						'can_view_forum'		=> '',
						'can_view_hidden'		=> '',
						'can_view_topics'		=> '',
						'can_post_topics'		=> '',
						'can_post_reply'		=> '',
						'can_report'			=> '',
						'can_upload_files'		=> '',
						'can_search'			=> ''
						);

		/** ------------------------------------
		/**  Populate array with selected values
		/** ------------------------------------*/

		foreach ($_POST as $key => $val)
		{
			if (is_array($val))
			{
				foreach ($val as $k => $v)
				{
					if (isset($perms[$key]))
					{
						$perms[$key] .= '|'.$v;
					}
				}
			}
		}

		/** ------------------------------------
		/**  Add pipe to the end of items
		/** ------------------------------------*/
		foreach ($perms as $key => $val)
		{
			if ($val != '')
				$perms[$key] = $val.'|';
		}

		/** ------------------------------------
		/**  Update DB
		/** ------------------------------------*/

		// Two versions:
		if ($forum_id == 'global')
		{
			ee()->db->where('board_id', $this->board_id);

			$d = array(
					'board_forum_permissions'		=> serialize($perms),
					'board_use_deft_permissions'	=> ee()->input->get_post('board_use_deft_permissions')
				);

			ee()->db->update('forum_boards', $d);

			$msg = 'forum_deft_permissions_updated';
		}
		else
		{
			ee()->db->where('forum_id', $forum_id);
			ee()->db->update('forums', array('forum_permissions' => serialize($perms)));

			$msg = 'forum_permissions_updated';
		}

		ee()->session->set_flashdata('message_success', ee()->lang->line($msg));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_permissions'.AMP.'forum_id='.$forum_id.AMP.'is_cat='.ee()->input->get_post('is_cat'));
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Administrators
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_admins()
	{
		ee()->load->library('table');

		$this->_forum_username_picker();
		$this->_forum_type_switcher();

		// Fetch Member Group Names

		// Since an admin can be a member group we'll fetch the group names up-front

		ee()->db->select('group_id, group_title');
		ee()->db->where('site_id', ee()->config->item('site_id'));
		ee()->db->order_by('group_title');
		$query = ee()->db->get('member_groups');

		$vars['member_groups'] = array();
		$vars['admins'] = array();

		foreach ($query->result_array() as $row)
		{
			if (in_array($row['group_title'], $this->english))
			{
				$vars['member_groups'][$row['group_id']] = ee()->lang->line(strtolower(str_replace(" ", "_", $row['group_title'])));
			}
			else
			{
				$vars['member_groups'][$row['group_id']] = $row['group_title'];
			}
		}

		$groups = $vars['member_groups'];

		ee()->db->select('admin_id, admin_member_id, admin_group_id');
		$query = ee()->db->get_where('forum_administrators',
									array('board_id' => $this->board_id));

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $item)
			{
				if ($item['admin_group_id'] != 0)
				{
					if (isset($groups[$item['admin_group_id']]))
					{
						$vars['admins']['forum_group'][$item['admin_id']] = $groups[$item['admin_group_id']];
					}
				}
				else
				{
					ee()->db->select('screen_name');
					$result = ee()->db->get_where('members', array('member_id' => $item['admin_member_id']));

					$vars['admins']['forum_individual'][$item['admin_id']] = $result->row('screen_name');
				}
			}
		}

		return $this->_content_wrapper('forum_admins', 'forum_admins', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Create a new Admin
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_create_admin()
	{
		unset($_POST['submit']);

		$admin_type = ee()->input->get_post('admin_type');
		$admin_name	= ee()->input->get_post('admin_name');

		if (ee()->input->get_post('admin_group_id') == FALSE && $admin_name == '')
		{
			show_error(ee()->lang->line('forum_user_identifier_required'));
		}

		if ($admin_type == 'member' AND $admin_name != '')
		{
			ee()->db->select('member_id');
			$query = ee()->db->get_where('members', array('username' => $admin_name));

			if ($query->num_rows() != 1)
			{
				show_error(ee()->lang->line('forum_username_error'));
			}

			$_POST['admin_member_id']	= $query->row('member_id') ;
			$_POST['admin_group_id']	= 0;
		}
		else
		{
			$_POST['admin_member_id']	= 0;
		}

		unset($_POST['admin_name']);
		unset($_POST['admin_type']);

		$_POST['board_id'] = $this->board_id;

		ee()->db->insert('forum_administrators', $_POST);

		ee()->session->set_flashdata('message_success', ee()->lang->line('forum_admin_added'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_admins');
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Admin Confirmation
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_remove_admin_confirm()
	{
		$admin_name	= '';
		$admin_id	= ee()->db->escape_str(ee()->input->get_post('admin_id'));

		$query = ee()->db->get_where('forum_administrators', array('admin_id' => $admin_id));

		if ($query->num_rows() == 0)
		{
			return '';
		}

		if ($query->row('admin_member_id')  != 0)
		{
			ee()->db->select('screen_name');
			$result = ee()->db->get_where('members', array('member_id' => $query->row('admin_member_id')));

			$admin_name = $result->row('screen_name');
		}
		else
		{
			ee()->db->select('group_title');
			ee()->db->where('site_id', ee()->config->item('site_id'));
			$result = ee()->db->get_where('member_groups', array('group_id' => $query->row('admin_group_id')));

			$admin_name = $result->row('group_title');

			if (in_array($admin_name, $this->english))
			{
				$admin_name = ee()->lang->line(strtolower(str_replace(" ", "_", $admin_name)));
			}
		}

		$vars = array(
			'url'		=> 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=forum_remove_admin',
			'msg'		=> 'forum_remove_admin_msg',
			'item'		=> $admin_name,
			'hidden'	=> array('admin_id' => $admin_id)
		);

		return $this->_content_wrapper('confirm', 'forum_remove_admin_confirm', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Remove Admin
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_remove_admin()
	{
		$admin_id = ee()->db->escape_str(ee()->input->get_post('admin_id'));

		if ($admin_id == FALSE OR ! is_numeric($admin_id))
		{
			ee()->session->set_flashdata('message_failure', ee()->lang->line('invalid_admin_id'));
			ee()->functions->redirect($this->id_base.AMP.'method=forum_admins');
		}

		ee()->db->where('admin_id', $admin_id);
		ee()->db->delete('forum_administrators');

		ee()->session->set_flashdata('message_success', ee()->lang->line('admin_removed'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_admins');
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Moderators
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_moderators()
	{
		ee()->load->library('table');

		// Fetch Member Group Names

		// Since a moderator can be a member group we'll fetch the group names up-front

		ee()->db->select('group_id, group_title');
		ee()->db->where('site_id', ee()->config->item('site_id'));
		ee()->db->order_by('group_title');
		$query = ee()->db->get('member_groups');

		$groups = array();

		foreach ($query->result_array() as $row)
		{
			$group_name = $row['group_title'];

			if (in_array($group_name, $this->english))
			{
				$group_name = ee()->lang->line(strtolower(str_replace(" ", "_", $group_name)));
			}

			$groups[$row['group_id']] = $group_name;
		}

		$vars['groups'] = $groups;
		$vars['forums'] = array();

		//Fetch the Forums
		ee()->db->order_by('forum_order');
		$query = ee()->db->get_where('forums', array('board_id' => $this->board_id));

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$mods = array();

				if ($row['forum_is_cat'] != 'y')
				{
					ee()->db->select('mod_id, mod_member_id, mod_member_name, mod_group_id');
					$query = ee()->db->get_where('forum_moderators',
												array('mod_forum_id' => $row['forum_id'])
					);

					$mods = array();

					if ($query->num_rows() > 0)
					{
						foreach($query->result_array() as $item)
						{
							if (isset($groups[$item['mod_group_id']]))
							{
								$item['data'] = $groups[$item['mod_group_id']];
								$mods[] = $item;
							}
							elseif ($item['mod_member_id'] != 0)
							{
								$item['data'] = $item['mod_member_name'];
								$mods[] = $item;
							}
						}
					}
				}

				$row['mods'] = $mods;
				$vars['forums'][] = $row;
			}
		}

		return $this->_content_wrapper('forum_moderators', 'forum_moderators', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * New/Edit Moderator
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_edit_moderator()
	{
		ee()->load->library('table');

		$this->_forum_username_picker();
		$this->_forum_type_switcher();

		// Creating new?  Or editing existing?

		$is_new = (ee()->input->get_post('mod_id') == FALSE) ? TRUE : FALSE;

		$title = ($is_new == TRUE) ? 'forum_new_moderator' : 'forum_edit_moderator';

		$mod_name 		= '';
		$mod_id			= ee()->input->get_post('mod_id');
		$mod_forum_id	= ee()->input->get_post('forum_id');

		$mod_member_id 	= 0;
 		$mod_group_id  	= 0;


		// Assign default values
		$matrix = array(
							'mod_can_edit' 				=> 'y',
							'mod_can_move' 				=> 'y',
							'mod_can_split' 			=> 'y',
							'mod_can_merge' 			=> 'y',
							'mod_can_delete' 			=> 'n',
							'mod_can_change_status' 	=> 'n',
							'mod_can_announce' 			=> 'n',
							'mod_can_view_ip' 			=> 'n'
						);

		// If editing, fetch the moderator data
		if ($is_new == FALSE)
		{
			$query = ee()->db->get_where('forum_moderators', array('mod_id' => $mod_id));

			if ($query->num_rows() > 0)
			{
				foreach ($query->row_array() as $key => $val)
				{
					if (isset($matrix[$key]))
					{
						$matrix[$key] = $val;
					}
					else
					{
						$$key = $val;
					}
				}

				if ($query->row('mod_member_id')  != 0)
				{
					ee()->db->select('username');
					$result = ee()->db->get_where('members', array('member_id' => $query->row('mod_member_id')));

					$mod_name = $result->row('username');
				}
			}
		}

		// Get Parent Forum Info
		ee()->db->select('forum_name');
		$query = ee()->db->get_where('forums', array('forum_id' => $mod_forum_id));

		$vars['current_forum'] = $query->row_array();


		ee()->db->select('group_id, group_title');
		ee()->db->order_by('group_title');
		$query = ee()->db->get_where('member_groups', array('site_id' => ee()->config->item('site_id')));

		$groups = array();

		foreach ($query->result_array() as $row)
		{
			$group_name = $row['group_title'];

			if (in_array($group_name, $this->english))
			{
				$group_name = ee()->lang->line(strtolower(str_replace(" ", "_", $group_name)));
			}

			$groups[$row['group_id']] = $group_name;
		}


		$vars['member_groups'] = $groups;
		$vars['hidden'] = array('mod_forum_id' => $mod_forum_id);

		if ($is_new == FALSE)
		{
			$vars['hidden']['mod_id'] = ee()->input->get_post('mod_id');
		}

		foreach(array('mod_name', 'mod_forum_id', 'mod_group_id', 'is_new', 'matrix') as $var)
		{
			$vars[$var] = $$var;
		}

		return $this->_content_wrapper('add_edit_moderator', $title, $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Create/Update Moderator
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_update_moderator()
	{
		unset($_POST['submit']);

		$is_new = (ee()->input->get_post('mod_id') == FALSE) ? TRUE : FALSE;

		$mod_id			= ee()->input->get_post('mod_id');
		$mod_type		= ee()->input->get_post('mod_type');
		$mod_name		= ee()->input->get_post('mod_name');
		$mod_forum_id	= ee()->input->get_post('mod_forum_id');

		if (ee()->input->get_post('mod_group_id') == FALSE && $mod_name == '')
		{
			show_error(ee()->lang->line('forum_user_identifier_required'));
		}

		if ($mod_type == 'member' AND $mod_name != '')
		{
			ee()->db->select('member_id, screen_name');
			$query = ee()->db->get_where('members', array('username' => $mod_name));

			if ($query->num_rows() != 1)
			{
				show_error(ee()->lang->line('forum_username_error'));
			}

			$_POST['mod_member_id']		= $query->row('member_id');
			$_POST['mod_member_name']	= $query->row('screen_name');
			$_POST['mod_group_id']		= 0;
		}
		else
		{
			$_POST['mod_member_id']	= 0;
		}

		unset($_POST['mod_id']);
		unset($_POST['mod_name']);
		unset($_POST['mod_type']);


		if ($is_new == TRUE)
		{
			$_POST['board_id'] = $this->board_id;
			ee()->db->insert('forum_moderators', $_POST);
		}
		else
		{
			ee()->db->where('mod_id', $mod_id);
			ee()->db->update('forum_moderators', $_POST);
		}

		$message = ($is_new == TRUE) ? 'forum_moderator_added' : 'forum_moderator_updated';

		ee()->session->set_flashdata('message_success', ee()->lang->line($message));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_moderators');
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Moderator Confirmation
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_remove_moderator_confirm()
	{
		$mod_name	= '';
		$mod_id		= ee()->db->escape_str(ee()->input->get_post('mod_id'));

		ee()->db->select('mod_forum_id, mod_member_id, mod_member_name, mod_group_id');
		$query = ee()->db->get_where('forum_moderators', array('mod_id' => $mod_id));

		if ($query->num_rows() == 0)
		{
			return '';
		}

		ee()->db->select('forum_name');
		$result = ee()->db->get_where('forums', array('forum_id' => $query->row('mod_forum_id')));

		$forum_name = $result->row('forum_name');

		if ($query->row('mod_member_id')  != 0)
		{
			$mod_name = $query->row('mod_member_name');
		}
		else
		{
			ee()->db->select('group_title');
			ee()->db->where('site_id', ee()->config->item('site_id'));
			ee()->db->where('group_id', $query->row('mod_group_id'));
			$result = ee()->db->get('member_groups');

			$mod_name = $result->row('group_title') ;

			if (in_array($mod_name, $this->english))
			{
				$mod_name = ee()->lang->line(strtolower(str_replace(" ", "_", $mod_name)));
			}
		}

		$vars = array(
			'url'		=> 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=forum_remove_moderator',
			'msg'		=> 'forum_remove_moderator_msg',
			'item'		=> $mod_name.' ('.ee()->lang->line('in_forum').NBS.$forum_name.')',
			'hidden'	=> array('mod_id' => $mod_id)
		);

		return $this->_content_wrapper('confirm', 'forum_remove_moderator_confirm', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Remove Moderator
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_remove_moderator()
	{
		$mod_id = ee()->db->escape_str(ee()->input->get_post('mod_id'));

		if ($mod_id == FALSE OR ! is_numeric($mod_id))
		{
			ee()->session->set_flashdata('message_failure', ee()->lang->line('invalid_mod_id'));
			ee()->functions->redirect($this->id_base.AMP.'method=forum_moderators');
		}

		ee()->db->where('mod_id', $mod_id);
		ee()->db->delete('forum_moderators');

		ee()->session->set_flashdata('message_success', ee()->lang->line('moderator_removed'));
		ee()->functions->redirect($this->id_base.AMP.'method=forum_moderators');
	}

	// --------------------------------------------------------------------

	/**
	 * Perform lookup
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_user_do_lookup()
	{
		$name = ee()->input->get_post('name');

		if ($name == FALSE)
		{
			exit('{"error": "'.ee()->lang->line('forum_no_results').'"}');
		}

		$sql = "SELECT username, screen_name FROM exp_members WHERE ";

		if (ee()->input->get_post('filterby') == 'username')
		{
			$sql .= "(username = '".ee()->db->escape_str($name)."'
					OR username LIKE '".ee()->db->escape_like_str($name)."%'
					OR username LIKE '%".ee()->db->escape_like_str($name)."%') ";
		}
		else
		{
			$sql .= "(screen_name = '".ee()->db->escape_str($name)."'
					OR screen_name LIKE '".ee()->db->escape_like_str($name)."%'
					OR screen_name LIKE '%".ee()->db->escape_like_str($name)."%') ";
		}

		$sql .= "ORDER BY screen_name, username LIMIT 100";

		$query = ee()->db->query($sql);

		if ($query->num_rows() === 0)
		{
			ee()->output->send_ajax_response(
				array('error' => lang('forum_no_results'))
			);
		}
		elseif ($query->num_rows() > 99)
		{
			ee()->output->send_ajax_response(
				array('error' => lang('forum_toomany_results'))
			);
		}

		ee()->output->send_ajax_response($query->result_array());
	}

	// --------------------------------------------------------------------

	/**
	 * Preferences Manager
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_prefs($is_new = FALSE)
	{
		// Preferences Matrix

		$P = array(
			'general'	=> array(
							'board_label'	 			=> array('t', '150'),
							'board_name'	 			=> array('t', '50'),
							'board_forum_url' 			=> array('t', '150'),
							'board_site_id' 			=> array('f', '_forum_site_menu'),
							'board_forum_trigger' 		=> array('t', '70'),
							'board_enabled'				=> array('r', array('y' => 'yes', 'n' => 'no'))
						),

			'php'	=> array(
							'board_allow_php'			=> array('r', array('y' => 'yes', 'n' => 'no')),
							'board_php_stage'			=> array('r', array('i' => 'input', 'o' => 'output'))
						),

			'themes'	=> array(
							'board_default_theme'		=> array('f', '_forum_theme_menu')
						),

			'image'		=> array(
							'board_upload_path'			=> array('t', '150'),
							'board_attach_types'		=> array('r', array('img' => 'images_only', 'all' => 'all_files')),
							'board_max_attach_perpost'	=> array('t', '4'),
							'board_max_attach_size'		=> array('t', '6'),
							'board_max_width'			=> array('t', '5'),
							'board_max_height'			=> array('t', '5'),
							'board_use_img_thumbs'		=> array('r', array('y' => 'yes', 'n' => 'no')),
							'board_thumb_width'			=> array('t', '4'),
							'board_thumb_height'		=> array('t', '4')
						),

			'notification'	=> array(
							'board_notify_emails_topics'=> array('t', '255'),
							'board_notify_emails'		=> array('t', '255'),
						),

			'topics'	=> array(
							'board_topics_perpage'		=> array('t', '4'),
							'board_posts_perpage'		=> array('t', '4'),
							'board_topic_order'			=> array('d', array('d' => 'descending', 'a' => 'ascending', 'r' => 'most_recent_topic')),
							'board_post_order'			=> array('d', array('d' => 'descending', 'a' => 'ascending')),
							'board_hot_topic'			=> array('t', '4'),
							'board_max_post_chars'		=> array('t', '5'),
							'board_post_timelock'		=> array('t', '4'),
							'board_display_edit_date'	=> array('r', array('y' => 'yes', 'n' => 'no'))
						),

			'formatting'	=> array(
							'board_text_formatting'		=> array('d', $this->fmt_options),
							'board_html_formatting'		=> array('d', array('safe' => 'safe', 'none' => 'none', 'all' => 'all')),
							'board_auto_link_urls'		=> array('r', array('y' => 'yes', 'n' => 'no')),
							'board_allow_img_urls'		=> array('r', array('y' => 'yes', 'n' => 'no'))
						),

			'rss' => array(
							'board_enable_rss'			=> array('r', array('y' => 'yes', 'n' => 'no')),
							'board_use_http_auth'		=> array('r', array('y' => 'yes', 'n' => 'no'))
						)
					);


		$subtext = array(
							'board_name'					=> 'single_word_no_spaces',
							'board_forum_trigger'			=> 'pref_forum_trigger_notes',
							'board_upload_path' 			=> 'path_message',
							'board_image_lib_path'			=> 'path_lib_message',
							'board_use_img_thumbs'			=> 'will_show_in_pop',
							'board_post_timelock'			=> 'pref_post_timelock_more',
							'board_notify_emails'			=> 'pref_notify_emails_all',
							'board_notify_emails_topics'	=> 'pref_notify_emails_topics_all',
							'board_forum_enabled'			=> 'pref_forum_enabled_info'
						);

		if (ee()->config->item('multiple_sites_enabled') !== 'y')
		{
			unset($P['general']['board_site_id']);
		}

		$alias = 'n';

		if (ee()->input->get_post('alias') === 'y' OR $this->prefs['board_alias_id'] != '0')
		{
			$alias = 'y';

			$P = array('general' => $P['general']);
			$P['general']['board_alias_id'] = array('f', '_board_alias_menu');
		}

		/** ---------------------------------
		/**  Build the page heading
		/** ---------------------------------*/

		// If the forum was just installed we'll hide the navigation tabs
		// and show a special message.  That way users can't use the forum
		// until they update their preferences

		if ($is_new == TRUE)
		{
			$this->show_nav = FALSE;

			$this->prefs['board_label'] 	= '';
			$this->prefs['board_name'] 		= '';

			$this->prefs['board_upload_path'] = (@realpath('../images/forum_attachments/') !== FALSE) ? str_replace("\\", "/", realpath('../images/forum_attachments/')).'/' : './images/forum_attachments/';
		}

		// Create the Preferences Form

		$hidden = array();

		$hidden['board_id'] = ($is_new === TRUE) ? '' : $this->prefs['board_id'];
		$hidden['board_forum_permissions'] = $this->prefs['board_forum_permissions'];

		if (ee()->config->item('multiple_sites_enabled') !== 'y')
		{
			$hidden['board_site_id'] = 1;
		}

		$img_prots = array('gd' => 'GD', 'gd2' => 'GD2', 'imagemagick' => 'Image Magick', 'netpbm' => 'NetPBM');

		foreach ($P as $title => $menu)
		{
			// Preference Input Prep

			foreach ($menu as $item => $val)
			{
				$label = ( ! isset(ee()->lang->language[$item])) ? str_replace('board_', 'pref_', $item) : $item;
				$form = '';

				if ($val['0'] == 't') // text input fields
				{
					$label = lang($label, $item);
					$form = form_input(array(
						'name'		=> $item,
						'id'		=> $item,
						'value'		=> set_value($item, $this->prefs[$item]),
						'maxlength'	=> $val['1'],
						'class'		=> 'field',
						'style'		=> 'width: 98%'
					));
				}
				elseif ($val['0'] == 'r') // radio buttons
				{
					$label = '<strong>'.lang($label).'</strong>';

					foreach ($val['1'] as $k => $v)
					{
						$form .= lang($v, $v).NBS;
						$form .= form_radio(array(
							'name'		=> $item,
							'id'		=> $v,
							'value'		=> $k,
							'checked'	=> ($k == $this->prefs[$item])
						)).NBS.NBS.NBS;
					}
				}
				elseif ($val['0'] == 'd' || $val['0'] == 'f')		// drop-down menus
				{
					$label = lang($label, $item);

					if ($val['0'] == 'f')
					{
						$items = $this->$val['1']();
					}
					else
					{
						$items = array();

						foreach ($val['1'] as $k => $v)
						{
							if (isset($img_prots[$k]))
							{
								$items[$k] = $img_prots[$k];
							}
							else
							{
								$items[$k] = isset($this->fmt_options[$k]) ? $v : ee()->lang->line($v);
							}
						}
					}

					$form = form_dropdown($item, $items, $this->prefs[$item]);
				}

				$P[$title][$item] = array(
					'label'		=> $label,
					'field'		=> $form,
					'subtext'	=> ( ! isset($subtext[$item])) ? '' : BR.ee()->lang->line($subtext[$item])
				);
			}
		}

		$title = ($alias == 'y') ? 'forum_board_alias_prefs' : 'forum_board_prefs';
		$title = ($is_new === TRUE) ? 'new_'.$title : $title;

		$this->_accordion_js();

		return $this->_content_wrapper('forum_prefs', $title, array('P' => $P, 'hidden' => $hidden));
	}

	// --------------------------------------------------------------------

	/**
	 * Accordion Javascript
	 *
	 * @access	private
	 * @return	void
	 */
	function _accordion_js()
	{
		ee()->javascript->output('
			$(".editAccordion > div").hide();
			$(".editAccordion > h3").css("cursor", "pointer").addClass("collapsed").parent().addClass("collapsed");

			$(".editAccordion").css("borderTop", $(".editAccordion").css("borderBottom"));

			$(".editAccordion h3").click(function() {
				if ($(this).hasClass("collapsed")) {
					$(this).siblings().slideDown("fast");
					$(this).removeClass("collapsed").parent().removeClass("collapsed");
				}
				else {
					$(this).siblings().slideUp("fast");
					$(this).addClass("collapsed").parent().addClass("collapsed");
				}
			});

			$("#toggle_accordion").toggle(function() {
				$(".editAccordion h3").removeClass("collapsed").parent().removeClass("collapsed");
				$(".editAccordion > div").show();
			}, function() {
				$(".editAccordion h3").addClass("collapsed").parent().addClass("collapsed");
				$(".editAccordion > div").hide();
			});

			$(".editAccordion.open h3").each(function() {
				$(this).siblings().show();
				$(this).removeClass("collapsed").parent().removeClass("collapsed");
			});
		');
	}

	// --------------------------------------------------------------------

	/**
	 * Update Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_prefs_update()
	{
		unset($_POST['update']);

		// Error Trapping

		// Required Fields

		$required = array('board_forum_url', 'board_name', 'board_label');

		$error = array();

		foreach ($required as $val)
		{
			if (ee()->input->get_post($val) == '')
			{
				$error[] = ee()->lang->line($val);
			}
		}

		if (count($error) > 0)
		{
			$msg = '<strong>'.ee()->lang->line('forum_empty_fields').'</strong><br />';

			foreach ($error as $val)
			{
				$msg .= $val.'<br />';
			}

			show_error($msg);
		}

		// Add slashes if needed
		$slashes = array('board_forum_url', 'board_upload_path');

		foreach ($slashes as $val)
		{
			if (isset($_POST[$val]) && $_POST[$val] != '' && substr($_POST[$val], -1) != '/')
			{
				$_POST[$val] .= '/';
			}
		}

		// Validate Upload path

		if (isset($_POST['board_upload_path']) && $_POST['board_upload_path'] != '')
		{
			if ( ! @is_dir($_POST['board_upload_path']))
			{
				$msg  = '<strong>'.ee()->lang->line('invalid_upload_path').'</strong><br />';
				$msg .= $_POST['board_upload_path'];

				show_error($msg);
			}

			if ( ! is_really_writable($_POST['board_upload_path']))
			{
				$msg  = '<strong>'.ee()->lang->line('unwritable_upload_path').'</strong><br />';
				$msg .= $_POST['board_upload_path'];

				show_error($msg);
			}
		}

		// Validate Forum Name
		ee()->db->where('board_name', $_POST['board_name']);
		ee()->db->where('board_id !=', $_POST['board_id']);
		$count = ee()->db->count_all_results('forum_boards');

		if ($count > 0)
		{
			show_error(ee()->lang->line('forum_name_unavailable'));
		}

		if ( ! preg_match("#^[a-zA-Z0-9_\-/]+$#i", $_POST['board_name']))
		{
			show_error(ee()->lang->line('illegal_characters_shortname'));
		}

		// Validate Forum Trigger
		if ($_POST['board_forum_trigger'] != '')
		{
			ee()->db->where('group_name', $_POST['board_forum_trigger']);
			ee()->db->where('site_id !=', $_POST['board_site_id']);
			$count = ee()->db->count_all_results('template_groups');

			if ($count > 0)
			{
				show_error(ee()->lang->line('forum_trigger_unavailable'));
			}

			ee()->db->where('board_forum_trigger', $_POST['board_forum_trigger']);
			ee()->db->where('board_site_id =', $_POST['board_site_id']);
			ee()->db->where('board_id !=', $_POST['board_id']);
			$count = ee()->db->count_all_results('forum_boards');

			if ($count > 0)
			{
				show_error(ee()->lang->line('forum_trigger_taken'));
			}

			if ( ! preg_match("#^[a-zA-Z0-9_\-/]+$#i", $_POST['board_forum_trigger']))
			{
				show_error(ee()->lang->line('illegal_characters'));
			}
		}

		// Do we have a theme?
		if ( ! isset($_POST['board_default_theme']))
		{
			$_POST['board_default_theme'] = 'default';
		}

		// Do we have an install date?
		$page = AMP.'method=forum_prefs';

		if ($this->prefs['board_install_date'] < 1 OR $_POST['board_id'] == '')
		{
			$_POST['board_install_date'] = ee()->localize->now;

			$page = '';
		}

		// Some clean up
		if (isset($_POST['board_max_attach_size']))
		{
			$_POST['board_max_attach_size'] = str_replace('K', '', $_POST['board_max_attach_size']);
			$_POST['board_max_attach_size'] = str_replace('k', '', $_POST['board_max_attach_size']);
			$_POST['board_max_attach_size'] = str_replace('KB', '', $_POST['board_max_attach_size']);
			$_POST['board_max_attach_size'] = str_replace('kb', '', $_POST['board_max_attach_size']);
		}

		if (isset($_POST['board_max_width']))
		{
			$_POST['board_max_width'] = str_replace('px', '', $_POST['board_max_width']);
			$_POST['board_max_width'] = str_replace('PX', '', $_POST['board_max_width']);
		}

		if (isset($_POST['board_max_height']))
		{
			$_POST['board_max_height'] = str_replace('px', '', $_POST['board_max_height']);
			$_POST['board_max_height'] = str_replace('PX', '', $_POST['board_max_height']);
		}

		// Insert/Update the DB
		if ($_POST['board_id'] != '' && is_numeric($_POST['board_id']))
		{
			$board_id = ee()->input->post('board_id');

			ee()->db->where('board_id', $board_id);
			ee()->db->update('forum_boards', $_POST);
		}
		else
		{
			unset($_POST['board_id']);

			ee()->db->insert('forum_boards', $_POST);
			$board_id = ee()->db->insert_id();
		}

		// Create Specialty Templates, If Missing
		ee()->db->where('site_id', $_POST['board_site_id']);
		ee()->db->where('template_name', 'forum_post_notification');
		$count = ee()->db->count_all_results('specialty_templates');

		if ($count == 0)
		{
			require_once APPPATH.'language/'.ee()->config->item('deft_lang').'/email_data.php';

			$d = array(
					'site_id'			=> ee()->input->post('board_site_id'),
					'template_name'		=> 'admin_notify_forum_post',
					'template_type'		=> 'email',
					'template_subtype'	=> 'forums',
					'edit_date'			=> time(),
					'data_title'		=> addslashes(trim(admin_notify_forum_post_title())),
					'template_data'		=> addslashes(admin_notify_forum_post())
				);

			ee()->db->insert('specialty_templates', $d);

			$d = array(
					'site_id'			=> ee()->input->post('board_site_id'),
					'template_name'		=> 'forum_post_notification',
					'template_type'		=> 'email',
					'template_subtype'	=> 'forums',
					'edit_date'			=> time(),
					'data_title'		=> addslashes(trim(forum_post_notification_title())),
					'template_data'		=> addslashes(forum_post_notification())
				);

			ee()->db->insert('specialty_templates', $d);

			$d = array(
					'site_id'			=> ee()->input->post('board_site_id'),
					'template_name'		=> 'forum_moderation_notification',
					'template_type'		=> 'email',
					'template_subtype'	=> 'forums',
					'edit_date'			=> time(),
					'data_title'		=> addslashes(trim(forum_moderation_notification_title())),
					'template_data'		=> addslashes(forum_moderation_notification())
				);

			ee()->db->insert('specialty_templates', $d);

			$d = array(
					'site_id'			=> ee()->input->post('board_site_id'),
					'template_name'		=> 'forum_report_notification',
					'template_type'		=> 'email',
					'template_subtype'	=> 'forums',
					'edit_date'			=> time(),
					'data_title'		=> addslashes(trim(forum_report_notification_title())),
					'template_data'		=> addslashes(forum_report_notification())
				);

			ee()->db->insert('specialty_templates', $d);
		}

		// Update the Triggers
		$this->update_triggers();

		// Update the local forum prefs

		// If this is the first time the prefs are being updated it means that
		// we have a brand new forum installation.  In this case we need to update
		// the initial forum with these prefs.

		if ($board_id == 1)
		{
			$query_master = ee()->db->get_where('forum_boards', array('board_id' => $this->board_id));
			$query_slave = ee()->db->get_where('forums', array('forum_id' => '1'));

			$sql_array = array();
			$exceptions = array('forum_id');

			foreach ($query_slave->row_array() as $key => $val)
			{
				if (in_array($key, $exceptions))
				{
					continue;
				}

				if (isset($query_master->row[$key]))
				{
					$sql_array[$key] = $query_master->row[$key];
				}
			}

			if (count($sql_array) > 0)
			{
				ee()->db->query(ee()->db->update_string('exp_forums', $sql_array, 'forum_id=2'));
			}
		}

		if (isset($_POST['board_alias_id']))
		{
			ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=list_boards');
		}

		ee()->session->set_flashdata('message_success', ee()->lang->line('forum_prefs_updated'));
		ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'board_id='.$board_id.$page);
	}

	// --------------------------------------------------------------------

	/**
	 * Browse Forum Templates
	 *
	 * @access	public
	 * @return	void
	 */
	function forum_templates()
	{
		$this->show_nav = FALSE;

		$vars['templates'] = array(
			'files'		=> array(),
			'folders'	=> array(),
		);

		$path = ee()->input->get_post('folder') ? ee()->input->get_post('folder') : '';

		list($crumb, $path) = $this->_create_template_breadcrumb(PATH_THIRD_THEMES.'/forum_themes', $path);
		$full_path = PATH_THIRD_THEMES.'/forum/'.$path;

		if (count($crumb))
		{
			$theme_list = FALSE;

			$vars['theme'] = strtolower(current(current($crumb)));
			$vars['theme_name'] = strtolower(str_replace('_', ' ', $vars['theme']));

			array_unshift($crumb, array($this->base.AMP.'method=forum_templates' => ee()->lang->line('forum_templates')));
			$this->_add_crumb = $crumb;
		}
		else
		{
			$theme_list = TRUE;
		}

		ee()->load->helper('directory');

		foreach (directory_map($full_path, TRUE) as $file)
		{
			if (is_dir($full_path.'/'.$file))
			{
				if (strncasecmp($file, 'forum_', 6) == 0)
				{
					$vars['templates']['folders'][$path.'/'.$file] = ucwords(str_replace('_', ' ', substr($file, 6)));
				}
				elseif ($theme_list)
				{
					$vars['templates']['folders'][$path.'/'.$file] = ucwords(str_replace('_', ' ', $file));
				}

			}
			elseif (strpos($file, '.') !== FALSE)
			{
				$vars['templates']['files'][$path.'/'.$file] = ucwords(str_replace('_', ' ', substr($file, 0, -strlen(strrchr($file, '.')))));
			}
		}

		asort($vars['templates']['folders']);
		asort($vars['templates']['files']);

		return $this->_content_wrapper('forum_templates', 'forum_templates', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Edit Template
	 *
	 * @access	public
	 * @return	void
	 */
	function edit_template()
	{
		$this->show_nav = FALSE;
		$path = ee()->input->get_post('folder') ? ee()->input->get_post('folder') : '';
		$vars['theme_list'] = '';

		list($crumb, $path) = $this->_create_template_breadcrumb(PATH_THIRD_THEMES.'/forum_themes', $path);
		$full_path = PATH_THIRD_THEMES.'/forum/'.$path;

		if (count($crumb))
		{
			$theme_list = FALSE;

			$vars['template'] = strtolower(end(current($crumb)));

			array_unshift($crumb, array($this->base.AMP.'method=forum_templates' => ee()->lang->line('forum_templates')));
			$this->_add_crumb = $crumb;
			$vars['template'] = end(current($crumb));
		}

		ee()->load->helper('form');
		ee()->load->helper('file');

		// can't read file?
		if (($vars['template_data'] = read_file($full_path)) === FALSE)
		{
			$vars['templates'] = array();
			return $this->_content_wrapper('forum_templates', 'forum_templates', $vars);
		}

		ee()->cp->add_js_script('plugin', 'markitup');

		$markItUp = array(
			'nameSpace'	=> "html",
			'onShiftEnter'	=> array('keepDefault' => FALSE, 'replaceWith' => "<br />\n"),
			'onCtrlEnter'	=> array('keepDefault' => FALSE, 'openWith' => "\n<p>", 'closeWith' => "</p>\n")
		);

		/* -------------------------------------------
		/*	Hidden Configuration Variable
		/*	- allow_textarea_tabs => Preserve tabs in all textareas or disable completely
		/* -------------------------------------------*/

		if(ee()->config->item('allow_textarea_tabs') != 'n') {
			$markItUp['onTab'] = array('keepDefault' => FALSE, 'replaceWith' => "\t");
		}

		ee()->javascript->output('
			$("#template_data").markItUp('.json_encode($markItUp).');
		');


		$vars['not_writable'] = ! is_really_writable($full_path);
		$vars['path'] = $path;

		return $this->_content_wrapper('edit_template', 'edit_template', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Save Template
	 *
	 * @access	public
	 * @return	void
	 */
	function update_template()
	{
		if (($path = ee()->input->get_post('path')) === FALSE)
		{
			show_error(ee()->lang->line('invalid_template'));
		}

		list($crumb, $path) = $this->_create_template_breadcrumb(PATH_THIRD_THEMES.'/forum_themes', $path);
		$full_path = PATH_THIRD_THEMES.'/forum/'.$path;

		if ( ! file_exists($full_path))
		{
			show_error(ee()->lang->line('unable_to_find_template_file'));
		}

		ee()->load->helper('file');

		if ( ! write_file($full_path, ee()->input->get_post('template_data')))
		{
			show_error(ee()->lang->line('error_opening_template'));
		}

		// Clear cache files
		ee()->functions->clear_caching('all');

		if (ee()->input->get_post('update_and_return') === FALSE)
		{
			ee()->session->set_flashdata('message_f', ee()->lang->line('template_updated'));
			ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=edit_template'.AMP.'folder='.$path);
		}

		$up = substr($path, 0, strrpos($path, '/'));
		ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum'.AMP.'method=forum_templates'.AMP.'folder='.$up);
	}

	// --------------------------------------------------------------------

	/**
	 * Create Template Breadcrumb
	 *
	 * @access	private
	 * @return	void
	 */
	function _create_template_breadcrumb($abs_base_path, $rel_path)
	{
		$crumb = array();
		$abs_base_path = rtrim($abs_base_path, ' /');
		$rel_path = trim($rel_path, ' /');

		$parts = array();

		foreach(explode('/', $rel_path) as $key => $part)
		{
			// using sanitize_filename in this way allows us to catch directory traversal attempts
			// while still providing a relative path
			$new_val = ee()->security->sanitize_filename('/'.$part.'/');

			if ( ! $new_val)
			{
				continue;
			}

			$url = $this->base.AMP.'method=forum_templates'.AMP.'folder=';

			if (count($parts) == 0)
			{
				$crumb[] = array($url.$new_val => ucfirst(str_replace('_', ' ', $new_val)));
			}
			else
			{
				$crumb[] = array(key(end($crumb)).'/'.$new_val => ucfirst(str_replace('_', ' ', $new_val)));
			}

			$parts[] = $new_val;
		}

		$rel_path = implode('/', $parts);
		unset($parts);

		return array($crumb, $rel_path);
	}

	// --------------------------------------------------------------------

	/**
	 * Gets a list of the forum themes available
	 *
	 * @return array An associateive array of theme directories
	 */
	private function getForumThemes()
	{
		$themes = array();
		$path = PATH_THIRD_THEMES.'forum/';

		if ( ! $fp = @opendir($path))
		{
			return $themes;
		}

		while (FALSE !== ($folder = readdir($fp)))
		{
			if (@is_dir($path . $folder) && substr($folder, 0, 1) != '.')
			{
				$themes[$folder] = ucwords(str_replace("_", " ", $folder));
			}
		}

		closedir($fp);
		ksort($themes);

		return $themes;
	}

	// --------------------------------------------------------------------

	/**
	 * Boards Pull Down Menu
	 *
	 * @access	private
	 * @return	void
	 */
	function _board_alias_menu()
	{
		ee()->db->select('board_label, board_id');
		ee()->db->order_by('board_label');
		$query = ee()->db->get_where('forum_boards', array('board_alias_id' => '0'));

		$menu = array();
		foreach($query->result_array() as $row)
		{
			$menu[$row['board_id']] = $row['board_label'];
		}

		return $menu;
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Username Picker
	 *
	 * @access	private
	 * @return	void
	 */
	function _forum_username_picker()
	{
		ee()->javascript->output('
		(function() {
			var url = "'.str_replace(AMP, '&', $this->id_base).'&method=forum_user_do_lookup",
				username = $("#name", "#ajaxContent"),
				filter = $("#filterby", "#ajaxContent"),
				spinner = $("#spinner", "#ajaxContent"),
				results = $("#user_lookup_results", "#ajaxContent"),
				error = $("#member_search_error", "#ajaxContent"),
				result_body = results.find("tbody");

			do_search = function() {

				$.ajax({
					url: url,
					data: {"name": username.val(), "filterby": filter.val(), "XID": EE.XID},
					dataType: "json",
					beforeSend: function() {
						error.hide();
						results.hide();
						spinner.show();
					},
					success: function(res) {
						spinner.hide();

						if (res.constructor == Array) {
							var result_rows = "";

							for (var i=0; i < res.length; i++) {
								result_rows += "<tr style=\"cursor:pointer;\"><td>"+res[0]["screen_name"]+"</td><td>"+res[0]["username"]+"</td></tr>";
							}

							result_body.html(result_rows);
							results.show();
						}
						else {
							if (res.error) {
								error.html(res.error).show();
								username.get(0).select();
							}
						}
					}
				});
			}

			$("#ajaxContent").dialog({
				autoOpen: false,
				resizable: false,
				modal: true,
				position: "center",
				minHeight: "0px",
				buttons: {
					"'.ee()->lang->line('cancel').'": function() { $(this).dialog("close"); },
					"'.ee()->lang->line('submit').'": do_search
				}
			});

			result_body.find("tr").live("click", function() {
				$("#admin_name, #mod_name").val(this.childNodes[1].innerHTML);
				$("#ajaxContent").dialog("close");
			});

			username.keydown(function(evt) {
				evt = evt || window.event;
				if (evt.keyCode == 13) {
					do_search();
				}
			});

			$("#forum_user_lookup").click(function() {
				$("#ajaxContent").dialog("option", "title", "'.ee()->lang->line('forum_user_lookup').'");
				$("#ajaxContent").dialog("open");
				return false;
			});
		})();
		');
	}

	// --------------------------------------------------------------------

	/**
	 * Forum Type Switcher
	 *
	 * @access	private
	 * @return	void
	 */
	function _forum_type_switcher()
	{
		ee()->javascript->output('
			var lookup = $("#forum_user_lookup"),
				parent = lookup.closest("tr"),
				type = parent.find("select[name=mod_type], select[name=admin_type]"),
				username = parent.find("input[type=text]"),
				groups = parent.find("select[name=mod_group_id], select[name=admin_group_id]"),
				notice = parent.find("p.notice");

			toggle_type = function() {
				if (type.val() == "member") {
					groups.attr("disabled", true);
					username.attr("disabled", false);
					notice.css("color", "");
					lookup.show();
				}
				else {
					username.attr("disabled", true);
					groups.attr("disabled", false);
					notice.css("color", "#888");
					lookup.hide();
				}
			}

			type.change(toggle_type);
			toggle_type();
		');
	}


	/** ----------------------------------------
	/**  JavaScript Toggle Code for Permissions
	/** ----------------------------------------*/
	function forum_permissions_toggle()
	{
		ob_start();

		?>
		<script type="text/javascript">
		<!--

		function toggle(thebutton)
		{
			var set_row = (thebutton.name == 'set_row') ? true : false;

			var row_id = (set_row == true) ? thebutton.value : 0;

			var val = (thebutton.checked) ? true : false;

			var len = document.permissions.elements.length;

			for (var i = 0; i < len; i++)
			{
				var button = document.permissions.elements[i];

				var name_array = button.name.split("[");

				if (set_row == false)
				{
					if (name_array[0] == "can_" + thebutton.name)
					{
						button.checked = val;
					}
				}
				else
				{
					if (name_array[0] == 'can_view_forum' 		OR
						name_array[0] == 'can_view_hidden'		OR
						name_array[0] == 'can_view_topics'		OR
						name_array[0] == 'can_post_topics' 		OR
						name_array[0] == 'can_post_reply' 		OR
						name_array[0] == 'can_report'			OR
						name_array[0] == 'can_upload_files'		OR
						name_array[0] == 'can_search'
						)
					{
						if (button.value == row_id)
						{
							button.checked = val;
						}
					}
				}

			}
		}

		//-->
		</script>
		<?php

		$out = ob_get_contents();

		ob_end_clean();

		return $out;
	}

	// --------------------------------------------------------------------

	/**
	 * Store Trigger Word
	 *
	 * @access	private
	 * @return	void
	 */
	function update_triggers()
	{
		ee()->db->select('site_id');
		$query = ee()->db->get('sites');

		foreach($query->result_array() as $row)
		{
			ee()->db->select('board_forum_trigger');
			$tquery = ee()->db->get_where('forum_boards', array('board_site_id' => $row['site_id']));

			$triggers = array();

			foreach($tquery->result_array() as $trow)
			{
				$triggers[] = $trow['board_forum_trigger'];
			}

			ee()->db->select('site_system_preferences');
			$pquery = ee()->db->get_where('sites', array('site_id' => $row['site_id']));

			$prefs = unserialize(base64_decode($pquery->row('site_system_preferences')));

			$prefs['forum_trigger'] = implode('|', $triggers);

			$d = array(
					'site_system_preferences'	=> base64_encode(serialize($prefs))
				);
			ee()->db->where('site_id', $row['site_id']);
			ee()->db->update('sites', $d);
		}
	}

	// --------------------------------------------------------------------

}
// END CLASS


/* End of file mcp.forum.php */
/* Location: ./system/expressionengine/modules/forum/mcp.forum.php */
