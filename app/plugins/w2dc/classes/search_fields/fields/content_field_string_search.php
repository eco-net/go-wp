<?php 

class w2dc_content_field_string_search extends w2dc_content_field_search {
	public $search_input_mode = 'keywords';
	
	public function searchConfigure() {
		global $wpdb, $w2dc_instance;
	
		if (w2dc_getValue($_POST, 'submit') && wp_verify_nonce($_POST['w2dc_configure_content_fields_nonce'], W2DC_PATH)) {
			$validation = new w2dc_form_validation();
			$validation->set_rules('search_input_mode', __('Search input mode', 'W2DC'), 'required');
			if ($validation->run()) {
				$result = $validation->result_array();
				if ($wpdb->update($wpdb->w2dc_content_fields, array('search_options' => serialize(array('search_input_mode' => $result['search_input_mode']))), array('id' => $this->content_field->id), null, array('%d')))
					w2dc_addMessage(__('Search field configuration was updated successfully!', 'W2DC'));
	
				$w2dc_instance->content_fields_manager->showContentFieldsTable();
			} else {
				$this->search_input_mode = $validation->result_array('search_input_mode');
				w2dc_addMessage($validation->error_array(), 'error');
	
				w2dc_renderTemplate('search_fields/fields/string_textarea_configuration.tpl.php', array('search_field' => $this));
			}
		} else
			w2dc_renderTemplate('search_fields/fields/string_textarea_configuration.tpl.php', array('search_field' => $this));
	}
	
	public function buildSearchOptions() {
		if (isset($this->content_field->search_options['search_input_mode']))
			$this->search_input_mode = $this->content_field->search_options['search_input_mode'];
	}

	public function renderSearch($search_form_id, $columns = 2, $defaults = array()) {
		if ($this->search_input_mode == 'input') {
			if (is_null($this->value) && isset($defaults['field_' . $this->content_field->slug])) {
				$this->value = $defaults['field_' . $this->content_field->slug];
			}
			
			w2dc_renderTemplate('search_fields/fields/string_textarea_input.tpl.php', array('search_field' => $this, 'columns' => $columns, 'search_form_id' => $search_form_id));
		}
	}
	
	public function validateSearch(&$args, $defaults = array(), $include_GET_params = true) {
		if ($this->search_input_mode == 'input') {
			$field_index = 'field_' . $this->content_field->slug;
	
			if ($include_GET_params)
				$this->value = ((w2dc_getValue($_REQUEST, $field_index, false) !== false) ? w2dc_getValue($_REQUEST, $field_index) : w2dc_getValue($defaults, $field_index));
			else
				$this->value = w2dc_getValue($defaults, $field_index, false);
	
			if ($this->value !== false && $this->value !== "") {
				$args['meta_query']['relation'] = 'AND';
				$args['meta_query'][] = array(
						'key' => '_content_field_' . $this->content_field->id,
						'value' => stripslashes($this->value),
						'compare' => 'LIKE'
				);
			}
		} elseif ($this->search_input_mode == 'keywords' && $this->content_field->on_search_form) {
			if (!empty($args['s'])) {
				$this->value = $args['s'];
	
				//var_dump($include_GET_params);
				//if (!has_filter('posts_clauses', array($this, 'postsClauses'))) {
					//var_dump(11);
					add_filter('posts_clauses', array($this, 'postsClauses'), 11, 2);
				//}
			}
		}
	}
	
	public function postsClauses($clauses, $q) {
		global $wpdb;

		$postmeta_table = 'w2dc_postmeta_' . $this->content_field->id;
		
		if ($this->value && strpos($clauses['join'], $postmeta_table) === false) { 
			$clauses['join'] .=' LEFT JOIN '.$wpdb->postmeta. ' AS ' . $postmeta_table . ' ON '. $wpdb->posts . '.ID = ' . $postmeta_table . '.post_id ';
			
			$postmeta_where = ' AND ' . $postmeta_table . '.meta_key = "_content_field_' . $this->content_field->id . '" ';
			
			$clauses['where'] = preg_replace(
					"/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
					"(".$wpdb->posts.".post_title LIKE $1) OR (".$postmeta_table.".meta_value LIKE $1 ".$postmeta_where.")", $clauses['where']);
		}
		
		return $clauses;
	}
	
	public function getVCParams() {
		return array(
				array(
						'type' => 'textfield',
						'param_name' => 'field_' . $this->content_field->slug,
						'heading' => $this->content_field->name,
				),
		);
	}
}
?>