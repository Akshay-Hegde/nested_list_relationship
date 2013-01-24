<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Streams Master Detail Relationships Field Type
 *
 * @package		CMS\Core\Modules\Streams Core\Field Types
 * @author		Ryan Thompson - AI Web Systems, Inc.
 * @copyright	Copyright (c) 2011 - 2012, AI Web Systems, Inc.
 * @license		http://
 * @link		http://
 */

class Field_Nested_list_relationship
{
	public $field_type_name			= 'Nested List Relationship';

	public $field_type_slug			= 'nested_list_relationship';
	
	public $db_col_type				= 'text';

	public $alt_process				= true;

	public $custom_parameters		= array('nested_list_stream', 'allow_disabled');

	public $version					= '1.1';

	public $author					= array('name'=>'AI Web Systems, Inc.', 'url'=>'http://aiwebsystems.com');

	// --------------------------------------------------------------------------

	/**
	 * Event
	 *
	 * Called before the form is built.
	 *
	 * @access	public
	 * @return	void
	 */
	public function event()
	{
		$this->CI->type->add_css('nested_list_relationship', 'nested_list_relationship.css');
	}

	// --------------------------------------------------------------------------

	/**
	 * Process before saving to database
	 *
	 * @access	public
	 * @param	string
	 * @param	obj
	 * @param	obj
	 * @param	int
	 * @return	void
	 */
	public function pre_save($input, $field, $stream, $id)
	{

		// Anything here?
		if ( ! isset($_POST['tree-item']) ) return '';

		// Get slug streams
		$nested_list_stream = $this->CI->streams_m->get_stream($field->field_data['nested_list_stream']);
		
		
		// Make a tablename
		$table_name = $stream->stream_prefix.$stream->stream_slug.'_'.$nested_list_stream->stream_slug;


		// Delete what we can (ignore disabled)
		$this->CI->db->delete($table_name, array($stream->stream_slug.'_id' => $id, 'disabled' => 0));
		
		// Build the entries
		foreach ( $_POST['tree-item'] as $master_detail_id )
		{
			
			// Nope, insert it
			$this->CI->db->insert($table_name, array(
				$stream->stream_slug.'_id' => $id,
			  	$nested_list_stream->stream_slug.'_id' => $master_detail_id,
			  	'disabled' => 0
				)
			);
		}

		// probably legacy stuff?
		/*
			// Build the cool value.
			$input = '*'.implode('*', $_POST['tree-item']).'*';

			// Save it manually cause alt fucks shit up
			$this->CI->db->update($table_name, array($field->field_slug => $input), array($stream->stream_slug.'_id' => $id));
		*/
	}

	// --------------------------------------------------------------------------

	/**
	 * Process before outputting to the backend
	 *
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	public function alt_pre_output($row_id, $extra, $type, $stream)
	{
		if ( ! $nested_list_stream = $this->CI->streams_m->get_stream($extra['nested_list_stream'])) return null;

		// -------------------------------------
		// Get the results from the table
		// -------------------------------------

		$q = $this->CI->db
				->select($nested_list_stream->stream_slug.'_id')
				->where($stream->stream_slug.'_id', $row_id)
				->get($stream->stream_prefix.$stream->stream_slug.'_'.$nested_list_stream->stream_slug)
				->result();
				
		// -------------------------------------
		// Create an array master_detail IDs found
		// -------------------------------------

		$nodes = array();
		$column = $nested_list_stream->stream_slug.'_id';

		foreach ($q as $node)
		{
			$nodes[] = $node->$column;
		}
		
		return $nodes;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Process for when adding field assignment
	 */
	public function field_assignment_construct($field, $stream)
	{
		$this->CI->load->dbforge();
				
		// Get the stream we are attaching to.
		$nested_list_stream = $this->CI->streams_m->get_stream($field->field_data['nested_list_stream']);
		
		// Make a tablename
		$table_name = $stream->stream_prefix.$stream->stream_slug.'_'.$nested_list_stream->stream_slug;

		$fields = array(
			$stream->stream_slug.'_id' => array(
				'type' => 'INT',
				'constraint' => 11
				),
			$nested_list_stream->stream_slug.'_id' => array(
				'type' => 'INT',
				'constraint' => 11
				),
			'disabled' => array(
				'type' => 'TINYINT',
				'constraint' => 1
				),
		);
		
		// Drop first in case there is old shit from the below TODO: issue.
		if ( $this->CI->db->table_exists($table_name) ) $this->CI->dbforge->drop_table($table_name);

		// Assign the fields
		$this->CI->dbforge->add_field($fields);

		// A little optimization
		$this->CI->dbforge->add_key($stream->stream_slug.'_id');
		$this->CI->dbforge->add_key($nested_list_stream->stream_slug.'_id');

		$this->CI->dbforge->create_table($table_name);

		// Make the entries unique. Last thing we need is two relationships to the same master_detail item
		$this->CI->db->query('ALTER TABLE `'.$this->CI->db->dbprefix($table_name).'` ADD UNIQUE INDEX `Unique Relationships` (`'.$stream->stream_slug.'_id'.'`, `'.$nested_list_stream->stream_slug.'_id'.'`)');

		return true;
	}

	// --------------------------------------------------------------------------

	/**
	 * Process for when removing field assignment
	 *
	 * @access	public
	 * @param	obj
	 * @param	obj
	 * @return	void
	 */
	public function field_assignment_destruct($field, $stream)
	{

		// Get the stream we are referring to.
		$nested_list_stream = $this->CI->streams_m->get_stream($field->field_data['nested_list_stream']);
		
		// @todo:
		// If the linked stream was already deleted, we have a bit
		// of a problem since we can't get the stream slug.
		// Until we figure that out, here's this:
		if ( ! $nested_list_stream OR ! isset($stream->stream_prefix) OR ! isset($stream->stream_slug)) return null;
				
		// Get the table name
		$table_name = $stream->stream_prefix.$stream->stream_slug.'_'.$nested_list_stream->stream_slug;
		
		// Remove the table		
		$this->CI->dbforge->drop_table($table_name);

		// Drop the column
		$this->CI->dbforge->drop_column($stream->stream_prefix.$stream->stream_slug, $field->field_slug);

		return true;
	}

	// --------------------------------------------------------------------------

	/**
	 * Entry delete
	 *
	 * @access	public
	 * @param	obj
	 * @param	obj
	 * @return	void
	 */
	public function entry_destruct($entry, $field, $stream)
	{
		// Delete the entries in our binding table
		$nested_list_stream = $this->CI->streams_m->get_stream($field->field_data['nested_list_stream']);
				
		// Get the table name
		$table_name = $stream->stream_prefix.$stream->stream_slug.'_'.$nested_list_stream->stream_slug;
		
		// Delete em
		$this->CI->db->where($stream->stream_slug.'_id', $entry->id)->delete($table_name);
	}

	// --------------------------------------------------------------------------

	/**
	 * Output form input
	 *
	 * @param	array
	 * @param	array
	 * @return	string
	 */
	public function form_output($data, $entry_id, $field)
	{

		// Get master detail stream
		$nested_list_stream = $this->CI->streams_m->get_stream($data['custom']['nested_list_stream']);

		if ( ! $nested_list_stream )
		{
			return '<em>'.$data['custom']['nested_list_stream'].' '.$this->CI->lang->line('streams.relationship.doesnt_exist').'</em>';
		}

		
		// Make a table
		$table_name = $field->stream_prefix.$field->stream_slug.'_'.$nested_list_stream->stream_slug;

		
		// Get the title column
		$data['title_column'] = $nested_list_stream->title_column;

		
		// Default to ID for title column
		if ( ! trim($data['title_column']) or !$this->CI->db->field_exists($data['title_column'], $nested_list_stream->stream_prefix.$nested_list_stream->stream_slug))
		{
			$data['title_column'] = 'id';
		}


		// Get the tree
		$data['tree'] = $this->_get_tree($nested_list_stream);

		
		// Asign info
		$data['entry_id'] = $entry_id;
		$data['field'] = $field;

		
		// Details for the list..
		$data['enabled'] = array();
		$data['disabled'] = array();
		$data['controller'] =& $this;

		
		// Populate options if there is an entry_id
		if ( is_numeric($entry_id) )
		{

			// Get the enabled ones
			$q = $this->CI->db
					->select($nested_list_stream->stream_slug.'_id')
					->where('disabled', 0)
					->where($field->stream_slug.'_id', $entry_id)
					->get($table_name)
					->result();

			$column = $nested_list_stream->stream_slug.'_id';

			// Add them
			foreach ( $q as $row )
			{
				$data['enabled'][] = $row->$column;
			}
		}

		// Grab the disabled ones too damnit..
		if ( $data['custom']['allow_disabled'] AND is_numeric($entry_id) )
		{

			// Aaand the disabled ones
			$q = $this->CI->db
					->select($nested_list_stream->stream_slug.'_id')
					->where('disabled', 1)
					->where($field->stream_slug.'_id', $entry_id)
					->get($table_name)
					->result();

			$column = $nested_list_stream->stream_slug.'_id';

			// Add them
			foreach ( $q as $row )
			{
				$data['disabled'][] = $row->$column;
			}
		}
		
		
		// Build that fucker
		return $this->CI->type->load_view('nested_list_relationship', 'tree_form', $data);
	}

	// --------------------------------------------------------------------------

	/**
	 * Get a list of streams to choose from for the master detail stream
	 *
	 * @access	public
	 * @return	string
	 */
	public function param_nested_list_stream($stream_id = false)
	{
		$choices = array();

		// Now get our streams and add them
		// under their namespace
		$streams = $this->CI->db->select('id, stream_name, stream_namespace')->get(STREAMS_TABLE)->result();
		
		foreach ($streams as $stream)
		{
			if ($stream->stream_namespace)
			{
				$choices[$stream->stream_namespace][$stream->id] = $stream->stream_name;
			}
		}
		
		return form_dropdown('nested_list_stream', $choices, $stream_id);
	}

	// --------------------------------------------------------------------------

	/**
	 * Get a list of streams to choose from for the master detail status stream
	 *
	 * @access	public
	 * @return	string
	 */
	public function param_allow_disabled($choice = 0)
	{		
		return form_dropdown('allow_disabled', array(0 => lang('global:disable'), 1 => lang('global:enable')), $choice);
	}

	// --------------------------------------------------------------------------

	/**
	 * Build a multi-array of parent > children.
	 *
	 * @author AI Web Systems, Inc. - Ryan Thompson
	 * @access public
	 * @return array
	 */
	public function _get_tree($stream)
	{
		$all_items = $this->CI->db
			 ->order_by('parent_id')
			 ->order_by('ordering_count')
			 ->get($stream->stream_prefix.$stream->stream_slug)
			 ->result_array();
		
		$items = array();
		$item_array = array();
		
		// we must reindex the array first
		foreach ($all_items as $item)
		{
			$items[$item['id']] = $item;
		}
		
		unset($all_items);
		
		// build a multidimensional array of parent > children
		foreach ($items as $item)
		{			
			if (array_key_exists($item['parent_id'], $items))
			{				
				// add this list to the children array of the parent list
				$items[$item['parent_id']]['children'][] =& $items[$item['id']];
			}
			
			if ($item['parent_id'] == 0)
			{
				$item_array[] =& $items[$item['id']];
			}
		}
		
		return $item_array;
	}

	// --------------------------------------------------------------------------

	/**
	 * Build the html for a nested list with selects in it.
	 *
	 * @access public
	 * @param array $item
	 */
	public function form_tree_builder($item, $enabled = array(), $disabled = array(), $title_column, $instance)
	{
		if (isset($item['children']))
		{
			foreach($item['children'] as $item)
			{
				
				echo '<li id="'.$item['id'].'" data-id="'.$item['id'].'">';
				
				echo '<div class="'.(in_array($item['id'], $disabled) ? 'disabled' : (in_array($item['id'], $enabled) ? 'green' : NULL)).'">';
					echo '<a href="#" rel="'.$item['id'].'" onclick="$(this).'.$instance.'(); return false;">' . $item[$title_column].'</a>';
					echo '<div class="hidden">';

						if(!in_array($item['id'], $disabled)):
							echo '<input type="checkbox" name="tree-item[]" value="'.$item['id'].'"'.(in_array($item['id'], $enabled) ? ' checked="checked"' : NULL).'" />';
						endif;
						
					echo '</div>';
				echo '</div>';

				if(isset($item['children']))
				{
					echo '<ul>';
						self::form_tree_builder($item, $enabled, $disabled, $title_column, $instance);
					echo '</ul>';
					echo '</li>';
				}
				else
				{
					echo '</li>';
				}
			}
		}
	}
}
