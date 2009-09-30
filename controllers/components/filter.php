<?php
/**
 * Filter component
 *
 * @original concept by Nik Chankov - http://nik.chankov.net
 * @modified and extended by Maciej Grajcarek - http://blog.uplevel.pl
 * @modified again by James Fairhurst - http://www.jamesfairhurst.co.uk
 * @modified yet again by Jose Diaz-Gonzalez - http://josediazgonzalez.com
 * @modified further by Jeffrey Marvin - http://blitztiger.com
 * @incoroporating changes made by 'mcurry' - http://github.com/mcurry/
 * @version 0.5
 * @author Jeffrey Marvin <support@blitztiger.com>
 * @license	http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package	app
 * @subpackage app.controller.components
 */
class FilterComponent extends Object {
	/**
	 * fields which will replace the regular syntax in where i.e. field = 'value'
	 */
	var $fieldFormatting = array(
		"string"	=> "LIKE '%%%s%%'",
		"text"		=> "LIKE '%%%s%%'",
		"datetime"	=> "LIKE '%%%s%%'"
	);

	/**
	 * Paginator params sent in URL
	 */
	var $paginatorParams = array(
		'page',
		'sort',
		'direction'
	);

	/**
	 *  Url variable used in paginate helper (array('url'=>$url));
	 */
	 var $url = '';

	/**
	 * Used to tell whether the data options have been parsed
	 */
	var $parsed = false;
	
	/**
	 * Used to tell whether to redirect so the url includes filter data
	 */
	var $redirect = false;
	
	/**
	 * Used to tell whether time should be used in the filtering
	 */
	var $useTime = false;

	// class variables
	var $filter = array();
	var $formOptionsDatetime = array();
	var $filterOptions = array();

	/**
	 * Before any Controller action
	 * @param array settings['actions']  the action the filter is to be applied to, 
	 * @param array settings['redirect'] is whether after filtering is completed it should redirect and put the filters in the url
	 */
	function initialize(&$controller, $settings = array()) {
		// for index actions
		if (!isset($settings['actions']) || empty($settings['actions'])) {
			$actions = array('index');
		} else {
			$actions = $settings['actions'];
		}
		if (!isset($settings['redirect']) || empty($settings['redirect'])) {
			$this->redirect = false;
		} else {
			$this->redirect = $settings['redirect'];
		}
		if (!isset($settings['useTime']) || empty($settings['useTime'])) {
			$this->useTime = false;
		} else {
			$this->useTime = $settings['useTime'];
		}
		foreach($actions as $action){
			$this->processAction($controller, $action);
		}
	}
	
	function processAction($controller, $controllerAction){
		if($controller->action == $controllerAction) {
			// setup filter component
			$this->filter = $this->processFilters($controller);
			$url = $this->url;
			if(empty($url)) {
				$url = '/';
			}
			$this->filterOptions = array('url' => array($url));
			// setup default datetime filter option
			$this->formOptionsDatetime = array('type' => 'date', 'dateFormat' => 'DMY', 'empty' => '-', 'minYear' => date("Y")-2, 'maxYear' => date("Y"));
			if(isset($controller->data['reset']) || isset($controller->data['cancel'])) {
				$this->filter = array();
				$this->url = '/';
				$this->filterOptions = array();
				$controller->redirect('/' . $controller->name . '/index/');
			}
		}
	}

	/**
	 * Builds up a selected datetime for the form helper
	 * @param string $fieldname
	 * @return null|string
	 */
	function processDatetime($fieldname) {
		$selected = null;
		if(isset($this->params['named'][$fieldname])) {
			$exploded = explode('-', $this->params['named'][$fieldname]);
			if(!empty($exploded)) {
				$selected = '';
				foreach($exploded as $k => $e) {
					if(empty($e)) {
						$selected .= (($k == 0) ? '0000' : '00');
					} else {
						$selected .= $e;
					}
					if($k != 2) {$selected .= '-';}
				}
			}
		}
		return $selected;
	}

	/**
	 * Function which will change controller->data array
	 * @param object $controller the class of the controller which call this component
	 * @param array $whiteList contains list of allowed filter attributes
	 * @access public
	 */
	function processFilters($controller, $whiteList = null){
		$controller = $this->_prepareFilter($controller);
		$ret = array();
		if(isset($controller->data)){
			// loop models
			foreach($controller->data as $key => $value) {
				// get fieldnames from database of model
				$columns = array();
				if(isset($controller->{$key})) {
					$columns = $controller->{$key}->getColumnTypes();
				} elseif (isset($controller->{$controller->modelClass}->belongsTo[$key])) {
					$columns = $controller->{$controller->modelClass}->{$key}->getColumnTypes();
				} elseif (isset($controller->{$controller->modelClass}->hasOne[$key])) {
					$columns = $controller->{$controller->modelClass}->{$key}->getColumnTypes();
				}
				// if columns exist
				if(!empty($columns)) {
					// loop through filter data
					foreach($value as $k => $v) {
						// JF: deal with datetime filter
						if(is_array($v) && $columns[$k] == 'datetime') {
							$v = $this->_prepare_datetime($v);
						}
						// if filter value has been entered
						if($v != '') {
							// if filter is in whitelist
							if(is_array($whiteList) && !in_array($k, $whiteList) ){
								continue;
							}
							// check if there are some fieldFormatting set
							if(isset($this->fieldFormatting[$columns[$k]])) {
								// insert value into fieldFormatting
								$tmp = sprintf($this->fieldFormatting[$columns[$k]], $v);
								// don't put key.fieldname as array key if a LIKE clause
								if (substr($tmp, 0, 4) == 'LIKE') {
									$ret[] = $key . '.' . $k . " " . $tmp;
								} else {
									$ret[$key . '.' . $k] = $tmp;
								}
							} else {
								// build up where clause with field and value
								$ret[$key . '.' . $k] = $v;
							}
							// save the filter data for the url
							$this->url .= '/'. $key . '.' . $k . ':' . $v;
						}
					}
					//unsetting the empty forms
					if(count($value) == 0){
						unset($controller->data[$key]);
					}
					if(!$this->parsed && $this->redirect){
						$this->url = '/Filter.parsed:true' . $this->url;
						$controller->redirect('/' . $controller->name . '/index' . $this->url . '/');
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * function which will take care of the storing the filter data and loading after this from the Session
	 * JF: modified to not htmlencode, caused problems with dates e.g. -05-
	 * @param object $controller the class of the controller which call this component
	 */
	function _prepareFilter($controller) {
		$filter = array();
		if(isset($controller->data)) {
			//pr($controller);
			foreach($controller->data as $model => $fields) {
				if(is_array($fields)) {
					foreach($fields as $key => $field) {
						if($field == '') {
							unset($controller->data[$model][$key]);
						}
					}
				}
			}
			App::import('Sanitize');
			$sanit = new Sanitize();
			$controller->data = $sanit->clean($controller->data, array('encode' => false));
			$filter = $controller->data;
		}
		if (empty($filter)) {
			$filter = $this->_checkParams($controller);
		}
		$controller->data = $filter;
		return $controller;
	}

	/**
	 * function which will take care of filters from URL
	 * JF: modified to not encode, caused problems with dates
	 * @param object $controller the class of the controller which call this component
	 */
	function _checkParams($controller) {
		if (empty($controller->params['named'])) {
			$filter = array();
		}

		App::import('Sanitize');
		$sanit = new Sanitize();
		$controller->params['named'] = $sanit->clean($controller->params['named'], array('encode' => false));
		
		if(isset($controller->params['named']['Filter.parsed'])){
			if($controller->params['named']['Filter.parsed']){
				$this->parsed = true;
				$filter = array();
			}
		}

		foreach($controller->params['named'] as $field => $value) {
			if(!in_array($field, $this->paginatorParams) && $field != 'Filter.parsed') {
				$fields = explode('.', $field);
				if (sizeof($fields) == 1) {
					$filter[$controller->modelClass][$field] = $value;
				} else {
					$filter[$fields[0]][$fields[1]] = $value;
				}
			}
		}
		if (!empty($filter)) {
			return $filter;
		}
		return array();
	}

	/**
	 * Prepares a date array for a Mysql where clause
	 * @author Jeffrey Marvin
	 * @param array $date
	 * @return string
	 */
	function _prepare_datetime($date) {
		if($this->useTime){
			return $date['year']
				. '-' . $date['month']
				. '-' . $date['day']
				. ' ' . (($date['meridian'] == 'pm' && $date['hour'] != 12) ? $date['hour'] + 12 : $date['hour'])
				. ':' . (($date['min'] < 10) ? '0' . $date['min'] : $date['min'])
			;
		} else {
			return $date['year']
				. '-' . $date['month']
				. '-' . $date['day']
			;
		}
	}
}
?>