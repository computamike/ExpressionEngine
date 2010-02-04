<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/docs/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP CSS Loading Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Javascript extends Controller {

	function Javascript()
	{
		// Call the Controller constructor.  
		// Without this, the world as we know it will end!
		parent::Controller();

		// Does the "core" class exist?  Normally it's initialized
		// automatically via the autoload.php file.  If it doesn't
		// exist it means there's a problem.
		if ( ! isset($this->core) OR ! is_object($this->core))
		{
			show_error('The ExpressionEngine Core was not initialized.  Please make sure your autoloader is correctly set up.');
		}

		if ( ! defined('PATH_JQUERY'))
		{
			if ($this->config->item('use_compressed_js') == 'n')
			{
				define('PATH_JQUERY', APPPATH.'javascript/src/jquery/');
			}
			else
			{
				define('PATH_JQUERY', APPPATH.'javascript/compressed/jquery/');
			}
		}

		$this->lang->loadfile('jquery');
	}

	// --------------------------------------------------------------------

	/**
	 * Index function
	 * 
	 * Every controller must have an index function, which gets called
	 * automatically by CodeIgniter when the URI does not contain a call to
	 * a specific method call
	 *
	 * @access	public
	 * @return	mixed
	 */
	function index()
	{
		$this->load('cp/global');
	}

	// --------------------------------------------------------------------

	/**
	 * Spellcheck iFrame
	 *
	 * Used by the Spellcheck crappola
	 *
	 * @access	public
	 * @return	void
	 */
	function spellcheck_iframe()
	{
		$this->output->enable_profiler(FALSE);
		
		if ( ! class_exists('EE_Spellcheck'))
		{
			require APPPATH.'libraries/Spellcheck'.EXT; 
		}

		return EE_Spellcheck::iframe();
	}

	// --------------------------------------------------------------------

	/**
	 * Spellcheck
	 *
	 * Used by the Spellcheck crappola
	 *
	 * @access	public
	 * @return	void
	 */
	function spellcheck()
	{
		$this->output->enable_profiler(FALSE);

		if ( ! class_exists('EE_Spellcheck'))
		{
			require APPPATH.'libraries/Spellcheck'.EXT; 
		}

		return EE_Spellcheck::check();
	}

	// --------------------------------------------------------------------

	/**
	 * Load
	 *
	 * Sends jQuery files to the browser
	 *
	 * @access	public
	 * @return	type
	 */
	function load($loadfile = '')
	{
		$this->output->enable_profiler(FALSE);
		
		$contents = '';		// needed for css parsing

		// trying to load a specific js file?
		$loadfile = ($loadfile) ? $loadfile : $this->input->get_post('file');
		$package = $this->input->get_post('package');
		
		if ($package && $loadfile)
		{
			$file = PATH_THIRD.$package.'/javascript/'.$loadfile.'.js';
		}
		elseif ($loadfile == '')
		{
			if (($plugin = $this->input->get_post('plugin')) !== FALSE)
			{
				$file = PATH_JQUERY.'plugins/'.$plugin.'.js';
			}
			elseif (($ui = $this->input->get_post('ui')) !== FALSE)
			{
				$file = PATH_JQUERY.'ui/ui.'.$ui.'.js';
			}
			elseif (($effect = $this->input->get_post('effect')) !== FALSE)
			{
				$file = PATH_JQUERY.'ui/effect.'.$effect.'.js';
			}
			else
			{
				$this->load->library('user_agent');
				
				if ($this->agent->browser() == 'Internet Explorer')
				{
					$file = APPPATH.'javascript/src/jquery/jquery.js';
				}
				else
				{
					$file = PATH_JQUERY.'jquery.js';					
				}
			}
		}
		elseif ($loadfile == 'css')
		{
			$file = $this->load->_ci_view_path.'css/advanced.css';
			$contents = 'css';
		}
		else
		{
			if ($this->config->item('use_compressed_js') == 'n')
			{
				$file = APPPATH.'javascript/src/'.$loadfile.'.js';
			}
			else
			{
				$file = APPPATH.'javascript/compressed/'.$loadfile.'.js';
			}
		}

		if ( ! file_exists($file))
		{
			if ($this->config->item('debug') >= 1)
			{
				$this->output->fatal_error($this->lang->line('missing_jquery_file'));
			}
			else
			{
				return FALSE;
			}
		}

		// Can't do any of this if we're not allowed
		// to send any headers

		if ($this->config->item('send_headers') == 'y')
		{
			$this->_set_headers($file);
		}

		// Grab the file, content length and serve
		// it up with the proper content type!

		if ($contents == 'css')
		{
			// File exists and not in client cache - reparse
			$contents = $this->_css_javascript();
		}
		else
		{
			$contents = file_get_contents($file);
		}

		if ($this->config->item('send_headers') == 'y')
		{
			@header('Content-Length: '.strlen($contents));
		}

		header("Content-type: text/javascript");
		exit($contents);
	}


	// --------------------------------------------------------------------

	/**
	 * Javascript Combo Loader 
	 *
	 * Combo load multiple javascript files to reduce HTTP requests
	 * BASE.AMP.'C=javascript&M=combo&ui=ui,packages&file=another&plugin=plugins&package=third,party,packages'
	 * 
	 * @access public
	 * @todo check for duplicated files.
	 * @return string
	 */
	function combo_load()
	{
		$this->output->enable_profiler(FALSE);

		$contents	= '';
		$folder 	= $this->config->item('use_compressed_js') == 'n' ? 'src' : 'compressed';
		$types		= array(
			'ui'		=> PATH_JQUERY.'ui/ui.',
			'file'		=> APPPATH.'javascript/'.$folder.'/',
			'plugin'	=> PATH_JQUERY.'plugins/',
			'package'	=> PATH_THIRD
		);
		
		foreach($types as $type => $path)
		{
			$files = explode(',', $this->input->get_post($type));
			
			foreach($files as $file)
			{
				if ($type == 'package')
				{
					$file = $file.'/javascript/'.$file;
				}
				
				$file = $path.$file.'.js';
				
				if (file_exists($file))
				{
					$contents .= file_get_contents($file)."\n\n";
				}
			}
		}

		$modified = $this->input->get_post('v');
		
		$modified = gmdate('D, d M Y H:i:s', $modified).' GMT';
		$expires = gmdate('D, d M Y H:i:s', time() + 5184000).' GMT';

		@header("Content-Type: text/javascript");
		@header("Content-Length: " . strlen($contents)); 
		@header("Cache-Control: max-age={$max_age}, must-revalidate");
		@header('Vary: Accept-Encoding');
		@header('Last-Modified: '.$modified);
		@header('Expires: '.$expires);

		exit($contents);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Set Headers
	 *
	 * @access private
     * @param string 
	 * @return str
	 */
    function _set_headers($file)
    {
		$max_age		= 5184000;
		$modified		= filemtime($file);
		$modified_since	= $this->input->server('HTTP_IF_MODIFIED_SINCE');

		// Remove anything after the semicolon

		if ($pos = strrpos($modified_since, ';') !== FALSE)
		{
			$modified_since = substr($modified_since, 0, $pos);
		}
		
		// Send a custom ETag to maintain a useful cache in
		// load-balanced environments
		
        header("ETag: ".md5($modified.$file));
		
		// If the file is in the client cache, we'll
		// send a 304 and be done with it.

		if ($modified_since && (strtotime($modified_since) == $modified))
		{
			$this->output->set_status_header(304);
			exit;
		}

		// All times GMT
		$modified = gmdate('D, d M Y H:i:s', $modified).' GMT';
		$expires = gmdate('D, d M Y H:i:s', time() + $max_age).' GMT';

		$this->output->set_status_header(200);
		@header("Content-Type: text/javascript");
		@header("Cache-Control: max-age={$max_age}, must-revalidate");
		@header('Vary: Accept-Encoding');
		@header('Last-Modified: '.$modified);
		@header('Expires: '.$expires);        
    }


	// --------------------------------------------------------------------

	/**
	 * CSS Javascript
	 *
	 * Javascript string that handles the client side css processing
	 *
	 * @access	private
	 * @return	string
	 */
	function _css_javascript()
	{
		$js = '(function($, doc) {
			var adv_css = '.$this->_advanced_css().', selector,
		 		compat_el = doc.createElement("ee_compat"),

				supported = false,

				inline_css = [],
				use = ["", "", "$1$3"],

				prefixes = " webkit o ms moz Moz".split(" "),
				corners = " -top-right -top-left -bottom-right -bottom-left".split(" "),

				regex = /^-(.)(.*?)-(.)(.*)/,

				css_radii = {};

			/* Detect browser support and define a proper prefix */
			
			$.each(prefixes, function(i) {
				
				var name = i ? this+"BorderRadius" : "borderRadius";

				if (compat_el.style[name] !== undefined) {
					
					if (i == 0) {
						use = ["border", "-radius", ""];
					}
					else if (i < 3) {
						use = ["-"+this+"-border", "-radius", ""];
					}
					else {
						use = ["-moz-border-radius", "", "-$1$2$3$4"];	/* ... thanks mozilla */
					}

					supported = true;
					return false;
				}
			});
			
			/*
			 * Different names for the same thing.
			 * Spec: border-bottom-left-radius, Moz: border-radius-bottomleft, Plugin: bl
			 */

			$.each(corners, function(i, v) {
				if (use[2]) {
					v = v.replace(regex, use[2]);
				}

				css_radii["border"+this+"-radius"] = use[0]+v+use[1];
			});

			function process_css(key, value) {

				if (key.indexOf("@") == -1) {
					
					var apply_radius = "",
						sep = (supported) ? ":" : " ",
						jQel;

					for (radius in css_radii) {
						if (value[radius]) {
							apply_radius += css_radii[radius]+sep+value[radius]+";";
							delete(value[radius]);
						}
					}

					if (supported) {
						inline_css.push(key+"{"+apply_radius+"}");
					}
					else {
						jQel = $(key).css(value);

						if (apply_radius) {
							jQel.uncorner().corner(apply_radius);
						}
					}
				}
				else if (key.indexOf("@"+EE.router_class) != -1) {
					$.each(value, process_css);
				}
			}
			
			if (supported) {
				$.each(adv_css, process_css);

				var head = doc.getElementsByTagName("head")[0],
					ss_txt = doc.createTextNode(inline_css.join("\n")),
					ss_el = doc.createElement("style");

				ss_el.setAttribute("type", "text/css");
				ss_el.appendChild(ss_txt);
				head.appendChild(ss_el);
			}
			else {
				$(doc).ready(function() {
					$.each(adv_css, process_css);
				});
			}

		})(jQuery, this.document)';

		$js = preg_replace('|/\*.*?\*/|s', '', $js);
		return str_replace(array("\t", "\n"), '', $js);
	}

	// --------------------------------------------------------------------

	/**
	 * Advanced CSS Parser
	 * 
	 * Uses javascript to provide cross browser capabilities for
	 * advanced selectors and corner rounding for the advanced.css file.
	 *
	 * @access	private
	 * @return	mixed
	 */	
	function _advanced_css()
	{
		if ( ! file_exists($this->load->_ci_view_path.'css/advanced.css'))
		{
			return array();
		}

		$this->css = file_get_contents($this->load->_ci_view_path.'css/advanced.css');
		$this->css = preg_replace('/\/\*.+?\*\//s', '', $this->css);
		
		if (trim($this->css) == '')
		{
			return array();
		}
		
		// Used by the loop to track bracing depth

		$selector_stack = array();		
		$open = FALSE;
		$depth = 0;

		/* The regex here is a bit crazy, but we need it to be
		 * really quick if we're going to parse css on the fly.
		 * The basic version is:
		 * 	/\s*(([^\}\{;]*?)\s*\{|\})/
		 *
		 * I've changed it to use a whitelist of characters instead,
		 * which pushes the regex processing time on a 2000 line test file
		 * down to 0.07 seconds. Acceptable - result cached by browser.
		 */
		$brackets = '/\s*(([@\w+~>\-\[\]=\(\'"):,.#\s]*?)\s*\{|\})\s*/';
		
		if (preg_match_all($brackets, $this->css, $matches, PREG_OFFSET_CAPTURE))
		{
			foreach($matches['1'] as $key => $data)	// data[0] - string | data[1] - offset
			{
				if ($data['0'] == '}')
				{
					if ($open)
					{
						// selector array, start offset, selector /w open-bracket, closing offset
						$this->_add_css($selector_stack, $open['0'], $open['1'], $data['1']);
						$open = FALSE;
					}
					
					array_pop($selector_stack);
					$depth--;
					
					continue;
				}

				$selector_stack[] = $matches['2'][$key]['0'];
				$open = array($data['1'], $data['0']);

				$depth++;
			}
		}

		$this->load->library('javascript');
		return $this->javascript->generate_json($this->parsed_css, TRUE);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Add css
	 *
	 * Parses block of css rules and creates an array
	 *
	 * @access	private
	 * @return	mixed
	 */
	function _add_css($selectors, $start, $open_string, $end)	// selector stack, start offset, selector /w open-bracket, closing offset
	{
		// Get the css rules
		$attr = array();
		
		$start += strlen($open_string);
		$attr_s = substr($this->css, $start, $end-$start);		

		foreach(explode(';', $attr_s) as $rule)
		{
			if (trim($rule))
			{
				list($key, $value) = explode(':', $rule);
				$attr[trim($key)] = trim($value);
			}
		}

		// Create controller/selector hierarchy
		
		$_ref =& $this->parsed_css;
		$selector = array_pop($selectors);

		foreach($selectors as $s)
		{
			$_ref =& $_ref[$s];
		}
				
		$_ref[$selector] = isset($_ref[$selector]) ? $attr + $_ref[$selector] : $attr;
	}

}

/* End of file javascript.php */
/* Location: ./system/expressionengine/controllers/cp/javascript.php */