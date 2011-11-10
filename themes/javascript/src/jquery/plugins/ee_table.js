(function($) {

"use strict";

		
		// @todo @pk ideas -------------
		
		// ensure copyright notice on all files (I always forget ...)
		// first page currently isn't cached until you return to it		
		// first page doesn't list initial sort
		
		// edit specific:
		
		// add base64 thing to "add" tab link to "save" the search
		// clear search link (redirect to base path)
		// "return to filtered entries" currently does not support sort / pagination
		//		- pagination isn't helpful, but adding sort makes sense I think
		
		
		// TODO:
		
		// remove EE.BASE content_edit occurences
		// flip headerSortUp and down in the css, is silly
		// events!
		
		// /@todo @pk todo/ideas -------------


$.widget('ee.table', {

	_listening: $(),		// form elements the filter is listening to
	
	options: {
		uniqid: null,		// uniqid of related elements
		
		pagination: null,	// element(s)
		
		template: null,		// table template
		pag_template: null,	// pagination template
		
		sort: [],			// [[column, desc], [column2, asc]]
		columns: [],		// column names to match_data / search, filter on the spot
		
		cache_limit: 600,	// number of items, not pages!
		
		filters: {},
		
		cssAsc: 'headerSortUp',
		cssDesc: 'headerSortDown'
	},
	
	_create: function() {
		
		var self = this,
			options = self.options;

		// set defaults
		self.filters = options.filters;
		
		console.log(options);
		
		// setup dependencies
		self.sort = new Sort(options, self);
		self.cache = new Cache(options.cache_limit);
		self.pagination = new Pagination(options, self);
		
		// create unique template name and compile
		self.template_id = options.uniqid + '_row_template';
		$.template(self.template_id, options.template);
		
		// bind events
		self._trigger('create', null, self._ui(/* @todo args */));
	},
	
	
	// Public util
	
	clear_cache: function() {
		this.cache.clear();
		return this;
	},
	
	clear_filters: function() {
		// @todo reset form content?
		
		this.filters = {};
		this._listening.each(function() {
			$(this).unbind('interact.ee_table');
		});
		return this;
	},
	
	clear_sort: function() {
		// @todo fire sort events
		this.sort.reset();
		this.refresh();
		return this;
	},


	// Filtering
	

	/**
	 * Add a filter
	 *
	 * Can be a form or a regular object
	 */
	add_filter: function(obj) {
		var self = this,
			url = EE.BASE + '&C=content_edit'; // @todo window.location.href
		
		// add to filters and update right away
		// @todo do not hardcode url!
		if ($.isPlainObject(obj)) {
			self._set_filter(self._listening);
			self.filters = $.extend(self.filters, obj);
			self._request(url);
			return this;
		}
		
		var form = obj.closest('form'),
			evts = 'interact.ee_table',
			_timeout;
		
		if (form) {
			url = form.attr('action');
			
			// bind to submit only if it's a form
			if (obj.is(form)) {
				evts += ' submit.ee_table';
			}
		}
		
		$(obj).bind(evts, function(e) {
			
			// @todo only timeout on some inputs? (textareas)
			
			clearTimeout(_timeout);
			_timeout = setTimeout(function() {
				self._set_filter(self._listening);
				self._request(url);
			}, 200);
			
			return false;
		});
		
		self._listening = self._listening.add(obj);
		return this;
	},
	
	
	// Sorting
	
	set_sort: function(column, dir) {
		this.sort.set(column, dir);
		return this;
	},
	
	add_sort: function(column, dir) {
		this.sort.add(column, dir);
		return this;
	},
	
	reset_sort: function() {
		this.sort.reset();
		return this;
	},
	
	
	// Requests
	
	
	refresh: function() {
		var url = EE.BASE + '&C=content_edit'; // @todo window.location.href?
		
		this._request(url);
		return this;
	},

	_request: function(url) {
		var self = this,
			body = self.element.find('tbody'),	// @todo cache
			data, success;
		
		self._trigger('load', null, self._ui(/* @todo args */));

		// A cache hit and an ajax result below are both
		// considered successes and will call this with
		// the correct data =)
		success = function(res) {
			self._trigger('update', null, self._ui(/* @todo args */));
			
			// @todo only remove those that are not in the result set?
			
			body.html(res.rows);
			self.pagination.update(res.pagination);			
		};
		
		
		// @todo on the backend make sure the key name actually exists
		// or it'll throw a sql exception in the search model
		self.filters.tbl_sort = this.sort.get();
		
		
		// Weed out the stuff we don't want in there, like XIDs and
		// session ids.
		
		// Also take this opportunity to create a stable cache key, as
		// some browsers sort objects and some do not =( . To get consistency
		// for those that don't sort, we push keys and values into an array,
		// sort the array, and concat to get a string. -pk
		
		var key, regex = /^(XID|S|D|C|M)$/,
			cache_key_relevant = [];
		
		for (key in self.filters) {
			if (self.filters[key] == '' || regex.exec(key) !== null) {
				delete self.filters[key];
			} else {
				cache_key_relevant.push(key, self.filters[key]);
			}
		}

		cache_key_relevant.sort();

		var cache_id = cache_key_relevant.join(''); // debug $.param(self.filters);
		
		// Do we have this page cached?
		data = self.cache.get(cache_id);
		if (data !== null) {
			return success(data);
		}
		
		// The pagination library reads from get, so we need
		// to move tbl_offset. Doing it down here allows it
		// to be in the cache key without dark magic.
		if (self.filters.tbl_offset) {
			url += '&tbl_offset=' + self.filters.tbl_offset;
			delete self.filters.tbl_offset;
		}
		
		// Always send an XID
		self.filters.XID = EE.XID;
		
		// fire request start event (show progress indicator)
		$.ajax(url, {
			type: 'post',
			data: self.filters,
			success: function(data) {
				
				// parse data
				data.rows = $.tmpl(self.template_id, data.rows);
				data.pagination = self.pagination.parse(data.pagination);

				// add to cache
				self.cache.set(cache_id, data, data.rows.length);
				success(data);
			},
			dataType: 'json'
		});
	},
	
	
	// Private util
	
	
	_set_filter: function(obj) {
		var els = obj.serializeArray(),
			self = this;
		
		$.each(els, function() {
			self.filters[this.name] = this.value;
		});
	},
	
	_ui: function() {
		return {
			sort: this.sort.get(),// sort order [[column, asc/desc], [column2, asc/desc]]
			filters: this.filters // all applied filters
		};
	}
	
});


// --------------------------------------------------------------------------


/**
 * Implements a LRU (least-recently-used) cache.
 */
function Cache(limit) {
	this.size = 0;
	this.limit = limit;
	this.cache = [];	 // [[page, data], [page2, data2]]
	this.cache_map = []; // [page, page, page] for faster access
}

Cache.prototype = {
	
	// Limit getter
	limit: function() {
		return this.limit;
	},
	
	// Size getter
	size: function() {
		return this.cache.length;
	},
	
	/**
	 * Add a cache item
	 *
	 * @param string	unique identifier
	 * @param mixed		data to cache
	 * @param int		penalty against cache limit [default = 1]
	 *
	 * We cache per page, but since our page length is variable, we want
	 * to control cache size per row. Cache_weight exists so that this
	 * plugin remains decoupled.
	 */
	set: function(id, data, cache_weight) {
		var penalty = cache_weight || 1;
		
		// evict data until this item fits
		while (this.size + penalty > this.limit) {
			var evicted = this.cache.shift();
			this.cache_map.shift();
			
			this.size -= evicted[2];
		}

		this.cache.push( [id, data, penalty] );
		this.cache_map.push(id);
		this.size += penalty;
		
		return this;
	},

	/**
	 * Get a cached item
	 *
	 * If the cache key exists, it is moved to the top
	 * of a stack to avoid eviction (LRU behavior).
	 *
	 * @param	string	cache id
	 * @return	mixed	cached item or null
	 */
	get: function(id) {
		var el, loc = this._find(id);
		
		if (loc > -1) {
			
			// detach and push on top of the queue (newest element)
			el = this.cache.splice(loc, 1)[0];
			this.cache.push(el);

			// fix up our map
			this.cache_map.splice(loc, 1);
			this.cache_map.push(el[0]);

			return el[1];
		}
				
		return null;
	},
	
	/**
	 * Delete a cached item
	 */
	'delete': function(id) {
		var el, loc = this._find(id);
		
		if (loc > -1) {
			el = this.cache.splice(loc, 1);
			this.cache_map.splice(loc, 1);
			this.size -= el[2];
		}

		return this;
	},
	
	/**
	 * Clear cache
	 */
	clear: function() {
		this.size = 0;
		this.cache = [];
		this.cache_map = [];
		
		return this;
	},
	
	/**
	 * Find item in cache
	 *
	 * Helper method as IE does not support indexOf
	 * on arrays. This is also the reason why cache_map
	 * exists: we can search it with a native function
	 * and it's faster to iterate if we fall back.
	 */
	_find: function(id) {
		// oh hello there IE
		if ( ! Array.prototype.indexOf) {
			var tmp = this.cache_map,
				len = tmp.length,
				i = 0;
			
			for (; i < len; i++) {
				if (tmp[i] == id) {
					return i;
				}
			}
			return -1;
		}
		
		// native functions!
		return this.cache_map.indexOf(id);
	}
};



// --------------------------------------------------------------------------

/**
 * Table pagination class
 */
function Pagination(options, plugin) {	
	var self = this;
	
	this.els = $('p.' + options.uniqid);
	this.template_id = options.uniqid + '_pag_template';
	
	// compile the template
	$.template(this.template_id, options.pagination);
	
	
	// _request will grab the new page, and then call update	
	this.els.delegate('a', 'click', function() {
		var filters = self._extract_qs(this.href);

		plugin.add_filter(filters);
		return false;
	});
}

Pagination.prototype = {
	
	/**
	 * Parse the pagination data
	 *
	 * Only parsed once and then stuck into the
	 * page cache along with its data
	 */
	parse: function(data) {
		if ( ! data) {
			return '';
		}
		
		return $.tmpl(this.template_id, data).html();
	},
	
	/**
	 * Update the pagination html
	 *
	 * @param mixed results from parse [cached]
	 */
	update: function(data) {
		if ( ! data) {
			this.els.html('');
			return;
		}
		
		this.els.html(data);
	},
	
	// Private methods //
	
	/**
	 * Extract Query String from link
	 *
	 * Needed to allow pagination on "saved" searches,
	 * where the keywords might be in the url and we need
	 * to manually apply them to the next page.
	 */
	_qs_splitter: new RegExp('([^&=]+)=?([^&]*)', 'g'),
	_extract_qs: function(url) {
		var seg,
			idx = url.indexOf('?'),
			res = {};
		
		// only work through the qs
		if (idx > 0) {
			url = url.slice(idx + 1);
		}
		
		while ( (seg = this._qs_splitter.exec(url)) ) {
			res[ decodeURIComponent(seg[1]) ] = decodeURIComponent(seg[2]);
		}
		
		return res;
	}
};


// --------------------------------------------------------------------------

/**
 * Table sorting class
 */
function Sort(options, plugin) {
	var self = this;
	
	this.sort = [];
	this.plugin = plugin;
	this.headers = plugin.element.find('th');
	this.css = {
		'asc': options.cssAsc,
		'desc': options.cssDesc
	};
	
	// helpers
	this.header_map = {};
	this._initial_sort = options.sort;
	
	
	// cache all headers and check if we want
	// them to be sortable
	this.headers.each(function() {
		var el = $(this),
			short_name = el.data('table_column');
		
		self.header_map[ short_name ] = el;
		
		// sortable?
		el.data('sortable', options.columns[short_name].sort);
	});
	
	// setup events
	plugin.element.find('thead')
		.delegate('th', 'selectstart', function() { return false; }) // don't select with shift
		.delegate('th', 'click', function(e) {
			var el = $(this);
		
			// if holding shift key: add
			if ( ! el.data('sortable')) {
				return false;
			}
			
			var fn = e.shiftKey ? 'add' : 'set';
			self[fn](
				el.data('table_column'),
				el.hasClass(options.cssAsc) ? 'desc' : 'asc'
			);
		
			return false;
	});
		
	// setup initial sort without making a request
	// @todo, this could be better
	var l = this._initial_sort.length;
	while (l--) {
		this.sort.push(this._initial_sort[l]);
		this.header_map[ this._initial_sort[l][0] ]
			.toggleClass(this.css.asc, (this._initial_sort[l][1] === 'asc'))
			.toggleClass(this.css.desc, (this._initial_sort[l][1] === 'desc'));
	}
}

Sort.prototype = {
	get: function(column) {
		if (column) {
			var l = this.sort.length;
			
			while (l--) {
				if (this.sort[l][0] == column) {
					return this.sort[l][1];
				}
			}
			
			return null;
		}
		
		return this.sort;
	},
	
	add: function(column, dir) {
		var sort = column, l;
		
		if (dir) {
			sort = [[column, dir]];
		}
		
		// @todo fire addSort events
		
		l = sort.length;
		while (l--) {
			this.sort.push(sort[l]);
			this.header_map[ sort[l][0] ]
				.toggleClass(this.css.asc, (sort[l][1] === 'asc'))
				.toggleClass(this.css.desc, (sort[l][1] === 'desc'));
			
			// @todo event
			//this._trigger('sort', null, this._ui(/* @todo args sort[l] */));
		}
		
		this.plugin.refresh();
		return this;
	},
	
	set: function(column, dir) {
		
		// clear and add
		this.clear();
		this.add(column, dir);
		
		this.plugin.refresh();
		return this;
	},
	
	reset: function() {
		this.clear();
		this.set(this._initial_sort);
		
		this.plugin.refresh();
		return this;
	},
	
	clear: function() {
		var l = this.sort.length;

		while (l--) {
			this.header_map[ this.sort[l][0] ].removeClass(
				this.css.asc + ' ' + this.css.desc
			);
			// @todo event
			// this._trigger('nosort', null, this._ui(/* @todo args this.sort[l] */));
		}
				
		this.sort = [];
		return this;
	}
}


// --------------------------------------------------------------------------

// Go go go! Init all affected tables on the page
$('table').each(function() {
	var config;
	
	if ($(this).data('table_config')) {
		config = $(this).data('table_config');
		$(this).table(config);
	}
});

})(jQuery);