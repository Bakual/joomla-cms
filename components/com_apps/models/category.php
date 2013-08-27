<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_contact
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * This models supports retrieving lists of contact categories.
 *
 * @package     Joomla.Site
 * @subpackage  com_apps
 * @since       1.6
 */
class AppsModelCategory extends JModelList
{
	/**
	 * Model context string.
	 *
	 * @var		string
	 */
	public $_context = 'com_apps.category';

	/**
	 * The category context (allows other extensions to derived from this model).
	 *
	 * @var		string
	 */
	protected $_extension = 'com_apps';

	private $_parent = null;

	private $_items = null;
	
	private $_remotedb = null;
	
	public $_extensionstitle = null;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since   1.6
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = JFactory::getApplication();
		$this->setState('filter.extension', $this->_extension);

		// Get the parent id if defined.
		$parentId = $app->input->getInt('id');
		$this->setState('filter.parentId', $parentId);

		$params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('filter.published',	1);
		$this->setState('filter.access',	true);
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id	A prefix for the store id.
	 *
	 * @return  string  A store id.
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id	.= ':'.$this->getState('filter.extension');
		$id	.= ':'.$this->getState('filter.published');
		$id	.= ':'.$this->getState('filter.access');
		$id	.= ':'.$this->getState('filter.parentId');

		return parent::getStoreId($id);
	}

	/**
	 * redefine the function an add some properties to make the styling more easy
	 *
	 * @return mixed An array of data items on success, false on failure.
	 */
	public function getItems()
	{
		if (!count($this->_items))
		{
			$app = JFactory::getApplication();
			$menu = $app->getMenu();
			$active = $menu->getActive();
			$params = new JRegistry;
			if ($active)
			{
				$params->loadString($active->params);
			}
			$options = array();
			$options['countItems'] = $params->get('show_cat_items_cat', 1) || !$params->get('show_empty_categories_cat', 0);
			$categories = JCategories::getInstance('Contact', $options);
			$this->_parent = $categories->get($this->getState('filter.parentId', 'root'));
			if (is_object($this->_parent))
			{
				$this->_items = $this->_parent->getChildren();
			} else {
				$this->_items = false;
			}
		}

		return $this->_items;
	}

	public function getParent()
	{
		if (!is_object($this->_parent))
		{
			$this->getItems();
		}
		return $this->_parent;
	}

	private function getRemoteDB() {
		if (!is_object($this->_remotedb)) {
			jimport('joomla.application.component.helper');
			$componentParams = JComponentHelper::getParams('com_apps');

			$fields = array('driver', 'host', 'user', 'password', 'database', 'port');
			$options = array();
		
			foreach ($fields as $field) {
				$options[$field] = $componentParams->get($field);
			}
		
			$this->_remotedb = JDatabaseDriver::getInstance( $options );
		}
		return $this->_remotedb;
	}
	
	public function getCategories()
	{
		// Get catid
		$input = new JInput;
		$catid = $input->get('id', null, 'int');

		// Get remote database
		$db = $this->getRemoteDB();
		
		// Form query
		$query = $db->getQuery(true);
		$query->select(
			array(
				'cat_id AS id',
				'cat_name AS name',
				'alias',
				'cat_desc AS description',
				'cat_parent AS parent',
			)
		);
		$query->from('jos_mt_cats');
		$query->where(
			array(
				'cat_published = 1',
				'cat_approved = 1',
			)
		);
		$query->order('cat_parent, cat_name ASC');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		
		// Properties to be populated
		$properties = array('id', 'name', 'alias', 'description', 'parent');
		
		// Array to collect children categories
		$children = array();
		
		// References to category objects
		$refs = array();
		
		// Array to collect active categories
		$active = array($catid);
		
		// Array to be returned
		$categories = array();
		foreach ($items as $item)
		{
			// Skip root category
			if (trim(strtolower($item->name)) == 'root')
			{
				continue;
			}
			
			// Base array is default parent for all categories
			$parent =& $categories;
			
			// Create empty array to populate with parent category's children
			if ($item->parent and !array_key_exists($item->parent, $children))
			{
				$children[$item->parent] = array();
			}
			
			// Change value of parent linking to children array
			if ($item->parent)
			{
				$parent =& $children[$item->parent];
			}
			
			// Populate category with values
			$parent[$item->id] = new stdclass;
			$parent[$item->id]->active = false;
			foreach ($properties as $p)
			{
				$parent[$item->id]->{$p} = $item->{$p};
			}
			
			// Create empty array for current category's own children
			if (!array_key_exists($item->id, $children))
			{
				$children[$item->id] = array();
			}
			$parent[$item->id]->children =& $children[$item->id];
			$refs[$item->id] =& $parent[$item->id];
			if (in_array($item->id, $active))
			{
				$parent[$item->id]->active = true;
				$id = $item->id;
				do
				{
					if (!array_key_exists($id, $refs)) {
						break;
					}
					$par = $refs[$id]->parent;
					$active[] = $par;
					if (array_key_exists($par, $refs)) {
						$refs[$par]->active = true;
						$id = $par;
					}
					else {
						break;
					}
				} while ($id);
			}
		}

		return $categories;
	}

	public function getExtensions()
	{
		// Get catid, search filter, order column, order direction
		$componentParams 	= JComponentHelper::getParams('com_apps');
		$default_limit		= $componentParams->get('default_limit', 8);
		$input 				= new JInput;
		$catid 				= $input->get('id', null, 'int');
		$limitstart 		= $input->get('limitstart', 0, 'int');
		$limit 				= $input->get('limit', $default_limit, 'int');
		$search 			= $input->get('filter_search', null, 'string');
		$orderCol 			= $this->state->get('list.ordering', 't2.link_rating');
		$orderDirn 			= $this->state->get('list.direction', 'DESC');
		$order 				= $orderCol.' '.$orderDirn;
		
		// Get remote database
		$db = $this->getRemoteDB();
		
		// Get category name
		if ($catid) {
			$query = $db->getQuery(true);
			$query->select('cat_name');
			$query->from('jos_mt_cats');
			$query->where('cat_id = ' . (int)$catid);
			$db->setQuery($query);
			$catname = $db->loadResult();
			if ($catname) {
				$this->_extensionstitle = $catname;
			}
		}
		
		// Form query
		$query = $db->getQuery(true);
		$query->select(
			array(
				't2.link_id AS id',
				't2.link_name AS name',
				't2.alias AS alias',
				't2.link_desc AS description',
				't2.link_rating AS rating',
				't2.user_id AS user_id',
				't3.filename AS image',
				't1.cat_id AS cat_id',
				'CONCAT("{", GROUP_CONCAT("\"", t5.caption, "\":\"", t4.value, "\""), "\"}") AS options',
			)
		);


		$where = array();

		// Select by specific category id or search filter
		$query->from('jos_mt_cl AS t1');
		$query->join('LEFT', 'jos_mt_links AS t2 ON t1.link_id = t2.link_id');
		if (!$order) {
			$order = 't2.link_rating DESC';
		}

		$query->join('LEFT', 'jos_mt_images AS t3 ON t3.link_id = t2.link_id');
		$query->join('LEFT', 'jos_mt_cfvalues AS t4 ON t2.link_id = t4.link_id');
		$query->join('LEFT', 'jos_mt_customfields AS t5 ON t4.cf_id = t5.cf_id');

		if ($catid) {
			$where[] = 't1.cat_id = ' . (int)$catid;
		}

		if ($search) {
			$where[] = '(t2.link_name LIKE(' . $db->quote('%'.$search.'%') . ') OR t2.link_desc LIKE(' . $db->quote('%'.$search.'%') . '))';
		}
		
		$where = array_merge($where, array(
			't2.link_published = 1',
			't2.link_approved = 1',
			'(t2.publish_up <= NOW() OR t2.publish_up = "0000-00-00 00:00:00")',
			'(t2.publish_down >= NOW() OR t2.publish_down = "0000-00-00 00:00:00")',
		));

		$query->where($where);
		$query->order($order);
		$query->group('t2.link_id');
		$db->setQuery($query, $limitstart, $limit);
		$items = $db->loadObjectList();
		
		// Get CDN URL
		$cdn = preg_replace('#/$#', '', trim($componentParams->get('cdn'))) . "/";
		
		// Populate array
		$extensions = array();
		foreach ($items as $item) {
			$options = new JRegistry($item->options);
			$data = new stdclass;
			$data->id = $item->id;
			$data->cat_id = $item->cat_id;
			$data->name = $item->name;
			$data->rating = $item->rating;
			$data->image = $cdn . $item->image;
			$data->user = $options->get('Developer Name');
			$data->tags = $options->get('Extension Includes');
			$data->compatibility = $options->get('Compatibility');
			$data->version = $options->get('Version');
			$data->downloadurl = $options->get('Link for download/registration/purchase: URL');
			$data->type = $options->get('Extension Apps* download type');
			$extensions[] = $data;
		}
		
		return $extensions;
		
	}

}
