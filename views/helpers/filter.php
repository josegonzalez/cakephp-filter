<?php
class FilterHelper extends Helper {
	var $helpers = array('Form');

	function form($model, $fields = array()) {
		$output = '<tr id="filters">';
		$output .= $this->Form->create($model, array('action' => 'index', 'id' => 'filters'));

		if (!empty($fields)) {
			foreach ($fields as $field) {
				if (empty($field) || substr($field, -3, 3)=='_id') {
					$output .= '<th>&nbsp;</th>';
				} else {
					$opts = array('label' => false);
					switch ($this->Form->fieldset['fields']["$model.$field"]['type']) {
						case "text":
							$opts += array('type' => 'text');
							break;
					}
					$output .= '<th>' . $this->Form->input($field, $opts) . '</th>';
				}
			}
		}
		$output .= '<th>';
		$output .= $this->Form->button(__('Filter', true), array('type' => 'submit', 'name' => 'data[filter]'))
		$output .= $this->Form->button(__('Reset', true), array('type' => 'submit', 'name' => 'data[reset]'))
		$output .= '</th>';
		$output .= $this->Form->end();
		$output .= '</tr>';
		return $output;
	}
}
?>