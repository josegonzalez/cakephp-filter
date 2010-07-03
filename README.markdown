# CakePHP Filter Plugin
Paginates Filtered Records

## Background
This plugin is a fork of [Jose Diaz-Gonzalez's Filter component](http://github.com/josegonzalez/cakephp-filter-component), which is something of a fork of [James Fairhurst's Filter Component](http://www.jamesfairhurst.co.uk/posts/view/cakephp_filter_component/), which is in turn a fork by [Maciej Grajcarek](http://blog.uplevel.pl/index.php/2008/06/cakephp-12-filter-component/), which is ITSELF a fork from [Nik Chankov's code](http://nik.chankov.net/2008/03/01/filtering-component-for-your-tables/). [Chad Jablonski](http://github.com/cjab/cakephp-filter-plugin) then added RANGE support with a few bug fixes. [jmroth](http://github.com/jmroth/cakephp-filter-plugin) pointed out a pretty bad redirect issue and "fixed it". Then [Jose Diaz-Gonzalez](http://josediazgonzalez.com/) took everyone's changes, merged them together, and updated the Component to be a bit more 1.3 compliant.

That's a lot of forks...

This also contains a view helper made by [Matt Curry](http://github.com/mcurry/cakephp-filter-component).

This also uses a behavior adapted from work by [Brenton](http://bakery.cakephp.org/articles/view/habtm-searching) to allow for HasAndBelongsToMany and HasMany relationships.

This works for all relationships.

## Installation
- Clone from github : in your plugin directory type

	`git clone  git://github.com/josegonzalez/cakephp-filter-plugin.git filter`

- Add as a git submodule : from your app/ directory type

	`git submodule add git://github.com/josegonzalez/cakephp-filter-plugin.git plugins/filter`

- Download an archive from github and extract the contents into `/plugins/filter`

## Usage
- Include the component in your controller (AppController or otherwise)

	`var $components = array('Filter.Filter');`

- Use something like the following in your index

<pre><code>function index() {
	$this->paginate = $this->Filter->paginate;
	$posts = $this->paginate();
	$this->set(compact('posts'));
}</code></pre>

- Finished example:

<pre><code><?php
class PostsController extends AppController {
	var $components = array('Filter.Filter');

	function index() {
		$this->paginate = $this->Filter->paginate;
		$posts = $this->paginate();
		$this->set(compact('posts'));
	}
}
?></code></pre>

## Advanced Usage


### Overriding the Filter pagination

#### Option 1: Controller-wide

By setting the `$paginate` variable for your controller, the Filter component merges those into it's own rules before processing incoming information.

<pre><code><?php
class PostsController extends AppController {
	var $name = 'Posts';
	var $components = array('Filter.Filter');
	var $paginate = array('contain' => array('Comment'), 'limit' => 5);

	function index() {
		$this->paginate = $this->Filter->paginate;
		$posts = $this->paginate();
		$this->set(compact('posts'));
	}
}
?></code></pre>

#### Option 2: Action-specific

You can merge in things to the paginate array before any pagination happens if necessary.

<pre><code><?php
class PostsController extends AppController {
	var $name = 'Posts';
	var $components = array('Filter.Filter');

	function index() {
		$this->paginate = array_merge($this->Filter->paginate,
			array('contain' => array('Comment'), 'limit' => 5)
		);
		$posts = $this->paginate();
		$this->set(compact('posts'));
	}
}
?></code></pre>

### Setting up search forms

#### Option 1: Helper
Use the helper In between the row with all the column headers and the first row of data add:

`<?php echo $this->Filter->form('Post', array('name')) ?>`

The first parameter is the model name. The second parameter is an array of fields. If you don't want to filter a particular field pass null in that spot.

#### Option 2: Manually

Create your own form however you want. Below is such an example.

<pre><code><?php echo $this->Form->create('Post', array('action' => 'index', 'id' => 'filters')); ?>
	&lt;table cellpadding="0" cellspacing="0"&gt;
		&lt;thead&gt;
			&lt;tr&gt;
				&lt;th&gt;<?php echo $this->Paginator->sort('Post.name'); ?>&lt;/th&gt;
				&lt;th class="actions"&gt;Actions&lt;/th&gt;
			&lt;/tr&gt;
			&lt;tr&gt;
				&lt;th&gt;<?php echo $this->Form->input('Post.name'); ?>&lt;/th&gt;
				&lt;th&gt;
					&lt;button type="submit" name="data[filter]" value="filter"&gt;Filter&lt;/button&gt;
					&lt;button type="submit" name="data[reset]" value="reset"&gt;Reset&lt;/button&gt;
				&lt;/th&gt;
			&lt;/tr&gt;
		&lt;/thead&gt;
		&lt;tbody&gt;
			// loop through and display your data
		&lt;/tbody&gt;
	&lt;/table&gt;
<?php echo $this->Form->end(); ?>
&lt;div class="paging"&gt;
	<?php echo $this->Paginator->prev('<< '.__('previous', true), array(), null, array('class' => 'disabled')); ?>
	<?php echo $this->Paginator->numbers(); ?>
	<?php echo $this->Paginator->next(__('next', true).' >>', array(), null, array('class' =>' disabled')); ?>
&lt;/div&gt;</code></pre>

### Filtering hasMany and hasAndBelongsToMany relationships

Add Behavior to model (only necessary for HABTM and HasMany):

<pre><code><?php
class Post extends AppModel {
	var $name = 'Post';
	var $actsAs = 'Filter.Filter';
}
?></code></pre>

### Initialization Tips
These different initialization options are combined in the setup array. Defaults are shown below.

<pre><code><?php
class PostsController extends AppController {
	var $name = 'Posts';
	var $components = array('Filter.Filter' => array(
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
	));
}
?></code></pre>

- actions:				Array of actions upon which this component will act upon.
- defaults:				Holds pagination defaults for controller actions. (syntax is `array('Model' => array('key' => 'value')`)
- fieldFormatting:		Fields which will replace the regular syntax in where i.e. field = 'value'
- formOptionsDatetime:	Formatting for datetime fields (unused)
- paginatorParams:		Paginator params sent in the URL
- parsed:				Used to tell whether the data options have been parsed
- redirect:				Used to tell whether to redirect so the url includes filter data
- useTime:				Used to tell whether time should be used in the filtering
- separator:			Separator to use between fields in a date input
- rangeSeparator:		Separator to use between dates in a date range
- url:					Url variable used in paginate helper (syntax is `array('url'=>$url)`);
- whitelist:			Array of fields and models for which this component may filter


## Todo
1. Better code commenting - Done, left to help enforce the habit
2. <del>Support Datetime</del> Done
3. <del>Support URL redirects and parsing</del> Done
4. <del>Refactor datetime filtering for ranges</del> Done
5. <del>Allow the action to be configurable</del> Done
6. <del>Support jQuery Datepicker</del> Outside scope
7. Support Router prefixes, plugins, and named parameters in a "scope" instead of "actions" key.
8. Expand hasMany and hasAndBelongsToMany support. Refactor behavior to conform with established practices.