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
	 * @param array settings['actions'] an array of the action(s) the filter is to be applied to, 
	 * @param array settings['redirect'] is whether after filtering is completed it should redirect and put the filters in the url,
	 * @param array settings['useTime'] is whether to filter date times with date in addition to time
	 */
	function initialize(&$controller, $settings = array()) {
		// If no action(s) is/are specified, defaults to 'index'
		if (!isset($settings['actions']) || empty($settings['actions'])) {
			$actions = array('index');
		} else {
			$actions = $settings['actions'];
		}
		// If no setting for redirect is specified, defaults to not redirect
		if (!isset($settings['redirect']) || empty($settings['redirect'])) {
			$this->redirect = false;
		} else {
			$this->redirect = $settings['redirect'];
		}
		//If no setting for using time in addition to date for datetimes, defaults to not using time.
		if (!isset($settings['useTime']) || empty($settings['useTime'])) {
			$this->useTime = false;
		} else {
			$this->useTime = $settings['useTime'];
		}
		//Process all action specified.
		foreach($actions as $action){
			$this->processAction($controller, $action);
		}
	}
	
	function processAction($controller, $controllerAction){
		// check to see if the current action is one that the filter is being run on
		if($controller->action == $controllerAction) {
			// setup filter component
			$this->filter = $this->processFilters($controller);
			//get the url
			$url = $this->url;
			//If there is no url, set it to '/'
			if(empty($url)) {
				$url = '/';
			}
			//set the url in the filter options
			$this->filterOptions = array('url' => array($url));
			// setup default datetime filter option
			$this->formOptionsDatetime = array('type' => 'date', 'dateFormat' => 'DMY', 'empty' => '-', 'minYear' => date("Y")-2, 'maxYear' => date("Y"));
			//If cancel or reset was pressed...
			if(isset($controller->data['reset']) || isset($controller->data['cancel'])) {
				//unset the filter
				$this->filter = array();
				//unset the url
				$this->url = '/';
				//unset the filter options
				$this->filterOptions = array();
				//redirect to the current page with no filtering being done
				$controller->redirect('/' . $controller->name . '/' . $controllerAction);
			}
		}
	}

	/**
	 * Builds up a selected datetime for the form helper
	 * @param string $fieldname
	 * @return null|string
	 */
	function processDatetime($fieldname) {
		//Create $selected
		$selected = null;
		//check to see that current field name is a field in the filter
		if(isset($this->params['named'][$fieldname])) {
			//Split the date on the '-'s
			$exploded = explode('-', $this->params['named'][$fieldname]);
			//check if the field contained anything
			if(!empty($exploded)) {
				//initialize $selected
				$selected = '';
				//cycle through the components of the datetime
				foreach($exploded as $k => $e) {
					//if there is no entry for the current part...
					if(empty($e)) {
						//if year, input 0000, else put 00
						$selected .= (($k == 0) ? '0000' : '00');
					//else
					} else {
						//add the current item to the string
						$selected .= $e;
					}
					//add the dashes back into the string
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
		//prepare the filters
		$controller = $this->_prepareFilter($controller);
		//initialize the return variable
		$ret = array();
		//check to see if there are models associated with the controller
		if(isset($controller->data)){
			// loop through the models
			foreach($controller->data as $key => $value) {
				// get fieldnames from database of model
				$columns = array();
				//check to see if the model is set
				if(isset($controller->{$key})) {
					$columns = $controller->{$key}->getColumnTypes();
				//check to see if what the model belongsTo or hasOne is set
				} elseif (isset($controller->{$controller->modelClass}->belongsTo[$key]) || isset($controller->{$controller->modelClass}->hasOne[$key])) {
					$columns = $controller->{$controller->modelClass}->{$key}->getColumnTypes();
				}
				// if columns exist
				if(!empty($columns)) {
					// loop through filter data
					foreach($value as $k => $v) {
						echo $key . ': ' . $k . ': ' . $v . '<br />';
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
				}
				else{
					//checks to see if the model's hasMany is set
					if (isset($controller->{$controller->modelClass}->hasMany[$key])) {
						$columns = $controller->{$controller->modelClass}->{$key}->getColumnTypes();
						// if columns exist
						if(!empty($columns)) {
							// loop through filter data
							foreach($value as $k => $v) {
								echo $key . ': ' . $k . ': ' . $v . '<br />';
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
//											$ret[] = array(
//												'table' => $key,
//												'type' => 'inner',
//												'conditions' => array($key . '.' . $k . " " . $tmp)
//											);
											$ret[] = $key . '.' . $k . " " . $tmp;
										} else {
//											$ret[] = array(
//												'table' => $key,
//												'type' => 'inner',
//												'conditions' => array($key . '.' . $k . " = " . $tmp)
//											);
											$ret[$key . '.' . $k] = $tmp;
										}
									} else {
										// build up where clause with field and value
//										$ret[] = array(
//											'table' => $key,
//											'type' => 'inner',
//											'conditions' => array($key . '.' . $k . " = " . $v)
//										);
										$ret[$key . '.' . $k] = $v;
									}
									// save the filter data for the url
									$this->url .= '/'. $key . '.' . $k . ':' . $v;
								}
							}
						}
					//checks to see if the model's HABTM is set
					}	elseif (isset($controller->{$controller->modelClass}->hasAndBelongsToMany[$key])) {
							$columns = $controller->{$controller->modelClass}->{$key}->getColumnTypes();
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
							}
						}
				}
				//unsetting the empty forms
				if(count($value) == 0){
					unset($controller->data[$key]);
				}
			}
		}
		//If redirect has been set true, and the data had not been parsed before and put into the url, does it now
		if(!$this->parsed && $this->redirect){
			$this->url = '/Filter.parsed:true' . $this->url;
			$controller->redirect('/' . $controller->name . '/index' . $this->url . '/');
		}
		return $ret;
	}

	/**
	 * function which will take care of the storing the filter data and loading after this from the Session
	 * JF: modified to not htmlencode, caused problems with dates e.g. -05-
	 * @param object $controller the class of the controller which call this component
	 */
	function _prepareFilter($controller) {
		//initialize the filter
		$filter = array();
		//check to see if the controller's data is set
		if(isset($controller->data)) {
			//cycle through the models
			foreach($controller->data as $model => $fields) {
				//check if the field is an array
				if(is_array($fields)) {
					//goes through each sub field
					foreach($fields as $key => $field) {
						//if the field is blank, it is unset
						if($field == '') {
							unset($controller->data[$model][$key]);
						}
					}
				}
			}
			//import the input sanitizer
			App::import('Sanitize');
			//initialize input sanitizer
			$sanit = new Sanitize();
			//sanitizes the inputs
			$controller->data = $sanit->clean($controller->data, array('encode' => false));
			//sets filter to the controller's data
			$filter = $controller->data;
		}
		//if filter is empty, it checks the parameters in the url
		if (empty($filter)) {
			$filter = $this->_checkParams($controller);
		}
		//set's the controllers data to the filter
		$controller->data = $filter;
		//return the controller
		return $controller;
	}

	/**
	 * function which will take care of filters from URL
	 * JF: modified to not encode, caused problems with dates
	 * @param object $controller the class of the controller which call this component
	 */
	function _checkParams($controller) {
		//if there are no named params, blanks the filter
		if (empty($controller->params['named'])) {
			$filter = array();
		}
		//import the input sanitizer
		App::import('Sanitize');
		//initialize the input sanitizer
		$sanit = new Sanitize();
		//sanitize the inputs
		$controller->params['named'] = $sanit->clean($controller->params['named'], array('encode' => false));
		//Checks to see if the filter has already pulled the data and put it in the url, and if so, sets the parsed variable to true
		if(isset($controller->params['named']['Filter.parsed'])){
			if($controller->params['named']['Filter.parsed']){
				$this->parsed = true;
				$filter = array();
			}
		}
		//Cycle through the named params
		foreach($controller->params['named'] as $field => $value) {
			//If it isn't the parsed variable, and it isn't in the paginatorParams
			if(!in_array($field, $this->paginatorParams) && $field != 'Filter.parsed') {
				//Break the filter from the Model.FilteredItem into an array
				$fields = explode('.', $field);
				//if it was an item without the Model.FilteredItem style, assumes the controller's model class as the model
				if (sizeof($fields) == 1) {
					$filter[$controller->modelClass][$field] = $value;
				//Otherwise uses what is specified
				} else {
					$filter[$fields[0]][$fields[1]] = $value;
				}
			}
		}
		//If the filter isn't empty, return the filter
		if (!empty($filter)) {
			return $filter;
		}
		//Otherwise return an empty array
		return array();
	}

	/**
	 * Prepares a date array for a Mysql where clause
	 * @author Jeffrey Marvin
	 * @param array $date
	 * @return string
	 */
	function _prepare_datetime($date) {
		//If it uses the time, breaks the datetime object into it's components and outputs the string
		if($this->useTime){
			return $date['year']
				. '-' . $date['month']
				. '-' . $date['day']
				. ' ' . (($date['meridian'] == 'pm' && $date['hour'] != 12) ? $date['hour'] + 12 : $date['hour'])
				. ':' . (($date['min'] < 10) ? '0' . $date['min'] : $date['min'])
			;
		//Otherwise it does the same thing but does not use the time parts of the date time.
		} else {
			return $date['year']
				. '-' . $date['month']
				. '-' . $date['day']
			;
		}
	}
}
?>