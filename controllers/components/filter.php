<?php
/**
 * Filter component
 *
 * @original concept by Nik Chankov - http://nik.chankov.net
 * @modified and extended by Maciej Grajcarek - http://blog.uplevel.pl
 * @modified again by James Fairhurst - http://www.jamesfairhurst.co.uk
 * @modified yet again by Jose Diaz-Gonzalez - http://josediazgonzalez.com
 * @modified further by Jeffrey Marvin - http://blitztiger.com
 * @incorporating changes made by Matt Curry - http://github.com/mcurry/
 * @version 0.8
 * @author Jeffrey Marvin <support@blitztiger.com>
 * @license	http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package	app
 * @subpackage app.controller.components
 */
class FilterComponent extends Component {

/**
 * Default Component::$params
 *
 * actions:				Actions upon which this component will act upon
 * defaults:			Holds pagination defaults for controller actions.
 * fieldFormatting:		Fields which will replace the regular syntax in where i.e. field = 'value'
 * formOptionsDatetime:	Formatting for datetime fields (unused)
 * paginatorParams:		Paginator params sent in the URL
 * parsed:				Used to tell whether the data options have been parsed
 * redirect:			Used to tell whether to redirect so the url includes filter data
 * useTime:				Used to tell whether time should be used in the filtering
 * separator:			Separator to use between fields in a date input
 * rangeSeparator:		Separator to use between dates in a date range
 * url:					Url variable used in paginate helper (array('url'=>$url));
 * whitelist:			Array of fields and models for which this component may filter
 *
 * @var array
 */
	var $settings = array(
		'actions' => array('index'),
		'defaults' => array(),
		'fieldFormatting' => array(
			'string'	=> "LIKE '%%%s%%'",
			'text'		=> "LIKE '%%%s%%'",
			'datetime'	=> "LIKE '%%%s%%'"
		),
		'formOptionsDatetime' => array(),
		'paginatorParams' => array(
			'page',
			'sort',
			'direction',
			'limit'
		),
		'parsed' => false,
		'redirect' => false,
		'useTime' => false,
		'separator' => '/',
		'rangeSeparator' => '-',
		'url' => array(),
		'whitelist' => array()
	);

/**
 * Pagination array for component
 *
 * @var array
 */
	var $paginate = array('conditions' => array());

/**
 * Holds filterOptions for 1.2 Compatibility
 *
 * @var array
 **/
	var $filterOptions = array();

/**
 * Stores data for the current pagination set
 *
 * @var array
 * @access private
 **/
	var $__data = array();

/**
 * Initializes FilterComponent for use in the controller
 *
 * @param object $controller A reference to the instantiating controller object
 * @param array $settings Array of settings for the Component
 * @return void
 * @access public
 */
	function initialize(&$controller, $settings = array()) {
		$this->settings['actions'] = (empty($settings['actions'])) ? $this->settings['actions'] : (array) $settings['actions'];

		if (in_array($controller->action, $this->settings['actions'])) {
			$settings['whitelist'] = (empty($settings['whitelist'])) ? array() : (array) $settings['whitelist'];
			$this->settings = array_merge($this->settings, $settings);
			$this->paginate = array_merge($this->paginate, $controller->paginate);

			$this->processAction($controller);
		}
	}

	function processAction(&$controller) {
		if (isset($controller->data['reset']) || isset($controller->data['cancel'])) {
			return $controller->redirect(array(
				'controller' => Inflector::underscore($controller->name),
				'action' => $controller->action,
			));
		}

		$this->processFilters($controller);

		foreach ($this->settings['url'] as $key => $value) {
			$controller->params['named'][$key] = $value;
		}
		$this->filterOptions = array('url' => array_diff(
			$controller->params['named'],
			array('page' => 1, 'limit' => 20, 'sort' => 'val')
		));

		$this->settings['formOptionsDatetime'] = array(
			'dateFormat' => 'DMY',
			'empty' => '-',
			'maxYear' => date("Y"),
			'minYear' => date("Y")-2,
			'type' => 'date'
		);
	}

/**
 * Builds up a selected datetime for the form helper
 *
 * @param string $fieldname the name of the field to process
 * @return null|string
 */
	function processDatetime($fieldname) {
		if (isset($this->params['named'][$fieldname])) {
			$exploded = explode('-', $this->params['named'][$fieldname]);
			if (!empty($exploded)) {
				$datetime = '';
				foreach ($exploded as $k => $e) {
					$datetime = (empty($e)) ? (($k == 0) ? '0000' : '00') : $e;
					if ($k != 2) $datetime .= '-';
				}
			}
		}
		return $datetime;
	}

/**
 * Function which will change controller->data array
 *
 * @param object $controller Reference to controller
 * @return void
 * @access public
 */
	function processFilters(&$controller) {
		$this->__prepareFilter($controller);

		// Set default filter values
		$this->__data = array_merge($this->settings['defaults'], $this->__data);
		$redirectData = array();

		if (isset($this->__data)) {
			foreach ($this->__data as $model => $fields) {
				$modelFieldNames = array();
				if (isset($controller->{$model})) {
					$modelFieldNames = $controller->{$model}->getColumnTypes();
				} else if (isset($controller->{$controller->modelClass}->belongsTo[$model]) || isset($controller->{$controller->modelClass}->hasOne[$model])) {
					$modelFieldNames = $controller->{$controller->modelClass}->{$model}->getColumnTypes();
				}
				if (!empty($modelFieldNames)) {
					foreach ($fields as $filteredFieldName => $filteredFieldData) {
						$this->__filterField($model, $filteredFieldName, $filteredFieldData, $modelFieldNames);
					}
				} else {
					if (isset($controller->{$controller->modelClass}->hasMany[$model])) {
						$modelFieldNames = $controller->{$controller->modelClass}->{$model}->getColumnTypes();
						if (!empty($modelFieldNames)) {
							foreach ($fields as $filteredFieldName => $filteredFieldData) {
								$this->__filterField($model, $filteredFieldName, $filteredFieldData, $modelFieldNames);
							}
						}
					} else if (isset($controller->{$controller->modelClass}->hasAndBelongsToMany[$model])) {
						$modelFieldNames = $controller->{$controller->modelClass}->{$model}->getColumnTypes();
						if (!empty($modelFieldNames)) {
							foreach ($fields as $filteredFieldName => $filteredFieldData) {
								$this->__filterField($model, $filteredFieldName, $filteredFieldData, $modelFieldNames);
							}
						}
					}
				}
				// Save model data for redirect
				if ($this->settings['redirect'] && is_array($this->__data[$model])) {
					foreach ($this->__data[$model] as $key => $val) {
						$redirectData["$model.$key"] = $val;
					}
				}
				// Unset empty model data
				if (count($fields) == 0) {
					unset($this->__data[$model]);
				}
			}
		}
		// If redirect has been set true, and the data had not been parsed before and put into the url, does it now
		if ($this->settings['parsed'] === false && $this->settings['redirect'] === true) {
			$this->settings['url'] = "/Filter.parsed:true/{$this->__buildNamedParams($redirectData)}";
			$controller->redirect("/{$controller->name}/index{$this->settings['url']}");
		}
	}

/**
 * Builds a named parameter list
 *
 * @param array $params An array of parameters to parse
 * @return string Parsed string of named parameters
 * @access private
 * @author Chad Jablonski
 **/
	function __buildNamedParams($params) {
		$paramString = '';

		foreach ($params as $key => $value) {
			$value = urlencode($value);
			$paramString .= "{$key}:{$value}/";
		}

		return $paramString;
	}


/**
 * Filters an individual field
 *
 * @param string $model name of model
 * @param string $filteredFieldName
 * @param string|array $filteredFieldName
 * @param array $modelFieldNames
 * @return array
 * @access private
 * @author Jose Diaz-Gonzalez
 **/
	function __filterField($model, $filteredFieldName, $filteredFieldData, $modelFieldNames = array()) {
		if (is_array($filteredFieldData)) {
			if (!isset($modelFieldNames[$filteredFieldName])) {
				if ($this->__arrayHasKeys($filteredFieldData, array('year', 'month', 'day'))) {
					$filteredFieldData = "{$filteredFieldData['month']}{$this->settings['separator']}{$filteredFieldData['day']}{$this->settings['separator']}{$filteredFieldData['year']}";
				}
			} else if ($modelFieldNames[$filteredFieldName] == 'datetime') {
				$filteredFieldData = $this->__prepareDatetime($filteredFieldData);
			}
		}

		if ($filteredFieldData != '') {
			if ((isset($this->settings['whitelist'][$model]) && is_array($this->settings['whitelist'][$model]) && !in_array('*', $this->settings['whitelist'][$model]) && !in_array($filteredFieldName, $this->settings['whitelist'][$model])) || (!isset($this->settings['whitelist'][$model]) && !empty($this->settings['whitelist']))) {
				return;
			}
			if (substr($filteredFieldName, 0, 5) == 'FROM_') {
				$filteredFieldName = substr($filteredFieldName, 5);
				$pieces = explode($this->settings['separator'], $filteredFieldData);
				$this->paginate['conditions']["{$model}.{$filteredFieldName} >="] = "{$pieces[2]}/{$pieces[0]}/{$pieces[1]}";
			} else if (substr($filteredFieldName, 0, 3) == 'TO_') {
				$filteredFieldName = substr($filteredFieldName, 3);
				$pieces = explode($this->settings['separator'], $filteredFieldData);
				$this->paginate['conditions']["{$model}.{$filteredFieldName} <="] = "{$pieces[2]}/{$pieces[0]}/{$pieces[1]}";
			} else if (substr($filteredFieldName, 0, 6) == 'RANGE_') {
				$filteredFieldName = substr($filteredFieldName, 6);
				$pieces = explode($this->settings['rangeSeparator'], $filteredFieldData);
				$startDate = date('Y/m/d', strtotime($pieces[0]));
				if (count($pieces) == 1) {
					$this->paginate['conditions']["{$model}.{$filteredFieldName}"] = $startDate;
				} else {
					$this->paginate['conditions']["{$model}.{$filteredFieldName} >="] = $startDate;
					$endDate = date('Y/m/d', strtotime($pieces[1]));
					$this->paginate['conditions']["{$model}.{$filteredFieldName} <="] = $endDate;
				}
			} else if (isset($modelFieldNames[$filteredFieldName]) && isset($this->settings['fieldFormatting'][$modelFieldNames[$filteredFieldName]])) {
				// insert value into fieldFormatting
				$tmp = sprintf($this->settings['fieldFormatting'][$modelFieldNames[$filteredFieldName]], $filteredFieldData);
				// don't put key.fieldname as array key if a LIKE clause
				if (substr($tmp, 0, 4) == 'LIKE') {
					$this->paginate['conditions']["{$model}.{$filteredFieldName} LIKE"] = "%{$filteredFieldData}%";
				} else {
					$this->paginate['conditions']["{$model}.{$filteredFieldName}"] = $tmp;
				}
			} else if (isset($modelFieldNames[$filteredFieldName])) {
				$this->paginate['conditions']["{$model}.{$filteredFieldName}"] = $filteredFieldData;
			}
			$this->settings['url']["{$model}.{$filteredFieldName}"] = $filteredFieldData;
		}
	}

/**
 * Store sanitized version of filter data
 *
 * @param object $controller Reference to controller
 * @access private
 */
	function __prepareFilter(&$controller) {
		if (isset($controller->data)) {
			$this->__data = $controller->data;
			foreach ($this->__data as $model => $fields) {
				if (is_array($fields)) {
					foreach ($fields as $key => $field) {
						if ($field == '') {
							unset($this->__data[$model][$key]);
						}
					}
				}
			}

			App::import('Sanitize');
			$sanitize = new Sanitize();
			$this->__data = $sanitize->clean($this->__data, array('encode' => false));
		}

		if (empty($this->__data)) {
			$this->__data = $this->__checkParams($controller);
		}
	}

/**
 * Parses named parameters from the current GET request
 *
 * @param object $controller Reference to controller
 * @return array Parsed params
 * @access private
 */
	function __checkParams(&$controller) {
		if (empty($controller->params['named'])) {
			$filter = array();
		}

		App::import('Sanitize');
		$sanitize = new Sanitize();

		$controller->params['named'] = $sanitize->clean($controller->params['named'], array('encode' => false));
		if (isset($controller->params['named']['Filter.parsed'])) {
			if ($controller->params['named']['Filter.parsed']) {
				$this->settings['parsed'] = true;
				$filter = array();
			}
		}

		foreach ($controller->params['named'] as $field => $value) {
			if (!in_array($field, $this->settings['paginatorParams']) && $field != 'Filter.parsed') {
				$fields = explode('.', $field);
				if (sizeof($fields) == 1) {
					$filter[$controller->modelClass][$field] = $value;
				} else {
					$filter[$fields[0]][$fields[1]] = $value;
				}
			}
		}

		return (!empty($filter)) ? $filter : array();
	}

/**
 * Prepares a date array for a MySQL WHERE clause
 *
 * @param array $date
 * @return string
 * @access private
 * @author Jeffrey Marvin
 */
	function __prepareDatetime($date) {
		if ($this->settings['useTime'] === true) {
			return  "{$date['year']}-{$date['month']}-{$date['day']}"
				. ' ' . (($date['meridian'] == 'pm' && $date['hour'] != 12) ? $date['hour'] + 12 : $date['hour'])
				. ':' . (($date['min'] < 10) ? "0{$date['min']}" : $date['min']);
		} else {
			return "{$date['year']}-{$date['month']}-{$date['day']}";
		}
	}

/**
 * Checks if all keys are held within an array
 *
 * @param array $array
 * @param array $keys
 * @param boolean $size
 * @return boolean array has keys, optional check on size of array
 * @access private
 * @author Jose Diaz-Gonzalez
 **/
	function __arrayHasKeys($array, $keys, $size = null) {
		if (count($array) != count($keys)) return false;

		$array = array_keys($array);
		foreach ($keys as &$key) {
			if (!in_array($key, $array)) {
				return false;
			}
		}
		return true;
	}
}