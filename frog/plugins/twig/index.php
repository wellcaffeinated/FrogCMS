<?php

Plugin::setInfos(array(
    'id'          => 'twig',
    'title'       => 'TWIG', 
    'description' => 'Provides the TWIG templating framework.', 
    'version'     => '1',
    'website'     => 'http://twig.sensiolabs.org')
);

if(Plugin::isEnabled('twig')){
	function registerDir( $path = '.' ){ 
		$ignore = array( 'cgi-bin', '.', '..' ); 
		// Directories to ignore when listing output. Many hosts 
		// will deny PHP access to the cgi-bin. 
		$dh = @opendir( $path ); 
		// Open the directory to the handle $dh 
		while( false !== ( $file = readdir( $dh ) ) ){ 
		// Loop through the directory 
		    if( !in_array( $file, $ignore ) ){ 
		    // Check that this file is not to be ignored 
			if( is_dir( "$path/$file" ) ){ 
			// Its a directory, so we need to keep reading down... 
			    registerDir( "$path/$file" ); 
			    // Re-call this same function but on a new directory. 
			    // this is what makes function recursive. 
			} else {
			    $class = str_replace(array(dirname(__FILE__).'/','/','.php'),array('','_',''), "$path/$file");
			    AutoLoader::addFile($class,"$path/$file");
			} 
		    } 
		}
		// Close the directory handle     
		closedir( $dh ); 
	} 
	registerDir(dirname(__FILE__).'/Twig');
	
	//Define the twig frog loader
	class Twig_Loader_Frog implements Twig_LoaderInterface
	{
		public function getSource($name)
		{
			global $__FROG_CONN__;
			
			$sql = 'SELECT content_html FROM '.TABLE_PREFIX.'snippet WHERE name = ?';
			
			$stmt = $__FROG_CONN__->prepare($sql);
			$stmt->execute(array($name));
			
			if ($snippet = $stmt->fetchObject())
			{
				ob_start();
				eval('?>'.$snippet->content_html);
				$tpl = ob_get_contents();
				ob_end_clean();
				return $tpl;
			} else {
				throw new Twig_Error_Loader(sprintf('Unable to find template "%s".', $name));
			}
		}
		
		public function getCacheKey($name)
		{
			return $name;
		}
	    
		public function isFresh($name, $time)
		{
			global $__FROG_CONN__;
			
			$sql = 'SELECT updated_on FROM '.TABLE_PREFIX.'snippet WHERE name = ?';
			
			$stmt = $__FROG_CONN__->prepare($sql);
			$stmt->execute(array($name));
			$ts = strtotime($stmt->fetchObject()->updated_on);
			return ($ts < $time);
		}
	}
	
	class TwigFrog {
		private static $_twig = null;
		private static $_globals = array();
		private static function _getTwig(){
			if(self::$_twig){
				return self::$_twig;
			}
			
			$loader = new Twig_Loader_Frog();
			$twig = new Twig_Environment($loader, array(
				'autoescape'=>false,
				'auto_reload'=>true,
				'debug'=>false,
				'cache'=> FROG_ROOT.'/public/cache/'
			));
			self::$_twig = $twig;
			return $twig;
		}
		public static function showTemplate($name){
			$twig = self::_getTwig();
			if(!isset(self::$_globals['page'])){
				add_twig_frog_context();
			}
			try{
				echo $twig->render($name, array());
			} catch(Twig_Error $e) {
				die('<html><head></head><body><pre>'.$e->getMessage().'</pre></body></html>');
			}
		}
		public static function addGlobal($name,$obj){
			$twig = self::_getTwig();
			self::$_globals[$name] = $obj;
			$twig->addGlobal($name,$obj);
		}
	}
	
	function add_twig_frog_context($page = null){
		// handles 404 requests IE: page not found
		if($page === null){
			global $__FROG_CONN__;
			
			$sql = 'SELECT * FROM '.TABLE_PREFIX."page WHERE behavior_id='page_not_found'";
			$stmt = $__FROG_CONN__->prepare($sql);
			$stmt->execute();
			
			if ($page = $stmt->fetchObject())
			{
				$page = find_page_by_uri($page->slug);
			}
			
			if(!is_object($page)){
				$page = find_page_by_uri('/');
			}
		}
		TwigFrog::addGlobal('page',$page);
		TwigFrog::addGlobal('frogRoot',$page->find('/'));
	}
	Observer::observe('page_found', 'add_twig_frog_context');
	
	// other globals
	TwigFrog::addGlobal('SITEURL', URL_PUBLIC.((USE_MOD_REWRITE)?'':'?'));
	TwigFrog::addGlobal('PUBLIC', URL_PUBLIC.'public');
}
	
?>