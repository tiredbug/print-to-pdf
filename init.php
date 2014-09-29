<?php 
/**
 * Plugin Name: WKPDF Press
 * Author: Carlos Oz Ramos
 * Author URI: http://web1776.com
 * Version: 9.12.14
 * Description: Converts any URL ending with "?pdf" into a 1:1 PDF. Optionally supports customized PDF template overwrites.
 *
 * @todo Change the query string
 * @todo Add predefined templates
 * @todo Create it's own site with a template gallery
 */
$wkpdf = array(
	'dir'		=> get_stylesheet_directory() . '/wkpdf-press/',
	'url'		=> get_stylesheet_directory_uri() . '/wkpdf-press/',
	'template'  => false
);

/**
 * Start generating a PDF if the URL has ?pdf in it
 */
add_action('wp', 'wkpdfPress_init');
function wkpdfPress_init(){
	if(isset($_GET['pdf'])){
		/**
		 * Create folders
		 */
		global $wkpdf;
		if(!file_exists($wkpdf['dir'])) mkdir($wkpdf['dir'], 0755, true);
		if(!file_exists($wkpdf['dir'] . 'templates/')) mkdir($wkpdf['dir'] . 'templates/', 0755, true);
		if(!file_exists($wkpdf['dir'] . 'cache/')) mkdir($wkpdf['dir'] . 'cache/', 0755, true);

		/**
		 * Load either the current page or a modification of it
		 * - Looks in templates. The template name must match 1:1, so if the template is "templates/contact.php" then the filename must be "wkpdf-press/templates/templates/contact.php"
		 * - If the template substitute doesn't exist, then it just loads the default page
		 */
		ob_start();
		$template = $wkpdf['dir'] . 'templates/';
		// Get the template name
		if(is_page())
			$wkpdf['template'] = basename(get_post_meta($post->ID, '_wp_page_template', true));
		else
			$wkpdf['template'] = get_post_type($post->ID) . '.php';
		// Load a replacement for template files...
		if(is_readable($template . $wkpdf['template'])){
			require $template . $wkpdf['template'];
			wkpdfPress_generate();
		}
		// ...CPTS...
		if(is_readable($template . $post->post_type . '.php')){
			require $template . $post->post_type . '.php';
			$wkpdf['template'] = $post->post_type . '.php';
			wkpdfPress_generate();
		// ...or General
		} else
			add_action('wp_footer', 'wkpdfPress_generate');
	}
}

/**
 * Generate the PDF
 * - Dumps HTML to a temporary file
 * - Renders that file
 * - Displays the file to the screen
 */
function wkpdfPress_generate(){
	global $wkpdf;
	$html = ob_get_clean();
	$templateFilename = $wkpdf['dir'] . 'cache/temp.html';
	$pdfFilename = $wkpdf['dir'] . 'cache/' . sanitize_title(get_the_title()) .'.pdf';

	/**
	 * Get a header
	 * - Looks for wkpdf-press/headers/TEMPLATE_NAME.php
	 * - Fallback to if it doesn't exist wkpdf-press/headers/default.php
	 */
	$header = array(
		'path'	=> array(
			'url'	=> $wkpdf['url'] . 'headers/',
			'dir'	=> $wkpdf['dir'] . 'headers/'
		),
		'file'		=> '',
		'uri'		=> '',
		'html'		=> '',
		'temp'		=> '',	//Our temporary header file
		'flag'		=> '', 	//Used in the shell_exec command to reference our header file
	);
	if(is_readable($header['path']['dir'] . $wkpdf['template'])){
		$header['file'] = $header['path']['dir'] . $wkpdf['template'];
		$header['uri'] = $header['path']['url'] . $wkpdf['template'];
	} elseif(is_readable($header['path']['dir'] . 'default.php')) {
		$header['file'] = $header['path']['dir'] . 'default.php';
		$header['uri'] = $header['path']['url'] . 'default.php';
	}
	//-- Create the temporary header file
	if($header['file']){
		$header['temp'] = $header['path']['dir'] . 'temp.html';
		$header['flag'] = '-T 30mm --header-html ' . $header['temp'] . ' ';
		ob_start();
			include $header['file'];
		$headerHTML = ob_get_clean();
		
		$template = fopen($header['temp'], 'w');
		fwrite($template, $headerHTML);
		fclose($template);
	}

	/**
	 * Create the HTML template file
	 */
	$template = fopen($templateFilename, 'w');
	fwrite($template, $html);
	fclose($template);

	/**
	 * Create the PDF
	 */
	shell_exec(plugin_dir_path(__FILE__) . 'wkhtmltopdf-i386 -L 2mm -R 2mm ' . $header['flag'] . $templateFilename . ' ' . $pdfFilename);

	/**
	 * Show the PDF
	 */
	$pdf = file_get_contents($pdfFilename);
	header('Content-Type: application/pdf');
	header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
	header('Pragma: public');
	header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Content-Length: '.strlen($pdf));
	header('Content-Disposition: inline; filename="'.basename($file).'";');
	echo $pdf;

	/**
	 * Show the PDF vs the actual site
	 */
	exit;
}