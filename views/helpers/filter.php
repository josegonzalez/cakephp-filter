<?php
class FilterHelper extends Helper {
	var $helpers = array('Form');

	function form($model, $fields = array()) {
		$output = '<tr id="filters">';
		$output .= $this->Form->create($model, array('action' => 'index', 'id' => 'filters'));

		if (!empty($fields)) {
			$cakeVersion = substr(Configure::read('Cake.version'), 0, 3);
			if ($cakeVersion === '1.2') {
				$output .= $this->_form12($model, $fields);
			} else if ($cakeVersion === '1.3') {
				$output .= $this->_form13($model, $fields);
			}
		}
		$output .= '<th>';
		$output .= $this->Form->button(__('Filter', true), array('type' => 'submit', 'name' => 'data[filter]'));
		$output .= $this->Form->button(__('Reset', true), array('type' => 'submit', 'name' => 'data[reset]'));
		$output .= '</th>';
		$output .= $this->Form->end();
		$output .= '</tr>';
		return $output;
	}

	function _form12($model, $fields) {
		$output = '';
		foreach ($fields as $field) {
			if (empty($field)) {
				$output .= '<th>&nbsp;</th>';
			} else {
				$opts = array('label' => false);
				switch ($this->Form->fieldset['fields']["{$model}.{$field}"]['type']) {
					case "text":
						$opts += array('type' => 'text');
						break;
				}
				$output .= '<th>' . $this->Form->input($field, $opts) . '</th>';
			}
		}
		return $output;
	}

	function _form13($model, $fields) {
		$output = '';
		foreach ($fields as $field) {
			if (empty($field)) {
				$output .= '<th>&nbsp;</th>';
			} else {
				$opts = array('label' => false);
				switch ($this->Form->fieldset[$model]['fields'][$field]['type']) {
					case "text":
						$opts += array('type' => 'text');
						break;
				}
				$output .= '<th>' . $this->Form->input($field, $opts) . '</th>';
			}
		}
		return $output;
	}

}
