Filter Paginated Indexes using the Filter Component Plugin

## Background
This component is a fork of Jose Gonzalez's Filter component(http://github.com/josegonzalez/cakephp-filter-component), which is something of a fork of James Fairhurst's Filter Component (http://www.jamesfairhurst.co.uk/posts/view/cakephp_filter_component/), which is in turn a fork by Maciej Grajcarek (http://blog.uplevel.pl/index.php/2008/06/cakephp-12-filter-component/) which is ITSELF a fork from Nik Chankov's code at http://nik.chankov.net/2008/03/01/filtering-component-for-your-tables/ .

That's a lot of forks...

This also contains a view helper made by 'mcurry' (http://github.com/mcurry/cakephp-filter-component).

Filters hasOne and belongsTo relationships (I prefer selects from dropdowns, but to each their own).

## Installation
- Clone from github : in your plugin directory type `git clone git://github.com/josegonzalez/filter-component.git filter`
- Add as a git submodule : in your plugin directory type `git submodule add git://github.com/josegonzalez/filter-component.git filter`
- Download an archive from github and extract it in `/plugins/filter`

## Usage
1. Include the component in your controller (AppController or otherwise)
	var $components = array('Filter.Filter');
2. Use something like the following in your index
	function index() {
		$filterOptions = $this->Filter->filterOptions;
		$posts = $this->paginate(null, $this->Filter->filter);
		$this->set(compact('filterOptions', 'posts'));
	}
3. Setup your view correctly:

-Option 1: Helper
	Use the helper In between the row with all the column headers and the first row of data add: 
		<?php echo $filter->form('Post', array('name')) ?>  
	The first parameter is the model name. 
	The second parameter is an array of fields. 
	If you don't want to filter a particular field pass null in that spot.

-Option 2: Manually
	<?php echo $form->create('Post', array('action' => 'index', 'id' => 'filters')); ?>
	<table cellpadding="0" cellspacing="0">
		<thead>
			<tr>
				<th><?php echo $paginator->sort('Name', 'name', $filter_options);?></th>
				<th class="actions">Actions</th>
			</tr>
			<tr>
				<th><?php echo $form->input('name'); ?></th>
				<th>
					<button type="submit" name="data[filter]" value="filter">Filter</button>
					<button type="submit" name="data[reset]" value="reset">Reset</button>
				</th>
			</tr>
		</thead>
		<tbody>
			// loop through and display your data
		</tbody>
	</table>
	<?php echo $form->end(); ?>
	<div class="paging">
		<?php echo $paginator->prev('<< '.__('previous', true), $filter_options, null, array('class' => 'disabled'));?>
	 | 	<?php echo $paginator->numbers($filter_options);?>
		<?php echo $paginator->next(__('next', true).' >>', $filter_options, null, array('class' =>' disabled'));?>
	</div>

At this point, everything should theoretically work.

## TODO:
1. Better code commenting
2. Support Datetime - Mostly Done
3. Support URL redirects and parsing 
4. Refactor datetime filtering for ranges
5. Allow the action to be configurable
6. Support jQuery Datepicker